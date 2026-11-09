<?php

namespace App\Http\Controllers;

use App\Models\ApiConnection;
use App\Services\CentroNotificationService;
use App\Services\IntegrationWebhooks;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class IntegrationApiController extends Controller
{
    public const SCOPES = ['clients:read', 'projects:read', 'tasks:read', 'tasks:write', 'comments:write', 'webhooks:write'];

    private function connection(Request $r, ?string $scope = null): ApiConnection
    {
        $c = $r->attributes->get('api_connection');
        abort_unless($c instanceof ApiConnection, 401);
        if ($scope) {
            abort_unless($c->kind === 'page_notes', 403);
        }
        if ($scope) {
            abort_unless(in_array($scope, $c->scopes, true) && $r->attributes->get('api_token')->can($scope), 403);
        }

        return $c;
    }

    private function page(Request $r, $query, callable $map)
    {
        $r->validate(['page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100', 'search' => 'sometimes|string|max:200', 'updated_since' => 'sometimes|date']);
        $p = $query->paginate((int) $r->input('per_page', 50));

        return response()->json(['data' => $p->getCollection()->map($map)->values(), 'meta' => ['page' => $p->currentPage(), 'per_page' => $p->perPage(), 'total' => $p->total()]]);
    }

    public function me(Request $r)
    {
        $c = $this->connection($r);

        return response()->json(['connection_id' => $c->id, 'token_name' => $c->name, 'kind' => $c->kind, 'access_policy' => $c->access_policy, 'scopes' => $c->scopes, 'client_id' => $c->client_id, 'project_id' => $c->project_id, 'expires_at' => $c->expires_at->toIso8601String()]);
    }

    public function revoke(Request $r)
    {
        $c = $this->connection($r);
        $c->update(['revoked_at' => now()]);
        $c->tokens()->delete();

        return response()->json(['revoked' => true]);
    }

    public function clients(Request $r)
    {
        if ($r->attributes->get('api_connection')->kind === 'general') {
            return app(CommonApiController::class)->index($r, 'clients');
        }
        $c = $this->connection($r, 'clients:read');

        return $this->page($r, DB::table('clients')->where('id', $c->client_id)->where('active', true)->when($r->input('search'), fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'))->orderBy('id'), fn ($v) => ['id' => $v->id, 'name' => $v->name]);
    }

    public function projects(Request $r)
    {
        if ($r->attributes->get('api_connection')->kind === 'general') {
            return app(CommonApiController::class)->index($r, 'projects');
        }
        $c = $this->connection($r, 'projects:read');

        return $this->page($r, DB::table('projects')->where('id', $c->project_id)->where('client_id', $c->client_id)->orderBy('id'), fn ($v) => ['id' => $v->id, 'name' => $v->name, 'client_id' => $v->client_id, 'status' => $v->status]);
    }

    private function query(ApiConnection $c, string $kind)
    {
        return DB::table('tasks')->join('api_resource_links as l', 'l.resource_id', '=', 'tasks.id')
            ->where('l.connection_id', $c->id)->where('l.kind', $kind)
            ->where('tasks.client_id', $c->client_id)->where('tasks.project_id', $c->project_id)
            ->select('tasks.*', 'l.external_ref', 'l.metadata', 'l.version');
    }

    private function task(ApiConnection $c, string $id, string $kind): object
    {
        $task = $this->query($c, $kind)->where('tasks.id', $id)->first();
        abort_unless($task, 404);
        if ($kind === 'subtask') {
            abort_unless($task->parent_task_id && $this->query($c, 'task')->where('tasks.id', $task->parent_task_id)->exists(), 404);
        }

        return $task;
    }

    private function requestHash(array $data): string
    {
        $sort = function (array $value) use (&$sort): array {
            if (! array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as &$item) {
                if (is_array($item)) {
                    $item = $sort($item);
                }
            }

            return $value;
        };

        return hash('sha256', json_encode($sort($data), JSON_THROW_ON_ERROR));
    }

    private function description(string $text, array $context): string
    {
        $html = '<p>'.nl2br(e($text)).'</p>';
        if (! empty($context['external_url'])) {
            $html .= '<p><a href="'.e($context['external_url']).'" rel="noopener noreferrer">Apri nota sul sito</a></p>';
        }
        if (! empty($context['author_name'])) {
            $html .= '<p>Autore esterno: '.e($context['author_name']).'</p>';
        }
        if (! empty($context['meta'])) {
            $html .= '<pre>'.e(json_encode($context['meta'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)).'</pre>';
        }

        return $html;
    }

    private function notify(string $id, string $type, string $message): void
    {
        $parent = DB::table('tasks')->where('id', $id)->value('parent_task_id');
        $ids = array_filter([$id, $parent]);
        $users = DB::table('task_assignees')->whereIn('task_id', $ids)->pluck('user_id')
            ->merge(DB::table('task_followers')->whereIn('task_id', $ids)->pluck('user_id'))->unique();
        DB::afterCommit(fn () => app(CentroNotificationService::class)->notifyUsers($users, null, $type, $message, $id));
    }

    public static function payload(object $t): array
    {
        $meta = json_decode($t->metadata ?? '{}', true) ?: [];

        return ['id' => $t->id, 'task_id' => $t->parent_task_id, 'title' => $t->title, 'description' => $t->description,
            'status' => $t->status === 'done' ? 'done' : 'open', 'version' => (int) $t->version,
            'external_ref' => $t->external_ref, 'external_url' => $meta['external_url'] ?? null, 'meta' => $meta['meta'] ?? (object) [],
            'client_id' => $t->client_id, 'project_id' => $t->project_id,
            'url' => url('/tasks/'.($t->parent_task_id ?: $t->id)).($t->parent_task_id ? '#sub-'.$t->id : ''),
            'updated_at' => $t->updated_at ? Carbon::parse($t->updated_at)->toIso8601String() : null];
    }

    public function tasks(Request $r)
    {
        if ($r->attributes->get('api_connection')->kind === 'general') {
            return app(CommonApiController::class)->index($r, 'tasks');
        }

        return $this->page($r, $this->query($this->connection($r, 'tasks:read'), 'task')->orderBy('tasks.id'), fn ($t) => self::payload($t));
    }

    public function showTask(Request $r, string $id)
    {
        if ($r->attributes->get('api_connection')->kind === 'general') {
            return app(CommonApiController::class)->show($r, 'tasks', $id);
        }

        return response()->json(self::payload($this->task($this->connection($r, 'tasks:read'), $id, 'task')));
    }

    public function subtasks(Request $r, string $id)
    {
        $c = $this->connection($r, 'tasks:read');
        $this->task($c, $id, 'task');
        $r->validate(['updated_since' => 'sometimes|date']);
        app(IntegrationWebhooks::class)->capture($c->id);

        return $this->page($r, $this->query($c, 'subtask')->where('parent_task_id', $id)
            ->when($r->input('updated_since'), fn ($q, $date) => $q->where('tasks.updated_at', '>=', Carbon::parse($date)->setTimezone(config('app.timezone'))->toDateTimeString()))->orderBy('tasks.id'), fn ($t) => self::payload($t));
    }

    public function storeTask(Request $r)
    {
        if ($r->attributes->get('api_connection')->kind === 'general') {
            return app(CommonApiController::class)->store($r, 'tasks');
        }

        return $this->create($r, null);
    }

    public function storeSubtask(Request $r, string $id)
    {
        return $this->create($r, $id);
    }

    private function create(Request $r, ?string $parent)
    {
        $c = $this->connection($r, 'tasks:write');
        $p = $r->validate(['title' => 'required|string|max:255', 'description' => 'required|string|max:20000', 'external_ref' => 'required|string|max:190',
            'external_url' => 'nullable|url:http,https|max:2000', 'author_name' => 'nullable|string|max:255', 'author_email' => 'nullable|email|max:255',
            'meta' => 'nullable|array|max:30', 'status' => ['sometimes', Rule::in(['open', 'done'])], 'client_id' => 'sometimes|uuid', 'project_id' => 'sometimes|uuid', 'due_date' => 'nullable|date_format:Y-m-d']);
        abort_if(strlen(json_encode($p['meta'] ?? [])) > 16000, 422);
        abort_if(isset($p['client_id']) && $p['client_id'] !== $c->client_id, 403);
        abort_if(isset($p['project_id']) && $p['project_id'] !== $c->project_id, 403);
        $kind = $parent ? 'subtask' : 'task';

        return DB::transaction(function () use ($c, $p, $parent, $kind) {
            // Serializes retries per connection, including simultaneous first requests.
            DB::table('api_connections')->where('id', $c->id)->lockForUpdate()->first();
            if ($parent) {
                $this->task($c, $parent, 'task');
            }
            $key = ['connection_id' => $c->id, 'kind' => $kind, 'parent_key' => $parent ?? '', 'external_ref' => $p['external_ref']];
            $hash = $this->requestHash($p);
            $existing = DB::table('api_resource_links')->where($key)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409);

                return response()->json(self::payload($this->task($c, $existing->resource_id, $kind)), 200);
            }
            abort_unless(DB::table('clients')->where('id', $c->client_id)->where('active', true)->exists(), 409);
            abort_unless(DB::table('projects')->where('id', $c->project_id)->where('client_id', $c->client_id)->where('status', '!=', 'archived')->exists(), 409);
            $id = (string) Str::uuid();
            $description = $this->description($p['description'], $p);
            DB::table('tasks')->insert(['id' => $id, 'title' => $p['title'], 'description' => $description, 'client_id' => $c->client_id, 'project_id' => $c->project_id,
                'parent_task_id' => $parent, 'status' => ($p['status'] ?? 'open') === 'done' ? 'done' : 'todo', 'task_type' => 'task', 'priority' => 'medium',
                'due_date' => $p['due_date'] ?? null, 'created_by' => $c->created_by, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('api_resource_links')->insert([...$key, 'id' => (string) Str::uuid(), 'resource_id' => $id, 'request_hash' => $hash,
                'metadata' => json_encode(array_intersect_key($p, array_flip(['external_url', 'author_name', 'author_email', 'meta']))), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('task_assignees')->insert(['id' => (string) Str::uuid(), 'task_id' => $id, 'user_id' => $c->created_by, 'created_at' => now(), 'updated_at' => now()]);
            $this->activity($c, $id, 'created');
            $this->notify($id, 'task_created', $c->name.' ha creato "'.$p['title'].'" da Page Notes.');

            return response()->json(self::payload($this->task($c, $id, $kind)), 201);
        });
    }

    public function updateSubtask(Request $r, string $id)
    {
        $c = $this->connection($r, 'tasks:write');
        $p = $r->validate(['status' => ['sometimes', Rule::in(['open', 'done'])], 'title' => 'sometimes|required|string|max:255', 'description' => 'sometimes|required|string|max:20000', 'version' => 'sometimes|integer|min:1']);

        return DB::transaction(function () use ($c, $p, $id) {
            DB::table('api_connections')->where('id', $c->id)->lockForUpdate()->first();
            app(IntegrationWebhooks::class)->capture($c->id);
            $t = $this->task($c, $id, 'subtask');
            if (isset($p['version'])) {
                abort_unless((int) $t->version === $p['version'], 409);
            }
            $change = [];
            if (($p['status'] ?? null) === 'done') {
                abort_if(DB::table('task_dependencies')->join('tasks', 'tasks.id', '=', 'task_dependencies.depends_on_task_id')
                    ->where('task_dependencies.task_id', $id)->where('tasks.status', '!=', 'done')->exists(), 409);
                // Do not complete manually added descendants outside this integration's scope.
                abort_if(DB::table('tasks')->where('parent_task_id', $id)->where('status', '!=', 'done')->exists(), 409);
            }
            if (isset($p['status']) && $p['status'] !== ($t->status === 'done' ? 'done' : 'open')) {
                $change['status'] = $p['status'] === 'done' ? 'done' : 'todo';
            }
            if (isset($p['title']) && $p['title'] !== $t->title) {
                $change['title'] = $p['title'];
            }
            if (isset($p['description'])) {
                $description = $this->description($p['description'], json_decode($t->metadata ?? '{}', true) ?: []);
                if ($description !== $t->description) {
                    $change['description'] = $description;
                }
            }
            if ($change) {
                DB::table('tasks')->where('id', $id)->update([...$change, 'updated_at' => now()]);
                $this->activity($c, $id, 'updated');
                app(IntegrationWebhooks::class)->capture($c->id);
                $this->notify($id, isset($change['status']) ? 'task_status' : 'task_updated', $c->name.' ha aggiornato "'.($p['title'] ?? $t->title).'" da Page Notes.');
            }

            return response()->json(self::payload($this->task($c, $id, 'subtask')));
        });
    }

    private function activity(ApiConnection $c, string $id, string $action): void
    {
        DB::table('task_activity')->insert(['id' => (string) Str::uuid(), 'task_id' => $id, 'user_id' => $c->created_by, 'action' => 'api_'.$action,
            'field' => 'integration', 'new_value' => 'Page Notes: '.$c->name, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function storeComment(Request $r, string $id)
    {
        $c = $this->connection($r, 'comments:write');
        $p = $r->validate(['body' => 'required|string|max:20000', 'author_name' => 'nullable|string|max:255', 'external_ref' => 'required|string|max:190']);

        return DB::transaction(function () use ($c, $p, $id) {
            DB::table('api_connections')->where('id', $c->id)->lockForUpdate()->first();
            $this->task($c, $id, 'subtask');
            $key = ['connection_id' => $c->id, 'kind' => 'comment', 'parent_key' => $id, 'external_ref' => $p['external_ref']];
            $hash = $this->requestHash($p);
            if ($old = DB::table('api_resource_links')->where($key)->first()) {
                abort_unless(hash_equals($old->request_hash, $hash), 409);

                return response()->json(['id' => $old->resource_id]);
            }
            $commentId = (string) Str::uuid();
            DB::table('task_comments')->insert(['id' => $commentId, 'task_id' => $id, 'user_id' => $c->created_by, 'content' => '<p>'.e($p['author_name'] ?? $c->name).' (Page Notes)</p><p>'.nl2br(e($p['body'])).'</p>', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('api_resource_links')->insert([...$key, 'id' => (string) Str::uuid(), 'resource_id' => $commentId, 'request_hash' => $hash, 'metadata' => json_encode($p), 'created_at' => now(), 'updated_at' => now()]);
            $this->activity($c, $id, 'comment');
            $this->notify($id, 'task_comment', ($p['author_name'] ?? $c->name).' ha commentato da Page Notes.');

            return response()->json(['id' => $commentId], 201);
        });
    }

    public function storeWebhook(Request $r, IntegrationWebhooks $webhooks)
    {
        if ($r->attributes->get('api_connection')->kind === 'general') {
            return app(CommonApiController::class)->storeWebhook($r);
        }
        $c = $this->connection($r, 'webhooks:write');
        $p = $r->validate(['task_id' => 'required|uuid', 'url' => 'required|url:https|max:1000', 'events' => 'required|array|min:1', 'events.*' => [Rule::in(['subtask.updated', 'comment.created'])]]);
        $this->task($c, $p['task_id'], 'task');
        $webhooks->validateUrl($p['url'], $c->site);
        abort_if(DB::table('api_webhooks')->where('connection_id', $c->id)->where('task_id', $p['task_id'])->exists(), 409);
        $secret = Str::random(64);
        $id = (string) Str::uuid();
        DB::table('api_webhooks')->insert(['id' => $id, 'connection_id' => $c->id, 'task_id' => $p['task_id'], 'url' => $p['url'], 'events' => json_encode(array_values(array_unique($p['events']))), 'secret' => Crypt::encryptString($secret), 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['id' => $id, 'secret' => $secret], 201);
    }

    public function deleteWebhook(Request $r, string $id)
    {
        if ($r->attributes->get('api_connection')->kind === 'general') {
            return app(CommonApiController::class)->deleteWebhook($r, $id);
        }
        $c = $this->connection($r, 'webhooks:write');
        abort_unless(DB::table('api_webhooks')->where('id', $id)->where('connection_id', $c->id)->delete(), 404);

        return response()->noContent();
    }
}
