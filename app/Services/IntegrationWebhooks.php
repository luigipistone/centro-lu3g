<?php

namespace App\Services;

use App\Models\ApiConnection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IntegrationWebhooks
{
    public function validateUrl(string $url, string $site): array
    {
        $p = parse_url($url);
        $host = strtolower($p['host'] ?? '');
        if (($p['scheme'] ?? '') !== 'https' || $host !== strtolower(parse_url($site, PHP_URL_HOST) ?? '')
            || isset($p['user']) || isset($p['pass']) || isset($p['fragment']) || ($p['port'] ?? 443) !== 443
            || filter_var($host, FILTER_VALIDATE_IP)) {
            throw ValidationException::withMessages(['url' => 'Usa un URL HTTPS sul dominio del sito collegato, porta 443.']);
        }
        $ips = gethostbynamel($host) ?: [];
        if (! $ips || collect($ips)->contains(fn ($ip) => ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
            throw ValidationException::withMessages(['url' => 'Il dominio deve risolvere a un indirizzo pubblico.']);
        }

        return [$host, $ips[0]];
    }

    public function capture(?string $connectionId = null): void
    {
        ApiConnection::where('kind', 'page_notes')->whereNull('revoked_at')->where('expires_at', '>', now())->when($connectionId, fn ($q) => $q->where('id', $connectionId))->each(function ($c) {
            DB::transaction(function () use ($c) {
                DB::table('api_connections')->where('id', $c->id)->lockForUpdate()->first();
                $links = DB::table('api_resource_links')->where('connection_id', $c->id)->where('kind', 'subtask')->get();
                foreach ($links as $link) {
                    $task = DB::table('tasks')->where('id', $link->resource_id)->where('client_id', $c->client_id)->where('project_id', $c->project_id)->first();
                    if (! $task) {
                        continue;
                    }
                    if (! DB::table('tasks')->join('api_resource_links as parent_link', 'parent_link.resource_id', '=', 'tasks.id')
                        ->where('parent_link.connection_id', $c->id)->where('parent_link.kind', 'task')->where('tasks.id', $task->parent_task_id)
                        ->where('tasks.client_id', $c->client_id)->where('tasks.project_id', $c->project_id)->exists()) {
                        continue;
                    }
                    $state = ['subtask_id' => $task->id, 'external_ref' => $link->external_ref, 'status' => $task->status === 'done' ? 'done' : 'open', 'title' => $task->title, 'description' => $task->description];
                    $hash = hash('sha256', json_encode($state));
                    if ($hash !== $link->snapshot_hash) {
                        $version = $link->snapshot_hash ? $link->version + 1 : $link->version;
                        DB::table('api_resource_links')->where('id', $link->id)->update(['snapshot_hash' => $hash, 'version' => $version, 'updated_at' => now()]);
                        $this->enqueue($c->id, $task->parent_task_id, 'subtask.updated', [...$state, 'version' => $version]);
                    }
                    // Imported comments are already mapped and must not echo back to WordPress.
                    DB::table('task_comments')->where('task_id', $task->id)->whereNotIn('id', DB::table('api_resource_links')->where('connection_id', $c->id)->where('kind', 'comment')->select('resource_id'))
                        ->orderBy('created_at')->get()->each(function ($comment) use ($c, $task, $link) {
                            DB::table('api_resource_links')->insert(['id' => (string) Str::uuid(), 'connection_id' => $c->id, 'kind' => 'comment', 'parent_key' => $task->id, 'external_ref' => 'centro:'.$comment->id, 'resource_id' => $comment->id, 'request_hash' => hash('sha256', $comment->content), 'created_at' => now(), 'updated_at' => now()]);
                            $this->enqueue($c->id, $task->parent_task_id, 'comment.created', ['subtask_id' => $task->id, 'external_ref' => $link->external_ref, 'comment_id' => $comment->id, 'body' => $comment->content, 'by' => DB::table('users')->where('id', $comment->user_id)->value('name')]);
                        });
                }
            });
        });
    }

    private function enqueue(string $connection, string $task, string $event, array $data): void
    {
        foreach (DB::table('api_webhooks')->where('connection_id', $connection)->where('task_id', $task)->where('active', true)->get() as $hook) {
            if (! in_array($event, json_decode($hook->events, true), true)) {
                continue;
            }
            $id = (string) Str::uuid();
            DB::table('api_webhook_deliveries')->insert(['id' => $id, 'webhook_id' => $hook->id,
                'payload' => json_encode(['id' => $id, 'event' => $event, 'occurred_at' => now()->toIso8601String(), 'origin' => 'centro', 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'next_attempt_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function deliver(): void
    {
        // Bounded work per tick; remaining deliveries stay queued, never discarded.
        foreach (DB::table('api_webhook_deliveries')->where('status', 'pending')->where('next_attempt_at', '<=', now())->orderBy('next_attempt_at')->limit(10)->get() as $d) {
            $hook = DB::table('api_webhooks')->where('id', $d->webhook_id)->where('active', true)->first();
            $c = $hook ? ApiConnection::find($hook->connection_id) : null;
            $owner = $c ? DB::table('users')->where('id', $c->created_by)->first() : null;
            $subtask = json_decode($d->payload, true)['data']['subtask_id'] ?? null;
            if (! $c || ! $c->enabled() || ! in_array('webhooks:write', $c->scopes, true)
                || ! $owner || ($owner->account_status ?? 'active') !== 'active'
                || ! DB::table('user_roles')->where('user_id', $owner->id)->where('role', 'superadmin')->exists()
                || ! DB::table('tasks')->where('id', $hook->task_id)->where('project_id', $c->project_id)->where('client_id', $c->client_id)->exists()
                || ! DB::table('tasks')->where('id', $subtask)->where('parent_task_id', $hook->task_id)->where('project_id', $c->project_id)->where('client_id', $c->client_id)->exists()) {
                DB::table('api_webhook_deliveries')->where('id', $d->id)->update(['status' => 'cancelled', 'updated_at' => now()]);

                continue;
            }
            $status = null;
            try {
                [$host, $ip] = $this->validateUrl($hook->url, $c->site);
                $timestamp = (string) now()->timestamp;
                $signature = hash_hmac('sha256', $timestamp.'.'.$d->payload, Crypt::decryptString($hook->secret));
                $response = Http::connectTimeout(3)->timeout(8)->withoutRedirecting()
                    ->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$host}:443:{$ip}"]]])
                    ->withHeaders(['X-Centro-Signature' => 'sha256='.$signature, 'X-Centro-Timestamp' => $timestamp, 'X-Centro-Event-ID' => $d->id])
                    ->withBody($d->payload, 'application/json')->post($hook->url);
                $status = $response->status();
            } catch (\Throwable $e) {
                // Do not persist credentials, payload contents or network exception details in logs.
            }
            $attempt = $d->attempts + 1;
            $ok = $status >= 200 && $status < 300;
            DB::table('api_webhook_deliveries')->where('id', $d->id)->update(['attempts' => $attempt, 'http_status' => $status,
                'status' => $ok ? 'delivered' : ($attempt >= 5 ? 'failed' : 'pending'),
                'next_attempt_at' => now()->addMinutes([1, 5, 30, 120, 120][$attempt - 1] ?? 120), 'updated_at' => now()]);
        }
    }
}
