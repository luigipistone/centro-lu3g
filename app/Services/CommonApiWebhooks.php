<?php

namespace App\Services;

use App\Http\Controllers\CommonApiController;
use App\Models\ApiConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CommonApiWebhooks
{
    public static function events(): array
    {
        $events = [];
        foreach (array_keys(CommonApiController::FIELDS) as $resource) {
            foreach (['created', 'updated', 'removed'] as $action) {
                $events[] = "$resource.$action";
            }
        }

        return $events;
    }

    private function request(ApiConnection $c): Request
    {
        $r = Request::create('/');
        $r->attributes->set('api_connection', $c);
        // Scope evaluation is shared with the HTTP reader; no account-wide fallback.
        $r->attributes->set('api_token', new class($c->scopes)
        {
            public function __construct(private array $scopes) {}

            public function can(string $scope): bool
            {
                return in_array($scope, $this->scopes, true);
            }
        });

        return $r;
    }

    public function capture(?string $only = null): void
    {
        foreach (DB::table('api_subscriptions')->where('active', true)->when($only, fn ($q) => $q->where('id', $only))->get() as $hook) {
            $c = ApiConnection::find($hook->connection_id);
            if (! $c || ! app(CommonApiAccess::class)->ownerEnabled($c) || ! in_array('webhooks:write', $c->scopes, true)) {
                continue;
            }
            DB::transaction(function () use ($hook, $c) {
                $hook = DB::table('api_subscriptions')->where('id', $hook->id)->lockForUpdate()->first();
                if (! $hook || ! $hook->active) {
                    return;
                }
                $scan = (string) Str::uuid();
                $events = json_decode($hook->events, true);
                foreach (array_keys(CommonApiController::FIELDS) as $resource) {
                    if (! in_array("$resource:read", $c->scopes, true)) {
                        DB::table('api_subscription_snapshots')->where('subscription_id', $hook->id)->where('resource', $resource)->delete();

                        continue;
                    }
                    if (! collect($events)->contains(fn ($event) => str_starts_with($event, "$resource."))) {
                        continue;
                    }
                    app(CommonApiController::class)->query($this->request($c), $resource)->orderBy('id')->chunk(250, function ($rows) use ($hook, $resource, $scan, $events) {
                        foreach ($rows as $row) {
                            $key = ['subscription_id' => $hook->id, 'resource' => $resource, 'resource_id' => $row->id];
                            $old = DB::table('api_subscription_snapshots')->where($key)->first();
                            foreach (match ($resource) {
                                'tasks' => ['task_assignees', 'task_followers'], 'projects' => ['project_followers'], default => []
                            } as $table) {
                                $row->$table = DB::table($table)->where($resource === 'tasks' ? 'task_id' : 'project_id', $row->id)->orderBy('user_id')->pluck('user_id')->all();
                            }
                            $hash = hash('sha256', json_encode($row));
                            $context = match ($resource) {
                                'tasks' => ['projects' => $row->project_id], 'absences' => ['users' => $row->user_id],
                                'services', 'holidays' => [], 'comments' => ['projects' => DB::table('tasks')->where('id', $row->task_id)->value('project_id')], default => [$resource => $row->id],
                            };
                            $event = "$resource.".($old ? 'updated' : 'created');
                            if ($hook->initialized_at && (! $old || $old->hash !== $hash) && in_array($event, $events, true)) {
                                $this->enqueue($hook->id, $event, $row->id, $context);
                            }
                            DB::table('api_subscription_snapshots')->updateOrInsert($key, ['hash' => $hash, 'context' => json_encode($context), 'scan_id' => $scan, 'created_at' => $old?->created_at ?? now(), 'updated_at' => now()]);
                        }
                    });
                }
                foreach (DB::table('api_subscription_snapshots')->where('subscription_id', $hook->id)->where('scan_id', '!=', $scan)->get() as $old) {
                    $context = json_decode($old->context, true);
                    // A scope change is not a deletion notification.
                    if (app(CommonApiAccess::class)->contextAllowed($c, $context) && in_array($old->resource.'.removed', $events, true)) {
                        $this->enqueue($hook->id, $old->resource.'.removed', $old->resource_id, $context);
                    }
                    DB::table('api_subscription_snapshots')->where('id', $old->id)->delete();
                }
                DB::table('api_subscriptions')->where('id', $hook->id)->update(['initialized_at' => $hook->initialized_at ?? now(), 'updated_at' => now()]);
            });
        }
    }

    private function enqueue(string $subscription, string $event, string $resourceId, array $context): void
    {
        $id = (string) Str::uuid();
        DB::table('api_events')->insert(['id' => $id, 'subscription_id' => $subscription, 'event' => $event, 'context' => json_encode($context),
            'payload' => json_encode(['id' => $id, 'event' => $event, 'observed_at' => now()->toIso8601String(), 'data' => ['id' => $resourceId]], JSON_UNESCAPED_SLASHES),
            'next_attempt_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function deliver(): void
    {
        foreach (DB::table('api_events')->where('status', 'pending')->where('next_attempt_at', '<=', now())->orderBy('next_attempt_at')->limit(10)->get() as $event) {
            $hook = DB::table('api_subscriptions')->where('id', $event->subscription_id)->where('active', true)->first();
            $c = $hook ? ApiConnection::find($hook->connection_id) : null;
            $resource = explode('.', $event->event)[0];
            $access = app(CommonApiAccess::class);
            $allowed = $c && $access->ownerEnabled($c) && in_array('webhooks:write', $c->scopes, true) && in_array($resource.':read', $c->scopes, true)
                && $access->contextAllowed($c, json_decode($event->context, true));
            if ($allowed && ! str_ends_with($event->event, '.removed')) {
                $allowed = app(CommonApiController::class)->query($this->request($c), $resource)->where('id', json_decode($event->payload, true)['data']['id'])->exists();
            }
            if (! $allowed) {
                DB::table('api_events')->where('id', $event->id)->update(['status' => 'cancelled', 'updated_at' => now()]);

                continue;
            }
            $status = null;
            try {
                [$host, $ip] = app(IntegrationWebhooks::class)->validateUrl($hook->url, $c->site);
                $timestamp = (string) now()->timestamp;
                $signature = hash_hmac('sha256', $timestamp.'.'.$event->payload, Crypt::decryptString($hook->secret));
                $status = Http::connectTimeout(3)->timeout(8)->withoutRedirecting()->withOptions(['curl' => [CURLOPT_RESOLVE => ["$host:443:$ip"]]])
                    ->withHeaders(['X-Centro-Signature' => 'sha256='.$signature, 'X-Centro-Timestamp' => $timestamp, 'X-Centro-Event-ID' => $event->id])
                    ->withBody($event->payload, 'application/json')->post($hook->url)->status();
            } catch (\Throwable) {
                // Neither secrets nor remote response bodies belong in the audit log.
            }
            $attempt = $event->attempts + 1;
            DB::table('api_events')->where('id', $event->id)->update(['attempts' => $attempt, 'http_status' => $status,
                'status' => $status >= 200 && $status < 300 ? 'delivered' : ($attempt >= 5 ? 'failed' : 'pending'),
                'next_attempt_at' => now()->addMinutes([1, 5, 30, 120, 120][$attempt - 1] ?? 120), 'updated_at' => now()]);
        }
    }
}
