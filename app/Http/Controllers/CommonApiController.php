<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use App\Services\CommonApiAccess;
use App\Services\CommonApiWebhooks;
use App\Services\CommonApiWrites;
use App\Services\IntegrationWebhooks;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CommonApiController extends Controller
{
    // Explicit projections keep newly added private columns out of the API.
    public const FIELDS = [
        'clients' => ['id', 'name', 'legal_name', 'active', 'email', 'phone', 'website', 'vat_number', 'tax_code', 'country', 'street', 'street_number', 'postal_code', 'city', 'province', 'created_at', 'updated_at'],
        'projects' => ['id', 'name', 'description', 'client_id', 'service_id', 'status', 'color', 'created_at', 'updated_at'],
        'tasks' => ['id', 'title', 'description', 'project_id', 'client_id', 'service_id', 'parent_task_id', 'task_type', 'status', 'priority', 'start_date', 'due_date', 'due_time', 'location', 'recurring_enabled', 'recurring_interval_value', 'recurring_interval_unit', 'recurring_mode', 'recurring_weekday', 'recurring_month_day', 'created_at', 'updated_at'],
        'users' => ['id', 'name', 'email', 'updated_at', 'avatar_url'],
        'services' => ['id', 'name', 'color', 'active', 'updated_at'],
        'absences' => ['id', 'user_id', 'start_date', 'end_date', 'start_time', 'end_time', 'status', 'updated_at'],
        'comments' => ['id', 'task_id', 'user_id', 'content', 'created_at', 'updated_at'],
    ];

    public function __construct(private CommonApiAccess $access) {}

    public function store(Request $r, string $resource)
    {
        return app(CommonApiWrites::class)->write($r, $resource);
    }

    public function update(Request $r, string $resource, string $id)
    {
        return app(CommonApiWrites::class)->write($r, $resource, $id);
    }

    public function storeComment(Request $r, string $id)
    {
        return app(CommonApiWrites::class)->comment($r, $id);
    }

    public function storeWebhook(Request $r)
    {
        $c = $this->access->connection($r, 'webhooks:write');
        $p = $r->validate(['url' => 'required|url:https|max:1000', 'events' => 'required|array|min:1', 'events.*' => [Rule::in(CommonApiWebhooks::events())]]);
        foreach ($p['events'] as $event) {
            $this->access->connection($r, explode('.', $event)[0].':read');
        }
        app(IntegrationWebhooks::class)->validateUrl($p['url'], $c->site);

        return DB::transaction(function () use ($c, $p) {
            DB::table('api_connections')->where('id', $c->id)->lockForUpdate()->first();
            abort_if(DB::table('api_subscriptions')->where('connection_id', $c->id)->where('url', $p['url'])->exists(), 409);
            $id = (string) Str::uuid();
            $secret = Str::random(64);
            DB::table('api_subscriptions')->insert(['id' => $id, 'connection_id' => $c->id, 'url' => $p['url'], 'events' => json_encode(array_values(array_unique($p['events']))),
                'secret' => Crypt::encryptString($secret), 'created_at' => now(), 'updated_at' => now()]);
            app(CommonApiWebhooks::class)->capture($id);

            return response()->json(['data' => ['id' => $id, 'secret' => $secret]], 201);
        });
    }

    public function webhooks(Request $r)
    {
        $c = $this->access->connection($r, 'webhooks:write');
        $hooks = DB::table('api_subscriptions')->where('connection_id', $c->id)->get(['id', 'url', 'events', 'active', 'created_at']);
        foreach ($hooks as $hook) {
            $hook->events = json_decode($hook->events, true);
            $hook->active = (bool) $hook->active;
        }

        return response()->json(['data' => $hooks]);
    }

    public function deleteWebhook(Request $r, string $id)
    {
        $c = $this->access->connection($r, 'webhooks:write');
        abort_unless(DB::table('api_subscriptions')->where('connection_id', $c->id)->where('id', $id)->delete(), 404);

        return response()->noContent();
    }

    public function query(Request $r, string $resource)
    {
        abort_unless(isset(self::FIELDS[$resource]), 404);
        $c = $this->access->connection($r, $resource.':read');
        $q = DB::table(match ($resource) {
            'absences' => 'absence_requests', 'comments' => 'task_comments', default => $resource
        });
        if ($resource === 'comments') {
            $tasks = $this->access->constrain(DB::table('tasks'), $c, 'projects', 'project_id')->select('id');
            $q->whereIn('task_id', $tasks);
        }
        if (in_array($resource, ['clients', 'projects', 'users'], true)) {
            $this->access->constrain($q, $c, $resource, 'id');
        } elseif ($resource === 'tasks') {
            // Projectless meetings require an explicit all-projects grant.
            $this->access->constrain($q, $c, 'projects', 'project_id');
        } elseif ($resource === 'absences') {
            $this->access->constrain($q, $c, 'users', 'user_id');
            $q->where('status', 'approved')->whereNotIn('type', ['smart_working', 'travel'])
                ->where(fn ($q) => $q->where('type', '!=', 'other')->orWhereNull('cause_code')->orWhereNotIn('cause_code', DB::table('attendance_causes')->where('reduces_presence', false)->select('code')));
        }
        if ($resource === 'users') {
            $q->where('account_status', 'active');
        }

        if ($resource === 'users') {
            return $q->select(array_diff(self::FIELDS[$resource], ['avatar_url']))
                ->selectSub(DB::table('profiles')->select('avatar_url')->whereColumn('user_id', 'users.id')->limit(1), 'avatar_url');
        }

        return $q->select(self::FIELDS[$resource]);
    }

    public function avatar(Request $r, string $id)
    {
        $user = $this->query($r, 'users')->where('id', $id)->first();
        abort_unless($user && preg_match('~^/(?:storage/)?avatars/([a-zA-Z0-9_.-]+)$~', $user->avatar_url ?? '', $matches), 404);
        $path = 'avatars/'.$matches[1];
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return response()->file(Storage::disk($disk)->path($path), [
                    'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
                ]);
            }
        }
        abort(404);
    }

    public function index(Request $r, string $resource)
    {
        $r->validate(['page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100',
            'search' => 'sometimes|string|max:200', 'updated_since' => 'sometimes|date',
            'active' => 'sometimes|boolean', 'client_id' => 'sometimes|uuid', 'project_id' => 'sometimes|uuid',
            'user_id' => 'sometimes|uuid', 'task_id' => 'sometimes|uuid', 'parent_task_id' => 'sometimes|uuid', 'status' => 'sometimes|string|max:40', 'date' => 'sometimes|date_format:Y-m-d']);
        $q = $this->query($r, $resource);
        if ($r->filled('search') && in_array($resource, ['clients', 'projects', 'users', 'services', 'tasks'], true)) {
            $q->where($resource === 'tasks' ? 'title' : 'name', 'like', '%'.$r->input('search').'%');
        }
        if ($r->filled('updated_since')) {
            $q->where('updated_at', '>=', Carbon::parse($r->input('updated_since'))->setTimezone(config('app.timezone'))->toDateTimeString());
        }
        foreach (['active', 'client_id', 'project_id', 'task_id', 'parent_task_id', 'user_id', 'status'] as $field) {
            if ($r->has($field) && in_array($field, self::FIELDS[$resource], true)) {
                $q->where($field, $r->input($field));
            }
        }
        if ($resource === 'absences' && $r->filled('date')) {
            $q->whereDate('start_date', '<=', $r->input('date'))->whereRaw('COALESCE(end_date, start_date) >= ?', [$r->input('date')]);
        }
        $p = $q->orderBy('id')->paginate((int) $r->input('per_page', 50));

        return response()->json(['data' => array_map($this->serialize(...), $p->items()), 'meta' => ['page' => $p->currentPage(), 'per_page' => $p->perPage(), 'total' => $p->total(), 'last_page' => $p->lastPage(), 'has_more' => $p->hasMorePages()]]);
    }

    public function show(Request $r, string $resource, string $id)
    {
        $record = $this->query($r, $resource)->where('id', $id)->first();
        abort_unless($record, 404);

        if ($resource === 'tasks') {
            $record->assignee_ids = DB::table('task_assignees')->where('task_id', $id)->orderBy('user_id')->pluck('user_id')->all();
            $record->follower_ids = DB::table('task_followers')->where('task_id', $id)->orderBy('user_id')->pluck('user_id')->all();
        }
        if ($resource === 'projects') {
            $record->user_ids = DB::table('project_followers')->where('project_id', $id)->orderBy('user_id')->pluck('user_id')->all();
        }

        return response()->json(['data' => $this->serialize($record)])->header('ETag', CommonApiWrites::etag(match ($resource) {
            'absences' => 'absence_requests', 'comments' => 'task_comments', default => $resource
        }, $id));
    }

    private function serialize(object $record): object
    {
        if (property_exists($record, 'avatar_url')) {
            $record->avatar_url = $record->avatar_url ? url('/api/v1/users/'.$record->id.'/avatar') : null;
        }
        if (property_exists($record, 'active')) {
            $record->active = (bool) $record->active;
        }
        if (property_exists($record, 'recurring_enabled')) {
            $record->recurring_enabled = (bool) $record->recurring_enabled;
        }
        foreach (['recurring_interval_value', 'recurring_weekday', 'recurring_month_day'] as $field) {
            if (isset($record->$field)) {
                $record->$field = (int) $record->$field;
            }
        }

        return $record;
    }

    public function availability(Request $r, AttendanceService $attendance)
    {
        $c = $this->access->connection($r, 'availability:read');
        $r->validate(['date' => 'required|date_format:Y-m-d', 'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100']);
        $day = Carbon::parse($r->input('date'), 'Europe/Rome');
        $users = $this->access->constrain(DB::table('users')->where('account_status', 'active')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('user_roles')->whereColumn('user_roles.user_id', 'users.id')->where('role', 'guest')), $c, 'users', 'users.id')
            ->orderBy('users.id')->paginate((int) $r->input('per_page', 50), ['users.id']);
        $ids = collect($users->items())->pluck('id');
        $profiles = DB::table('profiles')->whereIn('user_id', $ids)->get(['user_id', 'smartworking_day', 'smartworking_days'])->keyBy('user_id');
        $requests = DB::table('absence_requests')->whereIn('user_id', $ids)->where('status', 'approved')
            ->whereDate('start_date', '<=', $day->toDateString())->whereRaw('COALESCE(end_date, start_date) >= ?', [$day->toDateString()])
            ->get(['user_id', 'type', 'cause_code', 'start_time', 'end_time'])->groupBy('user_id');
        $nonAbsence = DB::table('attendance_causes')->where('reduces_presence', false)->pluck('code')->all();
        $data = [];
        foreach ($ids as $id) {
            $minutes = $attendance->workingMinutes($id, $day);
            $rows = $requests->get($id, collect());
            $absent = $rows->reject(fn ($row) => in_array($row->type, ['smart_working', 'travel'], true) || ($row->type === 'other' && in_array($row->cause_code, $nonAbsence, true)));
            $fullDay = $absent->contains(fn ($row) => ! $row->start_time || ! $row->end_time);
            $profile = $profiles->get($id);
            $days = json_decode($profile?->smartworking_days ?? '[]', true) ?: [];
            $weekday = strtolower($day->englishDayOfWeek);
            $smart = $rows->contains('type', 'smart_working') || in_array($weekday, $days, true) || $profile?->smartworking_day === $weekday;
            $travel = $rows->contains('type', 'travel');
            $data[] = ['user_id' => $id, 'date' => $day->toDateString(), 'planned_minutes' => $minutes,
                'status' => ! $minutes ? 'not_scheduled' : ($fullDay ? 'absent' : ($absent->isNotEmpty() ? 'partially_absent' : 'scheduled')),
                'work_mode' => ! $minutes || $fullDay ? null : ($travel ? 'travel' : ($smart ? 'remote' : 'office')),
                'absence_intervals' => $minutes && ! $fullDay ? $absent->map(fn ($a) => ['start' => substr($a->start_time, 0, 5), 'end' => substr($a->end_time, 0, 5)])->values()->all() : []];
        }

        return response()->json(['data' => $data, 'meta' => ['page' => $users->currentPage(), 'per_page' => $users->perPage(), 'total' => $users->total(), 'last_page' => $users->lastPage(), 'has_more' => $users->hasMorePages(), 'timezone' => 'Europe/Rome']]);
    }
}
