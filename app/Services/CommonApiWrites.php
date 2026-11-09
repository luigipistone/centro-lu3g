<?php

namespace App\Services;

use App\Http\Controllers\CentroPageController;
use App\Http\Controllers\CommonApiController;
use App\Models\ApiConnection;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CommonApiWrites
{
    public const FIELDS = [
        'clients' => ['name', 'legal_name', 'email', 'phone', 'website', 'vat_number', 'tax_code', 'country', 'street', 'street_number', 'postal_code', 'city', 'province', 'active'],
        'projects' => ['name', 'description', 'client_id', 'service_id', 'status', 'color', 'user_ids'],
        'tasks' => ['title', 'description', 'project_id', 'client_id', 'service_id', 'parent_task_id', 'task_type', 'status', 'priority', 'start_date', 'due_date', 'due_time', 'location', 'assignee_ids', 'follower_ids', 'recurring_enabled', 'recurring_interval_value', 'recurring_interval_unit', 'recurring_mode', 'recurring_weekday', 'recurring_month_day'],
    ];

    public function __construct(private CommonApiAccess $access) {}

    public static function etag(string $resource, string $id): string
    {
        $record = DB::table($resource)->where('id', $id)->first();
        $relations = [];
        foreach (match ($resource) {
            'tasks' => ['task_assignees', 'task_followers'], 'projects' => ['project_followers'], default => []
        } as $table) {
            $relations[$table] = DB::table($table)->where($resource === 'tasks' ? 'task_id' : 'project_id', $id)->orderBy('user_id')->pluck('user_id')->all();
        }

        return '"'.hash('sha256', json_encode([$record, $relations])).'"';
    }

    private function internal(Request $r, ApiConnection $c, string $resource, array $payload): Request
    {
        $request = Request::create('/api/v1/'.$resource, $r->method(), $payload);
        $request->setUserResolver(fn () => User::findOrFail($c->created_by));
        $route = (new Route([$r->method()], '/', fn () => null))->defaults('section', $resource);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        $request->attributes->set('common_api_write', true);

        return $request;
    }

    private function hash(array $payload): string
    {
        $sort = function ($value) use (&$sort) {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($sort, $value);
        };

        return hash('sha256', json_encode($sort($payload), JSON_THROW_ON_ERROR));
    }

    public function write(Request $r, string $resource, ?string $id = null)
    {
        abort_unless(isset(self::FIELDS[$resource]), 404);
        $c = $this->access->connection($r, $resource.':write');
        $this->access->connection($r, $resource.':read');
        $input = $r->json()->all();
        $unknown = array_diff(array_keys($input), self::FIELDS[$resource]);
        if ($unknown) {
            throw ValidationException::withMessages(['body' => 'Campi non supportati: '.implode(', ', $unknown)]);
        }
        if (! $input) {
            throw ValidationException::withMessages(['body' => 'Specifica almeno un campo.']);
        }
        $key = $r->header('Idempotency-Key');
        if (! $id) {
            Validator::make(['Idempotency-Key' => $key], ['Idempotency-Key' => 'required|string|max:128'])->validate();
        }
        if ($id) {
            abort_unless($r->hasHeader('If-Match'), 428, 'Richiesto If-Match ottenuto dal dettaglio della risorsa.');
        }
        $hash = $this->hash([$resource, $input]);
        $r->attributes->set('common_api_write', true);

        return DB::transaction(function () use ($r, $resource, $id, $c, $input, $key, $hash) {
            DB::table('api_connections')->where('id', $c->id)->lockForUpdate()->first();
            if (! $id && $oldKey = DB::table('api_write_keys')->where('connection_id', $c->id)->where('request_key', $key)->first()) {
                abort_unless(hash_equals($oldKey->request_hash, $hash), 409, 'Chiave già utilizzata con una richiesta diversa.');

                return app(CommonApiController::class)->show($r, $resource, $oldKey->resource_id)->header('Idempotency-Replayed', 'true');
            }
            $old = $id ? app(CommonApiController::class)->query($r, $resource)->where('id', $id)->lockForUpdate()->first() : null;
            if ($id) {
                abort_unless($old, 404);
                abort_unless(hash_equals(self::etag($resource, $id), $r->header('If-Match')), 412, 'Risorsa modificata: rileggi il dettaglio.');
                $old = DB::table($resource)->where('id', $id)->first();
            }
            $defaults = match ($resource) {
                'projects' => ['status' => 'active', 'color' => '#0B6EF3'], 'tasks' => ['task_type' => 'task', 'status' => 'todo', 'priority' => 'medium', 'recurring_enabled' => false, 'recurring_interval_value' => 1, 'recurring_interval_unit' => 'week', 'recurring_mode' => 'fixed'], default => []
            };
            $payload = array_replace($defaults, array_intersect_key((array) $old, array_flip(self::FIELDS[$resource])), $input);
            if (array_key_exists('description', $input) && $input['description'] !== null) {
                Validator::make($input, ['description' => 'string|max:50000'])->validate();
                $payload['description'] = '<p>'.nl2br(e($input['description'])).'</p>';
            }
            $this->relations($r, $c, $resource, $payload, $input, $old);
            if ($resource === 'tasks' && $old) {
                // The web form replaces both lists together; PATCH must preserve the omitted list.
                if (array_key_exists('assignee_ids', $input) || array_key_exists('follower_ids', $input)) {
                    $payload['assignee_ids'] ??= DB::table('task_assignees')->where('task_id', $id)->pluck('user_id')->all();
                    $payload['follower_ids'] ??= DB::table('task_followers')->where('task_id', $id)->pluck('user_id')->all();
                }
                if (! array_key_exists('due_time', $input) && isset($payload['due_time'])) {
                    $payload['due_time'] = substr($payload['due_time'], 0, 5);
                }
            }
            $parent = $payload['parent_task_id'] ?? null;
            unset($payload['parent_task_id']);
            $active = $payload['active'] ?? null;
            unset($payload['active']);
            $internal = $this->internal($r, $c, $resource, $payload);
            $controller = app(CentroPageController::class);
            $result = $id ? $controller->update($internal, $id) : $controller->store($internal, $resource);
            abort_unless($result instanceof JsonResponse, 422);
            $created = ! $id;
            $id ??= $result->getData(true)['id'];
            if ($resource === 'clients' && $active !== null) {
                DB::table('clients')->where('id', $id)->update(['active' => $active]);
            }
            if ($resource === 'tasks' && $created && $parent) {
                DB::table('tasks')->where('id', $id)->update(['parent_task_id' => $parent]);
            }
            if ($created && in_array($resource, ['clients', 'projects'], true) && ! ($c->access_policy[$resource]['all'] ?? false)) {
                $policy = $c->access_policy;
                $policy[$resource]['ids'][] = $id;
                $c->update(['access_policy' => $policy]);
            }
            if ($created) {
                DB::table('api_write_keys')->insert(['connection_id' => $c->id, 'request_key' => $key, 'request_hash' => $hash, 'resource' => $resource, 'resource_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }

            return app(CommonApiController::class)->show($r, $resource, $id)->setStatusCode($created ? 201 : 200);
        });
    }

    private function relations(Request $r, ApiConnection $c, string $resource, array &$p, array $input, ?object $old): void
    {
        Validator::make($p, ['active' => 'sometimes|boolean', 'start_date' => 'nullable|date_format:Y-m-d', 'due_date' => ['nullable', 'date_format:Y-m-d', ...(! empty($p['start_date']) ? ['after_or_equal:start_date'] : [])], 'description' => 'nullable|string|max:60000', 'parent_task_id' => 'nullable|uuid'])->validate();
        foreach (['assignee_ids', 'follower_ids', 'user_ids'] as $field) {
            if (! array_key_exists($field, $input)) {
                continue;
            }
            Validator::make($input, [$field => 'array', "$field.*" => 'uuid|distinct'])->validate();
            foreach ($input[$field] as $user) {
                abort_unless($this->access->allows($c, 'users', $user) && DB::table('users')->where('id', $user)->where('account_status', 'active')->exists(), 403);
            }
        }
        if (! empty($p['service_id']) && (! $old || array_key_exists('service_id', $input))) {
            $this->access->connection($r, 'services:read');
            abort_unless(DB::table('services')->where('id', $p['service_id'])->where('active', true)->exists(), 422);
        }
        if ($resource === 'projects') {
            if (! empty($p['client_id'])) {
                abort_unless($this->access->allows($c, 'clients', $p['client_id']), 403);
            }
            if ($old && $old->client_id !== ($p['client_id'] ?? null)) {
                abort_if(DB::table('tasks')->where('project_id', $old->id)->exists(), 409, 'Il progetto contiene task: il cliente non può essere cambiato via API.');
            }
        }
        if ($resource !== 'tasks') {
            return;
        }
        if ($old && array_key_exists('parent_task_id', $input) && $input['parent_task_id'] !== $old->parent_task_id) {
            abort(422, 'Il genitore non è modificabile.');
        }
        if (! empty($p['parent_task_id'])) {
            $parent = app(CommonApiController::class)->query($r, 'tasks')->where('id', $p['parent_task_id'])->first();
            abort_unless($parent, 404);
            $p['project_id'] ??= $parent->project_id;
            $p['client_id'] ??= $parent->client_id;
            abort_unless(($p['project_id'] ?? null) === $parent->project_id && ($p['client_id'] ?? null) === $parent->client_id, 422);
        }
        abort_unless($this->access->allows($c, 'projects', $p['project_id'] ?? null), 403);
        if (! empty($p['project_id'])) {
            $project = DB::table('projects')->where('id', $p['project_id'])->first();
            abort_unless($project, 422);
            if (! $old || $old->project_id !== $project->id) {
                abort_if($project->status === 'archived', 409);
            }
            $p['client_id'] ??= $project->client_id;
            if (! empty($p['client_id']) && $p['client_id'] !== $project->client_id) {
                abort_unless(DB::table('clients')->where('id', $p['client_id'])->where('parent_client_id', $project->client_id)->exists(), 422);
            }
        }
        if (! empty($p['client_id'])) {
            abort_unless($this->access->allows($c, 'clients', $p['client_id']), 403);
        }
        if ($old && ($p['status'] ?? '') === 'done') {
            abort_if(DB::table('tasks')->where('parent_task_id', $old->id)->where('status', '!=', 'done')->exists(), 409, 'Completa prima le sottoattività.');
        }
        if ($old && ($old->project_id !== ($p['project_id'] ?? null) || $old->client_id !== ($p['client_id'] ?? null))) {
            abort_if(DB::table('tasks')->where('parent_task_id', $old->id)->exists(), 409, 'La task contiene sottoattività: non può essere spostata via API.');
        }
    }

    public function comment(Request $r, string $taskId)
    {
        $c = $this->access->connection($r, 'comments:write');
        $this->access->connection($r, 'comments:read');
        $task = app(CommonApiController::class)->query($r, 'tasks')->where('id', $taskId)->first();
        abort_unless($task, 404);
        $input = $r->json()->all();
        if (array_diff(array_keys($input), ['body'])) {
            throw ValidationException::withMessages(['body' => 'Unico campo supportato: body.']);
        }
        $p = Validator::make($input, ['body' => 'required|string|max:20000'])->validate();
        $key = $r->header('Idempotency-Key');
        Validator::make(['key' => $key], ['key' => 'required|string|max:128'])->validate();
        $hash = $this->hash(['comments', $taskId, $p]);
        $r->attributes->set('common_api_write', true);

        return DB::transaction(function () use ($r, $c, $taskId, $p, $key, $hash) {
            DB::table('api_connections')->where('id', $c->id)->lockForUpdate()->first();
            abort_unless(app(CommonApiController::class)->query($r, 'tasks')->where('id', $taskId)->lockForUpdate()->exists(), 404);
            if ($old = DB::table('api_write_keys')->where('connection_id', $c->id)->where('request_key', $key)->first()) {
                abort_unless(hash_equals($old->request_hash, $hash), 409);

                return app(CommonApiController::class)->show($r, 'comments', $old->resource_id)->header('Idempotency-Replayed', 'true');
            }
            $internal = $this->internal($r, $c, 'tasks', ['content' => '<p>'.nl2br(e($p['body'])).'</p>']);
            $response = app(CentroPageController::class)->storeTaskComment($internal, $taskId);
            $id = $response->getData(true)['id'];
            DB::table('api_write_keys')->insert(['connection_id' => $c->id, 'request_key' => $key, 'request_hash' => $hash, 'resource' => 'comments', 'resource_id' => $id, 'parent_id' => $taskId, 'created_at' => now(), 'updated_at' => now()]);

            return app(CommonApiController::class)->show($r, 'comments', $id)->setStatusCode(201);
        });
    }
}
