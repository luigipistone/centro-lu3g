<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CentroBackupService;
use App\Services\CentroNotificationService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class CentroPageController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $guestTaskIds = $this->isGuest($request) ? $this->visibleTaskIdsForUser($request->user()->id) : null;
        $currentWeekStart = now('Europe/Rome')->startOfWeek()->toDateString();
        $currentWeekEnd = now('Europe/Rome')->endOfWeek()->toDateString();
        $taskScope = fn ($query) => $query
            ->where(fn ($query) => $query->whereNull('tasks.parent_task_id')->orWhere('tasks.parent_task_id', ''))
            ->when($guestTaskIds !== null, fn ($query) => $query->whereIn('tasks.id', $guestTaskIds));

        return Inertia::render('Dashboard', [
            'stats' => [
                'clients' => $this->isGuest($request) ? 0 : DB::table('clients')->count(),
                'openTasks' => DB::table('tasks')->where($taskScope)->where('status', '!=', 'done')->count(),
                'urgentTasks' => DB::table('tasks')->where($taskScope)->where('priority', 'urgent')->where('status', '!=', 'done')->count(),
            ],
            'recentClients' => $this->isGuest($request) ? collect() : DB::table('clients')->latest()->limit(6)->get(['id', 'name', 'email', 'phone', 'created_at']),
            'upcomingTasks' => DB::table('tasks')
                ->leftJoin('clients', 'clients.id', '=', 'tasks.client_id')
                ->where($taskScope)
                ->where('tasks.status', '!=', 'done')
                ->whereNotNull('tasks.due_date')
                ->whereBetween('tasks.due_date', [$currentWeekStart, $currentWeekEnd])
                ->orderBy('tasks.due_date')
                ->limit(6)
                ->get(['tasks.id', 'tasks.title', 'tasks.status', 'tasks.priority', 'tasks.due_date', 'clients.name as client_name']),
            'urgentTasks' => DB::table('tasks')
                ->leftJoin('clients', 'clients.id', '=', 'tasks.client_id')
                ->where($taskScope)
                ->where('tasks.priority', 'urgent')
                ->where('tasks.status', '!=', 'done')
                ->orderBy('tasks.due_date')
                ->limit(6)
                ->get(['tasks.id', 'tasks.title', 'tasks.status', 'tasks.priority', 'tasks.due_date', 'clients.name as client_name']),
            'myTasks' => DB::table('tasks')
                ->leftJoin('clients', 'clients.id', '=', 'tasks.client_id')
                ->where($taskScope)
                ->when(! $this->isGuest($request), fn ($query) => $query->whereIn('tasks.id', $this->visibleTaskIdsForUser($request->user()->id)))
                ->where('tasks.status', '!=', 'done')
                ->orderBy('tasks.due_date')
                ->limit(6)
                ->get(['tasks.id', 'tasks.title', 'tasks.status', 'tasks.priority', 'tasks.due_date', 'clients.name as client_name']),
            'activeProjects' => DB::table('projects')
                ->join('project_followers', 'project_followers.project_id', '=', 'projects.id')
                ->leftJoin('clients', 'clients.id', '=', 'projects.client_id')
                ->where('project_followers.user_id', $request->user()->id)
                ->where('projects.status', 'active')
                ->latest('projects.updated_at')
                ->limit(6)
                ->get(['projects.id', 'projects.name', 'projects.color', 'clients.name as client_name']),
            'dashboardWidgets' => $this->dashboardWidgetsFor($request->user()),
            'availableDashboardWidgets' => $this->availableDashboardWidgetsFor($request),
            'dashboardNote' => $this->dashboardNoteFor($request->user()),
            'passwordItems' => $this->dashboardPasswordItemRows($request),
            'todayAbsences' => $this->dashboardTodayAbsenceRows($request),
            'todaySmartworking' => $this->dashboardTodaySmartworkingRows($request),
        ]);
    }

    public function updateDashboardWidgets(Request $request): JsonResponse
    {
        $allowedTypes = array_column($this->availableDashboardWidgetsFor($request), 'type');

        $data = $request->validate([
            'widgets' => ['required', 'array'],
            'widgets.*.widget_type' => ['required', 'string', 'distinct', Rule::in($allowedTypes)],
            'widgets.*.position' => ['required', 'integer', 'min:0'],
            'widgets.*.col_span' => ['required', 'integer', 'min:1', 'max:4'],
            'widgets.*.visible' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $data) {
            DB::table('dashboard_widgets')->where('user_id', $request->user()->id)->delete();

            $now = now();
            DB::table('dashboard_widgets')->insert(collect($data['widgets'])->map(fn ($widget, $index) => [
                'id' => (string) Str::uuid(),
                'user_id' => $request->user()->id,
                'widget_type' => $widget['widget_type'],
                'position' => $index,
                'size' => $this->sizeFromSpan((int) $widget['col_span']),
                'col_span' => (int) $widget['col_span'],
                'visible' => (bool) $widget['visible'],
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });

        return response()->json(['widgets' => $this->dashboardWidgetsFor($request->user())]);
    }

    public function updateDashboardNote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'html' => ['nullable', 'string', 'max:100000'],
        ]);

        $content = ['html' => $this->sanitizeNoteHtml($data['html'] ?? '')];
        $existing = DB::table('user_notes')->where('user_id', $request->user()->id)->latest('updated_at')->first();
        $now = now();

        if ($existing) {
            DB::table('user_notes')->where('id', $existing->id)->update([
                'content' => json_encode($content),
                'updated_at' => $now,
            ]);
        } else {
            DB::table('user_notes')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $request->user()->id,
                'content' => json_encode($content),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return response()->json(['note' => $content]);
    }

    private function dashboardWidgetsFor(User $user): array
    {
        $defaults = collect($this->defaultDashboardWidgets());
        $isGuest = ((string) (DB::table('user_roles')->where('user_id', $user->id)->value('role') ?: 'guest')) === 'guest';
        if ($isGuest) {
            $defaults = $defaults->reject(fn ($widget) => in_array($widget['widget_type'], ['stat_clients', 'recent_clients'], true))->values();
        }
        $saved = DB::table('dashboard_widgets')
            ->where('user_id', $user->id)
            ->orderBy('position')
            ->get()
            ->keyBy('widget_type');

        if ($saved->isEmpty()) {
            $now = now();
            DB::table('dashboard_widgets')->insert($defaults->map(fn ($widget) => [
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'widget_type' => $widget['widget_type'],
                'position' => $widget['position'],
                'size' => $this->sizeFromSpan($widget['col_span']),
                'col_span' => $widget['col_span'],
                'visible' => $widget['visible'],
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());

            $saved = DB::table('dashboard_widgets')
                ->where('user_id', $user->id)
                ->orderBy('position')
                ->get()
                ->keyBy('widget_type');
        }

        return $defaults
            ->map(function ($widget) use ($saved) {
                $row = $saved[$widget['widget_type']] ?? null;
                $legacyProjectStat = $saved['stat_projects'] ?? null;

                if ($widget['widget_type'] === 'active_projects' && $legacyProjectStat && !($row?->visible)) {
                    $row = (object) [
                        'position' => $legacyProjectStat->position,
                        'col_span' => max((int) ($legacyProjectStat->col_span ?? 1), (int) ($row->col_span ?? $widget['col_span'])),
                        'visible' => true,
                    ];
                }

                return [
                    'widget_type' => $widget['widget_type'],
                    'position' => (int) ($row->position ?? $widget['position']),
                    'col_span' => max(1, min(4, (int) ($row->col_span ?? $widget['col_span']))),
                    'visible' => (bool) ($row->visible ?? false),
                ];
            })
            ->sortBy([
                ['visible', 'desc'],
                ['position', 'asc'],
            ])
            ->values()
            ->all();
    }

    private function defaultDashboardWidgets(): array
    {
        return [
            ['widget_type' => 'stat_clients', 'position' => 0, 'col_span' => 1, 'visible' => true],
            ['widget_type' => 'stat_open_tasks', 'position' => 1, 'col_span' => 1, 'visible' => true],
            ['widget_type' => 'stat_urgent_tasks', 'position' => 2, 'col_span' => 1, 'visible' => true],
            ['widget_type' => 'upcoming_tasks', 'position' => 3, 'col_span' => 2, 'visible' => true],
            ['widget_type' => 'my_tasks', 'position' => 4, 'col_span' => 1, 'visible' => true],
            ['widget_type' => 'active_projects', 'position' => 5, 'col_span' => 1, 'visible' => true],
            ['widget_type' => 'recent_clients', 'position' => 6, 'col_span' => 1, 'visible' => true],
            ['widget_type' => 'urgent_tasks', 'position' => 7, 'col_span' => 1, 'visible' => true],
            ['widget_type' => 'notes', 'position' => 8, 'col_span' => 2, 'visible' => false],
            ['widget_type' => 'password_search', 'position' => 9, 'col_span' => 2, 'visible' => false],
            ['widget_type' => 'attendance_today', 'position' => 10, 'col_span' => 2, 'visible' => false],
        ];
    }

    private function availableDashboardWidgets(): array
    {
        return [
            ['type' => 'stat_clients', 'label' => 'Clienti', 'description' => 'Totale anagrafiche'],
            ['type' => 'stat_open_tasks', 'label' => 'Task aperti', 'description' => 'Attivita da chiudere'],
            ['type' => 'stat_urgent_tasks', 'label' => 'Urgenti', 'description' => 'Task ad alta priorita'],
            ['type' => 'upcoming_tasks', 'label' => 'Task in scadenza', 'description' => 'Prossime attivita con data'],
            ['type' => 'my_tasks', 'label' => 'I miei task', 'description' => 'Task assegnati a te'],
            ['type' => 'active_projects', 'label' => 'Progetti attivi', 'description' => 'Progetti assegnati a te'],
            ['type' => 'recent_clients', 'label' => 'Clienti recenti', 'description' => 'Ultime anagrafiche inserite'],
            ['type' => 'urgent_tasks', 'label' => 'Task urgenti', 'description' => 'Attivita prioritarie'],
            ['type' => 'notes', 'label' => 'Note', 'description' => 'Scrittura libera con editor completo'],
            ['type' => 'password_search', 'label' => 'Password', 'description' => 'Ricerca credenziali per cassaforte'],
            ['type' => 'attendance_today', 'label' => 'Presenze oggi', 'description' => 'Assenze e smart working in giornata'],
        ];
    }

    private function availableDashboardWidgetsFor(Request $request): array
    {
        $widgets = collect($this->availableDashboardWidgets());
        if ($this->isGuest($request)) {
            $widgets = $widgets->reject(fn ($widget) => in_array($widget['type'], ['stat_clients', 'recent_clients'], true));
        }
        if (! $this->canViewAttendanceDashboardWidget($request)) {
            $widgets = $widgets->reject(fn ($widget) => $widget['type'] === 'attendance_today');
        }

        return $widgets->values()->all();
    }

    private function canViewAttendanceDashboardWidget(Request $request): bool
    {
        return in_array($this->currentUserRole($request), ['admin', 'superadmin'], true);
    }

    private function dashboardTodayAbsenceRows(Request $request)
    {
        if (! $this->canViewAttendanceDashboardWidget($request)) {
            return collect();
        }

        $today = now('Europe/Rome')->toDateString();

        return DB::table('absence_requests')
            ->leftJoin('users', 'users.id', '=', 'absence_requests.user_id')
            ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
            ->where('absence_requests.status', '!=', 'rejected')
            ->whereDate('absence_requests.start_date', '<=', $today)
            ->whereRaw('DATE(COALESCE(absence_requests.end_date, absence_requests.start_date)) >= ?', [$today])
            ->orderBy('users.name')
            ->get([
                'absence_requests.id',
                'absence_requests.type',
                'absence_requests.status',
                'absence_requests.start_time',
                'absence_requests.end_time',
                'absence_requests.inps_code',
                'users.name as user_name',
                'users.email as user_email',
                'profiles.avatar_url as user_avatar_url',
            ]);
    }

    private function dashboardTodaySmartworkingRows(Request $request)
    {
        if (! $this->canViewAttendanceDashboardWidget($request)) {
            return collect();
        }

        $todayKey = strtolower(now('Europe/Rome')->format('l'));

        return DB::table('users')
            ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
            ->where('profiles.smartworking_day', $todayKey)
            ->orderBy('users.name')
            ->get([
                'users.id',
                'users.name',
                'users.email',
                'profiles.avatar_url',
                'profiles.job_title',
                'profiles.smartworking_day',
            ]);
    }

    private function dashboardPasswordItemRows(Request $request)
    {
        if ($this->isGuest($request)) {
            return collect();
        }

        return $this->passwordItemsQuery($request)
            ->leftJoin('password_vaults', 'password_vaults.id', '=', 'password_items.password_vault_id')
            ->leftJoin('clients', 'clients.id', '=', 'password_items.client_id')
            ->orderBy('password_items.title')
            ->get([
                'password_items.id',
                'password_items.title',
                'password_items.url',
                'password_items.username',
                'password_items.password_vault_id',
                'password_vaults.name as vault_name',
                'password_vaults.color as vault_color',
                'clients.name as client_name',
            ]);
    }

    private function dashboardNoteFor(User $user): array
    {
        $note = DB::table('user_notes')->where('user_id', $user->id)->latest('updated_at')->first();
        $content = $note ? json_decode($note->content, true) : null;

        return [
            'html' => is_array($content) ? (string) ($content['html'] ?? '') : '',
        ];
    }

    private function sanitizeNoteHtml(string $html): string
    {
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><s><ul><ol><li><blockquote><a><h1><h2><h3><pre><code><div><span>');
        $html = preg_replace('/\s(on\w+|style|class|id)=("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/href=("[^"]*"|\'[^\']*\'|[^\s>]+)/i', 'href="#"', $html) ?? '';

        return trim($html);
    }

    private function sizeFromSpan(int $span): string
    {
        return match ($span) {
            1 => 'small',
            2 => 'medium',
            3 => 'large',
            default => 'full',
        };
    }

    public function index(Request $request, string $section): Response
    {
        if ($section === 'absences') {
            $this->ensureAdmin($request);
        }
        if ($section === 'settings') {
            $this->ensureSuperadmin($request);
        }

        $this->ensureRoleCanAccessIndex($request, $section);

        $config = $this->config($section);
        $limit = $section === 'billing' ? 500 : 100;
        $guestVisibleTaskIds = $this->isGuest($request) ? $this->visibleTaskIdsForUser($request->user()->id) : null;
        $guestTaskIds = $this->isGuest($request) && in_array($section, ['tasks', 'calendar'], true)
            ? $guestVisibleTaskIds
            : null;
        if ($section === 'absences') {
            $rows = DB::table('absence_requests')
                ->leftJoin('users', 'users.id', '=', 'absence_requests.user_id')
                ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
                ->select(
                    'absence_requests.*',
                    'users.name as user_name',
                    'users.email as user_email',
                    'profiles.avatar_url as user_avatar_url',
                )
                ->latest('absence_requests.created_at')
                ->limit(300)
                ->get();
        } elseif (str_starts_with($section, 'updates-')) {
            $rows = $this->serviceUpdateRows($config['serviceName']);
        } else {
            $rows = DB::table($config['table'])
                ->when($config['table'] === 'projects', fn ($query) => $query->leftJoin('clients', 'clients.id', '=', 'projects.client_id')->select('projects.*', 'clients.name as client_name'))
                ->when($config['table'] === 'projects' && $this->isGuest($request), fn ($query) => $query
                    ->join('project_followers', 'project_followers.project_id', '=', 'projects.id')
                    ->where('project_followers.user_id', $request->user()->id)
                )
                ->when($config['table'] === 'tasks' && $section !== 'calendar', fn ($query) => $query
                    ->leftJoin('projects', 'projects.id', '=', 'tasks.project_id')
                    ->leftJoin('clients', 'clients.id', '=', 'tasks.client_id')
                    ->leftJoin('services', 'services.id', '=', 'tasks.service_id')
                    ->where(fn ($query) => $query->whereNull('tasks.parent_task_id')->orWhere('tasks.parent_task_id', ''))
                    ->when($guestTaskIds !== null, fn ($query) => $query->whereIn('tasks.id', $guestTaskIds))
                    ->select('tasks.*', 'projects.name as project_name', 'clients.name as client_name', 'services.name as service_name', 'services.color as service_color')
                )
                ->when($section === 'calendar', fn ($query) => $query
                    ->leftJoin('projects', 'projects.id', '=', 'tasks.project_id')
                    ->leftJoin('clients', 'clients.id', '=', 'tasks.client_id')
                    ->leftJoin('services', 'services.id', '=', 'tasks.service_id')
                    ->where(fn ($query) => $query->whereNull('tasks.parent_task_id')->orWhere('tasks.parent_task_id', ''))
                    ->when($guestTaskIds !== null, fn ($query) => $query->whereIn('tasks.id', $guestTaskIds))
                    ->whereNotNull('tasks.due_date')
                    ->select('tasks.*', 'projects.name as project_name', 'projects.color as project_color', 'clients.name as client_name', 'services.name as service_name', 'services.color as service_color')
                )
                ->when($config['table'] === 'documents', fn ($query) => $query->leftJoin('clients', 'clients.id', '=', 'documents.client_id')->select('documents.*', 'clients.name as client_name'))
                ->when($config['table'] === 'users', fn ($query) => $query
                    ->leftJoin('user_roles', 'user_roles.user_id', '=', 'users.id')
                    ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
                    ->select('users.*', 'user_roles.role', 'profiles.avatar_url')
                )
                ->latest($config['table'].'.created_at')
                ->limit($limit)
                ->get();
        }

        if ($section === 'calendar') {
            $subtaskCounts = DB::table('tasks')
                ->whereNotNull('parent_task_id')
                ->select('parent_task_id', DB::raw('count(*) as aggregate'))
                ->groupBy('parent_task_id')
                ->pluck('aggregate', 'parent_task_id');
            $taskIds = $rows->pluck('id');
            $subtasksByTask = DB::table('tasks')
                ->whereIn('parent_task_id', $taskIds)
                ->orderBy('position')
                ->orderBy('created_at')
                ->get(['id', 'parent_task_id', 'title', 'status', 'priority', 'due_date', 'due_time', 'project_id', 'client_id', 'service_id', 'description', 'position', 'created_by'])
                ->groupBy('parent_task_id');
            $subtaskIds = $subtasksByTask->flatten(1)->pluck('id');
            $assigneesBySubtask = DB::table('task_assignees')
                ->whereIn('task_id', $subtaskIds)
                ->get(['task_id', 'user_id'])
                ->groupBy('task_id');
            $activityTaskIds = $taskIds->merge($subtaskIds)->filter()->values();
            $commentsByTask = DB::table('task_comments')
                ->leftJoin('users', 'users.id', '=', 'task_comments.user_id')
                ->whereIn('task_comments.task_id', $activityTaskIds)
                ->latest('task_comments.created_at')
                ->get(['task_comments.*', 'users.name as user_name'])
                ->groupBy('task_id')
                ->map(fn ($comments) => $comments->take(30)->values());
            $activityByTask = $this->taskActivityRows($activityTaskIds);
            $assigneesByTask = DB::table('task_assignees')
                ->whereIn('task_id', $taskIds)
                ->get(['task_id', 'user_id'])
                ->groupBy('task_id');
            $followersByTask = DB::table('task_followers')
                ->whereIn('task_id', $taskIds)
                ->get(['task_id', 'user_id'])
                ->groupBy('task_id');

            $dependencyRows = $this->taskDependencyRows($activityTaskIds);

            $rows = $rows->map(function ($row) use ($subtaskCounts, $subtasksByTask, $commentsByTask, $activityByTask, $assigneesByTask, $followersByTask, $assigneesBySubtask, $dependencyRows) {
                $row->subtask_count = (int) ($subtaskCounts[$row->id] ?? 0);
                $row->subtasks = ($subtasksByTask[$row->id] ?? collect())
                    ->map(function ($subtask) use ($assigneesBySubtask, $commentsByTask, $activityByTask, $dependencyRows) {
                        $subtask->assignee_ids = ($assigneesBySubtask[$subtask->id] ?? collect())->pluck('user_id')->values();
                        $subtask->comments = ($commentsByTask[$subtask->id] ?? collect())->values();
                        $subtask->activity = ($activityByTask[$subtask->id] ?? collect())->values();
                        $subtask->dependencies = ($dependencyRows[$subtask->id]['dependencies'] ?? collect())->values();
                        $subtask->dependents = ($dependencyRows[$subtask->id]['dependents'] ?? collect())->values();
                        $subtask->blocked_dependencies_count = ($subtask->dependencies ?? collect())->where('status', '!=', 'done')->count();

                        return $subtask;
                    })
                    ->values();
                $row->comments = ($commentsByTask[$row->id] ?? collect())->values();
                $row->activity = ($activityByTask[$row->id] ?? collect())->values();
                $row->assignee_ids = ($assigneesByTask[$row->id] ?? collect())->pluck('user_id')->values();
                $row->follower_ids = ($followersByTask[$row->id] ?? collect())->pluck('user_id')->values();
                $row->dependencies = ($dependencyRows[$row->id]['dependencies'] ?? collect())->values();
                $row->dependents = ($dependencyRows[$row->id]['dependents'] ?? collect())->values();
                $row->blocked_dependencies_count = ($row->dependencies ?? collect())->where('status', '!=', 'done')->count();

                return $row;
            });
        }

        if ($section === 'tasks') {
            $taskIds = $rows->pluck('id');
            $subtaskCounts = DB::table('tasks')
                ->whereIn('parent_task_id', $taskIds)
                ->select('parent_task_id', DB::raw('count(*) as aggregate'))
                ->groupBy('parent_task_id')
                ->pluck('aggregate', 'parent_task_id');
            $assigneesByTask = DB::table('task_assignees')
                ->whereIn('task_id', $taskIds)
                ->get(['task_id', 'user_id'])
                ->groupBy('task_id');
            $followersByTask = DB::table('task_followers')
                ->whereIn('task_id', $taskIds)
                ->get(['task_id', 'user_id'])
                ->groupBy('task_id');
            $dependencyRows = $this->taskDependencyRows($taskIds);

            $rows = $rows->map(function ($row) use ($subtaskCounts, $assigneesByTask, $followersByTask, $dependencyRows) {
                $row->subtask_count = (int) ($subtaskCounts[$row->id] ?? 0);
                $row->assignee_ids = ($assigneesByTask[$row->id] ?? collect())->pluck('user_id')->values();
                $row->follower_ids = ($followersByTask[$row->id] ?? collect())->pluck('user_id')->values();
                $row->dependencies = ($dependencyRows[$row->id]['dependencies'] ?? collect())->values();
                $row->dependents = ($dependencyRows[$row->id]['dependents'] ?? collect())->values();
                $row->blocked_dependencies_count = ($row->dependencies ?? collect())->where('status', '!=', 'done')->count();

                return $row;
            });
        }

        if ($section === 'clients') {
            $servicesByClient = DB::table('client_services')
                ->join('services', 'services.id', '=', 'client_services.service_id')
                ->get(['client_services.client_id', 'services.id', 'services.name', 'services.color'])
                ->groupBy('client_id');

            $rows = $rows->map(function ($row) use ($servicesByClient) {
                $row->services = ($servicesByClient[$row->id] ?? collect())->values();
                $row->projects_count = DB::table('projects')->where('client_id', $row->id)->count();
                $row->tasks_count = DB::table('tasks')->where('client_id', $row->id)->where('status', '!=', 'done')->count();
                $row->documents_count = DB::table('documents')->where('client_id', $row->id)->count();

                return $row;
            });
        }

        if ($section === 'projects') {
            $projectIds = $rows->pluck('id');
            $followersByProject = DB::table('project_followers')
                ->whereIn('project_id', $projectIds)
                ->get(['project_id', 'user_id'])
                ->groupBy('project_id');

            $rows = $rows->map(function ($row) use ($followersByProject) {
                $row->follower_ids = ($followersByProject[$row->id] ?? collect())->pluck('user_id')->values();

                return $row;
            });
        }

        return Inertia::render('Centro/Index', [
            ...$config,
            'rows' => $rows,
            'billingStats' => $section === 'billing' ? $this->billingStats() : null,
            'clientStats' => $section === 'clients' ? $this->clientStats() : null,
            'documentSettings' => $section === 'settings' ? DB::table('document_settings')->first() : null,
            'emailSettings' => $section === 'settings' ? $this->emailSettingsForView() : null,
            'numberings' => $section === 'settings' ? DB::table('document_numbering')->orderBy('doc_type')->orderByDesc('year')->get() : [],
            'backupRuns' => $section === 'settings' ? $this->backupRuns() : [],
            'clients' => $this->isGuest($request)
                ? DB::table('clients')
                    ->whereIn('id', $this->visibleClientIdsForUser($request->user()->id))
                    ->orderBy('name')
                    ->get(['id', 'name'])
                : DB::table('clients')->orderBy('name')->get(['id', 'name']),
            'projects' => DB::table('projects')
                ->when($this->isGuest($request), fn ($query) => $query
                    ->join('project_followers', 'project_followers.project_id', '=', 'projects.id')
                    ->where('project_followers.user_id', $request->user()->id)
                )
                ->orderBy('projects.name')
                ->get(['projects.id', 'projects.name']),
            'services' => DB::table('services')->where('active', true)->orderBy('name')->get(['id', 'name']),
            'users' => $this->isGuest($request) ? $this->visibleUserOptionsForGuest($request->user()->id) : $this->userOptions(),
            'taskDependencyOptions' => in_array($section, ['tasks', 'calendar'], true)
                ? ($this->isGuest($request) ? collect() : $this->taskDependencyOptions())
                : [],
            'projectTemplates' => $section === 'projects' && ! $this->isGuest($request)
                ? $this->projectTemplateOptions()
                : [],
        ]);
    }

    public function projectTemplates(Request $request): Response
    {
        $this->ensureAdmin($request);
        abort_unless(Schema::hasTable('project_templates'), 404);

        return Inertia::render('Centro/ProjectTemplates', [
            'templates' => $this->projectTemplateRows(),
        ]);
    }

    public function createProjectTemplate(Request $request): Response
    {
        $this->ensureAdmin($request);
        abort_unless(Schema::hasTable('project_templates'), 404);

        return Inertia::render('Centro/ProjectTemplateForm', [
            'template' => null,
            'services' => DB::table('services')->where('active', true)->orderBy('name')->get(['id', 'name', 'color']),
            'users' => $this->userOptions(),
        ]);
    }

    public function showProjectTemplate(Request $request, string $id): Response
    {
        $this->ensureAdmin($request);
        abort_unless(Schema::hasTable('project_templates'), 404);
        $template = $this->projectTemplateRows()->firstWhere('id', $id);
        abort_if(! $template, 404);

        return Inertia::render('Centro/ProjectTemplateForm', [
            'template' => $template,
            'services' => DB::table('services')->where('active', true)->orderBy('name')->get(['id', 'name', 'color']),
            'users' => $this->userOptions(),
        ]);
    }

    public function storeProjectTemplate(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);
        $payload = $this->validatedProjectTemplatePayload($request);
        $id = (string) str()->uuid();

        DB::transaction(function () use ($payload, $id, $request) {
            DB::table('project_templates')->insert([
                'id' => $id,
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'color' => $payload['color'] ?? '#2563eb',
                'active' => (bool) ($payload['active'] ?? true),
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncProjectTemplateStructure($id, $payload['sections'] ?? []);
        });

        return redirect()->route('project-templates.show', $id)->with('status', 'Modello creato.');
    }

    public function updateProjectTemplate(Request $request, string $id): RedirectResponse
    {
        $this->ensureAdmin($request);
        DB::table('project_templates')->where('id', $id)->exists() || abort(404);
        $payload = $this->validatedProjectTemplatePayload($request);

        DB::transaction(function () use ($payload, $id) {
            DB::table('project_templates')
                ->where('id', $id)
                ->update([
                    'name' => $payload['name'],
                    'description' => $payload['description'] ?? null,
                    'color' => $payload['color'] ?? '#2563eb',
                    'active' => (bool) ($payload['active'] ?? true),
                    'updated_at' => now(),
                ]);

            DB::table('project_template_tasks')
                ->whereIn('project_template_section_id', DB::table('project_template_sections')->where('project_template_id', $id)->pluck('id'))
                ->delete();
            DB::table('project_template_sections')->where('project_template_id', $id)->delete();
            $this->syncProjectTemplateStructure($id, $payload['sections'] ?? []);
        });

        return back()->with('status', 'Modello aggiornato.');
    }

    public function destroyProjectTemplate(Request $request, string $id): RedirectResponse
    {
        $this->ensureAdmin($request);
        DB::table('project_templates')->where('id', $id)->delete();

        return back()->with('status', 'Modello eliminato.');
    }

    public function updateAbsenceStatus(Request $request, string $id): RedirectResponse
    {
        $this->ensureAdmin($request);
        $absence = DB::table('absence_requests')->where('id', $id)->first();
        abort_if(! $absence, 404);

        $payload = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        DB::table('absence_requests')
            ->where('id', $id)
            ->update([
                'status' => $payload['status'],
                'updated_at' => now(),
            ]);

        $this->notifyAbsencePeople(
            $absence->user_id,
            $request->user()->id,
            'absence_status',
            $request->user()->name.' ha '.($payload['status'] === 'approved' ? 'approvato' : 'rifiutato').' una richiesta assenza.',
        );

        return back()->with('status', $payload['status'] === 'approved' ? 'Richiesta approvata.' : 'Richiesta rifiutata.');
    }

    public function updateAbsence(Request $request, string $id): RedirectResponse
    {
        $this->ensureAdmin($request);
        $absence = DB::table('absence_requests')->where('id', $id)->first();
        abort_if(! $absence, 404);

        $payload = $this->validatedAbsencePayload($request);
        if ($payload['type'] !== 'sickness') {
            if ($absence->medical_document_path) {
                Storage::disk('local')->delete($absence->medical_document_path);
            }
            $payload['medical_document_path'] = null;
            $payload['medical_document_name'] = null;
            $payload['medical_document_mime'] = null;
        }

        DB::table('absence_requests')
            ->where('id', $id)
            ->update([
                ...$payload,
                'updated_at' => now(),
            ]);

        $this->notifyAbsencePeople(
            $absence->user_id,
            $request->user()->id,
            'absence_updated',
            $request->user()->name.' ha modificato una richiesta assenza.',
        );

        return back()->with('status', 'Richiesta aggiornata.');
    }

    public function updateAbsenceMedicalDocument(Request $request, string $id): RedirectResponse
    {
        $this->ensureAdmin($request);
        $absence = DB::table('absence_requests')->where('id', $id)->first();
        abort_if(! $absence, 404);

        $payload = $request->validate([
            'medical_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $file = $payload['medical_document'];
        $path = $file->store('absence-medical-documents', 'local');
        Storage::disk('local')->setVisibility($path, 'private');

        if ($absence->medical_document_path) {
            Storage::disk('local')->delete($absence->medical_document_path);
        }

        DB::table('absence_requests')
            ->where('id', $id)
            ->update([
                'medical_document_path' => $path,
                'medical_document_name' => $file->getClientOriginalName(),
                'medical_document_mime' => $file->getMimeType(),
                'updated_at' => now(),
            ]);

        $this->notifyAbsencePeople(
            $absence->user_id,
            $request->user()->id,
            'absence_updated',
            $request->user()->name.' ha aggiornato il documento medico di una richiesta assenza.',
        );

        return back()->with('status', 'Documento medico aggiornato.');
    }

    public function downloadAbsenceMedicalDocument(Request $request, string $id)
    {
        $absence = DB::table('absence_requests')->where('id', $id)->first();
        abort_if(! $absence || ! $absence->medical_document_path, 404);
        abort_unless($this->canAccessAbsence($request, $absence), 403);

        abort_unless(Storage::disk('local')->exists($absence->medical_document_path), 404);

        return Storage::disk('local')->download(
            $absence->medical_document_path,
            $absence->medical_document_name ?: 'documento-medico',
            ['Content-Type' => $absence->medical_document_mime ?: 'application/octet-stream'],
        );
    }

    public function destroyAbsence(Request $request, string $id): RedirectResponse
    {
        $this->ensureAdmin($request);
        $absence = DB::table('absence_requests')->where('id', $id)->first();
        abort_if(! $absence, 404);

        if ($absence->medical_document_path) {
            Storage::disk('local')->delete($absence->medical_document_path);
        }

        DB::table('absence_requests')->where('id', $id)->delete();

        $this->notifyAbsencePeople(
            $absence->user_id,
            $request->user()->id,
            'absence_deleted',
            $request->user()->name.' ha eliminato una richiesta assenza.',
        );

        return back()->with('status', 'Richiesta eliminata.');
    }

    public function companyDocuments(Request $request): Response
    {
        $canManage = $this->canManageDocuments($request);
        $userId = (string) $request->user()->id;
        $reportYear = (int) $request->integer('year', now('Europe/Rome')->year);
        $reportMonth = (int) $request->integer('month', now('Europe/Rome')->month);
        $reportUserId = $canManage && $request->filled('user_id') && $request->input('user_id') !== 'all'
            ? (string) $request->input('user_id')
            : null;

        return Inertia::render('Centro/Documents', [
            'canManage' => $canManage,
            'activeAdminSection' => $canManage ? $request->route('documentView') : null,
            'documents' => $this->companyDocumentRows($canManage ? null : $userId, $canManage),
            'messages' => $this->companyMessageRows($canManage ? null : $userId, $canManage),
            'attendanceReport' => $canManage ? $this->attendanceReportData($reportYear, $reportMonth, $reportUserId) : null,
            'groups' => $canManage ? $this->documentGroupRows() : [],
            'users' => $canManage ? $this->userOptions() : [],
            'documentUsers' => $canManage ? $this->companyDocumentUserRows() : [],
            'documentCategories' => $this->companyDocumentCategories(),
        ]);
    }

    public function showCompanyDocumentsUser(Request $request, string $userId): Response
    {
        $this->ensureAdmin($request);

        $user = DB::table('users')
            ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
            ->where('users.id', $userId)
            ->first(['users.id', 'users.name', 'users.email', 'profiles.avatar_url']);
        abort_if(! $user, 404);

        return Inertia::render('Centro/DocumentUserShow', [
            'user' => $user,
            'documents' => $this->companyDocumentRows($userId, false),
            'documentCategories' => $this->companyDocumentCategories(),
        ]);
    }

    public function companyDocumentArchive(Request $request, int $year): Response
    {
        $currentYear = (int) now('Europe/Rome')->year;
        abort_if($year >= $currentYear || $year < 2000, 404);

        $canManage = $this->canManageDocuments($request);
        $userId = (string) $request->user()->id;

        return Inertia::render('Centro/DocumentArchive', [
            'canManage' => $canManage,
            'year' => $year,
            'documents' => $this->companyDocumentRows($canManage ? null : $userId, $canManage, $year),
            'groups' => $canManage ? $this->documentGroupRows() : [],
            'users' => $canManage ? $this->userOptions() : [],
            'documentCategories' => $this->companyDocumentCategories(),
        ]);
    }

    public function exportAttendanceReport(Request $request)
    {
        $this->ensureAdmin($request);

        if ($request->input('user_id') === 'all') {
            $request->merge(['user_id' => null]);
        }

        $payload = $request->validate([
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'user_id' => ['nullable', 'string', Rule::exists('users', 'id')],
        ]);

        $year = (int) ($payload['year'] ?? now('Europe/Rome')->year);
        $month = (int) ($payload['month'] ?? now('Europe/Rome')->month);
        $report = $this->attendanceReportData($year, $month, $payload['user_id'] ?? null);
        $path = $this->buildAttendanceReportXlsx($report);

        return response()->download($path, $report['file_name'], [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function storeCompanyDocument(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in(array_keys($this->companyDocumentCategories()))],
            'document_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'audience' => ['required', Rule::in(['all', 'users', 'groups'])],
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['uuid', 'exists:document_groups,id'],
        ]);

        if ($payload['audience'] === 'users' && empty($payload['user_ids'])) {
            return back()->withErrors(['user_ids' => 'Seleziona almeno un utente.'])->withInput();
        }

        if ($payload['audience'] === 'groups' && empty($payload['group_ids'])) {
            return back()->withErrors(['group_ids' => 'Seleziona almeno un gruppo.'])->withInput();
        }

        $documentId = (string) str()->uuid();
        $file = $payload['file'];
        $path = $file->store('company-documents', 'local');
        Storage::disk('local')->setVisibility($path, 'private');
        $now = now();

        DB::transaction(function () use ($payload, $documentId, $file, $path, $request, $now) {
            DB::table('company_documents')->insert([
                'id' => $documentId,
                'title' => $payload['title'],
                'description' => $payload['description'] ?? null,
                'category' => $payload['category'],
                'document_year' => (int) ($payload['document_year'] ?? now('Europe/Rome')->year),
                'audience' => $payload['audience'],
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_mime' => $file->getMimeType() ?: 'application/pdf',
                'file_size' => $file->getSize() ?: 0,
                'created_by' => $request->user()->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (collect($payload['user_ids'] ?? [])->unique()->values() as $userId) {
                DB::table('company_document_user')->insert([
                    'id' => (string) str()->uuid(),
                    'company_document_id' => $documentId,
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach (collect($payload['group_ids'] ?? [])->unique()->values() as $groupId) {
                DB::table('company_document_group')->insert([
                    'id' => (string) str()->uuid(),
                    'company_document_id' => $documentId,
                    'document_group_id' => $groupId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        $recipientIds = $this->companyDocumentRecipientIds($documentId);
        $this->ensureCompanyDocumentReadRows($documentId, $recipientIds);
        $this->notifyUsers(
            $recipientIds,
            $request->user()->id,
            'company_document_created',
            $request->user()->name.' ha pubblicato il documento "'.$payload['title'].'".',
            null,
            $documentId,
        );

        return redirect()->route('documents.index')->with('status', 'Documento pubblicato.');
    }

    public function showCompanyDocument(Request $request, string $id): Response
    {
        $document = DB::table('company_documents')->where('id', $id)->first();
        abort_if(! $document, 404);
        abort_unless($this->canAccessCompanyDocument($request, $document), 403);

        $canManage = $this->canManageDocuments($request);
        $userId = (string) $request->user()->id;
        $isRecipient = $this->companyDocumentRecipientIds($id)->contains($userId);
        if ($isRecipient) {
            $this->markCompanyDocumentOpened($id, $userId);
        }

        return Inertia::render('Centro/DocumentShow', [
            'canManage' => $canManage,
            'document' => $this->companyDocumentRow($document, $isRecipient ? $userId : null),
            'readers' => $canManage ? $this->companyDocumentReaderRows($id) : [],
            'documentCategories' => $this->companyDocumentCategories(),
        ]);
    }

    public function updateCompanyDocumentCategory(Request $request, string $id): RedirectResponse
    {
        $this->ensureAdmin($request);
        DB::table('company_documents')->where('id', $id)->exists() || abort(404);

        $payload = $request->validate([
            'category' => ['required', Rule::in(array_keys($this->companyDocumentCategories()))],
        ]);

        DB::table('company_documents')->where('id', $id)->update([
            'category' => $payload['category'],
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Categoria documento aggiornata.');
    }

    public function viewCompanyDocumentFile(Request $request, string $id)
    {
        $document = DB::table('company_documents')->where('id', $id)->first();
        abort_if(! $document, 404);
        abort_unless($this->canAccessCompanyDocument($request, $document), 403);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        $userId = (string) $request->user()->id;
        if ($this->companyDocumentRecipientIds($id)->contains($userId)) {
            $this->markCompanyDocumentOpened($id, $userId);
        }

        return response()->file(Storage::disk('local')->path($document->file_path), [
            'Content-Type' => $document->file_mime ?: 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$document->file_name.'"',
        ]);
    }

    public function markCompanyDocumentRead(Request $request, string $id): RedirectResponse
    {
        $document = DB::table('company_documents')->where('id', $id)->first();
        abort_if(! $document, 404);
        abort_unless($this->canAccessCompanyDocument($request, $document), 403);
        abort_unless($this->companyDocumentRecipientIds($id)->contains((string) $request->user()->id), 403);

        $row = DB::table('company_document_reads')
            ->where('company_document_id', $id)
            ->where('user_id', $request->user()->id)
            ->first(['id']);

        if ($row) {
            DB::table('company_document_reads')->where('id', $row->id)->update([
                'opened_at' => now(),
                'read_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('company_document_reads')->insert([
                'id' => (string) str()->uuid(),
                'company_document_id' => $id,
                'user_id' => $request->user()->id,
                'opened_at' => now(),
                'read_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('status', 'Documento segnato come letto.');
    }

    public function destroyCompanyDocument(Request $request, string $id): RedirectResponse
    {
        $this->ensureAdmin($request);
        $document = DB::table('company_documents')->where('id', $id)->first();
        abort_if(! $document, 404);

        Storage::disk('local')->delete($document->file_path);
        DB::table('company_documents')->where('id', $id)->delete();

        return back()->with('status', 'Documento eliminato.');
    }

    public function storeCompanyMessage(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
            'audience' => ['required', Rule::in(['all', 'users', 'groups'])],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['uuid', 'exists:document_groups,id'],
        ]);

        if ($payload['audience'] === 'users' && empty($payload['user_ids'])) {
            return back()->withErrors(['message_user_ids' => 'Seleziona almeno un utente.'])->withInput();
        }

        if ($payload['audience'] === 'groups' && empty($payload['group_ids'])) {
            return back()->withErrors(['message_group_ids' => 'Seleziona almeno un gruppo.'])->withInput();
        }

        $messageId = (string) str()->uuid();
        $now = now();

        DB::transaction(function () use ($payload, $messageId, $request, $now) {
            DB::table('company_messages')->insert([
                'id' => $messageId,
                'title' => $payload['title'],
                'body' => $payload['body'] ?? null,
                'audience' => $payload['audience'],
                'created_by' => $request->user()->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (collect($payload['user_ids'] ?? [])->unique()->values() as $userId) {
                DB::table('company_message_user')->insert([
                    'id' => (string) str()->uuid(),
                    'company_message_id' => $messageId,
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach (collect($payload['group_ids'] ?? [])->unique()->values() as $groupId) {
                DB::table('company_message_group')->insert([
                    'id' => (string) str()->uuid(),
                    'company_message_id' => $messageId,
                    'document_group_id' => $groupId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        $recipientIds = $this->companyMessageRecipientIds($messageId);
        $this->ensureCompanyMessageReadRows($messageId, $recipientIds);
        $this->notifyUsers(
            $recipientIds,
            $request->user()->id,
            'company_message_created',
            $request->user()->name.' ha pubblicato il messaggio "'.$payload['title'].'".',
            null,
            null,
            $messageId,
        );

        return redirect()->route('documents.index')->with('status', 'Messaggio pubblicato.');
    }

    public function showCompanyMessage(Request $request, string $id): Response
    {
        $message = DB::table('company_messages')->where('id', $id)->first();
        abort_if(! $message, 404);
        abort_unless($this->canAccessCompanyMessage($request, $message), 403);

        $canManage = $this->canManageDocuments($request);
        $userId = (string) $request->user()->id;
        $isRecipient = $this->companyMessageRecipientIds($id)->contains($userId);
        if ($isRecipient) {
            $this->markCompanyMessageOpened($id, $userId);
        }

        return Inertia::render('Centro/DocumentMessageShow', [
            'canManage' => $canManage,
            'message' => $this->companyMessageRow($message, $isRecipient ? $userId : null),
            'readers' => $canManage ? $this->companyMessageReaderRows($id) : [],
            'groups' => $canManage ? $this->documentGroupRows() : [],
            'users' => $canManage ? $this->userOptions() : [],
        ]);
    }

    public function markCompanyMessageRead(Request $request, string $id): RedirectResponse
    {
        $message = DB::table('company_messages')->where('id', $id)->first();
        abort_if(! $message, 404);
        abort_unless($this->canAccessCompanyMessage($request, $message), 403);
        abort_unless($this->companyMessageRecipientIds($id)->contains((string) $request->user()->id), 403);

        $row = DB::table('company_message_reads')
            ->where('company_message_id', $id)
            ->where('user_id', $request->user()->id)
            ->first(['id']);

        if ($row) {
            DB::table('company_message_reads')->where('id', $row->id)->update([
                'opened_at' => now(),
                'read_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('company_message_reads')->insert([
                'id' => (string) str()->uuid(),
                'company_message_id' => $id,
                'user_id' => $request->user()->id,
                'opened_at' => now(),
                'read_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('status', 'Messaggio segnato come letto.');
    }

    public function destroyCompanyMessage(Request $request, string $id): RedirectResponse
    {
        $this->ensureAdmin($request);
        DB::table('company_messages')->where('id', $id)->delete();

        return back()->with('status', 'Messaggio eliminato.');
    }

    public function storeDocumentGroup(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
        ]);

        if (empty($payload['user_ids'])) {
            return back()->withErrors(['user_ids' => 'Seleziona almeno un utente.'])->withInput();
        }

        $groupId = (string) str()->uuid();
        DB::transaction(function () use ($payload, $groupId, $request) {
            DB::table('document_groups')->insert([
                'id' => $groupId,
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncDocumentGroupUsers($groupId, $payload['user_ids'] ?? []);
        });

        return back()->with('status', 'Gruppo creato.');
    }

    public function updateDocumentGroup(Request $request, string $id): RedirectResponse
    {
        $this->ensureAdmin($request);
        abort_unless(DB::table('document_groups')->where('id', $id)->exists(), 404);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
        ]);

        if (empty($payload['user_ids'])) {
            return back()->withErrors(['user_ids' => 'Seleziona almeno un utente.'])->withInput();
        }

        DB::transaction(function () use ($payload, $id) {
            DB::table('document_groups')->where('id', $id)->update([
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'updated_at' => now(),
            ]);

            $this->syncDocumentGroupUsers($id, $payload['user_ids'] ?? []);
        });

        return back()->with('status', 'Gruppo aggiornato.');
    }

    public function destroyDocumentGroup(Request $request, string $id): RedirectResponse
    {
        $this->ensureAdmin($request);
        DB::table('document_groups')->where('id', $id)->delete();

        return back()->with('status', 'Gruppo eliminato.');
    }

    public function passwords(Request $request): Response
    {
        abort_if($this->isGuest($request), 403);
        $view = $request->route('view', 'items');
        $selectedVault = null;
        $selectedGroup = null;

        if ($view === 'vault-create') {
            abort_if($this->isGuest($request), 403);
        }

        if ($view === 'group-create') {
            abort_unless($this->canManagePasswords($request), 403);
        }

        if ($view === 'vault-detail') {
            $selectedVault = $this->passwordVaultRows($request)->firstWhere('id', $request->route('id'));
            abort_if(! $selectedVault, 404);
            abort_unless($selectedVault->can_edit, 403);
        }

        if ($view === 'group-detail') {
            $selectedGroup = $this->passwordGroupRows($request)->firstWhere('id', $request->route('id'));
            abort_if(! $selectedGroup, 404);
            abort_unless($this->canManagePasswords($request), 403);
        }

        return Inertia::render('Centro/Passwords', [
            'view' => $view,
            'canManage' => $this->canManagePasswords($request),
            'canCreateVaults' => ! $this->isGuest($request),
            'vaults' => $this->passwordVaultRows($request),
            'groups' => $this->passwordGroupRows($request),
            'items' => $this->passwordItemRows($request, $view === 'compromised'),
            'users' => $this->userOptions(),
            'clients' => $this->isGuest($request)
                ? DB::table('clients')->whereIn('id', $this->visibleClientIdsForUser($request->user()->id))->orderBy('name')->get(['id', 'name'])
                : DB::table('clients')->orderBy('name')->get(['id', 'name']),
            'projects' => $this->isGuest($request)
                ? $this->visibleProjectOptionsForUser($request->user()->id)
                : DB::table('projects')->orderBy('name')->get(['id', 'name']),
            'selectedVault' => $selectedVault,
            'selectedGroup' => $selectedGroup,
            'nav' => [
                ['route' => 'passwords.index', 'label' => 'Password', 'view' => 'items'],
                ['route' => 'passwords.vaults', 'label' => 'Casseforti', 'view' => 'vaults'],
                ['route' => 'passwords.groups', 'label' => 'Gruppi', 'view' => 'groups'],
                ['route' => 'passwords.compromised', 'label' => 'Compromesse', 'view' => 'compromised'],
            ],
        ]);
    }

    public function storePasswordVault(Request $request): RedirectResponse
    {
        abort_if($this->isGuest($request), 403);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'color' => ['nullable', 'string', 'max:24'],
            'visibility' => ['nullable', Rule::in(['personal', 'shared'])],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['uuid', 'exists:password_groups,id'],
        ]);
        if (! empty($payload['user_ids']) && ! empty($payload['group_ids'])) {
            return back()->withErrors(['group_ids' => 'Scegli gruppi oppure utenti singoli, non entrambi.'])->withInput();
        }
        if (! $this->canManagePasswords($request)) {
            $payload['visibility'] = 'personal';
            $payload['user_ids'] = [];
            $payload['group_ids'] = [];
        }

        $vaultId = (string) str()->uuid();
        DB::transaction(function () use ($payload, $vaultId, $request) {
            DB::table('password_vaults')->insert([
                'id' => $vaultId,
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'color' => $payload['color'] ?? '#0B6EF3',
                'visibility' => $payload['visibility'] ?? 'personal',
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncPasswordVaultShares(
                $vaultId,
                ($payload['visibility'] ?? 'personal') === 'shared' ? ($payload['user_ids'] ?? []) : [],
                ($payload['visibility'] ?? 'personal') === 'shared' ? ($payload['group_ids'] ?? []) : [],
            );
        });

        return redirect()->route('passwords.vaults')->with('status', 'Cassaforte creata.');
    }

    public function updatePasswordVault(Request $request, string $id): RedirectResponse
    {
        abort_if($this->isGuest($request), 403);
        $vault = DB::table('password_vaults')->where('id', $id)->first();
        abort_if(! $vault, 404);
        abort_unless($this->canEditPasswordVault($request, $vault), 403);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'color' => ['nullable', 'string', 'max:24'],
            'visibility' => ['nullable', Rule::in(['personal', 'shared'])],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['uuid', 'exists:password_groups,id'],
        ]);
        if (! empty($payload['user_ids']) && ! empty($payload['group_ids'])) {
            return back()->withErrors(['group_ids' => 'Scegli gruppi oppure utenti singoli, non entrambi.'])->withInput();
        }
        if (! $this->canManagePasswords($request)) {
            $payload['visibility'] = 'personal';
            $payload['user_ids'] = [];
            $payload['group_ids'] = [];
        }

        DB::transaction(function () use ($payload, $id) {
            DB::table('password_vaults')->where('id', $id)->update([
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'color' => $payload['color'] ?? '#0B6EF3',
                'visibility' => $payload['visibility'] ?? 'personal',
                'updated_at' => now(),
            ]);

            $this->syncPasswordVaultShares(
                $id,
                ($payload['visibility'] ?? 'personal') === 'shared' ? ($payload['user_ids'] ?? []) : [],
                ($payload['visibility'] ?? 'personal') === 'shared' ? ($payload['group_ids'] ?? []) : [],
            );
        });

        return back()->with('status', 'Cassaforte aggiornata.');
    }

    public function destroyPasswordVault(Request $request, string $id): RedirectResponse
    {
        $vault = DB::table('password_vaults')->where('id', $id)->first();
        abort_if(! $vault, 404);
        abort_unless($this->canEditPasswordVault($request, $vault), 403);
        DB::table('password_vaults')->where('id', $id)->delete();

        if (str_contains((string) $request->headers->get('referer'), "/passwords/vaults/{$id}")) {
            return redirect()->route('passwords.vaults')->with('status', 'Cassaforte eliminata.');
        }

        return back()->with('status', 'Cassaforte eliminata.');
    }

    public function storePasswordGroup(Request $request): RedirectResponse
    {
        $this->ensureCanManagePasswordStructure($request);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
        ]);

        if (empty($payload['user_ids'])) {
            return back()->withErrors(['user_ids' => 'Seleziona almeno un utente.'])->withInput();
        }

        $groupId = (string) str()->uuid();
        DB::transaction(function () use ($payload, $groupId, $request) {
            DB::table('password_groups')->insert([
                'id' => $groupId,
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncPasswordGroupUsers($groupId, $payload['user_ids'] ?? []);
        });

        return redirect()->route('passwords.groups')->with('status', 'Gruppo password creato.');
    }

    public function updatePasswordGroup(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManagePasswordStructure($request);
        abort_unless(DB::table('password_groups')->where('id', $id)->exists(), 404);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
        ]);

        if (empty($payload['user_ids'])) {
            return back()->withErrors(['user_ids' => 'Seleziona almeno un utente.'])->withInput();
        }

        DB::transaction(function () use ($payload, $id) {
            DB::table('password_groups')->where('id', $id)->update([
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'updated_at' => now(),
            ]);

            $this->syncPasswordGroupUsers($id, $payload['user_ids'] ?? []);
        });

        return back()->with('status', 'Gruppo password aggiornato.');
    }

    public function destroyPasswordGroup(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManagePasswordStructure($request);
        DB::table('password_groups')->where('id', $id)->delete();

        if (str_contains((string) $request->headers->get('referer'), "/passwords/groups/{$id}")) {
            return redirect()->route('passwords.groups')->with('status', 'Gruppo password eliminato.');
        }

        return back()->with('status', 'Gruppo password eliminato.');
    }

    public function storePasswordItem(Request $request): RedirectResponse
    {
        abort_if($this->isGuest($request), 403);
        $payload = $this->validatedPasswordItemPayload($request);
        $itemId = (string) str()->uuid();

        DB::transaction(function () use ($payload, $itemId, $request) {
            DB::table('password_items')->insert($this->passwordItemPayloadForDatabase($payload, [
                'id' => $itemId,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            $this->syncPasswordItemShares($itemId, $payload['user_ids'] ?? [], $payload['group_ids'] ?? [], $payload['share_permission'] ?? 'view');
            $this->logPasswordAction($itemId, $request->user()->id, 'created', 'Elemento password creato.');
        });

        return back()->with('status', 'Password salvata.');
    }

    public function updatePasswordItem(Request $request, string $id): RedirectResponse
    {
        abort_if($this->isGuest($request), 403);
        $item = DB::table('password_items')->where('id', $id)->first();
        abort_if(! $item, 404);
        abort_unless($this->canEditPasswordItem($request, $item), 403);

        $payload = $this->validatedPasswordItemPayload($request, true);

        DB::transaction(function () use ($payload, $id, $request) {
            DB::table('password_items')->where('id', $id)->update($this->passwordItemPayloadForDatabase($payload, [
                'updated_by' => $request->user()->id,
                'updated_at' => now(),
            ], true));

            $this->syncPasswordItemShares($id, $payload['user_ids'] ?? [], $payload['group_ids'] ?? [], $payload['share_permission'] ?? 'view');
            $this->logPasswordAction($id, $request->user()->id, 'updated', 'Elemento password aggiornato.');
        });

        return back()->with('status', 'Password aggiornata.');
    }

    public function revealPasswordItem(Request $request, string $id): JsonResponse
    {
        abort_if($this->isGuest($request), 403);
        $item = DB::table('password_items')->where('id', $id)->first();
        abort_if(! $item, 404);
        abort_unless($this->canViewPasswordItem($request, $item), 403);

        $this->logPasswordAction($id, $request->user()->id, 'revealed', 'Password rivelata.');

        return response()->json([
            'username' => $item->username ?? '',
            'password' => $item->encrypted_password ? Crypt::decryptString($item->encrypted_password) : '',
        ]);
    }

    public function destroyPasswordItem(Request $request, string $id): RedirectResponse
    {
        abort_unless($this->canManagePasswords($request), 403);
        $item = DB::table('password_items')->where('id', $id)->first();
        abort_if(! $item, 404);
        abort_unless($this->canEditPasswordItem($request, $item), 403);

        $this->logPasswordAction($id, $request->user()->id, 'deleted', 'Elemento password eliminato.');
        DB::table('password_items')->where('id', $id)->delete();

        return back()->with('status', 'Password eliminata.');
    }

    public function notifications(Request $request): Response
    {
        $this->archiveExpiredNotifications($request->user()->id);
        $this->purgeExpiredArchivedNotifications($request->user()->id);

        $archived = $request->boolean('archived');

        return Inertia::render('Centro/Notifications', [
            'notifications' => DB::table('notifications')
                ->leftJoin('tasks', 'tasks.id', '=', 'notifications.task_id')
                ->where('notifications.user_id', $request->user()->id)
                ->when($archived, fn ($query) => $query->whereNotNull('notifications.archived_at'), fn ($query) => $query->whereNull('notifications.archived_at'))
                ->latest('notifications.created_at')
                ->limit(120)
                ->get([
                    'notifications.id',
                    'notifications.task_id',
                    'notifications.company_document_id',
                    'notifications.company_message_id',
                    'notifications.type',
                    'notifications.message',
                    'notifications.read',
                    'notifications.archived_at',
                    'notifications.created_at',
                    'tasks.title as task_title',
                ]),
            'archived' => $archived,
            'activeCount' => DB::table('notifications')
                ->where('user_id', $request->user()->id)
                ->whereNull('archived_at')
                ->count(),
            'archivedCount' => DB::table('notifications')
                ->where('user_id', $request->user()->id)
                ->whereNotNull('archived_at')
                ->count(),
        ]);
    }

    public function modules(Request $request): Response
    {
        $this->ensureAdmin($request);

        return Inertia::render('Centro/Modules', [
            'folders' => $this->adminModuleFolderRows(),
            'modules' => $this->adminModuleRows(),
            'agentOptions' => $this->adminModuleAgentOptions(),
            'moduleStatusOptions' => $this->adminModuleStatusOptions(),
        ]);
    }

    public function storeModuleFolder(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);
        $payload = $this->validatedModuleFolderPayload($request);

        DB::table('admin_module_folders')->insert([
            'id' => (string) str()->uuid(),
            'name' => $payload['name'],
            'description' => $payload['description'] ?? null,
            'color' => $payload['color'] ?? '#2563eb',
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Cartella creata.');
    }

    public function updateModuleFolder(Request $request, string $id): RedirectResponse
    {
        $this->ensureAdmin($request);
        DB::table('admin_module_folders')->where('id', $id)->exists() || abort(404);
        $payload = $this->validatedModuleFolderPayload($request);

        DB::table('admin_module_folders')->where('id', $id)->update([
            'name' => $payload['name'],
            'description' => $payload['description'] ?? null,
            'color' => $payload['color'] ?? '#2563eb',
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Cartella aggiornata.');
    }

    public function destroyModuleFolder(Request $request, string $id): RedirectResponse
    {
        $this->ensureAdmin($request);
        DB::table('admin_module_folders')->where('id', $id)->exists() || abort(404);
        DB::table('admin_module_folders')->where('id', $id)->delete();

        return back()->with('status', 'Cartella eliminata.');
    }

    public function storeModuleItem(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);
        $payload = $this->validatedModuleItemPayload($request);

        DB::table('admin_modules')->insert([
            'id' => (string) str()->uuid(),
            ...$this->moduleItemDatabasePayload($payload),
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Modulo creato.');
    }

    public function updateModuleItem(Request $request, string $id): RedirectResponse
    {
        $this->ensureAdmin($request);
        DB::table('admin_modules')->where('id', $id)->exists() || abort(404);
        $payload = $this->validatedModuleItemPayload($request);
        $payload['dependency_module_ids'] = collect($payload['dependency_module_ids'] ?? [])
            ->reject(fn ($moduleId) => $moduleId === $id)
            ->values()
            ->all();
        $this->ensureModuleParentIsValid($payload['parent_module_id'] ?? null, $id);

        DB::table('admin_modules')->where('id', $id)->update([
            ...$this->moduleItemDatabasePayload($payload),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Modulo aggiornato.');
    }

    public function destroyModuleItem(Request $request, string $id): RedirectResponse
    {
        $this->ensureAdmin($request);
        DB::table('admin_modules')->where('id', $id)->exists() || abort(404);
        DB::table('admin_modules')->where('id', $id)->delete();

        return back()->with('status', 'Modulo eliminato.');
    }

    public function enablePush(Request $request): \Illuminate\Contracts\View\View
    {
        return view('push-enable', [
            'vapidPublicKey' => config('services.webpush.public_key'),
            'subscriptionCount' => DB::table('push_subscriptions')
                ->where('user_id', $request->user()->id)
                ->count(),
        ]);
    }

    public function testPush(Request $request): JsonResponse
    {
        $notificationId = (string) str()->uuid();

        $this->sendBrowserPushNotification(
            (string) $request->user()->id,
            $notificationId,
            'Notifica push di prova da Il Centro.',
        );

        return response()->json([
            'status' => 'queued',
            'subscriptions' => DB::table('push_subscriptions')
                ->where('user_id', $request->user()->id)
                ->count(),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $section = $request->route('section');
        if ($section === 'users') {
            $this->ensureSuperadmin($request);
        }
        if ($section === 'absences') {
            $this->ensureAdmin($request);
        }

        $config = $this->config($section);
        $record = match ($section) {
            'users' => DB::table('users')
                ->leftJoin('user_roles', 'user_roles.user_id', '=', 'users.id')
                ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
                ->where('users.id', $id)
                ->select('users.*', 'user_roles.role', 'profiles.avatar_url', 'profiles.employee_code', 'profiles.job_title', 'profiles.phone', 'profiles.bio', 'profiles.completion_effect', 'profiles.smartworking_day')
                ->first(),
            'absences' => DB::table('absence_requests')
                ->leftJoin('users', 'users.id', '=', 'absence_requests.user_id')
                ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
                ->where('absence_requests.id', $id)
                ->select('absence_requests.*', 'users.name as user_name', 'users.email as user_email', 'profiles.avatar_url as user_avatar_url')
                ->first(),
            default => DB::table($config['table'])->where('id', $id)->first(),
        };
        abort_if(! $record, 404);
        $this->ensureRoleCanViewRecord($request, $section, $id);
        $this->ensureGuestCanViewRecord($request, $section, $id);

        $related = match ($section) {
            'clients' => [
                'projects' => DB::table('projects')->where('client_id', $id)->latest()->get(),
                'tasks' => DB::table('tasks')
                    ->where('client_id', $id)
                    ->where(fn ($query) => $query->whereNull('parent_task_id')->orWhereRaw("TRIM(parent_task_id) = ''"))
                    ->latest()
                    ->limit(20)
                    ->get(),
                'documents' => DB::table('documents')->where('client_id', $id)->latest()->limit(20)->get(),
                'contacts' => DB::table('client_contacts')->where('client_id', $id)->latest()->get(),
                'clientServices' => DB::table('client_services')->where('client_id', $id)->pluck('service_id'),
                'services' => DB::table('services')->where('active', true)->orderBy('name')->get(['id', 'name', 'color']),
                'subscriptions' => DB::table('subscriptions')->where('client_id', $id)->latest()->get(),
            ],
            'projects' => [
                'sections' => $this->projectSections($id),
                'tasks' => $this->projectTaskRows($id, $this->isGuest($request) ? $request->user()->id : null),
                'messages' => $this->projectMessages($id),
                'resources' => $this->projectFiles($id, 'resource'),
                'files' => $this->projectFiles($id, 'file'),
                'client' => $record->client_id ? DB::table('clients')->where('id', $record->client_id)->first() : null,
                'projectClients' => $this->isGuest($request)
                    ? DB::table('clients')->where('id', $record->client_id)->get(['id', 'name'])
                    : DB::table('clients')->orderBy('name')->get(['id', 'name']),
                'projectUsers' => $this->isGuest($request) ? $this->userOptionsForProject($id) : $this->userOptions(),
                'users' => $this->isGuest($request) ? $this->userOptionsForProject($id) : $this->userOptions(),
                'taskClients' => $this->isGuest($request)
                    ? DB::table('clients')->where('id', $record->client_id)->get(['id', 'name'])
                    : DB::table('clients')->orderBy('name')->get(['id', 'name']),
                'taskProjects' => $this->isGuest($request)
                    ? DB::table('projects')->where('id', $id)->get(['id', 'name'])
                    : DB::table('projects')->orderBy('name')->get(['id', 'name']),
                'taskServices' => DB::table('services')->where('active', true)->orderBy('name')->get(['id', 'name', 'color']),
                'taskDependencyOptions' => $this->isGuest($request) ? collect() : $this->taskDependencyOptions(),
                'followers' => DB::table('project_followers')->where('project_id', $id)->pluck('user_id'),
            ],
            'tasks' => [
                'comments' => DB::table('task_comments')
                    ->leftJoin('users', 'users.id', '=', 'task_comments.user_id')
                    ->where('task_id', $id)
                    ->latest('task_comments.created_at')
                    ->limit(30)
                    ->get(['task_comments.*', 'users.name as user_name']),
                'activity' => $this->taskActivityRows(collect([$id]))[$id] ?? collect(),
                'assignees' => DB::table('task_assignees')->where('task_id', $id)->pluck('user_id'),
                'followers' => DB::table('task_followers')->where('task_id', $id)->pluck('user_id'),
                'users' => $this->isGuest($request)
                    ? $this->userOptions()->filter(fn ($user) => collect([
                        ...DB::table('task_assignees')->where('task_id', $id)->pluck('user_id')->all(),
                        ...DB::table('task_followers')->where('task_id', $id)->pluck('user_id')->all(),
                    ])->contains($user->id))->values()
                    : $this->userOptions(),
                'taskClients' => $this->isGuest($request)
                    ? DB::table('clients')->where('id', $record->client_id)->get(['id', 'name'])
                    : DB::table('clients')->orderBy('name')->get(['id', 'name']),
                'taskProjects' => $this->isGuest($request)
                    ? $this->visibleProjectOptionsForUser($request->user()->id)
                    : DB::table('projects')->orderBy('name')->get(['id', 'name']),
                'taskServices' => DB::table('services')->where('active', true)->orderBy('name')->get(['id', 'name', 'color']),
                'subtasks' => $this->taskSubtaskRows($id),
                'parentTask' => $record->parent_task_id ? DB::table('tasks')->where('id', $record->parent_task_id)->first(['id', 'title']) : null,
                'project' => $record->project_id ? DB::table('projects')->where('id', $record->project_id)->first() : null,
                'client' => $record->client_id ? DB::table('clients')->where('id', $record->client_id)->first() : null,
                'dependencies' => ($this->taskDependencyRows(collect([$id]))[$id]['dependencies'] ?? collect())->values(),
                'dependents' => ($this->taskDependencyRows(collect([$id]))[$id]['dependents'] ?? collect())->values(),
                'taskDependencyOptions' => $this->isGuest($request) ? collect() : $this->taskDependencyOptions($id),
            ],
            'billing' => [
                'client' => DB::table('clients')->where('id', $record->client_id)->first(),
                'lines' => DB::table('document_lines')->where('document_id', $id)->orderBy('position')->get(),
                'payments' => DB::table('document_payments')->where('document_id', $id)->latest('paid_at')->get(),
            ],
            'users' => [
                'roleOptions' => ['superadmin', 'admin', 'editor', 'guest'],
                'performance' => $this->userPerformanceStats($id),
            ],
            'absences' => [
                'user' => [
                    'id' => $record->user_id,
                    'name' => $record->user_name,
                    'email' => $record->user_email,
                    'avatar_url' => $record->user_avatar_url,
                ],
            ],
            default => [],
        };

        return Inertia::render('Centro/Show', [
            ...$config,
            'record' => $record,
            'related' => $related,
        ]);
    }

    public function store(Request $request, string $section): RedirectResponse
    {
        $this->ensureRoleCanStore($request, $section);

        if ($section === 'users') {
            return $this->storeUser($request);
        }

        if ($section === 'billing') {
            return $this->storeDocument($request);
        }

        if (str_starts_with($section, 'updates-')) {
            return $this->storeServiceUpdate($request, $section);
        }

        $payload = $this->validatedPayload($request, $section);
        $taskPeople = $section === 'tasks' ? $this->extractTaskPeoplePayload($payload) : null;
        $taskDependencies = $section === 'tasks' ? $this->extractTaskDependencyPayload($payload) : null;
        $projectFollowers = $section === 'projects' ? $this->extractProjectFollowersPayload($payload) : null;
        $projectTemplate = $section === 'projects' ? $this->extractProjectTemplatePayload($payload) : null;

        if ($taskDependencies) {
            $dependencyIds = collect($taskDependencies['dependencies']);
            $dependentIds = collect($taskDependencies['dependents']);

            if ($dependencyIds->intersect($dependentIds)->isNotEmpty()) {
                return back()->withErrors(['dependencies' => 'Una task non può essere sia bloccante sia bloccata dalla stessa task.'])->withInput();
            }

            $cyclicPair = $dependencyIds->first(fn ($dependencyId) => $dependentIds->first(fn ($dependentId) => $this->taskDependsOn($dependencyId, $dependentId)));
            if ($cyclicPair) {
                return back()->withErrors(['dependencies' => 'Questa relazione creerebbe un ciclo tra task.'])->withInput();
            }
        }

        $payload['id'] = (string) str()->uuid();
        $payload['created_at'] = now();
        $payload['updated_at'] = now();

        if (in_array($section, ['clients', 'projects', 'tasks'], true)) {
            $payload['created_by'] = $request->user()->id;
        }

        if ($section === 'tasks' && ! empty($payload['project_section_id'])) {
            $sectionProjectId = DB::table('project_sections')
                ->where('id', $payload['project_section_id'])
                ->value('project_id');

            if ($sectionProjectId) {
                $payload['project_id'] = $sectionProjectId;
            }
        }

        DB::table($this->config($section)['table'])->insert($payload);

        if ($section === 'projects') {
            if ($projectFollowers !== null) {
                $this->syncProjectFollowersList($payload['id'], $projectFollowers);
            }
            if ($projectTemplate && ! empty($projectTemplate['template_id'])) {
                $this->createProjectTasksFromTemplate(
                    $payload['id'],
                    $projectTemplate['template_id'],
                    $projectTemplate['start_date'] ?: now('Europe/Rome')->toDateString(),
                    $request->user()->id,
                    $payload['client_id'] ?? null,
                );
            }
        }

        if ($section === 'tasks') {
            $this->syncTaskPeopleLists($payload['id'], $taskPeople['assignees'] ?? [], $taskPeople['followers'] ?? []);
            if ($taskDependencies) {
                $this->syncTaskDependencyEdges($payload['id'], $taskDependencies['dependencies'], $taskDependencies['dependents']);
            }
            $this->notifyTaskPeople(
                $payload['id'],
                $request->user()->id,
                'task_created',
                $request->user()->name.' ha creato la task "'.$payload['title'].'".',
            );
        }

        return back()
            ->with('status', 'Creato.')
            ->with('created_id', $payload['id']);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $section = $request->route('section');
        $this->ensureRoleCanUpdateRecord($request, $section, $id);

        if ($section === 'users') {
            return $this->updateUser($request, $id);
        }

        if ($section === 'billing') {
            return $this->updateDocument($request, $id);
        }

        if (str_starts_with($section, 'updates-')) {
            return $this->updateServiceUpdate($request, $id);
        }

        $payload = $this->validatedPayload($request, $section);
        $taskPeople = $section === 'tasks' ? $this->extractTaskPeoplePayload($payload) : null;
        $taskDependencies = $section === 'tasks' ? $this->extractTaskDependencyPayload($payload) : null;
        if ($section === 'tasks' && $this->isGuest($request)) {
            $taskPeople = null;
            $taskDependencies = null;
        }
        $projectFollowers = $section === 'projects' ? $this->extractProjectFollowersPayload($payload) : null;
        $oldTask = $section === 'tasks' ? DB::table('tasks')->where('id', $id)->first() : null;
        $oldProject = $section === 'projects' ? DB::table('projects')->where('id', $id)->first() : null;
        $oldTaskPeople = $section === 'tasks' && $taskPeople !== null ? [
            'assignees' => DB::table('task_assignees')->where('task_id', $id)->pluck('user_id')->sort()->values()->all(),
            'followers' => DB::table('task_followers')->where('task_id', $id)->pluck('user_id')->sort()->values()->all(),
        ] : null;
        $oldProjectFollowers = $section === 'projects' && $projectFollowers !== null
            ? DB::table('project_followers')->where('project_id', $id)->pluck('user_id')->sort()->values()->all()
            : null;

        if ($section === 'tasks' && ($payload['status'] ?? null) === 'done' && $this->taskOpenDependencyCount($id) > 0) {
            return back()->withErrors(['status' => 'Questa task è bloccata: completa prima le dipendenze.']);
        }

        $payload['updated_at'] = now();

        DB::table($this->config($section)['table'])->where('id', $id)->update($payload);

        if ($section === 'tasks' && $taskPeople !== null) {
            $this->syncTaskPeopleLists($id, $taskPeople['assignees'], $taskPeople['followers']);
        }

        if ($section === 'tasks' && $taskDependencies !== null) {
            $this->syncTaskDependencyEdges($id, $taskDependencies['dependencies'], $taskDependencies['dependents']);
        }

        if ($section === 'tasks' && $oldTask) {
            $changedFields = $this->changedTaskFields($oldTask, $payload);
            $this->recordTaskFieldChanges($id, $request->user()->id, $oldTask, $payload);

            if ($taskPeople !== null && $oldTaskPeople !== null) {
                $this->recordTaskPeopleChanges($id, $request->user()->id, $oldTaskPeople, $taskPeople);
            }

            if ($changedFields || $taskPeople !== null || $taskDependencies !== null) {
                $details = $changedFields ? 'campi: '.implode(', ', array_map(fn ($field) => $this->taskFieldLabel($field), $changedFields)) : 'persone coinvolte';
                $this->notifyTaskPeople(
                    $id,
                    $request->user()->id,
                    'task_updated',
                    $request->user()->name.' ha modificato "'.$oldTask->title.'" ('.$details.').',
                );
            }
        }

        if ($section === 'projects' && $projectFollowers !== null) {
            $this->syncProjectFollowersList($id, $projectFollowers);
        }

        if ($section === 'projects' && $oldProject) {
            $changedFields = $this->changedProjectFields($oldProject, $payload);
            $projectPeopleChanged = $projectFollowers !== null
                && $oldProjectFollowers !== null
                && $oldProjectFollowers !== collect($projectFollowers)->sort()->values()->all();

            if ($changedFields || $projectPeopleChanged) {
                $this->notifyProjectPeople(
                    $id,
                    $request->user()->id,
                    'project_updated',
                    $request->user()->name.' ha modificato il progetto "'.($payload['name'] ?? $oldProject->name).'".',
                    $oldProjectFollowers,
                );
            }
        }

        return back()->with('status', 'Aggiornato.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $section = $request->route('section');
        $this->ensureRoleCanDestroyRecord($request, $section, $id);

        if ($section === 'users') {
            $this->ensureSuperadmin($request);
            User::query()->whereKey($id)->delete();

            return back()->with('status', 'Utente eliminato.');
        }

        $task = null;
        if ($section === 'tasks') {
            $task = DB::table('tasks')->where('id', $id)->first();
            if ($task) {
                $this->notifyTaskPeople(
                    $id,
                    $request->user()->id,
                    'task_deleted',
                    $request->user()->name.' ha eliminato la task "'.$task->title.'".',
                );
            }
        }

        DB::table($this->config($section)['table'])->where('id', $id)->delete();

        if ($section === 'tasks') {
            if ($request->boolean('stay')) {
                return back()->with('status', 'Eliminato.');
            }

            return redirect($task?->parent_task_id ? route('tasks.show', $task->parent_task_id) : route('tasks.index'))
                ->with('status', 'Eliminato.');
        }

        return back()->with('status', 'Eliminato.');
    }

    public function updateDocumentSettings(Request $request): RedirectResponse
    {
        $this->ensureSuperadmin($request);

        $payload = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'legal_form' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:32'],
            'tax_code' => ['nullable', 'string', 'max:32'],
            'tax_regime' => ['nullable', 'string', 'max:32'],
            'street' => ['nullable', 'string', 'max:255'],
            'street_number' => ['nullable', 'string', 'max:32'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:16'],
            'country' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'pec' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'sdi_code' => ['nullable', 'string', 'max:16'],
            'iban' => ['nullable', 'string', 'max:64'],
            'bic_swift' => ['nullable', 'string', 'max:32'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'default_payment_method' => ['nullable', 'string', 'max:255'],
            'default_payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'default_withholding_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_pension_fund_label' => ['nullable', 'string', 'max:255'],
            'default_pension_fund_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bollo_threshold' => ['nullable', 'numeric', 'min:0'],
            'bollo_amount' => ['nullable', 'numeric', 'min:0'],
            'bollo_charged_to_client' => ['boolean'],
            'footer_notes' => ['nullable', 'string'],
        ]);

        $payload = $this->nullifyEmptyStrings($payload);
        $payload['bollo_charged_to_client'] = $request->boolean('bollo_charged_to_client');

        $existing = DB::table('document_settings')->first();
        DB::table('document_settings')->updateOrInsert(
            ['id' => $existing->id ?? (string) str()->uuid()],
            [...$payload, 'updated_at' => now(), 'created_at' => $existing->created_at ?? now()],
        );

        return back()->with('status', 'Impostazioni aggiornate.');
    }

    public function updateEmailSettings(Request $request): RedirectResponse
    {
        $this->ensureSuperadmin($request);

        $payload = $request->validate([
            'smtp_enabled' => ['boolean'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:1000'],
            'smtp_secure' => ['boolean'],
            'smtp_from_email' => ['nullable', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
            'smtp_reply_to' => ['nullable', 'email', 'max:255'],
            'pec_username' => ['nullable', 'string', 'max:255'],
            'pec_password' => ['nullable', 'string', 'max:1000'],
        ]);

        $payload = $this->nullifyEmptyStrings($payload);
        $payload['smtp_enabled'] = $request->boolean('smtp_enabled');
        $payload['smtp_secure'] = $request->boolean('smtp_secure');

        $existing = DB::table('email_settings')->first();
        if (empty($payload['smtp_password']) && $existing) {
            unset($payload['smtp_password']);
        }
        if (empty($payload['pec_password']) && $existing) {
            unset($payload['pec_password']);
        }

        DB::table('email_settings')->updateOrInsert(
            ['id' => $existing->id ?? (string) str()->uuid()],
            [...$payload, 'updated_at' => now(), 'created_at' => $existing->created_at ?? now()],
        );

        return back()->with('status', 'Impostazioni email aggiornate.');
    }

    public function sendTestEmail(Request $request): RedirectResponse
    {
        $this->ensureSuperadmin($request);

        $payload = $request->validate([
            'recipient' => ['required', 'email', 'max:255'],
        ]);

        $settings = DB::table('email_settings')->first();
        if (! $settings || ! $settings->smtp_enabled) {
            return back()->withErrors(['recipient' => 'Attiva SMTP e salva la configurazione prima di inviare una mail di test.']);
        }

        if (! $settings->smtp_host || ! $settings->smtp_from_email || ! $settings->smtp_password) {
            return back()->withErrors(['recipient' => 'Completa host, mittente e password SMTP prima di inviare una mail di test.']);
        }

        $this->applyEmailSettingsConfig($settings);

        try {
            Mail::raw(
                "Questa e una mail di test inviata da Il Centro.\n\nSe la ricevi, la configurazione SMTP e corretta.",
                function ($mail) use ($payload, $settings) {
                    $mail->to($payload['recipient'])
                        ->subject('Il Centro - test SMTP');

                    if ($settings->smtp_reply_to) {
                        $mail->replyTo($settings->smtp_reply_to);
                    }
                },
            );
        } catch (\Throwable $exception) {
            Log::warning('Invio mail di test SMTP non riuscito.', [
                'recipient' => $payload['recipient'],
                'reason' => $exception->getMessage(),
            ]);

            return back()->withErrors(['recipient' => 'Invio non riuscito: '.$exception->getMessage()]);
        }

        return back()->with('status', 'Mail di test inviata a '.$payload['recipient'].'.');
    }

    private function emailSettingsForView(): ?object
    {
        $settings = DB::table('email_settings')->first();
        if (! $settings) {
            return null;
        }

        $settings->smtp_password_saved = filled($settings->smtp_password);
        $settings->pec_password_saved = filled($settings->pec_password);
        $settings->smtp_password = '';
        $settings->pec_password = '';

        return $settings;
    }

    private function applyEmailSettingsConfig(object $settings): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $settings->smtp_host);
        Config::set('mail.mailers.smtp.port', $settings->smtp_port ?: 587);
        Config::set('mail.mailers.smtp.username', $settings->smtp_username);
        Config::set('mail.mailers.smtp.password', $settings->smtp_password);
        Config::set('mail.mailers.smtp.encryption', $settings->smtp_secure ? 'tls' : null);
        Config::set('mail.from.address', $settings->smtp_from_email);
        Config::set('mail.from.name', $settings->smtp_from_name ?: 'Il Centro');
    }

    public function updateNumbering(Request $request, string $id): RedirectResponse
    {
        $this->ensureSuperadmin($request);

        $payload = $request->validate([
            'prefix' => ['nullable', 'string', 'max:32'],
            'format' => ['required', 'string', 'max:255'],
            'current_seq' => ['required', 'integer', 'min:0'],
            'yearly_reset' => ['boolean'],
        ]);

        $payload['prefix'] = $payload['prefix'] ?? '';
        $payload['yearly_reset'] = $request->boolean('yearly_reset');
        $payload['updated_at'] = now();

        DB::table('document_numbering')->where('id', $id)->update($payload);

        return back()->with('status', 'Numerazione aggiornata.');
    }

    public function runBackup(Request $request, CentroBackupService $backupService): RedirectResponse
    {
        $this->ensureSuperadmin($request);

        try {
            $backupService->create('manual');
        } catch (\Throwable $exception) {
            return back()->with('status', 'Backup non completato: '.$exception->getMessage());
        }

        return back()->with('status', 'Backup manuale creato correttamente.');
    }

    public function restoreBackup(Request $request, CentroBackupService $backupService, string $id): RedirectResponse
    {
        $this->ensureSuperadmin($request);

        try {
            $backupService->restore($id);
        } catch (\Throwable $exception) {
            return back()->with('status', 'Ripristino non completato: '.$exception->getMessage());
        }

        return back()->with('status', 'Backup ripristinato correttamente.');
    }

    public function destroyBackup(Request $request, CentroBackupService $backupService, string $id): RedirectResponse
    {
        $this->ensureSuperadmin($request);

        try {
            $backupService->delete($id);
        } catch (\Throwable $exception) {
            return back()->with('status', 'Backup non eliminato: '.$exception->getMessage());
        }

        return back()->with('status', 'Backup eliminato.');
    }

    private function nullifyEmptyStrings(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if ($value === '') {
                $payload[$key] = null;
            }
        }

        return $payload;
    }

    private function backupRuns()
    {
        return DB::table('backup_runs')
            ->latest('started_at')
            ->limit(12)
            ->get()
            ->map(function ($run) {
                $run->restorable = $run->status === 'completed'
                    && filled($run->storage_path)
                    && Str::endsWith((string) $run->storage_path, '.sql')
                    && Storage::disk('local')->exists($run->storage_path);

                return $run;
            });
    }

    private function config(string $section): array
    {
        return match ($section) {
            'clients' => [
                'section' => 'clients',
                'title' => 'Clienti',
                'description' => 'Anagrafica clienti, contatti, servizi collegati e dati di fatturazione.',
                'table' => 'clients',
                'columns' => ['name', 'legal_name', 'email', 'phone', 'city', 'vat_number'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true],
                    ['name' => 'legal_name', 'label' => 'Ragione sociale', 'type' => 'text'],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                    ['name' => 'phone', 'label' => 'Telefono', 'type' => 'text'],
                    ['name' => 'vat_number', 'label' => 'Partita IVA', 'type' => 'text'],
                    ['name' => 'tax_code', 'label' => 'Codice fiscale', 'type' => 'text'],
                    ['name' => 'legal_form', 'label' => 'Natura giuridica', 'type' => 'select', 'options' => ['srl', 'srls', 'spa', 'sas', 'snc', 'ditta_individuale', 'libero_professionista', 'associazione', 'ente_pubblico', 'altro']],
                    ['name' => 'business_sector', 'label' => 'Settore', 'type' => 'select', 'options' => ['ecommerce', 'retail', 'servizi', 'immobiliare', 'turismo', 'ristorazione', 'salute_benessere', 'formazione', 'industria', 'no_profit', 'altro']],
                    ['name' => 'source', 'label' => 'Sorgente', 'type' => 'select', 'options' => ['passaparola', 'sito_web', 'social', 'campagna_adv', 'evento', 'partner', 'chiamata', 'email', 'altro']],
                    ['name' => 'country', 'label' => 'Paese', 'type' => 'select', 'options' => ['IT', 'SM', 'VA', 'FR', 'DE', 'ES', 'CH', 'AT', 'GB', 'US', 'altro']],
                    ['name' => 'street', 'label' => 'Via', 'type' => 'text'],
                    ['name' => 'street_number', 'label' => 'Numero', 'type' => 'text'],
                    ['name' => 'postal_code', 'label' => 'CAP', 'type' => 'text'],
                    ['name' => 'city', 'label' => 'Citta', 'type' => 'text'],
                    ['name' => 'province', 'label' => 'Provincia', 'type' => 'text'],
                    ['name' => 'pec', 'label' => 'PEC', 'type' => 'email'],
                    ['name' => 'sdi_code', 'label' => 'Codice SDI', 'type' => 'text'],
                    ['name' => 'website', 'label' => 'Sito web', 'type' => 'text'],
                    ['name' => 'iban', 'label' => 'IBAN', 'type' => 'text'],
                    ['name' => 'bic_swift', 'label' => 'BIC/SWIFT', 'type' => 'text'],
                    ['name' => 'vat_treatment', 'label' => 'Trattamento IVA', 'type' => 'select', 'options' => ['ordinario', 'split_payment', 'reverse_charge', 'esente', 'non_imponibile', 'fuori_campo', 'forfettario']],
                    ['name' => 'payment_terms_days', 'label' => 'Termini pagamento', 'type' => 'select', 'options' => [0, 15, 30, 45, 60, 90, 120]],
                    ['name' => 'is_pa', 'label' => 'Pubblica amministrazione', 'type' => 'checkbox'],
                    ['name' => 'notes', 'label' => 'Note', 'type' => 'textarea'],
                ],
            ],
            'projects' => [
                'section' => 'projects',
                'title' => 'Progetti',
                'description' => 'Progetti collegati ai clienti con stato, colore e attivita.',
                'table' => 'projects',
                'columns' => ['name', 'client_name', 'status', 'color'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true],
                    ['name' => 'client_id', 'label' => 'Cliente', 'type' => 'client'],
                    ['name' => 'status', 'label' => 'Stato', 'type' => 'select', 'options' => ['active', 'completed', 'on_hold', 'archived']],
                    ['name' => 'color', 'label' => 'Colore', 'type' => 'color'],
                    ['name' => 'description', 'label' => 'Descrizione', 'type' => 'textarea'],
                ],
            ],
            'tasks' => [
                'section' => 'tasks',
                'title' => 'Task',
                'description' => 'Attivita, assegnazioni, ricorrenze, priorita e calendario.',
                'table' => 'tasks',
                'columns' => ['title', 'project_name', 'client_name', 'status', 'priority', 'due_date'],
                'fields' => [
                    ['name' => 'title', 'label' => 'Titolo', 'type' => 'text', 'required' => true],
                    ['name' => 'task_type', 'label' => 'Tipo', 'type' => 'select', 'options' => ['project', 'task', 'ongoing', 'meeting']],
                    ['name' => 'project_id', 'label' => 'Progetto', 'type' => 'project'],
                    ['name' => 'client_id', 'label' => 'Cliente', 'type' => 'client'],
                    ['name' => 'service_id', 'label' => 'Servizio', 'type' => 'service'],
                    ['name' => 'status', 'label' => 'Stato', 'type' => 'select', 'options' => ['todo', 'in_progress', 'in_review', 'done']],
                    ['name' => 'priority', 'label' => 'Priorità', 'type' => 'select', 'options' => ['low', 'medium', 'high', 'urgent']],
                    ['name' => 'start_date', 'label' => 'Inizio', 'type' => 'date'],
                    ['name' => 'due_date', 'label' => 'Scadenza', 'type' => 'date'],
                    ['name' => 'due_time', 'label' => 'Ora', 'type' => 'time'],
                    ['name' => 'location', 'label' => 'Luogo/link', 'type' => 'text'],
                    ['name' => 'recurring_enabled', 'label' => 'Ricorrente', 'type' => 'checkbox'],
                    ['name' => 'recurring_interval_value', 'label' => 'Ogni', 'type' => 'number'],
                    ['name' => 'recurring_interval_unit', 'label' => 'Unita ricorrenza', 'type' => 'select', 'options' => ['week', 'month']],
                    ['name' => 'recurring_mode', 'label' => 'Modalita ricorrenza', 'type' => 'select', 'options' => ['fixed', 'relative']],
                    ['name' => 'recurring_weekday', 'label' => 'Giorno settimana', 'type' => 'number'],
                    ['name' => 'recurring_month_day', 'label' => 'Giorno mese', 'type' => 'number'],
                    ['name' => 'description', 'label' => 'Descrizione', 'type' => 'textarea'],
                ],
            ],
            'billing' => [
                'section' => 'billing',
                'title' => 'Billing',
                'description' => 'Preventivi, proforma, fatture, pagamenti, abbonamenti e numerazioni.',
                'table' => 'documents',
                'columns' => ['number', 'client_name', 'doc_type', 'status', 'issue_date', 'total_amount'],
                'fields' => [
                    ['name' => 'client_id', 'label' => 'Cliente', 'type' => 'client', 'required' => true],
                    ['name' => 'doc_type', 'label' => 'Tipo', 'type' => 'select', 'options' => ['preventivo', 'proforma', 'fattura', 'nota_credito']],
                    ['name' => 'status', 'label' => 'Stato', 'type' => 'select', 'options' => ['draft', 'sent', 'accepted', 'rejected', 'paid', 'partially_paid', 'overdue', 'cancelled']],
                    ['name' => 'issue_date', 'label' => 'Data emissione', 'type' => 'date', 'required' => true],
                    ['name' => 'due_date', 'label' => 'Scadenza', 'type' => 'date'],
                    ['name' => 'total_amount', 'label' => 'Totale', 'type' => 'number'],
                    ['name' => 'notes', 'label' => 'Note', 'type' => 'textarea'],
                ],
            ],
            'users' => [
                'section' => 'users',
                'title' => 'Utenti',
                'description' => 'Profili e ruoli applicativi equivalenti a superadmin, admin, editor e guest.',
                'table' => 'users',
                'columns' => ['name', 'email', 'role', 'created_at'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                    ['name' => 'role', 'label' => 'Ruolo', 'type' => 'select', 'options' => ['superadmin', 'admin', 'editor', 'guest']],
                    ['name' => 'password', 'label' => 'Password', 'type' => 'password'],
                ],
            ],
            'settings' => [
                'section' => 'settings',
                'title' => 'Impostazioni',
                'description' => 'Gestione servizi, usati anche dalle viste aggiornamenti SOCIAL, NEWSLETTER, SEO e ADV.',
                'table' => 'services',
                'columns' => ['name', 'active', 'color', 'created_at'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Servizio', 'type' => 'text', 'required' => true],
                    ['name' => 'color', 'label' => 'Colore', 'type' => 'color'],
                    ['name' => 'description', 'label' => 'Descrizione', 'type' => 'textarea'],
                    ['name' => 'active', 'label' => 'Attivo', 'type' => 'checkbox'],
                ],
            ],
            'calendar' => [
                'section' => 'calendar',
                'title' => 'Calendario',
                'description' => 'Vista rapida delle attivita con scadenza.',
                'table' => 'tasks',
                'columns' => ['title', 'due_date', 'due_time', 'status', 'priority'],
                'fields' => [],
            ],
            'absences' => [
                'section' => 'absences',
                'title' => 'Assenze',
                'description' => 'Richieste ferie, permessi, malattie, ritardi e altre assenze del team.',
                'table' => 'absence_requests',
                'columns' => ['user_name', 'type', 'start_date', 'end_date', 'status'],
                'fields' => [],
            ],
            'updates-social' => $this->updatesConfig('SOCIAL', 'Social'),
            'updates-newsletter' => $this->updatesConfig('NEWSLETTER', 'Newsletter'),
            'updates-seo' => $this->updatesConfig('SEO', 'SEO'),
            'updates-adv' => $this->updatesConfig('ADV', 'ADV'),
            default => abort(404),
        };
    }

    private function updatesConfig(string $service, string $title): array
    {
        return [
            'section' => 'updates-'.strtolower($service),
            'title' => $title,
            'description' => 'Clienti collegati al servizio '.$service.' e attivita aperte.',
            'table' => 'client_service_updates',
            'columns' => ['client_name', 'service_name', 'responsible_name', 'cadence', 'contact', 'report_url', 'updated_at'],
            'fields' => [
                ['name' => 'client_id', 'label' => 'Cliente', 'type' => 'client', 'required' => true],
                ['name' => 'responsible_user_id', 'label' => 'Responsabile', 'type' => 'user'],
                ['name' => 'cadence', 'label' => 'Cadenza', 'type' => 'select', 'options' => ['on_request', 'weekly', 'biweekly', 'monthly']],
                ['name' => 'contact', 'label' => 'Contatto', 'type' => 'text'],
                ['name' => 'report_url', 'label' => 'Report URL', 'type' => 'text'],
                ['name' => 'notes', 'label' => 'Note', 'type' => 'textarea'],
            ],
            'serviceName' => $service,
        ];
    }

    private function serviceUpdateRows(string $serviceName)
    {
        $service = $this->serviceByName($serviceName);
        if (! $service) {
            return collect();
        }

        $rows = DB::table('client_services')
            ->join('clients', 'clients.id', '=', 'client_services.client_id')
            ->leftJoin('client_service_updates', function ($join) use ($service) {
                $join->on('client_service_updates.client_id', '=', 'client_services.client_id')
                    ->where('client_service_updates.service_id', '=', $service->id);
            })
            ->leftJoin('users', 'users.id', '=', 'client_service_updates.responsible_user_id')
            ->where('client_services.service_id', $service->id)
            ->orderBy('clients.name')
            ->get([
                'client_service_updates.id',
                'client_service_updates.responsible_user_id',
                'client_service_updates.cadence',
                'client_service_updates.contact',
                'client_service_updates.report_url',
                'client_service_updates.notes',
                'client_service_updates.updated_at',
                'client_services.client_id',
                'clients.name as client_name',
                'users.name as responsible_name',
            ]);

        $clientIds = $rows->pluck('client_id')->filter()->values();
        $lastTasks = $clientIds->isEmpty()
            ? collect()
            : DB::table('tasks')
                ->where('service_id', $service->id)
                ->where('status', 'done')
                ->whereIn('client_id', $clientIds)
                ->orderByDesc('updated_at')
                ->get(['id', 'title', 'client_id', 'updated_at'])
                ->unique('client_id')
                ->keyBy('client_id');

        return $rows->map(function ($row) use ($lastTasks, $service) {
            $task = $lastTasks->get($row->client_id);
            $row->service_id = $service->id;
            $row->service_name = $service->name;
            $row->last_task_id = $task?->id;
            $row->last_task_title = $task?->title;
            $row->last_task_updated_at = $task?->updated_at;

            return $row;
        });
    }

    private function serviceByName(string $serviceName)
    {
        return DB::table('services')
            ->whereRaw('UPPER(TRIM(name)) = ?', [strtoupper(trim($serviceName))])
            ->first(['id', 'name']);
    }

    private function billingStats(): array
    {
        $year = (int) now()->format('Y');
        $today = now()->toDateString();
        $documents = DB::table('documents')
            ->leftJoin('clients', 'clients.id', '=', 'documents.client_id')
            ->select('documents.*', 'clients.name as client_name', 'clients.legal_name as client_legal_name')
            ->whereYear('issue_date', $year)
            ->get();

        $invoices = $documents->where('doc_type', 'fattura');
        $totalInvoiced = (float) $invoices->sum('total_amount');
        $totalReceived = (float) $invoices->sum('total_paid');
        $overdue = $invoices->filter(fn ($document) => $document->due_date && $document->due_date < $today && (float) $document->total_paid < (float) $document->total_amount);
        $overdueAmount = (float) $overdue->sum(fn ($document) => (float) $document->total_amount - (float) $document->total_paid);

        $months = collect(range(1, 12))->map(function (int $month) use ($invoices) {
            $docs = $invoices->filter(fn ($document) => (int) substr((string) $document->issue_date, 5, 2) === $month);

            return [
                'month' => ['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'][$month - 1],
                'invoiced' => round((float) $docs->sum('total_amount'), 2),
                'paid' => round((float) $docs->sum('total_paid'), 2),
                'open' => round((float) $docs->sum(fn ($document) => max(0, (float) $document->total_amount - (float) $document->total_paid)), 2),
            ];
        })->values();

        $topClients = $invoices
            ->groupBy('client_id')
            ->map(function ($docs) {
                $first = $docs->first();

                return [
                    'name' => $first->client_legal_name ?: ($first->client_name ?: '-'),
                    'total' => round((float) $docs->sum('total_amount'), 2),
                    'paid' => round((float) $docs->sum('total_paid'), 2),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->take(5);

        $statusBreakdown = $invoices
            ->groupBy('status')
            ->map(fn ($docs, $status) => [
                'status' => $status,
                'total' => round((float) $docs->sum('total_amount'), 2),
                'count' => $docs->count(),
            ])
            ->values();

        return [
            'year' => $year,
            'totalInvoiced' => round($totalInvoiced, 2),
            'totalReceived' => round($totalReceived, 2),
            'openAmount' => round(max(0, $totalInvoiced - $totalReceived), 2),
            'overdueAmount' => round($overdueAmount, 2),
            'overdueCount' => $overdue->count(),
            'collectedPct' => $totalInvoiced > 0 ? round(($totalReceived / $totalInvoiced) * 100) : 0,
            'monthly' => $months,
            'topClients' => $topClients,
            'statusBreakdown' => $statusBreakdown,
        ];
    }

    private function clientStats(): array
    {
        return [
            'total' => DB::table('clients')->count(),
            'withServices' => DB::table('client_services')->distinct('client_id')->count('client_id'),
            'withOpenTasks' => DB::table('tasks')->where('status', '!=', 'done')->distinct('client_id')->count('client_id'),
            'withDocuments' => DB::table('documents')->distinct('client_id')->count('client_id'),
        ];
    }

    private function validatedPayload(Request $request, string $section): array
    {
        $rules = match ($section) {
            'clients' => [
                'name' => ['required', 'string', 'max:255'],
                'legal_name' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:255'],
                'vat_number' => ['nullable', 'string', 'max:255'],
                'tax_code' => ['nullable', 'string', 'max:255'],
                'legal_form' => ['nullable', 'string', 'max:255'],
                'business_sector' => ['nullable', 'string', 'max:255'],
                'source' => ['nullable', 'string', 'max:255'],
                'country' => ['nullable', 'string', 'max:255'],
                'street' => ['nullable', 'string', 'max:255'],
                'street_number' => ['nullable', 'string', 'max:255'],
                'postal_code' => ['nullable', 'string', 'max:255'],
                'city' => ['nullable', 'string', 'max:255'],
                'province' => ['nullable', 'string', 'max:255'],
                'pec' => ['nullable', 'email', 'max:255'],
                'sdi_code' => ['nullable', 'string', 'max:255'],
                'website' => ['nullable', 'string', 'max:255'],
                'iban' => ['nullable', 'string', 'max:255'],
                'bic_swift' => ['nullable', 'string', 'max:255'],
                'vat_treatment' => ['nullable', 'string', 'max:255'],
                'payment_terms_days' => ['nullable', 'integer', 'min:0'],
                'is_pa' => ['boolean'],
                'notes' => ['nullable', 'string'],
            ],
            'projects' => [
                'name' => ['required', 'string', 'max:255'],
                'client_id' => ['nullable', 'uuid', 'exists:clients,id'],
                'status' => ['required', Rule::in(['active', 'completed', 'on_hold', 'archived'])],
                'color' => ['required', 'string', 'max:20'],
                'description' => ['nullable', 'string'],
                'user_ids' => ['nullable', 'array'],
                'user_ids.*' => ['uuid', 'exists:users,id'],
                'template_id' => ['nullable', 'uuid', 'exists:project_templates,id'],
                'template_start_date' => ['nullable', 'date'],
            ],
            'tasks' => [
                'title' => ['required', 'string', 'max:255'],
                'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
                'project_section_id' => ['nullable', 'uuid', 'exists:project_sections,id'],
                'client_id' => ['nullable', 'uuid', 'exists:clients,id'],
                'service_id' => ['nullable', 'uuid', 'exists:services,id'],
                'task_type' => ['required', Rule::in(['task', 'project', 'ongoing', 'meeting'])],
                'status' => ['required', Rule::in(['todo', 'in_progress', 'in_review', 'done'])],
                'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
                'start_date' => ['nullable', 'date'],
                'due_date' => ['nullable', 'date'],
                'due_time' => ['nullable', 'date_format:H:i'],
                'location' => ['nullable', 'string', 'max:255'],
                'recurring_enabled' => ['boolean'],
                'recurring_interval_value' => ['nullable', 'integer', 'min:1', 'max:365'],
                'recurring_interval_unit' => ['nullable', Rule::in(['week', 'month'])],
                'recurring_mode' => ['nullable', Rule::in(['fixed', 'relative'])],
                'recurring_weekday' => ['nullable', 'integer', 'min:1', 'max:7'],
                'recurring_month_day' => ['nullable', 'integer', 'min:1', 'max:31'],
                'assignee_ids' => ['nullable', 'array'],
                'assignee_ids.*' => ['uuid', 'exists:users,id'],
                'follower_ids' => ['nullable', 'array'],
                'follower_ids.*' => ['uuid', 'exists:users,id'],
                'dependency_ids' => ['nullable', 'array'],
                'dependency_ids.*' => ['uuid', 'exists:tasks,id'],
                'dependent_ids' => ['nullable', 'array'],
                'dependent_ids.*' => ['uuid', 'exists:tasks,id'],
                'description' => ['nullable', 'string'],
            ],
            'settings' => [
                'name' => ['required', 'string', 'max:255'],
                'color' => ['required', 'string', 'max:20'],
                'description' => ['nullable', 'string'],
                'active' => ['boolean'],
            ],
            'billing' => [
                'client_id' => ['required', 'uuid', 'exists:clients,id'],
                'doc_type' => ['required', Rule::in(['preventivo', 'proforma', 'fattura', 'nota_credito'])],
                'status' => ['required', Rule::in(['draft', 'sent', 'accepted', 'rejected', 'paid', 'partially_paid', 'overdue', 'cancelled'])],
                'issue_date' => ['required', 'date'],
                'due_date' => ['nullable', 'date'],
                'total_amount' => ['nullable', 'numeric', 'min:0'],
                'notes' => ['nullable', 'string'],
            ],
            default => abort(404),
        };

        if ($section === 'projects' && ! Schema::hasTable('project_templates')) {
            unset($rules['template_id'], $rules['template_start_date']);
        }

        $payload = $request->validate($rules);

        foreach ($payload as $key => $value) {
            if ($value === '') {
                $payload[$key] = null;
            }
        }

        if ($section === 'settings') {
            $payload['active'] = $request->boolean('active');
        }

        if ($section === 'clients') {
            $payload['is_pa'] = $request->boolean('is_pa');
        }

        if ($section === 'tasks') {
            $payload['recurring_enabled'] = $request->boolean('recurring_enabled');
            if (! $payload['recurring_enabled']) {
                $payload['recurring_interval_value'] = null;
                $payload['recurring_interval_unit'] = null;
                $payload['recurring_mode'] = null;
                $payload['recurring_weekday'] = null;
                $payload['recurring_month_day'] = null;
            } else {
                $payload['recurring_interval_value'] = $payload['recurring_interval_value'] ?: 1;
                $payload['recurring_interval_unit'] = $payload['recurring_interval_unit'] ?: 'week';
                $payload['recurring_mode'] = $payload['recurring_mode'] ?: 'fixed';
            }
        }

        return $payload;
    }

    private function validatedProjectTemplatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:20'],
            'active' => ['boolean'],
            'sections' => ['nullable', 'array'],
            'sections.*.name' => ['required', 'string', 'max:255'],
            'sections.*.tasks' => ['nullable', 'array'],
            'sections.*.tasks.*.template_key' => ['nullable', 'string', 'max:80'],
            'sections.*.tasks.*.title' => ['required', 'string', 'max:255'],
            'sections.*.tasks.*.description' => ['nullable', 'string'],
            'sections.*.tasks.*.service_id' => ['nullable', 'uuid', 'exists:services,id'],
            'sections.*.tasks.*.assignee_ids' => ['nullable', 'array'],
            'sections.*.tasks.*.assignee_ids.*' => ['uuid', 'exists:users,id'],
            'sections.*.tasks.*.day_offset' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'sections.*.tasks.*.date_offset_direction' => ['nullable', Rule::in(['before', 'after'])],
            'sections.*.tasks.*.date_reference_type' => ['nullable', Rule::in(['project_start', 'task'])],
            'sections.*.tasks.*.date_reference_task_key' => ['nullable', 'string', 'max:80'],
            'sections.*.tasks.*.dependency_mode' => ['nullable', Rule::in(['none', 'blocked_by', 'blocks'])],
            'sections.*.tasks.*.dependency_task_keys' => ['nullable', 'array'],
            'sections.*.tasks.*.dependency_task_keys.*' => ['string', 'max:80'],
            'sections.*.tasks.*.duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'sections.*.tasks.*.due_time' => ['nullable', 'date_format:H:i'],
            'sections.*.tasks.*.location' => ['nullable', 'string', 'max:255'],
            'sections.*.tasks.*.priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'sections.*.tasks.*.status' => ['nullable', Rule::in(['todo', 'in_progress', 'in_review', 'done'])],
            'sections.*.tasks.*.task_type' => ['nullable', Rule::in(['task', 'project', 'meeting'])],
        ]);
    }

    private function extractTaskPeoplePayload(array &$payload): ?array
    {
        $hasAssignees = array_key_exists('assignee_ids', $payload);
        $hasFollowers = array_key_exists('follower_ids', $payload);

        if (! $hasAssignees && ! $hasFollowers) {
            return null;
        }

        $people = [
            'assignees' => array_values(array_unique($payload['assignee_ids'] ?? [])),
            'followers' => array_values(array_unique($payload['follower_ids'] ?? [])),
        ];

        unset($payload['assignee_ids'], $payload['follower_ids']);

        return $people;
    }

    private function extractTaskDependencyPayload(array &$payload): ?array
    {
        $hasDependencies = array_key_exists('dependency_ids', $payload);
        $hasDependents = array_key_exists('dependent_ids', $payload);

        if (! $hasDependencies && ! $hasDependents) {
            return null;
        }

        $dependencies = array_values(array_unique($payload['dependency_ids'] ?? []));
        $dependents = array_values(array_unique($payload['dependent_ids'] ?? []));

        unset($payload['dependency_ids'], $payload['dependent_ids']);

        return [
            'dependencies' => $dependencies,
            'dependents' => $dependents,
        ];
    }

    private function extractProjectFollowersPayload(array &$payload): ?array
    {
        if (! array_key_exists('user_ids', $payload)) {
            return null;
        }

        $followers = array_values(array_unique($payload['user_ids'] ?? []));

        unset($payload['user_ids']);

        return $followers;
    }

    private function extractProjectTemplatePayload(array &$payload): ?array
    {
        if (! array_key_exists('template_id', $payload) && ! array_key_exists('template_start_date', $payload)) {
            return null;
        }

        $template = [
            'template_id' => $payload['template_id'] ?? null,
            'start_date' => $payload['template_start_date'] ?? null,
        ];

        unset($payload['template_id'], $payload['template_start_date']);

        return $template;
    }

    private function syncTaskPeopleLists(string $taskId, array $assigneeIds, array $followerIds): void
    {
        foreach ([['task_assignees', $assigneeIds], ['task_followers', $followerIds]] as [$table, $userIds]) {
            DB::table($table)->where('task_id', $taskId)->delete();
            foreach ($userIds as $userId) {
                DB::table($table)->insert([
                    'id' => (string) str()->uuid(),
                    'task_id' => $taskId,
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function syncProjectFollowers(Request $request, string $id): RedirectResponse
    {
        $this->ensureGuestCanEditProject($request);

        $project = DB::table('projects')->where('id', $id)->first();
        abort_unless($project, 404);

        $payload = $request->validate([
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
        ]);

        $oldFollowers = DB::table('project_followers')->where('project_id', $id)->pluck('user_id')->sort()->values()->all();
        $newFollowers = collect($payload['user_ids'] ?? [])->unique()->sort()->values()->all();

        $this->syncProjectFollowersList($id, $newFollowers);

        if ($oldFollowers !== $newFollowers) {
            $this->notifyProjectPeople(
                $id,
                $request->user()->id,
                'project_updated',
                $request->user()->name.' ha aggiornato le persone coinvolte nel progetto "'.$project->name.'".',
                $oldFollowers,
            );
        }

        return back()->with('status', 'Membri progetto aggiornati.');
    }

    public function storeProjectSection(Request $request, string $id): RedirectResponse
    {
        $this->ensureGuestCanEditProject($request);
        abort_unless(DB::table('projects')->where('id', $id)->exists(), 404);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        DB::table('project_sections')->insert([
            'id' => (string) str()->uuid(),
            'project_id' => $id,
            'name' => $payload['name'],
            'position' => DB::table('project_sections')->where('project_id', $id)->count(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Sezione aggiunta.');
    }

    public function updateProjectSection(Request $request, string $projectId, string $sectionId): RedirectResponse
    {
        $this->ensureGuestCanEditProject($request);
        abort_unless(DB::table('project_sections')->where('project_id', $projectId)->where('id', $sectionId)->exists(), 404);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        DB::table('project_sections')
            ->where('project_id', $projectId)
            ->where('id', $sectionId)
            ->update([
                'name' => $payload['name'],
                'updated_at' => now(),
            ]);

        return back()->with('status', 'Sezione aggiornata.');
    }

    public function duplicateProjectSection(Request $request, string $projectId, string $sectionId): RedirectResponse
    {
        $this->ensureGuestCanEditProject($request);
        $section = DB::table('project_sections')->where('project_id', $projectId)->where('id', $sectionId)->first();
        abort_unless($section, 404);

        DB::transaction(function () use ($request, $projectId, $section) {
            $newSectionId = (string) str()->uuid();
            $now = now();

            DB::table('project_sections')->insert([
                'id' => $newSectionId,
                'project_id' => $projectId,
                'name' => $section->name.' (copia)',
                'position' => DB::table('project_sections')->where('project_id', $projectId)->count(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $tasks = DB::table('tasks')
                ->where('project_id', $projectId)
                ->where('project_section_id', $section->id)
                ->where(fn ($query) => $query->whereNull('parent_task_id')->orWhereRaw("TRIM(parent_task_id) = ''"))
                ->orderBy('position')
                ->orderBy('created_at')
                ->get();

            foreach ($tasks as $position => $task) {
                $newTaskId = (string) str()->uuid();

                DB::table('tasks')->insert([
                    'id' => $newTaskId,
                    'title' => $task->title.' (copia)',
                    'description' => $task->description,
                    'project_id' => $projectId,
                    'project_section_id' => $newSectionId,
                    'client_id' => $task->client_id,
                    'service_id' => $task->service_id,
                    'parent_task_id' => null,
                    'start_date' => $task->start_date,
                    'due_date' => $task->due_date,
                    'due_time' => $task->due_time,
                    'location' => $task->location,
                    'priority' => $task->priority,
                    'status' => 'todo',
                    'task_type' => $task->task_type,
                    'recurring_enabled' => $task->recurring_enabled,
                    'recurring_mode' => $task->recurring_mode,
                    'recurring_interval_value' => $task->recurring_interval_value,
                    'recurring_interval_unit' => $task->recurring_interval_unit,
                    'recurring_weekday' => $task->recurring_weekday,
                    'recurring_month_day' => $task->recurring_month_day,
                    'position' => $position,
                    'created_by' => $request->user()->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach (['task_assignees', 'task_followers'] as $table) {
                    foreach (DB::table($table)->where('task_id', $task->id)->pluck('user_id') as $userId) {
                        DB::table($table)->insert([
                            'id' => (string) str()->uuid(),
                            'task_id' => $newTaskId,
                            'user_id' => $userId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }

                foreach (DB::table('tasks')->where('parent_task_id', $task->id)->orderBy('position')->get() as $subtask) {
                    $newSubtaskId = (string) str()->uuid();

                    DB::table('tasks')->insert([
                        'id' => $newSubtaskId,
                        'title' => $subtask->title,
                        'description' => $subtask->description,
                        'project_id' => $projectId,
                        'project_section_id' => $newSectionId,
                        'client_id' => $subtask->client_id,
                        'service_id' => $subtask->service_id,
                        'parent_task_id' => $newTaskId,
                        'start_date' => $subtask->start_date,
                        'due_date' => $subtask->due_date,
                        'due_time' => $subtask->due_time,
                        'location' => $subtask->location,
                        'priority' => $subtask->priority,
                        'status' => 'todo',
                        'task_type' => $subtask->task_type,
                        'recurring_enabled' => false,
                        'position' => $subtask->position,
                        'created_by' => $request->user()->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    foreach (['task_assignees', 'task_followers'] as $table) {
                        foreach (DB::table($table)->where('task_id', $subtask->id)->pluck('user_id') as $userId) {
                            DB::table($table)->insert([
                                'id' => (string) str()->uuid(),
                                'task_id' => $newSubtaskId,
                                'user_id' => $userId,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }
                    }
                }
            }
        });

        return back()->with('status', 'Sezione duplicata.');
    }

    public function destroyProjectSection(Request $request, string $projectId, string $sectionId): RedirectResponse
    {
        $this->ensureGuestCanEditProject($request);
        abort_unless(DB::table('project_sections')->where('project_id', $projectId)->where('id', $sectionId)->exists(), 404);

        DB::transaction(function () use ($projectId, $sectionId) {
            DB::table('tasks')
                ->where('project_id', $projectId)
                ->where('project_section_id', $sectionId)
                ->update([
                    'project_section_id' => null,
                    'updated_at' => now(),
                ]);

            DB::table('project_sections')
                ->where('project_id', $projectId)
                ->where('id', $sectionId)
                ->delete();
        });

        return back()->with('status', 'Sezione eliminata.');
    }

    public function reorderProjectTasks(Request $request, string $id): RedirectResponse
    {
        $this->ensureGuestCanEditProject($request);
        abort_unless(DB::table('projects')->where('id', $id)->exists(), 404);

        $payload = $request->validate([
            'section_id' => ['nullable', 'uuid', 'exists:project_sections,id'],
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'uuid', 'exists:tasks,id'],
        ]);

        $sectionId = $payload['section_id'] ?? null;
        if ($sectionId) {
            abort_unless(DB::table('project_sections')->where('project_id', $id)->where('id', $sectionId)->exists(), 404);
        }

        foreach (array_values($payload['ids']) as $position => $taskId) {
            DB::table('tasks')
                ->where('id', $taskId)
                ->where('project_id', $id)
                ->where(fn ($query) => $query->whereNull('parent_task_id')->orWhereRaw("TRIM(parent_task_id) = ''"))
                ->update([
                    'project_section_id' => $sectionId,
                    'position' => $position,
                    'updated_at' => now(),
                ]);
        }

        return back()->with('status', 'Ordine task aggiornato.');
    }

    public function storeProjectMessage(Request $request, string $id): RedirectResponse
    {
        $this->ensureGuestCanEditProject($request);
        $project = DB::table('projects')->where('id', $id)->first();
        abort_unless($project, 404);

        $payload = $request->validate([
            'content' => ['required', 'string'],
        ]);

        DB::table('project_messages')->insert([
            'id' => (string) str()->uuid(),
            'project_id' => $id,
            'user_id' => $request->user()->id,
            'content' => $payload['content'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->notifyProjectPeople(
            $id,
            $request->user()->id,
            'project_message',
            $request->user()->name.' ha pubblicato un messaggio nel progetto "'.$project->name.'".',
        );

        return back()->with('status', 'Messaggio pubblicato.');
    }

    public function storeProjectFile(Request $request, string $id): RedirectResponse
    {
        $this->ensureGuestCanEditProject($request);
        $project = DB::table('projects')->where('id', $id)->first();
        abort_unless($project, 404);

        $payload = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'kind' => ['required', Rule::in(['resource', 'file'])],
        ]);

        $uploadedFile = $payload['file'];
        $path = $uploadedFile->store('project-files/'.$id, 'local');
        Storage::disk('local')->setVisibility($path, 'private');

        DB::table('project_files')->insert([
            'id' => (string) str()->uuid(),
            'project_id' => $id,
            'uploaded_by' => $request->user()->id,
            'kind' => $payload['kind'],
            'name' => pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME),
            'original_name' => $uploadedFile->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $uploadedFile->getClientMimeType(),
            'size' => $uploadedFile->getSize() ?: 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->notifyProjectPeople(
            $id,
            $request->user()->id,
            'project_file',
            $request->user()->name.' ha caricato un file nel progetto "'.$project->name.'".',
        );

        return back()->with('status', 'File caricato.');
    }

    public function downloadProjectFile(Request $request, string $projectId, string $fileId)
    {
        if ($this->isGuest($request)) {
            abort_unless($this->isProjectParticipant($projectId, $request->user()->id), 403);
        }

        $file = DB::table('project_files')
            ->where('project_id', $projectId)
            ->where('id', $fileId)
            ->first();

        abort_unless($file, 404);

        if (! Storage::disk('local')->exists($file->path) && Storage::disk('public')->exists($file->path)) {
            Storage::disk('local')->put($file->path, Storage::disk('public')->get($file->path));
            Storage::disk('local')->setVisibility($file->path, 'private');
            Storage::disk('public')->delete($file->path);
        }

        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }

    public function destroyProjectFile(Request $request, string $projectId, string $fileId): RedirectResponse
    {
        $this->ensureGuestCanEditProject($request);

        $file = DB::table('project_files')
            ->where('project_id', $projectId)
            ->where('id', $fileId)
            ->first();

        abort_unless($file, 404);

        Storage::disk('local')->delete($file->path);
        Storage::disk('public')->delete($file->path);
        DB::table('project_files')->where('id', $fileId)->delete();

        return back()->with('status', 'File eliminato.');
    }

    private function syncProjectFollowersList(string $projectId, array $userIds): void
    {
        DB::table('project_followers')->where('project_id', $projectId)->delete();

        foreach ($userIds as $userId) {
            DB::table('project_followers')->insert([
                'id' => (string) str()->uuid(),
                'project_id' => $projectId,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function userPerformanceStats(string $userId): array
    {
        $today = now()->toDateString();
        $weekEnd = now()->copy()->endOfWeek()->toDateString();
        $monthStart = now()->copy()->subDays(30);
        $yearStart = now()->copy()->startOfYear()->toDateString();

        $assignedTaskQuery = DB::table('tasks')
            ->join('task_assignees', 'task_assignees.task_id', '=', 'tasks.id')
            ->where('task_assignees.user_id', $userId)
            ->where(fn ($query) => $query->whereNull('tasks.parent_task_id')->orWhereRaw("TRIM(tasks.parent_task_id) = ''"));

        $openTaskQuery = (clone $assignedTaskQuery)->where('tasks.status', '!=', 'done');
        $completedLast30 = (clone $assignedTaskQuery)
            ->where('tasks.status', 'done')
            ->where('tasks.updated_at', '>=', $monthStart)
            ->count();
        $touchedLast30 = (clone $assignedTaskQuery)
            ->where('tasks.updated_at', '>=', $monthStart)
            ->count();
        $completionRate = $touchedLast30 > 0 ? (int) round(($completedLast30 / $touchedLast30) * 100) : 0;

        $statusLabels = [
            'todo' => 'Da fare',
            'in_progress' => 'In corso',
            'in_review' => 'Review',
            'done' => 'Fatte',
        ];
        $priorityLabels = [
            'low' => 'Bassa',
            'medium' => 'Media',
            'high' => 'Alta',
            'urgent' => 'Urgente',
        ];

        $statusCounts = (clone $assignedTaskQuery)
            ->select('tasks.status as status', DB::raw('count(*) as total'))
            ->groupBy('tasks.status')
            ->pluck('total', 'status');
        $priorityCounts = (clone $openTaskQuery)
            ->select('tasks.priority as priority', DB::raw('count(*) as total'))
            ->groupBy('tasks.priority')
            ->pluck('total', 'priority');

        $documents = $this->companyDocumentRows($userId, false);
        $absenceRows = DB::table('absence_requests')
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->whereDate('end_date', '>=', $yearStart)
            ->get(['type', 'start_date', 'end_date', 'start_time', 'end_time']);

        $absenceDays = $absenceRows->sum(function ($absence) use ($yearStart) {
            $start = max($absence->start_date, $yearStart);
            $end = $absence->end_date ?: $absence->start_date;
            $startDate = \Carbon\Carbon::parse($start)->startOfDay();
            $endDate = \Carbon\Carbon::parse($end)->startOfDay();
            $days = max(1, $startDate->diffInDays($endDate) + 1);

            if ($absence->start_time && $absence->end_time && $startDate->equalTo($endDate)) {
                return round(max(0.25, \Carbon\Carbon::parse($absence->start_time)->diffInMinutes(\Carbon\Carbon::parse($absence->end_time)) / 480), 2);
            }

            return $days;
        });

        $upcomingTasks = (clone $openTaskQuery)
            ->leftJoin('clients', 'clients.id', '=', 'tasks.client_id')
            ->leftJoin('projects', 'projects.id', '=', 'tasks.project_id')
            ->whereNotNull('tasks.due_date')
            ->orderBy('tasks.due_date')
            ->limit(6)
            ->get([
                'tasks.id',
                'tasks.title',
                'tasks.status',
                'tasks.priority',
                'tasks.task_type',
                'tasks.due_date',
                'tasks.due_time',
                'clients.name as client_name',
                'projects.name as project_name',
            ]);

        $recentCompletedTasks = (clone $assignedTaskQuery)
            ->leftJoin('clients', 'clients.id', '=', 'tasks.client_id')
            ->where('tasks.status', 'done')
            ->orderByDesc('tasks.updated_at')
            ->limit(5)
            ->get([
                'tasks.id',
                'tasks.title',
                'tasks.updated_at',
                'clients.name as client_name',
            ]);

        return [
            'kpis' => [
                [
                    'label' => 'Task aperte',
                    'value' => (clone $openTaskQuery)->count(),
                    'detail' => 'Assegnate e non completate',
                    'tone' => 'blue',
                ],
                [
                    'label' => 'In ritardo',
                    'value' => (clone $openTaskQuery)->whereDate('tasks.due_date', '<', $today)->count(),
                    'detail' => 'Scadenza superata',
                    'tone' => 'red',
                ],
                [
                    'label' => 'Scadenze settimana',
                    'value' => (clone $openTaskQuery)->whereBetween('tasks.due_date', [$today, $weekEnd])->count(),
                    'detail' => 'Da oggi a fine settimana',
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Completate 30 gg',
                    'value' => $completedLast30,
                    'detail' => $completionRate.'% sul lavoro aggiornato',
                    'tone' => 'green',
                ],
                [
                    'label' => 'Progetti attivi',
                    'value' => DB::table('projects')
                        ->join('project_followers', 'project_followers.project_id', '=', 'projects.id')
                        ->where('project_followers.user_id', $userId)
                        ->where('projects.status', 'active')
                        ->count(),
                    'detail' => 'Dove risulta coinvolto',
                    'tone' => 'violet',
                ],
                [
                    'label' => 'Documenti da leggere',
                    'value' => $documents->filter(fn ($document) => blank($document->user_read_at))->count(),
                    'detail' => $documents->count().' documenti assegnati',
                    'tone' => 'slate',
                ],
            ],
            'completionRate' => $completionRate,
            'status' => collect(['todo', 'in_progress', 'in_review', 'done'])->map(fn ($status) => [
                'key' => $status,
                'label' => $statusLabels[$status],
                'value' => (int) ($statusCounts[$status] ?? 0),
            ])->values(),
            'priority' => collect(['urgent', 'high', 'medium', 'low'])->map(fn ($priority) => [
                'key' => $priority,
                'label' => $priorityLabels[$priority],
                'value' => (int) ($priorityCounts[$priority] ?? 0),
            ])->values(),
            'upcomingTasks' => $upcomingTasks,
            'recentCompletedTasks' => $recentCompletedTasks,
            'absence' => [
                'approvedDaysYear' => $absenceDays,
                'approvedRequestsYear' => $absenceRows->count(),
                'pendingRequests' => DB::table('absence_requests')
                    ->where('user_id', $userId)
                    ->where('status', 'pending')
                    ->count(),
            ],
        ];
    }

    private function storeUser(Request $request): RedirectResponse
    {
        $this->ensureSuperadmin($request);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['superadmin', 'admin', 'editor', 'guest'])],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::query()->create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => Hash::make($payload['password']),
        ]);

        $this->syncProfileAndRole($user, $payload['role']);

        return back()->with('status', 'Utente creato.');
    }

    private function updateUser(Request $request, string $id): RedirectResponse
    {
        $this->ensureSuperadmin($request);

        $user = User::query()->findOrFail($id);
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(['superadmin', 'admin', 'editor', 'guest'])],
            'password' => ['nullable', 'string', 'min:8'],
            'employee_code' => ['nullable', 'string', 'max:64'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'completion_effect' => ['nullable', Rule::in(['balloons', 'fireworks', 'snow', 'glitch'])],
            'smartworking_day' => ['nullable', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'none'])],
        ]);

        $user->name = $payload['name'];
        $user->email = $payload['email'];
        $previousSmartworkingDay = DB::table('profiles')->where('user_id', $user->id)->value('smartworking_day');
        $nextSmartworkingDay = ($payload['smartworking_day'] ?? null) === 'none' ? null : ($payload['smartworking_day'] ?? null);
        if (! empty($payload['password'])) {
            $user->password = Hash::make($payload['password']);
        }
        $user->save();

        $this->syncProfileAndRole($user, $payload['role']);
        DB::table('profiles')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'id' => (string) str()->uuid(),
                'full_name' => $user->name,
                'employee_code' => $payload['employee_code'] ?? null,
                'job_title' => $payload['job_title'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'bio' => $payload['bio'] ?? null,
                'completion_effect' => $payload['completion_effect'] ?? 'balloons',
                'smartworking_day' => $nextSmartworkingDay,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        if ($previousSmartworkingDay !== $nextSmartworkingDay) {
            $this->notifyUsers(
                [$user->id],
                $request->user()->id,
                'profile_smartworking_updated',
                $request->user()->name.' ha aggiornato il tuo giorno di smart working: '.$this->smartworkingDayLabel($nextSmartworkingDay).'.',
            );
        }

        return back()->with('status', 'Utente aggiornato.');
    }

    public function updateUserAvatar(Request $request, string $id): RedirectResponse
    {
        $this->ensureSuperadmin($request);

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = User::query()->findOrFail($id);
        $currentAvatar = DB::table('profiles')->where('user_id', $user->id)->value('avatar_url');
        $path = $request->file('avatar')->store('avatars', 'local');
        Storage::disk('local')->setVisibility($path, 'private');

        if ($currentAvatar && str_starts_with($currentAvatar, '/avatars/')) {
            Storage::disk('local')->delete('avatars/'.basename($currentAvatar));
            Storage::disk('public')->delete('avatars/'.basename($currentAvatar));
        }

        DB::table('profiles')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'id' => (string) str()->uuid(),
                'full_name' => $user->name,
                'avatar_url' => '/avatars/'.basename($path),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return back()->with('status', 'Foto profilo aggiornata.');
    }

    private function currentUserRole(Request $request): string
    {
        return (string) (DB::table('user_roles')->where('user_id', $request->user()->id)->value('role') ?: 'guest');
    }

    private function isGuest(Request $request): bool
    {
        return $this->currentUserRole($request) === 'guest';
    }

    private function isEditor(Request $request): bool
    {
        return $this->currentUserRole($request) === 'editor';
    }

    private function ensureSuperadmin(Request $request): void
    {
        abort_unless($this->currentUserRole($request) === 'superadmin', 403);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(in_array($this->currentUserRole($request), ['superadmin', 'admin'], true), 403);
    }

    private function ensureRoleCanAccessIndex(Request $request, string $section): void
    {
        if ($section === 'settings') {
            $this->ensureSuperadmin($request);

            return;
        }

        if ($this->isGuest($request)) {
            abort_unless(in_array($section, ['projects', 'tasks', 'calendar'], true), 403);

            return;
        }

        if ($this->isEditor($request)) {
            abort_unless(
                in_array($section, ['clients', 'projects', 'tasks', 'calendar'], true)
                    || str_starts_with($section, 'updates-'),
                403,
            );
        }
    }

    private function ensureGuestCanViewRecord(Request $request, string $section, string $id): void
    {
        if (! $this->isGuest($request)) {
            return;
        }

        if ($section === 'projects') {
            abort_unless($this->isProjectParticipant($id, $request->user()->id), 403);

            return;
        }

        if ($section === 'tasks') {
            abort_unless($this->isTaskParticipant($id, $request->user()->id), 403);

            return;
        }

        abort(403);
    }

    private function ensureRoleCanViewRecord(Request $request, string $section, string $id): void
    {
        if (! $this->isEditor($request)) {
            return;
        }

        abort_unless(
            in_array($section, ['clients', 'projects', 'tasks'], true)
                || str_starts_with($section, 'updates-'),
            403,
        );
    }

    private function ensureRoleCanStore(Request $request, string $section): void
    {
        if ($section === 'settings') {
            $this->ensureSuperadmin($request);
        }

        if ($this->isGuest($request)) {
            abort(403);
        }

        if ($this->isEditor($request)) {
            abort_unless($section === 'tasks' || str_starts_with($section, 'updates-'), 403);
        }
    }

    private function ensureRoleCanUpdateRecord(Request $request, string $section, string $id): void
    {
        if ($section === 'settings') {
            $this->ensureSuperadmin($request);
        }

        if ($this->isGuest($request)) {
            abort_unless($section === 'tasks' && $this->isTaskParticipant($id, $request->user()->id), 403);

            return;
        }

        if ($this->isEditor($request)) {
            abort_unless($section === 'projects' || $section === 'tasks' || str_starts_with($section, 'updates-'), 403);
        }
    }

    private function ensureRoleCanDestroyRecord(Request $request, string $section, string $id): void
    {
        if ($section === 'settings') {
            $this->ensureSuperadmin($request);
        }

        if ($this->isGuest($request)) {
            abort(403);
        }

        if (! $this->isEditor($request)) {
            return;
        }

        if ($section === 'tasks') {
            abort_unless($this->canEditorDeleteTask($request, $id), 403);

            return;
        }

        if (str_starts_with($section, 'updates-')) {
            return;
        }

        abort(403);
    }

    private function ensureGuestCanEditTask(Request $request, string $taskId): void
    {
        if (! $this->isGuest($request)) {
            return;
        }

        abort_unless($this->isTaskParticipant($taskId, $request->user()->id), 403);
    }

    private function ensureGuestCanEditProject(Request $request): void
    {
        abort_if($this->isGuest($request), 403);
    }

    private function ensureCanManageClients(Request $request): void
    {
        abort_if($this->isGuest($request) || $this->isEditor($request), 403);
    }

    private function ensureCanManageBilling(Request $request): void
    {
        abort_if($this->isGuest($request) || $this->isEditor($request), 403);
    }

    private function canEditorDeleteTask(Request $request, string $taskId): bool
    {
        if (! $this->isEditor($request)) {
            return true;
        }

        return DB::table('tasks')
            ->where('id', $taskId)
            ->where('created_by', $request->user()->id)
            ->exists();
    }

    private function visibleTaskIdsForUser(string $userId): \Illuminate\Support\Collection
    {
        $directTaskIds = DB::table('task_assignees')
            ->where('user_id', $userId)
            ->pluck('task_id')
            ->merge(DB::table('task_followers')->where('user_id', $userId)->pluck('task_id'))
            ->unique()
            ->values();

        if ($directTaskIds->isEmpty()) {
            return collect();
        }

        $parentTaskIds = DB::table('tasks')
            ->whereIn('id', $directTaskIds)
            ->pluck('parent_task_id')
            ->filter()
            ->values();

        return $directTaskIds->merge($parentTaskIds)->unique()->values();
    }

    private function isTaskParticipant(string $taskId, string $userId): bool
    {
        $task = DB::table('tasks')->where('id', $taskId)->first(['id', 'parent_task_id']);
        if (! $task) {
            return false;
        }

        $taskIds = collect([$task->id, $task->parent_task_id])->filter()->unique()->values();

        return DB::table('task_assignees')->whereIn('task_id', $taskIds)->where('user_id', $userId)->exists()
            || DB::table('task_followers')->whereIn('task_id', $taskIds)->where('user_id', $userId)->exists();
    }

    private function isProjectParticipant(string $projectId, string $userId): bool
    {
        return DB::table('project_followers')->where('project_id', $projectId)->where('user_id', $userId)->exists();
    }

    private function userOptionsForProject(string $projectId)
    {
        $userIds = DB::table('project_followers')->where('project_id', $projectId)->pluck('user_id');

        return $this->userOptions()->filter(fn ($user) => $userIds->contains($user->id))->values();
    }

    private function visibleProjectOptionsForUser(string $userId)
    {
        return DB::table('projects')
            ->join('project_followers', 'project_followers.project_id', '=', 'projects.id')
            ->where('project_followers.user_id', $userId)
            ->orderBy('projects.name')
            ->get(['projects.id', 'projects.name']);
    }

    private function visibleClientIdsForUser(string $userId): \Illuminate\Support\Collection
    {
        $taskClientIds = DB::table('tasks')
            ->whereIn('id', $this->visibleTaskIdsForUser($userId))
            ->pluck('client_id');

        $projectClientIds = DB::table('projects')
            ->join('project_followers', 'project_followers.project_id', '=', 'projects.id')
            ->where('project_followers.user_id', $userId)
            ->pluck('projects.client_id');

        return $taskClientIds->merge($projectClientIds)->filter()->unique()->values();
    }

    private function visibleUserOptionsForGuest(string $userId)
    {
        $visibleTaskIds = $this->visibleTaskIdsForUser($userId);
        $visibleProjectIds = DB::table('project_followers')
            ->where('user_id', $userId)
            ->pluck('project_id');

        $userIds = collect([$userId])
            ->merge(DB::table('task_assignees')->whereIn('task_id', $visibleTaskIds)->pluck('user_id'))
            ->merge(DB::table('task_followers')->whereIn('task_id', $visibleTaskIds)->pluck('user_id'))
            ->merge(DB::table('project_followers')->whereIn('project_id', $visibleProjectIds)->pluck('user_id'))
            ->filter()
            ->unique()
            ->values();

        return $this->userOptions()->filter(fn ($user) => $userIds->contains($user->id))->values();
    }

    private function canManageDocuments(Request $request): bool
    {
        return in_array($this->currentUserRole($request), ['superadmin', 'admin'], true);
    }

    private function validatedModuleFolderPayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'color' => ['nullable', 'string', 'max:24'],
        ]);
    }

    private function validatedModuleItemPayload(Request $request): array
    {
        return $request->validate([
            'admin_module_folder_id' => ['required', 'uuid', Rule::exists('admin_module_folders', 'id')],
            'parent_module_id' => ['nullable', 'uuid', Rule::exists('admin_modules', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
            'version' => ['nullable', 'string', 'max:40'],
            'status' => ['required', Rule::in(['draft', 'test', 'approved'])],
            'description' => ['nullable', 'string', 'max:10000'],
            'required_inputs' => ['nullable'],
            'required_inputs.*' => ['nullable', 'string', 'max:255'],
            'dependency_module_ids' => ['nullable', 'array'],
            'dependency_module_ids.*' => ['uuid', Rule::exists('admin_modules', 'id')],
            'rules' => ['nullable', 'string', 'max:20000'],
            'output' => ['nullable', 'string', 'max:10000'],
            'allowed_agents' => ['nullable'],
            'allowed_agents.*' => ['nullable', 'string', 'max:120'],
            'active' => ['nullable', 'boolean'],
        ]);
    }

    private function moduleItemDatabasePayload(array $payload): array
    {
        return [
            'admin_module_folder_id' => $payload['admin_module_folder_id'],
            'parent_module_id' => $payload['parent_module_id'] ?? null,
            'name' => $payload['name'],
            'category' => $payload['category'] ?? null,
            'version' => ($payload['version'] ?? null) ?: '1.0',
            'status' => $payload['status'] ?? 'draft',
            'description' => $payload['description'] ?? null,
            'required_inputs' => json_encode($this->normalizeModuleList($payload['required_inputs'] ?? [])),
            'dependency_module_ids' => json_encode($this->normalizeModuleList($payload['dependency_module_ids'] ?? [])),
            'rules' => $payload['rules'] ?? null,
            'output' => $payload['output'] ?? null,
            'allowed_agents' => json_encode($this->normalizeModuleList($payload['allowed_agents'] ?? [])),
            'active' => (bool) ($payload['active'] ?? true),
        ];
    }

    private function normalizeModuleList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\r\n|\r|\n/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function adminModuleFolderRows()
    {
        $moduleCounts = DB::table('admin_modules')
            ->select('admin_module_folder_id', DB::raw('count(*) as aggregate'))
            ->groupBy('admin_module_folder_id')
            ->pluck('aggregate', 'admin_module_folder_id');

        return DB::table('admin_module_folders')
            ->orderBy('name')
            ->get()
            ->map(function ($folder) use ($moduleCounts) {
                $folder->modules_count = (int) ($moduleCounts[$folder->id] ?? 0);

                return $folder;
            });
    }

    private function adminModuleRows()
    {
        $modules = DB::table('admin_modules')
            ->leftJoin('admin_module_folders', 'admin_module_folders.id', '=', 'admin_modules.admin_module_folder_id')
            ->orderBy('admin_module_folders.name')
            ->orderBy('admin_modules.name')
            ->get([
                'admin_modules.*',
                'admin_module_folders.name as folder_name',
                'admin_module_folders.color as folder_color',
            ]);

        $moduleLookup = $modules->mapWithKeys(fn ($module) => [
            $module->id => [
                'id' => $module->id,
                'name' => $module->name,
                'category' => $module->category,
                'folder_name' => $module->folder_name,
            ],
        ]);
        $childCounts = $modules
            ->filter(fn ($module) => filled($module->parent_module_id ?? null))
            ->countBy('parent_module_id');

        return $modules
            ->map(function ($module) use ($moduleLookup, $childCounts) {
                $module->required_inputs = $this->decodeJsonArray($module->required_inputs);
                $module->parent_module = $module->parent_module_id
                    ? ($moduleLookup[$module->parent_module_id] ?? null)
                    : null;
                $module->children_count = (int) ($childCounts[$module->id] ?? 0);
                $module->dependency_module_ids = $this->decodeJsonArray($module->dependency_module_ids ?? null);
                $module->dependency_modules = collect($module->dependency_module_ids)
                    ->map(fn ($moduleId) => $moduleLookup[$moduleId] ?? null)
                    ->filter()
                    ->values()
                    ->all();
                $module->allowed_agents = $this->decodeJsonArray($module->allowed_agents);
                $module->status = $module->status ?: 'draft';
                $module->version = $module->version ?: '1.0';

                return $module;
            });
    }

    private function ensureModuleParentIsValid(?string $parentModuleId, string $moduleId): void
    {
        if (! $parentModuleId) {
            return;
        }

        if ($parentModuleId === $moduleId) {
            throw ValidationException::withMessages([
                'parent_module_id' => 'Un modulo non puo\' essere figlio di se stesso.',
            ]);
        }

        $parents = DB::table('admin_modules')->pluck('parent_module_id', 'id');
        $current = $parentModuleId;

        while ($current) {
            if ($current === $moduleId) {
                throw ValidationException::withMessages([
                    'parent_module_id' => 'Questa scelta creerebbe un ciclo nella gerarchia dei moduli.',
                ]);
            }

            $current = $parents[$current] ?? null;
        }
    }

    private function adminModuleStatusOptions(): array
    {
        return [
            ['value' => 'draft', 'label' => 'Bozza'],
            ['value' => 'test', 'label' => 'Test'],
            ['value' => 'approved', 'label' => 'Approvato'],
        ];
    }

    private function adminModuleAgentOptions(): array
    {
        return [
            'Project Manager',
            'Account',
            'Copywriter',
            'Designer',
            'Developer',
            'SEO Specialist',
            'ADV Specialist',
            'Social Media Manager',
        ];
    }

    private function decodeJsonArray(?string $json): array
    {
        $decoded = $json ? json_decode($json, true) : [];

        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function canManagePasswords(Request $request): bool
    {
        return in_array($this->currentUserRole($request), ['superadmin', 'admin'], true);
    }

    private function ensureCanManagePasswordStructure(Request $request): void
    {
        abort_unless($this->canManagePasswords($request), 403);
    }

    private function passwordVaultRows(Request $request)
    {
        $query = DB::table('password_vaults')->orderBy('name');
        if ($this->currentUserRole($request) !== 'superadmin') {
            $query->whereIn('id', $this->visiblePasswordVaultIds($request));
        }

        $vaults = $query->get();
        $userShares = DB::table('password_vault_user')
            ->whereIn('password_vault_id', $vaults->pluck('id'))
            ->get(['password_vault_id', 'user_id'])
            ->groupBy('password_vault_id');
        $groupShares = DB::table('password_vault_group')
            ->whereIn('password_vault_id', $vaults->pluck('id'))
            ->get(['password_vault_id', 'password_group_id'])
            ->groupBy('password_vault_id');
        $itemCounts = DB::table('password_items')
            ->whereIn('password_vault_id', $vaults->pluck('id'))
            ->select('password_vault_id', DB::raw('COUNT(*) as total'))
            ->groupBy('password_vault_id')
            ->pluck('total', 'password_vault_id');

        return $vaults
            ->map(function ($vault) use ($request, $userShares, $groupShares, $itemCounts) {
                $vault->items_count = (int) ($itemCounts[$vault->id] ?? 0);
                $vault->user_ids = ($userShares[$vault->id] ?? collect())->pluck('user_id')->values();
                $vault->group_ids = ($groupShares[$vault->id] ?? collect())->pluck('password_group_id')->values();
                $vault->can_edit = $this->canEditPasswordVault($request, $vault);

                return $vault;
            });
    }

    private function passwordGroupRows(Request $request)
    {
        $query = DB::table('password_groups')->orderBy('name');
        if (! $this->canManagePasswords($request)) {
            $query->whereIn('id', DB::table('password_group_user')->where('user_id', $request->user()->id)->pluck('password_group_id'));
        }

        $groups = $query->get();
        $members = DB::table('password_group_user')
            ->whereIn('password_group_id', $groups->pluck('id'))
            ->get(['password_group_id', 'user_id'])
            ->groupBy('password_group_id');

        return $groups->map(function ($group) use ($members) {
            $group->user_ids = ($members[$group->id] ?? collect())->pluck('user_id')->values();
            $group->members_count = $group->user_ids->count();

            return $group;
        });
    }

    private function passwordItemRows(Request $request, bool $withCompromiseCheck = false)
    {
        $items = $this->passwordItemsQuery($request)
            ->leftJoin('password_vaults', 'password_vaults.id', '=', 'password_items.password_vault_id')
            ->leftJoin('clients', 'clients.id', '=', 'password_items.client_id')
            ->leftJoin('projects', 'projects.id', '=', 'password_items.project_id')
            ->latest('password_items.updated_at')
            ->get([
                'password_items.*',
                'password_vaults.name as vault_name',
                'password_vaults.color as vault_color',
                'clients.name as client_name',
                'projects.name as project_name',
            ]);

        $itemIds = $items->pluck('id');
        $userShares = DB::table('password_item_user')
            ->whereIn('password_item_id', $itemIds)
            ->get(['password_item_id', 'user_id', 'permission'])
            ->groupBy('password_item_id');
        $groupShares = DB::table('password_item_group')
            ->whereIn('password_item_id', $itemIds)
            ->get(['password_item_id', 'password_group_id', 'permission'])
            ->groupBy('password_item_id');
        $audit = DB::table('password_audit_logs')
            ->leftJoin('users', 'users.id', '=', 'password_audit_logs.user_id')
            ->whereIn('password_audit_logs.password_item_id', $itemIds)
            ->latest('password_audit_logs.created_at')
            ->get([
                'password_audit_logs.password_item_id',
                'password_audit_logs.action',
                'password_audit_logs.details',
                'password_audit_logs.created_at',
                'users.name as user_name',
            ])
            ->groupBy('password_item_id');

        return $items->map(function ($item) use ($request, $userShares, $groupShares, $audit, $withCompromiseCheck) {
            $item->has_password = filled($item->encrypted_password);
            $encryptedPassword = $item->encrypted_password;
            unset($item->encrypted_password);
            $item->tags = $item->tags ? json_decode($item->tags, true) : [];
            $item->custom_fields = $item->custom_fields ? json_decode($item->custom_fields, true) : [];
            $item->user_ids = ($userShares[$item->id] ?? collect())->pluck('user_id')->values();
            $item->group_ids = ($groupShares[$item->id] ?? collect())->pluck('password_group_id')->values();
            $item->share_permission = ($userShares[$item->id] ?? collect())->first()?->permission
                ?: (($groupShares[$item->id] ?? collect())->first()?->permission ?: 'view');
            $item->can_edit = $this->canEditPasswordItem($request, $item);
            $item->can_delete = $this->canManagePasswords($request);
            $item->risk_flags = $this->passwordRiskFlags($item, $encryptedPassword, $withCompromiseCheck);
            $item->audit = ($audit[$item->id] ?? collect())->take(8)->values();

            return $item;
        });
    }

    private function passwordItemsQuery(Request $request)
    {
        $query = DB::table('password_items');
        if ($this->currentUserRole($request) === 'superadmin') {
            return $query;
        }

        $groupIds = DB::table('password_group_user')->where('user_id', $request->user()->id)->pluck('password_group_id');

        return $query->where(function ($query) use ($request, $groupIds) {
            $query->where('password_items.created_by', $request->user()->id)
                ->orWhereIn('password_items.password_vault_id', $this->visiblePasswordVaultIds($request))
                ->orWhereIn('password_items.id', DB::table('password_item_user')->where('user_id', $request->user()->id)->pluck('password_item_id'))
                ->orWhereIn('password_items.id', DB::table('password_item_group')->whereIn('password_group_id', $groupIds)->pluck('password_item_id'));
        });
    }

    private function visiblePasswordVaultIds(Request $request): \Illuminate\Support\Collection
    {
        if ($this->currentUserRole($request) === 'superadmin') {
            return DB::table('password_vaults')->pluck('id');
        }

        $groupIds = DB::table('password_group_user')->where('user_id', $request->user()->id)->pluck('password_group_id');

        return DB::table('password_vaults')
            ->where(function ($query) use ($request, $groupIds) {
                $query->where('created_by', $request->user()->id)
                    ->orWhereIn('id', DB::table('password_vault_user')->where('user_id', $request->user()->id)->pluck('password_vault_id'))
                    ->orWhereIn('id', DB::table('password_vault_group')->whereIn('password_group_id', $groupIds)->pluck('password_vault_id'));

                if ($this->currentUserRole($request) === 'admin') {
                    $query->orWhere('visibility', 'shared');
                }
            })
            ->pluck('id')
            ->filter()
            ->unique()
            ->values();
    }

    private function canEditPasswordVault(Request $request, object $vault): bool
    {
        if ($this->currentUserRole($request) === 'superadmin') {
            return true;
        }

        if ($vault->created_by === $request->user()->id) {
            return true;
        }

        return $this->currentUserRole($request) === 'admin'
            && ($vault->visibility ?? 'personal') === 'shared';
    }

    private function passwordRiskFlags(object $item, ?string $encryptedPassword = null, bool $withCompromiseCheck = false): array
    {
        $flags = [];
        if (! $item->has_password) {
            $flags[] = 'Senza password salvata';
        }
        if ($withCompromiseCheck && filled($encryptedPassword)) {
            $count = $this->passwordCompromiseCount($item, $encryptedPassword);
            if ($count > 0) {
                $flags[] = "Presente in {$count} violazioni note";
            }
        }

        return $flags;
    }

    private function passwordCompromiseCount(object $item, string $encryptedPassword): int
    {
        $hasCacheColumns = Schema::hasColumn('password_items', 'compromised_count')
            && Schema::hasColumn('password_items', 'compromised_checked_at');

        if (
            $hasCacheColumns
            && $item->compromised_checked_at
            && \Carbon\Carbon::parse($item->compromised_checked_at)->greaterThan(now()->subDays(7))
        ) {
            return max(0, (int) ($item->compromised_count ?? 0));
        }

        try {
            $password = Crypt::decryptString($encryptedPassword);
            if ($password === '') {
                return 0;
            }

            $hash = strtoupper(sha1($password));
            $prefix = substr($hash, 0, 5);
            $suffix = substr($hash, 5);
            $response = Http::timeout(6)
                ->withHeaders(['Add-Padding' => 'true'])
                ->get("https://api.pwnedpasswords.com/range/{$prefix}");

            if (! $response->ok()) {
                return max(0, (int) ($item->compromised_count ?? 0));
            }

            $count = collect(preg_split('/\r\n|\r|\n/', trim($response->body())) ?: [])
                ->mapWithKeys(function ($line) {
                    [$hashSuffix, $count] = array_pad(explode(':', $line, 2), 2, 0);

                    return [strtoupper($hashSuffix) => (int) $count];
                })
                ->get($suffix, 0);

            if ($hasCacheColumns) {
                DB::table('password_items')->where('id', $item->id)->update([
                    'compromised_count' => $count,
                    'compromised_checked_at' => now(),
                ]);
            }

            return max(0, (int) $count);
        } catch (\Throwable $exception) {
            Log::warning('Password compromise check failed', [
                'password_item_id' => $item->id ?? null,
                'message' => $exception->getMessage(),
            ]);

            return max(0, (int) ($item->compromised_count ?? 0));
        }
    }

    private function canViewPasswordItem(Request $request, object $item): bool
    {
        if ($this->currentUserRole($request) === 'superadmin' || $item->created_by === $request->user()->id) {
            return true;
        }

        $groupIds = DB::table('password_group_user')->where('user_id', $request->user()->id)->pluck('password_group_id');

        return $this->visiblePasswordVaultIds($request)->contains($item->password_vault_id)
            || DB::table('password_item_user')->where('password_item_id', $item->id)->where('user_id', $request->user()->id)->exists()
            || DB::table('password_item_group')->where('password_item_id', $item->id)->whereIn('password_group_id', $groupIds)->exists();
    }

    private function canEditPasswordItem(Request $request, object $item): bool
    {
        if ($this->currentUserRole($request) === 'superadmin' || $item->created_by === $request->user()->id || $this->visiblePasswordVaultIds($request)->contains($item->password_vault_id)) {
            return true;
        }

        $groupIds = DB::table('password_group_user')->where('user_id', $request->user()->id)->pluck('password_group_id');

        return DB::table('password_item_user')
            ->where('password_item_id', $item->id)
            ->where('user_id', $request->user()->id)
            ->where('permission', 'edit')
            ->exists()
            || DB::table('password_item_group')
                ->where('password_item_id', $item->id)
                ->whereIn('password_group_id', $groupIds)
                ->where('permission', 'edit')
                ->exists();
    }

    private function validatedPasswordItemPayload(Request $request, bool $updating = false): array
    {
        $payload = $request->validate([
            'password_vault_id' => ['nullable', 'uuid', 'exists:password_vaults,id'],
            'title' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:1000'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => [$updating ? 'nullable' : 'required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'tags_text' => ['nullable', 'string', 'max:1000'],
            'expires_at' => ['nullable', 'date'],
            'favorite' => ['boolean'],
            'client_id' => ['nullable', 'uuid', 'exists:clients,id'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
            'share_permission' => ['nullable', Rule::in(['view', 'edit'])],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['uuid', 'exists:password_groups,id'],
            'custom_fields' => ['nullable', 'array'],
            'custom_fields.*.label' => ['nullable', 'string', 'max:120'],
            'custom_fields.*.value' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $this->canManagePasswords($request)) {
            abort_unless(blank($payload['password_vault_id'] ?? null) || $this->visiblePasswordVaultIds($request)->contains($payload['password_vault_id']), 403);
            $payload['user_ids'] = [];
            $payload['group_ids'] = [];
            $payload['share_permission'] = 'view';
        }

        if (blank($payload['password_vault_id'] ?? null)) {
            $payload['password_vault_id'] = $this->visiblePasswordVaultIds($request)->first();
        }

        return $payload;
    }

    private function passwordItemPayloadForDatabase(array $payload, array $extra = [], bool $updating = false): array
    {
        $data = [
            'password_vault_id' => $payload['password_vault_id'] ?? null,
            'title' => $payload['title'],
            'url' => $payload['url'] ?? null,
            'username' => $payload['username'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'tags' => json_encode(collect(explode(',', (string) ($payload['tags_text'] ?? '')))
                ->map(fn ($tag) => trim($tag))
                ->filter()
                ->unique()
                ->values()
                ->all()),
            'custom_fields' => json_encode(collect($payload['custom_fields'] ?? [])
                ->filter(fn ($field) => filled($field['label'] ?? null) || filled($field['value'] ?? null))
                ->values()
                ->all()),
            'expires_at' => $payload['expires_at'] ?? null,
            'favorite' => (bool) ($payload['favorite'] ?? false),
            'client_id' => $payload['client_id'] ?? null,
            'project_id' => $payload['project_id'] ?? null,
        ];

        if (! $updating || filled($payload['password'] ?? null)) {
            $data['encrypted_password'] = Crypt::encryptString($payload['password'] ?? '');
            if (Schema::hasColumn('password_items', 'compromised_count')) {
                $data['compromised_count'] = 0;
            }
            if (Schema::hasColumn('password_items', 'compromised_checked_at')) {
                $data['compromised_checked_at'] = null;
            }
        }

        return [...$data, ...$extra];
    }

    private function syncPasswordItemShares(string $itemId, array $userIds, array $groupIds, string $sharePermission = 'view'): void
    {
        $permission = $sharePermission === 'edit' ? 'edit' : 'view';
        DB::table('password_item_user')->where('password_item_id', $itemId)->delete();
        DB::table('password_item_group')->where('password_item_id', $itemId)->delete();

        $now = now();
        $userRows = collect($userIds)->unique()->values()->map(fn ($userId) => [
            'id' => (string) str()->uuid(),
            'password_item_id' => $itemId,
            'user_id' => $userId,
            'permission' => $permission,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        $groupRows = collect($groupIds)->unique()->values()->map(fn ($groupId) => [
            'id' => (string) str()->uuid(),
            'password_item_id' => $itemId,
            'password_group_id' => $groupId,
            'permission' => $permission,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($userRows) {
            DB::table('password_item_user')->insert($userRows);
        }
        if ($groupRows) {
            DB::table('password_item_group')->insert($groupRows);
        }
    }

    private function syncPasswordVaultShares(string $vaultId, array $userIds, array $groupIds): void
    {
        DB::table('password_vault_user')->where('password_vault_id', $vaultId)->delete();
        DB::table('password_vault_group')->where('password_vault_id', $vaultId)->delete();

        $now = now();
        $userRows = collect($userIds)->unique()->values()->map(fn ($userId) => [
            'id' => (string) str()->uuid(),
            'password_vault_id' => $vaultId,
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        $groupRows = collect($groupIds)->unique()->values()->map(fn ($groupId) => [
            'id' => (string) str()->uuid(),
            'password_vault_id' => $vaultId,
            'password_group_id' => $groupId,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($userRows) {
            DB::table('password_vault_user')->insert($userRows);
        }
        if ($groupRows) {
            DB::table('password_vault_group')->insert($groupRows);
        }
    }

    private function syncPasswordGroupUsers(string $groupId, array $userIds): void
    {
        DB::table('password_group_user')->where('password_group_id', $groupId)->delete();

        $rows = collect($userIds)->unique()->values()->map(fn ($userId) => [
            'id' => (string) str()->uuid(),
            'password_group_id' => $groupId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if ($rows) {
            DB::table('password_group_user')->insert($rows);
        }
    }

    private function logPasswordAction(?string $itemId, ?string $userId, string $action, string $details): void
    {
        DB::table('password_audit_logs')->insert([
            'id' => (string) str()->uuid(),
            'password_item_id' => $itemId,
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function canAccessAbsence(Request $request, object $absence): bool
    {
        return $absence->user_id === $request->user()?->id
            || in_array($this->currentUserRole($request), ['superadmin', 'admin'], true);
    }

    private function canAccessCompanyDocument(Request $request, object $document): bool
    {
        if ($this->canManageDocuments($request)) {
            return true;
        }

        return $this->companyDocumentRecipientIds($document->id)->contains($request->user()?->id);
    }

    private function canAccessCompanyMessage(Request $request, object $message): bool
    {
        if ($this->canManageDocuments($request)) {
            return true;
        }

        return $this->companyMessageRecipientIds($message->id)->contains($request->user()?->id);
    }

    private function companyDocumentRows(?string $userId = null, bool $adminView = false, ?int $year = null)
    {
        $query = DB::table('company_documents')
            ->leftJoin('users', 'users.id', '=', 'company_documents.created_by')
            ->select('company_documents.*', 'users.name as creator_name')
            ->latest('company_documents.created_at');

        if ($userId) {
            $documentIds = $this->visibleCompanyDocumentIdsForUser($userId);
            $query->whereIn('company_documents.id', $documentIds);
        }

        if ($year) {
            $query->where(function ($query) use ($year) {
                $query
                    ->where('company_documents.document_year', $year)
                    ->orWhere(function ($query) use ($year) {
                        $query
                            ->whereNull('company_documents.document_year')
                            ->whereYear('company_documents.created_at', $year);
                    });
            });
        }

        $documents = $query->limit($adminView ? 300 : 150)->get();

        return $documents->map(fn ($document) => $this->companyDocumentRow($document, $userId));
    }

    private function companyDocumentRow(object $document, ?string $userId = null): object
    {
        $recipientIds = $this->companyDocumentRecipientIds($document->id);
        $readRows = DB::table('company_document_reads')
            ->where('company_document_id', $document->id)
            ->get(['user_id', 'opened_at', 'read_at'])
            ->keyBy('user_id');

        $document->recipient_count = $recipientIds->count();
        $document->read_count = $readRows->filter(fn ($row) => filled($row->read_at))->count();
        $document->opened_count = $readRows->filter(fn ($row) => filled($row->opened_at))->count();
        $document->category = $document->category ?: 'documenti_vari';
        $document->document_year = (int) ($document->document_year ?: optional($document->created_at ? \Carbon\Carbon::parse($document->created_at) : null)->year ?: now('Europe/Rome')->year);
        $document->user_is_recipient = $userId ? $recipientIds->contains($userId) : false;
        $document->user_read_at = $userId ? ($readRows[$userId]->read_at ?? null) : null;
        $document->user_opened_at = $userId ? ($readRows[$userId]->opened_at ?? null) : null;
        $document->user_ids = DB::table('company_document_user')->where('company_document_id', $document->id)->pluck('user_id');
        $document->group_ids = DB::table('company_document_group')->where('company_document_id', $document->id)->pluck('document_group_id');

        return $document;
    }

    private function companyDocumentCategories(): array
    {
        return [
            'compensi' => 'Compensi',
            'contratti' => 'Contratti',
            'corsi_attestati' => 'Corsi e Attestati',
            'documenti_identita' => "Documenti d'identità",
            'documenti_vari' => 'Documenti Vari',
        ];
    }

    private function attendanceReportData(int $year, int $month, ?string $userId = null): array
    {
        $year = max(2020, min(2100, $year));
        $month = max(1, min(12, $month));
        $start = \Carbon\Carbon::create($year, $month, 1, 0, 0, 0, 'Europe/Rome')->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();
        $days = collect(\Carbon\CarbonPeriod::create($start, $end))->map(fn ($day) => [
            'iso' => $day->toDateString(),
            'day' => (int) $day->day,
            'weekday' => $this->shortItalianWeekday((int) $day->dayOfWeekIso),
            'label' => $this->shortItalianWeekday((int) $day->dayOfWeekIso).' '.$day->day,
            'is_weekend' => $day->isWeekend(),
        ])->values();

        $users = DB::table('users')
            ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
            ->leftJoin('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->where(function ($query) {
                $query->whereNull('user_roles.role')->orWhere('user_roles.role', '!=', 'guest');
            })
            ->when($userId, fn ($query) => $query->where('users.id', $userId))
            ->orderBy('users.name')
            ->get([
                'users.id',
                'users.name',
                'profiles.full_name',
                'profiles.employee_code',
            ]);

        $absenceRows = DB::table('absence_requests')
            ->where('status', '!=', 'rejected')
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereRaw('DATE(COALESCE(end_date, start_date)) >= ?', [$start->toDateString()])
            ->get();

        $rows = $users->map(function ($user, $index) use ($days, $absenceRows, $start, $end) {
            $dayValues = [];
            $totals = [
                'ordinary' => 0,
                'extra' => 0,
                'vacation' => 0,
                'permissions' => 0,
                'sickness' => 0,
                'other' => 0,
                'holiday' => 0,
            ];

            foreach ($days as $day) {
                if ($day['is_weekend']) {
                    $dayValues[$day['iso']] = '';
                    continue;
                }

                $workMinutes = 480;
                $label = null;
                $dayAbsences = $absenceRows
                    ->where('user_id', $user->id)
                    ->filter(function ($absence) use ($day) {
                        $startDate = \Carbon\Carbon::parse($absence->start_date)->toDateString();
                        $endDate = \Carbon\Carbon::parse($absence->end_date ?: $absence->start_date)->toDateString();

                        return $startDate <= $day['iso'] && $endDate >= $day['iso'];
                    });

                foreach ($dayAbsences as $absence) {
                    $minutes = $this->absenceMinutesForDay($absence, $day['iso']);
                    $workMinutes = max(0, $workMinutes - $minutes);

                    if ($absence->type === 'vacation') {
                        $totals['vacation'] += $minutes;
                        $label = $minutes >= 480 ? 'FER' : $this->formatAttendanceMinutes($workMinutes);
                    } elseif (in_array($absence->type, ['permission', 'late'], true)) {
                        $totals['permissions'] += $minutes;
                        $label = $workMinutes > 0 ? $this->formatAttendanceMinutes($workMinutes) : 'PER';
                    } elseif ($absence->type === 'sickness') {
                        $totals['sickness'] += $minutes;
                        $label = $minutes >= 480 ? 'MAL' : $this->formatAttendanceMinutes($workMinutes);
                    } else {
                        $totals['other'] += $minutes;
                        $label = $workMinutes > 0 ? $this->formatAttendanceMinutes($workMinutes) : 'ASS';
                    }
                }

                $totals['ordinary'] += $workMinutes;
                $dayValues[$day['iso']] = $label ?: $this->formatAttendanceMinutes($workMinutes);
            }

            return [
                'user_id' => $user->id,
                'name' => $user->full_name ?: $user->name,
                'employee_code' => $user->employee_code ?: str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'days' => $dayValues,
                'totals' => $totals,
                'total_labels' => collect($totals)->map(fn ($minutes) => $this->formatAttendanceMinutes($minutes))->all(),
            ];
        })->values();
        $scopeLabel = $userId && $rows->count() === 1 ? $rows->first()['name'] : 'Tutto il team';
        $scopeSlug = $userId && $rows->count() === 1 ? '-'.Str::slug($rows->first()['name']) : '';

        return [
            'company' => 'LU3G SRL',
            'year' => $year,
            'month' => $month,
            'selected_user_id' => $userId,
            'scope_label' => $scopeLabel,
            'month_label' => $this->italianMonthName($month).' '.$year,
            'file_name' => 'lu3gsrl-presenze-'.strtolower($this->italianMonthName($month)).'-'.$year.$scopeSlug.'.xlsx',
            'days' => $days,
            'rows' => $rows,
            'summary' => [
                'users' => $rows->count(),
                'ordinary' => $this->formatAttendanceMinutes($rows->sum(fn ($row) => $row['totals']['ordinary'])),
                'vacation' => $this->formatAttendanceMinutes($rows->sum(fn ($row) => $row['totals']['vacation'])),
                'permissions' => $this->formatAttendanceMinutes($rows->sum(fn ($row) => $row['totals']['permissions'])),
                'sickness' => $this->formatAttendanceMinutes($rows->sum(fn ($row) => $row['totals']['sickness'])),
                'other' => $this->formatAttendanceMinutes($rows->sum(fn ($row) => $row['totals']['other'])),
            ],
        ];
    }

    private function absenceMinutesForDay(object $absence, string $dayIso): int
    {
        $startDate = \Carbon\Carbon::parse($absence->start_date)->toDateString();
        $endDate = \Carbon\Carbon::parse($absence->end_date ?: $absence->start_date)->toDateString();
        if ($startDate === $endDate && $dayIso === $startDate && $absence->start_time && $absence->end_time) {
            return min(480, max(0, \Carbon\Carbon::parse($absence->start_time)->diffInMinutes(\Carbon\Carbon::parse($absence->end_time))));
        }

        return 480;
    }

    private function formatAttendanceMinutes(int|float $minutes): string
    {
        $minutes = (int) round($minutes);
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $remaining > 0 ? $hours.'h '.$remaining.'m' : $hours.'h';
    }

    private function italianMonthName(int $month): string
    {
        return [
            1 => 'Gennaio',
            2 => 'Febbraio',
            3 => 'Marzo',
            4 => 'Aprile',
            5 => 'Maggio',
            6 => 'Giugno',
            7 => 'Luglio',
            8 => 'Agosto',
            9 => 'Settembre',
            10 => 'Ottobre',
            11 => 'Novembre',
            12 => 'Dicembre',
        ][$month] ?? 'Mese';
    }

    private function shortItalianWeekday(int $dayOfWeekIso): string
    {
        return [
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mer',
            4 => 'Gio',
            5 => 'Ven',
            6 => 'Sab',
            7 => 'Dom',
        ][$dayOfWeekIso] ?? '';
    }

    private function buildAttendanceReportXlsx(array $report): string
    {
        $headers = array_merge(
            ['Cognome Nome', 'Matricola'],
            collect($report['days'])->pluck('label')->all(),
            ['Ore ordinarie', 'Lavoro extra', 'Ferie', 'Permessi', 'Malattia', 'Altre assenze', 'Festivo'],
        );
        $rows = [
            ['Azienda: '.$report['company']],
            [],
            [$report['month_label']],
            [],
            $headers,
        ];

        foreach ($report['rows'] as $row) {
            $rows[] = array_merge(
                [$row['name'], $row['employee_code']],
                collect($report['days'])->map(fn ($day) => $row['days'][$day['iso']] ?? '')->all(),
                [
                    $row['total_labels']['ordinary'],
                    $row['total_labels']['extra'],
                    $row['total_labels']['vacation'],
                    $row['total_labels']['permissions'],
                    $row['total_labels']['sickness'],
                    $row['total_labels']['other'],
                    $row['total_labels']['holiday'],
                ],
            );
        }

        $sheetXml = $this->attendanceSheetXml($rows);
        $tempPath = tempnam(sys_get_temp_dir(), 'centro-presenze-').'.xlsx';
        $zip = new \ZipArchive();
        $zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $this->xlsxContentTypesXml());
        $zip->addFromString('_rels/.rels', $this->xlsxRootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->xlsxWorkbookXml($report['month_label']));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xlsxWorkbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->xlsxStylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        return $tempPath;
    }

    private function attendanceSheetXml(array $rows): string
    {
        $xmlRows = [];
        foreach ($rows as $rowIndex => $row) {
            $cells = [];
            foreach ($row as $columnIndex => $value) {
                if ($value === null) {
                    continue;
                }

                $ref = $this->xlsxColumnName($columnIndex + 1).($rowIndex + 1);
                $style = $rowIndex === 4 ? 1 : ($columnIndex === 0 && $rowIndex >= 5 ? 2 : 0);
                if ($rowIndex >= 5 && in_array($value, ['FER', 'MAL', 'PER', 'ASS'], true)) {
                    $style = 3;
                }
                $cells[] = '<c r="'.$ref.'" t="inlineStr" s="'.$style.'"><is><t>'.$this->xmlEscape((string) $value).'</t></is></c>';
            }
            $xmlRows[] = '<row r="'.($rowIndex + 1).'">'.implode('', $cells).'</row>';
        }

        $cols = [
            '<col min="1" max="1" width="25" customWidth="1"/>',
            '<col min="2" max="2" width="20" customWidth="1"/>',
            '<col min="3" max="33" width="5" customWidth="1"/>',
            '<col min="34" max="40" width="14" customWidth="1"/>',
        ];

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<cols>'.implode('', $cols).'</cols>'
            .'<sheetData>'.implode('', $xmlRows).'</sheetData>'
            .'</worksheet>';
    }

    private function xlsxColumnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function xlsxContentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function xlsxRootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function xlsxWorkbookXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$this->xmlEscape($sheetName).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function xlsxWorkbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function xlsxStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="4"><font/><font><b/><color rgb="FFFFFFFF"/></font><font><b/></font><font><color rgb="FFED7D31"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF9A9A9A"/></patternFill></fill></fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    private function companyMessageRows(?string $userId = null, bool $adminView = false)
    {
        $query = DB::table('company_messages')
            ->leftJoin('users', 'users.id', '=', 'company_messages.created_by')
            ->select('company_messages.*', 'users.name as creator_name')
            ->latest('company_messages.created_at');

        if ($userId) {
            $messageIds = $this->visibleCompanyMessageIdsForUser($userId);
            $query->whereIn('company_messages.id', $messageIds);
        }

        return $query
            ->limit($adminView ? 200 : 100)
            ->get()
            ->map(fn ($message) => $this->companyMessageRow($message, $userId));
    }

    private function companyMessageRow(object $message, ?string $userId = null): object
    {
        $recipientIds = $this->companyMessageRecipientIds($message->id);
        $readRows = DB::table('company_message_reads')
            ->where('company_message_id', $message->id)
            ->get(['user_id', 'opened_at', 'read_at'])
            ->keyBy('user_id');

        $message->recipient_count = $recipientIds->count();
        $message->read_count = $readRows->filter(fn ($row) => filled($row->read_at))->count();
        $message->opened_count = $readRows->filter(fn ($row) => filled($row->opened_at))->count();
        $message->user_is_recipient = $userId ? $recipientIds->contains($userId) : false;
        $message->user_read_at = $userId ? ($readRows[$userId]->read_at ?? null) : null;
        $message->user_opened_at = $userId ? ($readRows[$userId]->opened_at ?? null) : null;
        $message->user_ids = DB::table('company_message_user')->where('company_message_id', $message->id)->pluck('user_id');
        $message->group_ids = DB::table('company_message_group')->where('company_message_id', $message->id)->pluck('document_group_id');

        return $message;
    }

    private function companyMessageReaderRows(string $messageId)
    {
        $recipientIds = $this->companyMessageRecipientIds($messageId);

        return DB::table('users')
            ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
            ->leftJoin('company_message_reads', function ($join) use ($messageId) {
                $join->on('company_message_reads.user_id', '=', 'users.id')
                    ->where('company_message_reads.company_message_id', '=', $messageId);
            })
            ->whereIn('users.id', $recipientIds)
            ->orderBy('users.name')
            ->get([
                'users.id',
                'users.name',
                'users.email',
                'profiles.avatar_url',
                'company_message_reads.opened_at',
                'company_message_reads.read_at',
            ]);
    }

    private function visibleCompanyMessageIdsForUser(string $userId): \Illuminate\Support\Collection
    {
        $groupIds = DB::table('document_group_user')->where('user_id', $userId)->pluck('document_group_id');

        return DB::table('company_messages')
            ->where('audience', 'all')
            ->pluck('id')
            ->merge(DB::table('company_message_user')->where('user_id', $userId)->pluck('company_message_id'))
            ->merge(DB::table('company_message_group')->whereIn('document_group_id', $groupIds)->pluck('company_message_id'))
            ->filter()
            ->unique()
            ->values();
    }

    private function companyMessageRecipientIds(string $messageId): \Illuminate\Support\Collection
    {
        $message = DB::table('company_messages')->where('id', $messageId)->first(['audience']);
        if (! $message) {
            return collect();
        }

        if ($message->audience === 'all') {
            return DB::table('users')->pluck('id');
        }

        $userIds = DB::table('company_message_user')
            ->where('company_message_id', $messageId)
            ->pluck('user_id');

        $groupIds = DB::table('company_message_group')
            ->where('company_message_id', $messageId)
            ->pluck('document_group_id');

        return $userIds
            ->merge(DB::table('document_group_user')->whereIn('document_group_id', $groupIds)->pluck('user_id'))
            ->filter()
            ->unique()
            ->values();
    }

    private function ensureCompanyMessageReadRows(string $messageId, iterable $userIds): void
    {
        foreach (collect($userIds)->filter()->unique()->values() as $userId) {
            $exists = DB::table('company_message_reads')
                ->where('company_message_id', $messageId)
                ->where('user_id', $userId)
                ->exists();

            if ($exists) {
                DB::table('company_message_reads')
                    ->where('company_message_id', $messageId)
                    ->where('user_id', $userId)
                    ->update(['updated_at' => now()]);

                continue;
            }

            DB::table('company_message_reads')->insert([
                'id' => (string) str()->uuid(),
                'company_message_id' => $messageId,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function markCompanyMessageOpened(string $messageId, string $userId): void
    {
        $row = DB::table('company_message_reads')
            ->where('company_message_id', $messageId)
            ->where('user_id', $userId)
            ->first(['id', 'opened_at']);

        if ($row) {
            DB::table('company_message_reads')->where('id', $row->id)->update([
                'opened_at' => $row->opened_at ?: now(),
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('company_message_reads')->insert([
            'id' => (string) str()->uuid(),
            'company_message_id' => $messageId,
            'user_id' => $userId,
            'opened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function documentGroupRows()
    {
        $groups = DB::table('document_groups')->orderBy('name')->get();
        $members = DB::table('document_group_user')
            ->whereIn('document_group_id', $groups->pluck('id'))
            ->get(['document_group_id', 'user_id'])
            ->groupBy('document_group_id');

        return $groups->map(function ($group) use ($members) {
            $group->user_ids = ($members[$group->id] ?? collect())->pluck('user_id')->values();
            $group->members_count = $group->user_ids->count();

            return $group;
        });
    }

    private function companyDocumentUserRows()
    {
        return $this->userOptions()->map(function ($user) {
            $documents = $this->companyDocumentRows($user->id, false);
            $user->documents_count = $documents->count();
            $user->read_count = $documents->filter(fn ($document) => filled($document->user_read_at))->count();
            $user->unread_count = max(0, $user->documents_count - $user->read_count);

            return $user;
        });
    }

    private function companyDocumentReaderRows(string $documentId)
    {
        $recipientIds = $this->companyDocumentRecipientIds($documentId);

        return DB::table('users')
            ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
            ->leftJoin('company_document_reads', function ($join) use ($documentId) {
                $join->on('company_document_reads.user_id', '=', 'users.id')
                    ->where('company_document_reads.company_document_id', '=', $documentId);
            })
            ->whereIn('users.id', $recipientIds)
            ->orderBy('users.name')
            ->get([
                'users.id',
                'users.name',
                'users.email',
                'profiles.avatar_url',
                'company_document_reads.opened_at',
                'company_document_reads.read_at',
            ]);
    }

    private function visibleCompanyDocumentIdsForUser(string $userId): \Illuminate\Support\Collection
    {
        $groupIds = DB::table('document_group_user')->where('user_id', $userId)->pluck('document_group_id');

        return DB::table('company_documents')
            ->where('audience', 'all')
            ->pluck('id')
            ->merge(DB::table('company_document_user')->where('user_id', $userId)->pluck('company_document_id'))
            ->merge(DB::table('company_document_group')->whereIn('document_group_id', $groupIds)->pluck('company_document_id'))
            ->filter()
            ->unique()
            ->values();
    }

    private function companyDocumentRecipientIds(string $documentId): \Illuminate\Support\Collection
    {
        $document = DB::table('company_documents')->where('id', $documentId)->first(['audience']);
        if (! $document) {
            return collect();
        }

        if ($document->audience === 'all') {
            return DB::table('users')->pluck('id');
        }

        $userIds = DB::table('company_document_user')
            ->where('company_document_id', $documentId)
            ->pluck('user_id');

        $groupIds = DB::table('company_document_group')
            ->where('company_document_id', $documentId)
            ->pluck('document_group_id');

        return $userIds
            ->merge(DB::table('document_group_user')->whereIn('document_group_id', $groupIds)->pluck('user_id'))
            ->filter()
            ->unique()
            ->values();
    }

    private function ensureCompanyDocumentReadRows(string $documentId, iterable $userIds): void
    {
        foreach (collect($userIds)->filter()->unique()->values() as $userId) {
            $exists = DB::table('company_document_reads')
                ->where('company_document_id', $documentId)
                ->where('user_id', $userId)
                ->exists();

            if ($exists) {
                DB::table('company_document_reads')
                    ->where('company_document_id', $documentId)
                    ->where('user_id', $userId)
                    ->update(['updated_at' => now()]);

                continue;
            }

            DB::table('company_document_reads')->insert([
                'id' => (string) str()->uuid(),
                'company_document_id' => $documentId,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function markCompanyDocumentOpened(string $documentId, string $userId): void
    {
        $row = DB::table('company_document_reads')
            ->where('company_document_id', $documentId)
            ->where('user_id', $userId)
            ->first(['id', 'opened_at']);

        if ($row) {
            DB::table('company_document_reads')->where('id', $row->id)->update([
                'opened_at' => $row->opened_at ?: now(),
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('company_document_reads')->insert([
            'id' => (string) str()->uuid(),
            'company_document_id' => $documentId,
            'user_id' => $userId,
            'opened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function syncDocumentGroupUsers(string $groupId, array $userIds): void
    {
        DB::table('document_group_user')->where('document_group_id', $groupId)->delete();

        $rows = collect($userIds)->filter()->unique()->values()->map(fn ($userId) => [
            'id' => (string) str()->uuid(),
            'document_group_id' => $groupId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if ($rows) {
            DB::table('document_group_user')->insert($rows);
        }
    }

    private function validatedAbsencePayload(Request $request): array
    {
        $payload = $request->validate([
            'type' => ['required', Rule::in(['vacation', 'permission', 'sickness', 'late', 'other'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'regex:/^([01][0-9]|2[0-3]):00$/'],
            'end_time' => ['nullable', 'regex:/^([01][0-9]|2[0-3]):00$/'],
            'inps_code' => ['nullable', 'required_if:type,sickness', 'string', 'max:255'],
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'notes' => ['nullable', 'string', 'max:6000'],
        ]);

        if (in_array($payload['type'], ['vacation', 'sickness'], true)) {
            $payload['start_time'] = null;
            $payload['end_time'] = null;
        }

        if (in_array($payload['type'], ['permission', 'late'], true)) {
            $payload['end_date'] = $payload['start_date'];
        }

        $payload['end_date'] = ($payload['end_date'] ?? null) ?: $payload['start_date'];
        $payload['start_time'] = ($payload['start_time'] ?? null) ?: null;
        $payload['end_time'] = ($payload['end_time'] ?? null) ?: null;
        $payload['inps_code'] = $payload['type'] === 'sickness' ? (($payload['inps_code'] ?? null) ?: null) : null;
        $payload['notes'] = ($payload['notes'] ?? null) ?: null;

        return $payload;
    }

    private function syncProfileAndRole(User $user, string $role): void
    {
        DB::table('profiles')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'id' => (string) str()->uuid(),
                'full_name' => $user->name,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        DB::table('user_roles')->where('user_id', $user->id)->delete();
        DB::table('user_roles')->insert([
            'id' => (string) str()->uuid(),
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    private function userOptions()
    {
        return DB::table('users')
            ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email', 'profiles.avatar_url', 'profiles.job_title', 'profiles.smartworking_day']);
    }

    private function taskSubtaskRows(string $taskId)
    {
        $subtasks = DB::table('tasks')
            ->where('parent_task_id', $taskId)
            ->orderBy('position')
            ->orderBy('created_at')
            ->get([
                'id',
                'title',
                'description',
                'project_id',
                'project_section_id',
                'client_id',
                'service_id',
                'parent_task_id',
                'task_type',
                'status',
                'priority',
                'start_date',
                'due_date',
                'due_time',
                'location',
                'recurring_enabled',
                'recurring_interval_value',
                'recurring_interval_unit',
                'recurring_mode',
                'recurring_weekday',
                'recurring_month_day',
                'position',
                'created_by',
            ]);

        $assigneesBySubtask = DB::table('task_assignees')
            ->whereIn('task_id', $subtasks->pluck('id'))
            ->get(['task_id', 'user_id'])
            ->groupBy('task_id');
        $followersBySubtask = DB::table('task_followers')
            ->whereIn('task_id', $subtasks->pluck('id'))
            ->get(['task_id', 'user_id'])
            ->groupBy('task_id');
        $dependencyRows = $this->taskDependencyRows($subtasks->pluck('id'));
        $comments = DB::table('task_comments')
            ->leftJoin('users', 'users.id', '=', 'task_comments.user_id')
            ->whereIn('task_comments.task_id', $subtasks->pluck('id'))
            ->latest('task_comments.created_at')
            ->get(['task_comments.*', 'users.name as user_name'])
            ->groupBy('task_id')
            ->map(fn ($items) => $items->take(30)->values());
        $activity = $this->taskActivityRows($subtasks->pluck('id'));

        return $subtasks->map(function ($subtask) use ($assigneesBySubtask, $followersBySubtask, $dependencyRows, $comments, $activity) {
            $subtask->assignee_ids = ($assigneesBySubtask[$subtask->id] ?? collect())->pluck('user_id')->values();
            $subtask->follower_ids = ($followersBySubtask[$subtask->id] ?? collect())->pluck('user_id')->values();
            $subtask->subtasks = collect();
            $subtask->comments = ($comments[$subtask->id] ?? collect())->values();
            $subtask->activity = ($activity[$subtask->id] ?? collect())->values();
            $subtask->dependencies = ($dependencyRows[$subtask->id]['dependencies'] ?? collect())->values();
            $subtask->dependents = ($dependencyRows[$subtask->id]['dependents'] ?? collect())->values();
            $subtask->blocked_dependencies_count = ($subtask->dependencies ?? collect())->where('status', '!=', 'done')->count();

            return $subtask;
        });
    }

    private function hydrateTaskRow(object $task): object
    {
        $taskIds = collect([$task->id]);
        $assigneeIds = DB::table('task_assignees')
            ->where('task_id', $task->id)
            ->pluck('user_id')
            ->values();
        $followerIds = DB::table('task_followers')
            ->where('task_id', $task->id)
            ->pluck('user_id')
            ->values();
        $comments = DB::table('task_comments')
            ->leftJoin('users', 'users.id', '=', 'task_comments.user_id')
            ->where('task_comments.task_id', $task->id)
            ->latest('task_comments.created_at')
            ->get(['task_comments.*', 'users.name as user_name'])
            ->take(30)
            ->values();
        $activity = ($this->taskActivityRows($taskIds)[$task->id] ?? collect())->values();
        $dependencies = $this->taskDependencyRows($taskIds);

        $task->assignee_ids = $assigneeIds;
        $task->follower_ids = $followerIds;
        $task->comments = $comments;
        $task->activity = $activity;
        $task->dependencies = ($dependencies[$task->id]['dependencies'] ?? collect())->values();
        $task->dependents = ($dependencies[$task->id]['dependents'] ?? collect())->values();
        $task->blocked_dependencies_count = ($task->dependencies ?? collect())->where('status', '!=', 'done')->count();
        $task->subtasks = $task->parent_task_id ? collect() : $this->taskSubtaskRows($task->id);
        $task->subtask_count = $task->subtasks->count();

        return $task;
    }

    public function taskSnapshot(Request $request, string $id)
    {
        $task = DB::table('tasks')
            ->leftJoin('projects', 'projects.id', '=', 'tasks.project_id')
            ->leftJoin('clients', 'clients.id', '=', 'tasks.client_id')
            ->leftJoin('services', 'services.id', '=', 'tasks.service_id')
            ->where('tasks.id', $id)
            ->select('tasks.*', 'projects.name as project_name', 'projects.color as project_color', 'clients.name as client_name', 'services.name as service_name', 'services.color as service_color')
            ->first();

        abort_if(! $task, 404);

        if ($this->isGuest($request)) {
            $rootTaskId = $task->parent_task_id ?: $task->id;
            abort_unless($this->visibleTaskIdsForUser($request->user()->id)->contains($rootTaskId), 403);
        }

        return response()->json($this->hydrateTaskRow($task));
    }

    private function taskDependencyRows($taskIds): \Illuminate\Support\Collection
    {
        $ids = collect($taskIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $dependencies = DB::table('task_dependencies')
            ->join('tasks', 'tasks.id', '=', 'task_dependencies.depends_on_task_id')
            ->leftJoin('clients', 'clients.id', '=', 'tasks.client_id')
            ->whereIn('task_dependencies.task_id', $ids)
            ->orderBy('tasks.due_date')
            ->orderBy('tasks.title')
            ->get([
                'task_dependencies.task_id',
                'tasks.id',
                'tasks.title',
                'tasks.status',
                'tasks.due_date',
                'tasks.parent_task_id',
                'clients.name as client_name',
            ])
            ->groupBy('task_id');

        $dependents = DB::table('task_dependencies')
            ->join('tasks', 'tasks.id', '=', 'task_dependencies.task_id')
            ->leftJoin('clients', 'clients.id', '=', 'tasks.client_id')
            ->whereIn('task_dependencies.depends_on_task_id', $ids)
            ->orderBy('tasks.due_date')
            ->orderBy('tasks.title')
            ->get([
                'task_dependencies.depends_on_task_id as task_id',
                'tasks.id',
                'tasks.title',
                'tasks.status',
                'tasks.due_date',
                'tasks.parent_task_id',
                'clients.name as client_name',
            ])
            ->groupBy('task_id');

        return $ids->mapWithKeys(fn ($id) => [
            $id => [
                'dependencies' => $dependencies[$id] ?? collect(),
                'dependents' => $dependents[$id] ?? collect(),
            ],
        ]);
    }

    private function taskDependencyOptions(?string $currentTaskId = null): \Illuminate\Support\Collection
    {
        return DB::table('tasks')
            ->leftJoin('clients', 'clients.id', '=', 'tasks.client_id')
            ->when($currentTaskId, fn ($query) => $query->where('tasks.id', '!=', $currentTaskId))
            ->where('tasks.status', '!=', 'done')
            ->orderBy('tasks.due_date')
            ->orderBy('tasks.title')
            ->limit(300)
            ->get([
                'tasks.id',
                'tasks.title',
                'tasks.status',
                'tasks.due_date',
                'tasks.parent_task_id',
                'clients.name as client_name',
            ]);
    }

    private function projectTemplateOptions(): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('project_templates')) {
            return collect();
        }

        return DB::table('project_templates')
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);
    }

    private function projectTemplateRows(): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('project_templates')) {
            return collect();
        }

        $templates = DB::table('project_templates')
            ->leftJoin('users', 'users.id', '=', 'project_templates.created_by')
            ->orderBy('project_templates.name')
            ->get([
                'project_templates.*',
                'users.name as created_by_name',
            ]);
        $sections = DB::table('project_template_sections')
            ->orderBy('position')
            ->orderBy('created_at')
            ->get()
            ->groupBy('project_template_id');
        $tasks = DB::table('project_template_tasks')
            ->orderBy('position')
            ->orderBy('created_at')
            ->get()
            ->groupBy('project_template_section_id');

        return $templates->map(function ($template) use ($sections, $tasks) {
            $template->sections = ($sections[$template->id] ?? collect())
                ->map(function ($section) use ($tasks) {
                    $section->tasks = ($tasks[$section->id] ?? collect())->values();

                    return $section;
                })
                ->values();
            $template->tasks_count = $template->sections->sum(fn ($section) => $section->tasks->count());

            return $template;
        });
    }

    private function syncProjectTemplateStructure(string $templateId, array $sections): void
    {
        foreach (array_values($sections) as $sectionIndex => $section) {
            $sectionId = (string) str()->uuid();
            DB::table('project_template_sections')->insert([
                'id' => $sectionId,
                'project_template_id' => $templateId,
                'name' => $section['name'],
                'position' => $sectionIndex,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (array_values($section['tasks'] ?? []) as $taskIndex => $task) {
                DB::table('project_template_tasks')->insert([
                    'id' => (string) str()->uuid(),
                    'template_key' => $task['template_key'] ?? (string) str()->uuid(),
                    'project_template_section_id' => $sectionId,
                    'title' => $task['title'],
                    'description' => $task['description'] ?? null,
                    'service_id' => $task['service_id'] ?? null,
                    'assignee_ids' => json_encode(array_values(array_unique($task['assignee_ids'] ?? []))),
                    'day_offset' => (int) ($task['day_offset'] ?? 0),
                    'date_offset_direction' => $task['date_offset_direction'] ?? 'after',
                    'date_reference_type' => ($task['date_reference_type'] ?? 'project_start') === 'task' ? 'task' : 'project_start',
                    'date_reference_task_key' => ($task['date_reference_type'] ?? 'project_start') === 'task' ? ($task['date_reference_task_key'] ?? null) : null,
                    'dependency_mode' => in_array($task['dependency_mode'] ?? 'none', ['blocked_by', 'blocks'], true) ? $task['dependency_mode'] : 'none',
                    'dependency_task_keys' => json_encode(array_values(array_unique($task['dependency_task_keys'] ?? []))),
                    'duration_days' => max(1, (int) ($task['duration_days'] ?? 1)),
                    'due_time' => ($task['task_type'] ?? 'project') === 'meeting' ? ($task['due_time'] ?? null) : null,
                    'location' => ($task['task_type'] ?? 'project') === 'meeting' ? ($task['location'] ?? null) : null,
                    'priority' => $task['priority'] ?? 'medium',
                    'task_type' => ($task['task_type'] ?? 'project') === 'meeting' ? 'meeting' : 'project',
                    'status' => $task['status'] ?? 'todo',
                    'position' => $taskIndex,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function createProjectTasksFromTemplate(string $projectId, string $templateId, string $startDate, string $actorId, ?string $clientId): void
    {
        $template = DB::table('project_templates')->where('id', $templateId)->where('active', true)->first();
        if (! $template) {
            return;
        }

        $baseDate = \Carbon\Carbon::parse($startDate, 'Europe/Rome')->startOfDay();
        $templateSections = DB::table('project_template_sections')
            ->where('project_template_id', $templateId)
            ->orderBy('position')
            ->orderBy('created_at')
            ->get();
        $templateTasksBySection = DB::table('project_template_tasks')
            ->whereIn('project_template_section_id', $templateSections->pluck('id'))
            ->orderBy('position')
            ->orderBy('created_at')
            ->get()
            ->groupBy('project_template_section_id');
        $templateTaskLookup = $templateTasksBySection
            ->flatMap(fn ($rows) => $rows)
            ->keyBy(fn ($task) => $task->template_key ?: $task->id);
        $createdTaskIdsByTemplateKey = [];
        $resolvedStarts = [];
        $resolving = [];
        $resolveStart = function ($templateTask) use (&$resolveStart, &$resolvedStarts, &$resolving, $templateTaskLookup, $baseDate) {
            $key = $templateTask->template_key ?: $templateTask->id;
            if (isset($resolvedStarts[$key])) {
                return $resolvedStarts[$key]->copy();
            }
            if (isset($resolving[$key])) {
                return $baseDate->copy();
            }

            $resolving[$key] = true;
            $referenceDate = $baseDate->copy();
            if (($templateTask->date_reference_type ?? 'project_start') === 'task' && ! empty($templateTask->date_reference_task_key)) {
                $referenceTask = $templateTaskLookup[$templateTask->date_reference_task_key] ?? null;
                if ($referenceTask) {
                    $referenceDate = $resolveStart($referenceTask);
                }
            }

            $days = (int) ($templateTask->day_offset ?? 0);
            $start = ($templateTask->date_offset_direction ?? 'after') === 'before'
                ? $referenceDate->subDays($days)
                : $referenceDate->addDays($days);

            unset($resolving[$key]);
            $resolvedStarts[$key] = $start->copy();

            return $start;
        };

        foreach ($templateSections as $templateSection) {
            $sectionId = (string) str()->uuid();
            DB::table('project_sections')->insert([
                'id' => $sectionId,
                'project_id' => $projectId,
                'name' => $templateSection->name,
                'position' => DB::table('project_sections')->where('project_id', $projectId)->count(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $templateTasks = $templateTasksBySection[$templateSection->id] ?? collect();

            foreach ($templateTasks as $templateTask) {
                $taskStart = $resolveStart($templateTask);
                $taskDue = $taskStart->copy()->addDays(max(1, (int) $templateTask->duration_days) - 1);
                $taskId = (string) str()->uuid();

                DB::table('tasks')->insert([
                    'id' => $taskId,
                    'title' => $templateTask->title,
                    'description' => $templateTask->description,
                    'project_id' => $projectId,
                    'project_section_id' => $sectionId,
                    'client_id' => $clientId,
                    'service_id' => $templateTask->service_id ?? null,
                    'parent_task_id' => null,
                    'start_date' => $taskStart->toDateString(),
                    'due_date' => $taskDue->toDateString(),
                    'due_time' => ($templateTask->task_type === 'meeting') ? $templateTask->due_time : null,
                    'location' => ($templateTask->task_type === 'meeting') ? $templateTask->location : null,
                    'priority' => $templateTask->priority,
                    'status' => $templateTask->status ?: 'todo',
                    'task_type' => $templateTask->task_type,
                    'recurring_enabled' => false,
                    'recurring_mode' => null,
                    'recurring_interval_value' => null,
                    'recurring_interval_unit' => null,
                    'recurring_weekday' => null,
                    'recurring_month_day' => null,
                    'created_by' => $actorId,
                    'position' => $templateTask->position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $createdTaskIdsByTemplateKey[$templateTask->template_key ?: $templateTask->id] = $taskId;

                $assigneeIds = collect(json_decode($templateTask->assignee_ids ?: '[]', true) ?: [])
                    ->filter()
                    ->unique()
                    ->values();
                if ($assigneeIds->isNotEmpty()) {
                    DB::table('task_assignees')->insert($assigneeIds->map(fn ($userId) => [
                        'id' => (string) str()->uuid(),
                        'task_id' => $taskId,
                        'user_id' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all());
                }
            }
        }

        $dependencyRows = [];
        $existingDependencyKeys = [];
        $templateTasksBySection->flatMap(fn ($rows) => $rows)->each(function ($templateTask) use (&$dependencyRows, &$existingDependencyKeys, $createdTaskIdsByTemplateKey) {
            $sourceKey = $templateTask->template_key ?: $templateTask->id;
            $sourceTaskId = $createdTaskIdsByTemplateKey[$sourceKey] ?? null;
            $mode = in_array($templateTask->dependency_mode ?? 'none', ['blocked_by', 'blocks'], true) ? $templateTask->dependency_mode : 'none';
            $targetKeys = collect(json_decode($templateTask->dependency_task_keys ?: '[]', true) ?: [])
                ->filter()
                ->unique()
                ->values();

            if (! $sourceTaskId || $mode === 'none' || $targetKeys->isEmpty()) {
                return;
            }

            foreach ($targetKeys as $targetKey) {
                $targetTaskId = $createdTaskIdsByTemplateKey[$targetKey] ?? null;
                if (! $targetTaskId || $targetTaskId === $sourceTaskId) {
                    continue;
                }

                $taskId = $mode === 'blocks' ? $targetTaskId : $sourceTaskId;
                $dependsOnTaskId = $mode === 'blocks' ? $sourceTaskId : $targetTaskId;
                $uniqueKey = "{$taskId}:{$dependsOnTaskId}";

                if (isset($existingDependencyKeys[$uniqueKey])) {
                    continue;
                }

                $existingDependencyKeys[$uniqueKey] = true;
                $dependencyRows[] = [
                    'id' => (string) str()->uuid(),
                    'task_id' => $taskId,
                    'depends_on_task_id' => $dependsOnTaskId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        });

        if ($dependencyRows) {
            DB::table('task_dependencies')->insert($dependencyRows);
        }
    }

    private function projectSections(string $projectId): \Illuminate\Support\Collection
    {
        $existing = DB::table('project_sections')
            ->where('project_id', $projectId)
            ->orderBy('position')
            ->orderBy('created_at')
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        foreach (['Fase Preliminare', 'Fase Realizzativa', 'Fase Conclusiva'] as $position => $name) {
            DB::table('project_sections')->insert([
                'id' => (string) str()->uuid(),
                'project_id' => $projectId,
                'name' => $name,
                'position' => $position,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return DB::table('project_sections')
            ->where('project_id', $projectId)
            ->orderBy('position')
            ->orderBy('created_at')
            ->get();
    }

    private function projectTaskRows(string $projectId, ?string $visibleForUserId = null): \Illuminate\Support\Collection
    {
        $visibleTaskIds = $visibleForUserId ? $this->visibleTaskIdsForUser($visibleForUserId) : null;

        $rows = DB::table('tasks')
            ->where('project_id', $projectId)
            ->where(fn ($query) => $query->whereNull('parent_task_id')->orWhereRaw("TRIM(parent_task_id) = ''"))
            ->when($visibleTaskIds !== null, fn ($query) => $query->whereIn('tasks.id', $visibleTaskIds))
            ->orderByRaw('project_section_id is null')
            ->orderBy('project_section_id')
            ->orderBy('position')
            ->orderBy('due_date')
            ->orderBy('title')
            ->get();

        $assignees = DB::table('task_assignees')
            ->join('users', 'users.id', '=', 'task_assignees.user_id')
            ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
            ->whereIn('task_assignees.task_id', $rows->pluck('id'))
            ->orderBy('users.name')
            ->get(['task_assignees.task_id', 'users.id', 'users.name', 'users.email', 'profiles.avatar_url'])
            ->groupBy('task_id');
        $assigneeIds = DB::table('task_assignees')
            ->whereIn('task_id', $rows->pluck('id'))
            ->get(['task_id', 'user_id'])
            ->groupBy('task_id');
        $followerIds = DB::table('task_followers')
            ->whereIn('task_id', $rows->pluck('id'))
            ->get(['task_id', 'user_id'])
            ->groupBy('task_id');
        $comments = DB::table('task_comments')
            ->leftJoin('users', 'users.id', '=', 'task_comments.user_id')
            ->whereIn('task_comments.task_id', $rows->pluck('id'))
            ->latest('task_comments.created_at')
            ->get(['task_comments.*', 'users.name as user_name'])
            ->groupBy('task_id')
            ->map(fn ($items) => $items->take(30)->values());
        $activity = $this->taskActivityRows($rows->pluck('id'));
        $dependencies = $this->taskDependencyRows($rows->pluck('id'));

        return $rows->map(function ($row) use ($assignees, $assigneeIds, $followerIds, $comments, $activity, $dependencies) {
            $row->assignees = ($assignees[$row->id] ?? collect())->values();
            $row->assignee_ids = ($assigneeIds[$row->id] ?? collect())->pluck('user_id')->values();
            $row->follower_ids = ($followerIds[$row->id] ?? collect())->pluck('user_id')->values();
            $row->subtasks = $this->taskSubtaskRows($row->id);
            $row->comments = ($comments[$row->id] ?? collect())->values();
            $row->activity = ($activity[$row->id] ?? collect())->values();
            $row->dependencies = ($dependencies[$row->id]['dependencies'] ?? collect())->values();
            $row->dependents = ($dependencies[$row->id]['dependents'] ?? collect())->values();
            $row->blocked_dependencies_count = ($row->dependencies ?? collect())->where('status', '!=', 'done')->count();

            return $row;
        });
    }

    private function projectMessages(string $projectId): \Illuminate\Support\Collection
    {
        return DB::table('project_messages')
            ->leftJoin('users', 'users.id', '=', 'project_messages.user_id')
            ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
            ->where('project_messages.project_id', $projectId)
            ->latest('project_messages.created_at')
            ->limit(50)
            ->get([
                'project_messages.*',
                'users.name as user_name',
                'users.email as user_email',
                'profiles.avatar_url as user_avatar_url',
            ]);
    }

    private function projectFiles(string $projectId, string $kind): \Illuminate\Support\Collection
    {
        return DB::table('project_files')
            ->leftJoin('users', 'users.id', '=', 'project_files.uploaded_by')
            ->where('project_files.project_id', $projectId)
            ->where('project_files.kind', $kind)
            ->latest('project_files.created_at')
            ->get([
                'project_files.*',
                'users.name as uploaded_by_name',
            ]);
    }

    private function taskDependsOn(string $taskId, string $targetTaskId, array $visited = []): bool
    {
        if ($taskId === $targetTaskId) {
            return true;
        }

        if (in_array($taskId, $visited, true)) {
            return false;
        }

        $dependencies = DB::table('task_dependencies')
            ->where('task_id', $taskId)
            ->pluck('depends_on_task_id');

        foreach ($dependencies as $dependencyId) {
            if ($dependencyId === $targetTaskId || $this->taskDependsOn($dependencyId, $targetTaskId, [...$visited, $taskId])) {
                return true;
            }
        }

        return false;
    }

    private function taskOpenDependencyCount(string $taskId): int
    {
        return DB::table('task_dependencies')
            ->join('tasks', 'tasks.id', '=', 'task_dependencies.depends_on_task_id')
            ->where('task_dependencies.task_id', $taskId)
            ->where('tasks.status', '!=', 'done')
            ->count();
    }

    private function storeDocument(Request $request): RedirectResponse
    {
        $payload = $this->validatedPayload($request, 'billing');
        $payload = $this->normalizeDocumentPayload($payload, $request->user()->id);
        $payload['id'] = (string) str()->uuid();
        $payload['created_at'] = now();
        $payload['updated_at'] = now();

        DB::table('documents')->insert($payload);

        return back()->with('status', 'Documento creato.');
    }

    private function updateDocument(Request $request, string $id): RedirectResponse
    {
        $payload = $this->normalizeDocumentPayload($this->validatedPayload($request, 'billing'), $request->user()->id, false);
        $payload['updated_at'] = now();

        DB::table('documents')->where('id', $id)->update($payload);

        return back()->with('status', 'Documento aggiornato.');
    }

    public function updateDocumentHeader(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManageBilling($request);
        DB::table('documents')->where('id', $id)->exists() || abort(404);

        $payload = $request->validate([
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['draft', 'sent', 'accepted', 'rejected', 'paid', 'partially_paid', 'overdue', 'cancelled'])],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0'],
            'causale' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'footer_notes' => ['nullable', 'string'],
            'withholding_pct' => ['nullable', 'numeric', 'min:0'],
            'pension_fund_pct' => ['nullable', 'numeric', 'min:0'],
            'pension_fund_label' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($payload as $key => $value) {
            if ($value === '') {
                $payload[$key] = null;
            }
        }

        DB::table('documents')->where('id', $id)->update([
            ...$payload,
            'year' => (int) substr($payload['issue_date'], 0, 4),
            'updated_at' => now(),
        ]);
        $this->recalculateDocument($id);

        return back()->with('status', 'Documento salvato.');
    }

    public function issueDocument(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManageBilling($request);
        $document = DB::table('documents')->where('id', $id)->first();
        abort_if(! $document, 404);

        if ($document->number) {
            return back()->with('status', 'Documento gia emesso.');
        }

        $year = $document->year ?: (int) substr((string) $document->issue_date, 0, 4);

        DB::transaction(function () use ($document, $year) {
            $numbering = DB::table('document_numbering')
                ->where('doc_type', $document->doc_type)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $numbering) {
                $numbering = (object) [
                    'id' => (string) str()->uuid(),
                    'doc_type' => $document->doc_type,
                    'year' => $year,
                    'prefix' => strtoupper(substr((string) $document->doc_type, 0, 1)),
                    'format' => '{prefix}{year}/{seq}',
                    'current_seq' => 0,
                ];
                DB::table('document_numbering')->insert([
                    'id' => $numbering->id,
                    'doc_type' => $numbering->doc_type,
                    'year' => $numbering->year,
                    'prefix' => $numbering->prefix,
                    'format' => $numbering->format,
                    'current_seq' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $seq = ((int) $numbering->current_seq) + 1;
            $number = str_replace(
                ['{prefix}', '{year}', '{seq}'],
                [$numbering->prefix, (string) $year, str_pad((string) $seq, 4, '0', STR_PAD_LEFT)],
                $numbering->format,
            );

            DB::table('document_numbering')->where('id', $numbering->id)->update([
                'current_seq' => $seq,
                'updated_at' => now(),
            ]);

            DB::table('documents')->where('id', $document->id)->update([
                'number' => $number,
                'seq' => $seq,
                'year' => $year,
                'status' => 'sent',
                'updated_at' => now(),
            ]);
        });

        return back()->with('status', 'Documento emesso.');
    }

    public function duplicateDocument(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManageBilling($request);
        $newId = $this->copyDocument($id, null, $request->user()->id);

        return redirect()->route('billing.show', $newId)->with('status', 'Documento duplicato.');
    }

    public function convertDocument(Request $request, string $id, string $type): RedirectResponse
    {
        $this->ensureCanManageBilling($request);
        abort_unless(in_array($type, ['proforma', 'fattura', 'nota_credito'], true), 404);
        $newId = $this->copyDocument($id, $type, $request->user()->id);

        return redirect()->route('billing.show', $newId)->with('status', 'Documento creato.');
    }

    public function downloadDocumentPdf(string $id): \Illuminate\Http\Response
    {
        $bundle = $this->documentBundle($id);
        $pdf = $this->documentPdf($bundle);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->documentFilename($bundle['document'], 'pdf').'"',
        ]);
    }

    public function downloadDocumentXml(string $id): \Illuminate\Http\Response
    {
        $bundle = $this->documentBundle($id);
        $xml = $this->documentXml($bundle);

        DB::table('documents')->where('id', $id)->update([
            'xml_generated_at' => now(),
            'updated_at' => now(),
        ]);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->documentFilename($bundle['document'], 'xml').'"',
        ]);
    }

    public function sendDocumentEmail(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManageBilling($request);
        $payload = $request->validate([
            'recipient' => ['required', 'email', 'max:255'],
            'cc' => ['nullable', 'string', 'max:500'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'include_xml' => ['boolean'],
        ]);
        $bundle = $this->documentBundle($id);
        $document = $bundle['document'];
        $client = $bundle['client'];
        $documentNumber = $document->number ?: 'bozza';
        $subject = $payload['subject'] ?: $this->documentTypeLabel($document->doc_type).' '.$documentNumber;
        $body = nl2br(e($payload['message'] ?: "Buongiorno,\n\nin allegato trova il documento richiesto.\n\nCordiali saluti."));
        $pdf = $this->documentPdf($bundle);
        $includeXml = $request->boolean('include_xml') && in_array($document->doc_type, ['fattura', 'nota_credito'], true);

        try {
            Mail::html($body, function ($message) use ($payload, $subject, $pdf, $bundle, $includeXml) {
                $message->to($payload['recipient'])->subject($subject);
                if (! empty($payload['cc'])) {
                    $message->cc(array_map('trim', explode(',', $payload['cc'])));
                }
                $message->attachData($pdf, $this->documentFilename($bundle['document'], 'pdf'), [
                    'mime' => 'application/pdf',
                ]);
                if ($includeXml) {
                    $message->attachData($this->documentXml($bundle), $this->documentFilename($bundle['document'], 'xml'), [
                        'mime' => 'application/xml',
                    ]);
                }
            });

            DB::table('document_emails')->insert([
                'id' => (string) str()->uuid(),
                'document_id' => $id,
                'sent_by' => $request->user()->id,
                'channel' => 'smtp',
                'recipient' => $payload['recipient'],
                'cc' => $payload['cc'] ?? null,
                'subject' => $subject,
                'status' => 'sent',
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            DB::table('document_emails')->insert([
                'id' => (string) str()->uuid(),
                'document_id' => $id,
                'sent_by' => $request->user()->id,
                'channel' => 'smtp',
                'recipient' => $payload['recipient'],
                'cc' => $payload['cc'] ?? null,
                'subject' => $subject,
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return back()->with('status', 'Invio non riuscito: '.$exception->getMessage());
        }

        return back()->with('status', 'Email inviata.');
    }

    private function copyDocument(string $id, ?string $docType, string $userId): string
    {
        $document = DB::table('documents')->where('id', $id)->first();
        abort_if(! $document, 404);

        $newId = (string) str()->uuid();
        $targetType = $docType ?: $document->doc_type;
        $sign = $targetType === 'nota_credito' ? -1 : 1;

        DB::transaction(function () use ($document, $newId, $targetType, $sign, $userId) {
            $data = (array) $document;
            unset($data['id']);
            $data['doc_type'] = $targetType;
            $data['status'] = 'draft';
            $data['number'] = null;
            $data['seq'] = null;
            $data['issue_date'] = now()->toDateString();
            $data['due_date'] = null;
            $data['parent_document_id'] = $document->id;
            $data['year'] = now()->year;
            $data['created_by'] = $userId;
            $data['created_at'] = now();
            $data['updated_at'] = now();

            DB::table('documents')->insert(['id' => $newId, ...$data]);

            $lines = DB::table('document_lines')->where('document_id', $document->id)->orderBy('position')->get();
            foreach ($lines as $line) {
                $lineData = (array) $line;
                unset($lineData['id']);
                $lineData['document_id'] = $newId;
                $lineData['unit_price'] = ((float) $line->unit_price) * $sign;
                $lineData['subtotal'] = ((float) $line->subtotal) * $sign;
                $lineData['created_at'] = now();
                $lineData['updated_at'] = now();
                DB::table('document_lines')->insert(['id' => (string) str()->uuid(), ...$lineData]);
            }
        });

        $this->recalculateDocument($newId);

        return $newId;
    }

    private function documentBundle(string $id): array
    {
        $document = DB::table('documents')->where('id', $id)->first();
        abort_if(! $document, 404);

        return [
            'document' => $document,
            'client' => DB::table('clients')->where('id', $document->client_id)->first(),
            'settings' => DB::table('document_settings')->first(),
            'lines' => DB::table('document_lines')->where('document_id', $id)->orderBy('position')->get(),
            'payments' => DB::table('document_payments')->where('document_id', $id)->orderByDesc('paid_at')->get(),
        ];
    }

    private function documentPdf(array $bundle): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $html = view('documents.pdf', [
            ...$bundle,
            'typeLabel' => $this->documentTypeLabel($bundle['document']->doc_type),
        ])->render();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    private function documentXml(array $bundle): string
    {
        $document = $bundle['document'];
        $client = $bundle['client'];
        $settings = $bundle['settings'];
        $lines = $bundle['lines'];
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElement('p:FatturaElettronica');
        $root->setAttribute('versione', 'FPR12');
        $root->setAttribute('xmlns:p', 'http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2');
        $root->setAttribute('xmlns:ds', 'http://www.w3.org/2000/09/xmldsig#');
        $root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $doc->appendChild($root);

        $header = $root->appendChild($doc->createElement('FatturaElettronicaHeader'));
        $transmission = $header->appendChild($doc->createElement('DatiTrasmissione'));
        $idTrasmittente = $transmission->appendChild($doc->createElement('IdTrasmittente'));
        $idTrasmittente->appendChild($doc->createElement('IdPaese', 'IT'));
        $idTrasmittente->appendChild($doc->createElement('IdCodice', preg_replace('/\D+/', '', (string) ($settings->vat_number ?? '00000000000')) ?: '00000000000'));
        $transmission->appendChild($doc->createElement('ProgressivoInvio', str_pad((string) ($document->seq ?: 1), 5, '0', STR_PAD_LEFT)));
        $transmission->appendChild($doc->createElement('FormatoTrasmissione', 'FPR12'));
        $transmission->appendChild($doc->createElement('CodiceDestinatario', $client->sdi_code ?: '0000000'));

        $supplier = $header->appendChild($doc->createElement('CedentePrestatore'));
        $supplierData = $supplier->appendChild($doc->createElement('DatiAnagrafici'));
        $supplierVat = $supplierData->appendChild($doc->createElement('IdFiscaleIVA'));
        $supplierVat->appendChild($doc->createElement('IdPaese', 'IT'));
        $supplierVat->appendChild($doc->createElement('IdCodice', preg_replace('/\D+/', '', (string) ($settings->vat_number ?? '00000000000')) ?: '00000000000'));
        $supplierData->appendChild($doc->createElement('Anagrafica'))->appendChild($doc->createElement('Denominazione', $settings->company_name ?? config('app.name')));
        $supplierData->appendChild($doc->createElement('RegimeFiscale', $settings->tax_regime ?: 'RF01'));
        $supplierAddress = $supplier->appendChild($doc->createElement('Sede'));
        $supplierAddress->appendChild($doc->createElement('Indirizzo', $settings->street ?: '-'));
        $supplierAddress->appendChild($doc->createElement('CAP', $settings->postal_code ?: '00000'));
        $supplierAddress->appendChild($doc->createElement('Comune', $settings->city ?: '-'));
        $supplierAddress->appendChild($doc->createElement('Provincia', $settings->province ?: 'NA'));
        $supplierAddress->appendChild($doc->createElement('Nazione', $settings->country ?: 'IT'));

        $customer = $header->appendChild($doc->createElement('CessionarioCommittente'));
        $customerData = $customer->appendChild($doc->createElement('DatiAnagrafici'));
        if ($client->vat_number) {
            $customerVat = $customerData->appendChild($doc->createElement('IdFiscaleIVA'));
            $customerVat->appendChild($doc->createElement('IdPaese', 'IT'));
            $customerVat->appendChild($doc->createElement('IdCodice', preg_replace('/\D+/', '', (string) $client->vat_number)));
        }
        if ($client->tax_code) {
            $customerData->appendChild($doc->createElement('CodiceFiscale', $client->tax_code));
        }
        $customerData->appendChild($doc->createElement('Anagrafica'))->appendChild($doc->createElement('Denominazione', $client->legal_name ?: $client->name));
        $customerAddress = $customer->appendChild($doc->createElement('Sede'));
        $customerAddress->appendChild($doc->createElement('Indirizzo', trim(($client->street ?: $client->address ?: '-').' '.($client->street_number ?: ''))));
        $customerAddress->appendChild($doc->createElement('CAP', $client->postal_code ?: '00000'));
        $customerAddress->appendChild($doc->createElement('Comune', $client->city ?: '-'));
        $customerAddress->appendChild($doc->createElement('Provincia', $client->province ?: 'NA'));
        $customerAddress->appendChild($doc->createElement('Nazione', $client->country ?: 'IT'));

        $body = $root->appendChild($doc->createElement('FatturaElettronicaBody'));
        $general = $body->appendChild($doc->createElement('DatiGenerali'))->appendChild($doc->createElement('DatiGeneraliDocumento'));
        $general->appendChild($doc->createElement('TipoDocumento', $document->doc_type === 'nota_credito' ? 'TD04' : 'TD01'));
        $general->appendChild($doc->createElement('Divisa', $document->currency ?: 'EUR'));
        $general->appendChild($doc->createElement('Data', $document->issue_date));
        $general->appendChild($doc->createElement('Numero', $document->number ?: 'BOZZA-'.$document->id));
        $general->appendChild($doc->createElement('ImportoTotaleDocumento', number_format((float) $document->total_amount, 2, '.', '')));

        $goods = $body->appendChild($doc->createElement('DatiBeniServizi'));
        foreach ($lines as $index => $line) {
            $detail = $goods->appendChild($doc->createElement('DettaglioLinee'));
            $detail->appendChild($doc->createElement('NumeroLinea', (string) ($index + 1)));
            $detail->appendChild($doc->createElement('Descrizione', $line->description));
            $detail->appendChild($doc->createElement('Quantita', number_format((float) $line->quantity, 2, '.', '')));
            $detail->appendChild($doc->createElement('PrezzoUnitario', number_format((float) $line->unit_price, 2, '.', '')));
            $detail->appendChild($doc->createElement('PrezzoTotale', number_format((float) $line->subtotal, 2, '.', '')));
            $detail->appendChild($doc->createElement('AliquotaIVA', number_format((float) $line->vat_rate, 2, '.', '')));
            if ((float) $line->vat_rate === 0.0 && $line->vat_nature_code) {
                $detail->appendChild($doc->createElement('Natura', $line->vat_nature_code));
            }
        }

        $summary = $goods->appendChild($doc->createElement('DatiRiepilogo'));
        $summary->appendChild($doc->createElement('AliquotaIVA', '22.00'));
        $summary->appendChild($doc->createElement('ImponibileImporto', number_format((float) $document->total_taxable, 2, '.', '')));
        $summary->appendChild($doc->createElement('Imposta', number_format((float) $document->total_vat, 2, '.', '')));

        return $doc->saveXML();
    }

    private function documentFilename(object $document, string $extension): string
    {
        $number = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($document->number ?: 'bozza-'.$document->id));

        return strtolower($document->doc_type.'-'.$number.'.'.$extension);
    }

    private function documentTypeLabel(string $type): string
    {
        return [
            'preventivo' => 'Preventivo',
            'proforma' => 'Proforma',
            'fattura' => 'Fattura',
            'nota_credito' => 'Nota credito',
        ][$type] ?? $type;
    }

    private function normalizeDocumentPayload(array $payload, string $userId, bool $withCreator = true): array
    {
        $amount = (float) ($payload['total_amount'] ?? 0);
        $payload['currency'] = 'EUR';
        $payload['total_taxable'] = $amount;
        $payload['total_amount'] = $amount;
        $payload['total_discount'] = 0;
        $payload['total_vat'] = 0;
        $payload['total_pension_fund'] = 0;
        $payload['total_withholding'] = 0;
        $payload['total_paid'] = 0;
        $payload['apply_bollo'] = false;
        $payload['year'] = $payload['issue_date'] ? (int) substr($payload['issue_date'], 0, 4) : now()->year;

        if ($withCreator) {
            $payload['created_by'] = $userId;
        }

        return $payload;
    }

    public function storeTaskComment(Request $request, string $id): RedirectResponse
    {
        $this->ensureGuestCanEditTask($request, $id);

        $task = DB::table('tasks')->where('id', $id)->first();
        abort_if(! $task, 404);

        $payload = $request->validate([
            'content' => ['required', 'string'],
        ]);

        DB::table('task_comments')->insert([
            'id' => (string) str()->uuid(),
            'task_id' => $id,
            'user_id' => $request->user()->id,
            'content' => $payload['content'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recordTaskActivity($id, $request->user()->id, 'comment_created', 'content', null, $payload['content']);

        $this->notifyTaskPeople(
            $id,
            $request->user()->id,
            'task_comment',
            $request->user()->name.' ha commentato il task "'.$task->title.'".',
        );

        return back()->with('status', 'Commento aggiunto.');
    }

    public function updateTaskComment(Request $request, string $taskId, string $commentId): RedirectResponse
    {
        $this->ensureGuestCanEditTask($request, $taskId);

        $comment = DB::table('task_comments')
            ->where('task_id', $taskId)
            ->where('id', $commentId)
            ->first();
        abort_if(! $comment, 404);

        $payload = $request->validate([
            'content' => ['required', 'string'],
        ]);

        DB::table('task_comments')
            ->where('task_id', $taskId)
            ->where('id', $commentId)
            ->update([
                'content' => $payload['content'],
                'updated_at' => now(),
            ]);

        DB::table('task_activity')->insert([
            'id' => (string) str()->uuid(),
            'task_id' => $taskId,
            'user_id' => $request->user()->id,
            'action' => 'comment_updated',
            'field' => 'content',
            'old_value' => $comment->content,
            'new_value' => $payload['content'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Commento aggiornato.');
    }

    public function destroyTaskComment(Request $request, string $taskId, string $commentId): RedirectResponse
    {
        abort_if($this->isGuest($request), 403);

        $comment = DB::table('task_comments')
            ->where('task_id', $taskId)
            ->where('id', $commentId)
            ->first();
        abort_if(! $comment, 404);

        DB::table('task_comments')
            ->where('task_id', $taskId)
            ->where('id', $commentId)
            ->delete();

        DB::table('task_activity')->insert([
            'id' => (string) str()->uuid(),
            'task_id' => $taskId,
            'user_id' => $request->user()->id,
            'action' => 'comment_deleted',
            'field' => 'content',
            'old_value' => $comment->content,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Commento eliminato.');
    }

    public function storeSubtask(Request $request, string $id): RedirectResponse
    {
        $this->ensureGuestCanEditTask($request, $id);

        $task = DB::table('tasks')->where('id', $id)->first();
        abort_if(! $task, 404);

        if ($task->parent_task_id) {
            return back()->withErrors(['subtasks' => 'Le sottoattività non possono avere ulteriori sottoattività.']);
        }

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'due_date' => ['nullable', 'date'],
            'assignee_ids' => ['array'],
            'assignee_ids.*' => ['uuid', 'exists:users,id'],
        ]);

        foreach ($payload as $key => $value) {
            if ($value === '') {
                $payload[$key] = null;
            }
        }

        $subtaskId = (string) str()->uuid();
        $position = DB::table('tasks')->where('parent_task_id', $id)->count();

        DB::table('tasks')->insert([
            'id' => $subtaskId,
            'title' => $payload['title'],
            'priority' => $payload['priority'] ?? 'medium',
            'due_date' => $payload['due_date'] ?? null,
            'task_type' => $task->task_type === 'meeting' ? 'ongoing' : ($task->task_type ?: 'task'),
            'status' => 'todo',
            'project_id' => $task->project_id,
            'client_id' => $task->client_id,
            'service_id' => $task->service_id,
            'parent_task_id' => $id,
            'position' => $position,
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (array_unique($payload['assignee_ids'] ?? []) as $userId) {
            DB::table('task_assignees')->insert([
                'id' => (string) str()->uuid(),
                'task_id' => $subtaskId,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->recordTaskActivity($id, $request->user()->id, 'subtask_created', 'title', null, $payload['title']);
        $this->recordTaskActivity($subtaskId, $request->user()->id, 'task_created', 'title', null, $payload['title']);

        $this->notifyTaskPeople(
            $id,
            $request->user()->id,
            'subtask_created',
            $request->user()->name.' ha creato una sottoattività in "'.$task->title.'".',
        );

        return back()->with('status', 'Sottoattività creata.');
    }

    public function reorderSubtasks(Request $request, string $id): RedirectResponse
    {
        $this->ensureGuestCanEditTask($request, $id);

        DB::table('tasks')->where('id', $id)->whereNull('parent_task_id')->exists() || abort(404);

        $payload = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['uuid'],
        ]);

        $validIds = DB::table('tasks')
            ->where('parent_task_id', $id)
            ->whereIn('id', $payload['ids'])
            ->pluck('id')
            ->all();

        foreach (array_values($payload['ids']) as $position => $subtaskId) {
            if (! in_array($subtaskId, $validIds, true)) {
                continue;
            }

            DB::table('tasks')
                ->where('id', $subtaskId)
                ->where('parent_task_id', $id)
                ->update([
                    'position' => $position,
                    'updated_at' => now(),
                ]);
        }

        return back()->with('status', 'Ordine sottoattività aggiornato.');
    }

    public function syncTaskPeople(Request $request, string $id, string $type): RedirectResponse
    {
        abort_if($this->isGuest($request), 403);

        DB::table('tasks')->where('id', $id)->exists() || abort(404);
        abort_unless(in_array($type, ['assignees', 'followers'], true), 404);

        $payload = $request->validate([
            'user_ids' => ['array'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
        ]);

        $table = $type === 'assignees' ? 'task_assignees' : 'task_followers';
        $oldUserIds = DB::table($table)->where('task_id', $id)->pluck('user_id')->sort()->values()->all();
        DB::table($table)->where('task_id', $id)->delete();

        foreach (array_unique($payload['user_ids'] ?? []) as $userId) {
            DB::table($table)->insert([
                'id' => (string) str()->uuid(),
                'task_id' => $id,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $newUserIds = collect($payload['user_ids'] ?? [])->unique()->sort()->values()->all();
        if ($oldUserIds !== $newUserIds) {
            $this->recordTaskActivity(
                $id,
                $request->user()->id,
                'people_updated',
                $type === 'assignees' ? 'assignee_ids' : 'follower_ids',
                implode(',', $oldUserIds),
                implode(',', $newUserIds),
            );

            $task = DB::table('tasks')->where('id', $id)->first(['title']);
            $this->notifyTaskPeople(
                $id,
                $request->user()->id,
                'task_people',
                $request->user()->name.' ha aggiornato '.($type === 'assignees' ? 'gli assegnatari' : 'i follower').' di "'.($task->title ?? 'task').'".',
            );
        }

        return back()->with('status', $type === 'assignees' ? 'Assegnatari aggiornati.' : 'Follower aggiornati.');
    }

    public function syncTaskDependencies(Request $request, string $id): RedirectResponse
    {
        abort_if($this->isGuest($request), 403);

        $task = DB::table('tasks')->where('id', $id)->first(['id', 'title']);
        abort_if(! $task, 404);

        $payload = $request->validate([
            'dependency_ids' => ['array'],
            'dependency_ids.*' => ['uuid', 'exists:tasks,id'],
            'dependent_ids' => ['array'],
            'dependent_ids.*' => ['uuid', 'exists:tasks,id'],
        ]);
        $syncDependents = $request->has('dependent_ids');

        $dependencyIds = collect($payload['dependency_ids'] ?? [])
            ->filter(fn ($dependencyId) => $dependencyId !== $id)
            ->unique()
            ->values();
        $dependentIds = collect($payload['dependent_ids'] ?? [])
            ->filter(fn ($dependentId) => $dependentId !== $id)
            ->unique()
            ->values();

        if ($syncDependents && $dependencyIds->intersect($dependentIds)->isNotEmpty()) {
            return back()->withErrors(['dependencies' => 'Una task non può essere sia bloccante sia bloccata dalla stessa task.']);
        }

        $cyclicDependency = $dependencyIds->first(fn ($dependencyId) => $this->taskDependsOn($dependencyId, $id));
        if ($cyclicDependency) {
            return back()->withErrors(['dependencies' => 'Questa dipendenza creerebbe un ciclo tra task.']);
        }

        if ($syncDependents) {
            $cyclicDependent = $dependentIds->first(fn ($dependentId) => $this->taskDependsOn($id, $dependentId));
            if ($cyclicDependent) {
                return back()->withErrors(['dependencies' => 'Questa relazione creerebbe un ciclo tra task.']);
            }
        }

        $oldDependencyIds = DB::table('task_dependencies')
            ->where('task_id', $id)
            ->pluck('depends_on_task_id')
            ->sort()
            ->values()
            ->all();
        $oldDependentIds = $syncDependents
            ? DB::table('task_dependencies')
                ->where('depends_on_task_id', $id)
                ->pluck('task_id')
                ->sort()
                ->values()
                ->all()
            : [];

        $this->syncTaskDependencyEdges($id, $dependencyIds->all(), $dependentIds->all(), $syncDependents);

        $newDependencyIds = $dependencyIds->sort()->values()->all();
        $newDependentIds = $syncDependents ? $dependentIds->sort()->values()->all() : [];
        if ($oldDependencyIds !== $newDependencyIds || $oldDependentIds !== $newDependentIds) {
            $this->recordTaskActivity(
                $id,
                $request->user()->id,
                'dependencies_updated',
                'dependencies',
                implode(',', [...$oldDependencyIds, ...array_map(fn ($taskId) => 'blocks:'.$taskId, $oldDependentIds)]),
                implode(',', [...$newDependencyIds, ...array_map(fn ($taskId) => 'blocks:'.$taskId, $newDependentIds)]),
            );

            $this->notifyTaskPeople(
                $id,
                $request->user()->id,
                'task_dependencies',
                $request->user()->name.' ha aggiornato le dipendenze di "'.$task->title.'".',
            );
        }

        return back()->with('status', 'Dipendenze aggiornate.');
    }

    private function syncTaskDependencyEdges(string $taskId, array $dependencyIds, array $dependentIds = [], bool $syncDependents = true): void
    {
        DB::transaction(function () use ($taskId, $dependencyIds, $dependentIds, $syncDependents) {
            DB::table('task_dependencies')->where('task_id', $taskId)->delete();
            if ($syncDependents) {
                DB::table('task_dependencies')->where('depends_on_task_id', $taskId)->delete();
            }

            foreach ($dependencyIds as $dependencyId) {
                DB::table('task_dependencies')->insert([
                    'id' => (string) str()->uuid(),
                    'task_id' => $taskId,
                    'depends_on_task_id' => $dependencyId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (! $syncDependents) {
                return;
            }

            foreach ($dependentIds as $dependentId) {
                DB::table('task_dependencies')->insert([
                    'id' => (string) str()->uuid(),
                    'task_id' => $dependentId,
                    'depends_on_task_id' => $taskId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function storeClientContact(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManageClients($request);

        DB::table('clients')->where('id', $id)->exists() || abort(404);

        $payload = $this->validatedClientContactPayload($request);

        DB::table('client_contacts')->insert([
            ...$payload,
            'id' => (string) str()->uuid(),
            'client_id' => $id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Referente aggiunto.');
    }

    public function updateClientContact(Request $request, string $clientId, string $contactId): RedirectResponse
    {
        $this->ensureCanManageClients($request);

        DB::table('client_contacts')->where('client_id', $clientId)->where('id', $contactId)->exists() || abort(404);

        DB::table('client_contacts')
            ->where('client_id', $clientId)
            ->where('id', $contactId)
            ->update([
                ...$this->validatedClientContactPayload($request),
                'updated_at' => now(),
            ]);

        return back()->with('status', 'Referente aggiornato.');
    }

    public function destroyClientContact(Request $request, string $clientId, string $contactId): RedirectResponse
    {
        $this->ensureCanManageClients($request);

        DB::table('client_contacts')
            ->where('client_id', $clientId)
            ->where('id', $contactId)
            ->delete();

        return back()->with('status', 'Referente eliminato.');
    }

    public function attachClientService(Request $request, string $clientId, string $serviceId): RedirectResponse
    {
        $this->ensureCanManageClients($request);

        DB::table('clients')->where('id', $clientId)->exists() || abort(404);
        DB::table('services')->where('id', $serviceId)->exists() || abort(404);

        DB::table('client_services')->insertOrIgnore([
            'id' => (string) str()->uuid(),
            'client_id' => $clientId,
            'service_id' => $serviceId,
        ]);

        return back()->with('status', 'Servizio collegato.');
    }

    public function detachClientService(Request $request, string $clientId, string $serviceId): RedirectResponse
    {
        $this->ensureCanManageClients($request);

        DB::table('client_services')
            ->where('client_id', $clientId)
            ->where('service_id', $serviceId)
            ->delete();

        return back()->with('status', 'Servizio scollegato.');
    }

    public function updateTaskStatus(Request $request, string $id): RedirectResponse
    {
        $this->ensureGuestCanEditTask($request, $id);

        $task = DB::table('tasks')->where('id', $id)->first();
        abort_if(! $task, 404);

        $payload = $request->validate([
            'status' => ['required', Rule::in(['todo', 'in_progress', 'in_review', 'done'])],
        ]);

        if ($payload['status'] === 'done') {
            if ($this->taskOpenDependencyCount($id) > 0) {
                return back()->withErrors(['status' => 'Questa task è bloccata: completa prima le dipendenze.']);
            }
        }

        DB::transaction(function () use ($id, $task, $payload, $request) {
            DB::table('tasks')->where('id', $id)->update([
                'status' => $payload['status'],
                'updated_at' => now(),
            ]);

            if ($payload['status'] === 'done') {
                DB::table('tasks')
                    ->where('parent_task_id', $id)
                    ->update(['status' => 'done', 'updated_at' => now()]);

                if (! $task->parent_task_id && $task->recurring_enabled && $task->status !== 'done') {
                    $this->createNextRecurringTask($task, $request->user()->id);
                }
            }
        });

        if ($task->status !== $payload['status']) {
            $this->recordTaskActivity($id, $request->user()->id, 'task_updated', 'status', $task->status, $payload['status']);
        }

        $this->notifyTaskPeople(
            $id,
            $request->user()->id,
            'task_status',
            $request->user()->name.' ha impostato "'.$task->title.'" su '.$payload['status'].'.',
        );

        return back()->with('status', 'Stato task aggiornato.');
    }

    private function purgeExpiredArchivedNotifications(?string $userId = null): void
    {
        DB::table('notifications')
            ->whereNotNull('archived_at')
            ->where('archived_at', '<', now()->subDays(30))
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->delete();
    }

    private function archiveExpiredNotifications(?string $userId = null): void
    {
        DB::table('notifications')
            ->whereNull('archived_at')
            ->where('created_at', '<', now()->subDays(30))
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->update(['archived_at' => now(), 'read' => true, 'updated_at' => now()]);
    }

    public function duplicateTask(Request $request, string $id): RedirectResponse
    {
        abort_if($this->isGuest($request), 403);

        $task = DB::table('tasks')->where('id', $id)->first();
        abort_if(! $task, 404);

        $newTaskId = (string) str()->uuid();

        DB::transaction(function () use ($task, $newTaskId, $request) {
            DB::table('tasks')->insert([
                'id' => $newTaskId,
                'title' => $task->title.' (copia)',
                'description' => $task->description,
                'project_id' => $task->project_id,
                'client_id' => $task->client_id,
                'service_id' => $task->service_id,
                'parent_task_id' => $task->parent_task_id,
                'start_date' => $task->start_date,
                'due_date' => $task->due_date,
                'due_time' => $task->due_time,
                'location' => $task->location,
                'priority' => $task->priority,
                'status' => 'todo',
                'task_type' => $task->task_type,
                'recurring_enabled' => $task->recurring_enabled,
                'recurring_mode' => $task->recurring_mode,
                'recurring_interval_value' => $task->recurring_interval_value,
                'recurring_interval_unit' => $task->recurring_interval_unit,
                'recurring_weekday' => $task->recurring_weekday,
                'recurring_month_day' => $task->recurring_month_day,
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (['task_assignees', 'task_followers'] as $table) {
                foreach (DB::table($table)->where('task_id', $task->id)->pluck('user_id') as $userId) {
                    DB::table($table)->insert([
                        'id' => (string) str()->uuid(),
                        'task_id' => $newTaskId,
                        'user_id' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            foreach (DB::table('tasks')->where('parent_task_id', $task->id)->get() as $subtask) {
                DB::table('tasks')->insert([
                    'id' => (string) str()->uuid(),
                    'title' => $subtask->title,
                    'description' => $subtask->description,
                    'project_id' => $subtask->project_id,
                    'client_id' => $subtask->client_id,
                    'service_id' => $subtask->service_id,
                    'parent_task_id' => $newTaskId,
                    'start_date' => $subtask->start_date,
                    'due_date' => $subtask->due_date,
                    'due_time' => $subtask->due_time,
                    'location' => $subtask->location,
                    'priority' => $subtask->priority,
                    'status' => 'todo',
                    'task_type' => $subtask->task_type,
                    'recurring_enabled' => false,
                    'created_by' => $request->user()->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('tasks.show', $newTaskId)->with('status', 'Task duplicata.');
    }

    public function updateTaskSchedule(Request $request, string $id): RedirectResponse
    {
        $this->ensureGuestCanEditTask($request, $id);

        $task = DB::table('tasks')->where('id', $id)->first();
        abort_if(! $task, 404);

        $payload = $request->validate([
            'due_date' => ['required', 'date'],
            'start_date' => ['nullable', 'date'],
        ]);

        DB::table('tasks')->where('id', $id)->update([
            'due_date' => $payload['due_date'],
            'start_date' => $payload['start_date'] ?? null,
            'updated_at' => now(),
        ]);

        $this->recordTaskFieldChanges($id, $request->user()->id, $task, [
            'due_date' => $payload['due_date'],
            'start_date' => $payload['start_date'] ?? null,
        ]);

        $this->notifyTaskPeople(
            $id,
            $request->user()->id,
            'task_schedule',
            $request->user()->name.' ha spostato "'.$task->title.'" al '.$payload['due_date'].'.',
        );

        return back()->with('status', 'Scadenza task aggiornata.');
    }

    private function createNextRecurringTask(object $task, string $userId): string
    {
        $nextDueDate = $this->nextRecurringTaskDate($task);
        $nextStartDate = null;

        if ($task->start_date && $task->due_date && $task->start_date !== $task->due_date) {
            $duration = \Carbon\Carbon::parse($task->start_date)->diffInDays(\Carbon\Carbon::parse($task->due_date));
            $nextStartDate = \Carbon\Carbon::parse($nextDueDate)->subDays($duration)->toDateString();
        }

        $newTaskId = (string) str()->uuid();
        DB::table('tasks')->insert([
            'id' => $newTaskId,
            'title' => $task->title,
            'description' => $task->description,
            'project_id' => $task->project_id,
            'client_id' => $task->client_id,
            'service_id' => $task->service_id,
            'parent_task_id' => null,
            'start_date' => $nextStartDate,
            'due_date' => $nextDueDate,
            'due_time' => $task->due_time,
            'location' => $task->location,
            'priority' => $task->priority,
            'status' => 'todo',
            'task_type' => $task->task_type,
            'recurring_enabled' => $task->recurring_enabled,
            'recurring_mode' => $task->recurring_mode,
            'recurring_interval_value' => $task->recurring_interval_value,
            'recurring_interval_unit' => $task->recurring_interval_unit,
            'recurring_weekday' => $task->recurring_weekday,
            'recurring_month_day' => $task->recurring_month_day,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['task_assignees', 'task_followers'] as $table) {
            $rows = DB::table($table)->where('task_id', $task->id)->pluck('user_id');
            foreach ($rows as $userIdForRelation) {
                DB::table($table)->insert([
                    'id' => (string) str()->uuid(),
                    'task_id' => $newTaskId,
                    'user_id' => $userIdForRelation,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $subtasks = DB::table('tasks')->where('parent_task_id', $task->id)->get();
        foreach ($subtasks as $subtask) {
            DB::table('tasks')->insert([
                'id' => (string) str()->uuid(),
                'title' => $subtask->title,
                'description' => $subtask->description,
                'project_id' => $task->project_id,
                'client_id' => $task->client_id,
                'service_id' => $task->service_id,
                'parent_task_id' => $newTaskId,
                'start_date' => null,
                'due_date' => null,
                'due_time' => $subtask->due_time,
                'location' => $subtask->location,
                'priority' => $subtask->priority,
                'status' => 'todo',
                'task_type' => $subtask->task_type,
                'recurring_enabled' => false,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $newTaskId;
    }

    private function nextRecurringTaskDate(object $task): string
    {
        $base = \Carbon\Carbon::parse($task->due_date ?: now()->toDateString());
        $interval = max(1, (int) ($task->recurring_interval_value ?: 1));

        if ($task->recurring_interval_unit === 'month') {
            $next = $base->copy()->addMonths($interval);
            if ($task->recurring_mode === 'fixed' && $task->recurring_month_day) {
                $maxDay = $next->copy()->endOfMonth()->day;
                $next->day(min((int) $task->recurring_month_day, $maxDay));
            }

            return $next->toDateString();
        }

        $next = $base->copy()->addWeeks($interval);
        if ($task->recurring_weekday) {
            $currentWeekday = $next->dayOfWeekIso;
            $next->addDays(((int) $task->recurring_weekday) - $currentWeekday);
        }

        return $next->toDateString();
    }

    public function markNotificationRead(Request $request, string $id): RedirectResponse
    {
        DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->whereNull('archived_at')
            ->update(['read' => true, 'updated_at' => now()]);

        return back()->with('status', 'Notifica letta.');
    }

    public function markAllNotificationsRead(Request $request): RedirectResponse
    {
        DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->whereNull('archived_at')
            ->where('read', false)
            ->update(['read' => true, 'updated_at' => now()]);

        return back()->with('status', 'Notifiche segnate come lette.');
    }

    public function archiveNotification(Request $request, string $id): RedirectResponse
    {
        DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->whereNull('archived_at')
            ->update(['archived_at' => now(), 'read' => true, 'updated_at' => now()]);

        return back()->with('status', 'Notifica archiviata.');
    }

    public function archiveAllNotifications(Request $request): RedirectResponse
    {
        DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->whereNull('archived_at')
            ->update(['archived_at' => now(), 'read' => true, 'updated_at' => now()]);

        return back()->with('status', 'Notifiche archiviate.');
    }

    public function restoreNotification(Request $request, string $id): RedirectResponse
    {
        DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->whereNotNull('archived_at')
            ->update(['archived_at' => null, 'updated_at' => now()]);

        return back()->with('status', 'Notifica ripristinata.');
    }

    public function storePushSubscription(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string'],
        ]);

        $existingId = DB::table('push_subscriptions')->where('endpoint', $payload['endpoint'])->value('id');

        DB::table('push_subscriptions')->updateOrInsert(
            ['endpoint' => $payload['endpoint']],
            [
                'id' => $existingId ?: (string) str()->uuid(),
                'user_id' => $request->user()->id,
                'public_key' => $payload['keys']['p256dh'],
                'auth_token' => $payload['keys']['auth'],
                'content_encoding' => $payload['contentEncoding'] ?? 'aes128gcm',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return response()->json(['status' => 'ok']);
    }

    public function storeDocumentLine(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManageBilling($request);
        DB::table('documents')->where('id', $id)->exists() || abort(404);

        [$payload, $subtotal] = $this->validatedDocumentLinePayload($request);

        DB::table('document_lines')->insert([
            'id' => (string) str()->uuid(),
            'document_id' => $id,
            'position' => DB::table('document_lines')->where('document_id', $id)->count(),
            ...$payload,
            'subtotal' => $subtotal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recalculateDocument($id);

        return back()->with('status', 'Riga aggiunta.');
    }

    public function updateDocumentLine(Request $request, string $documentId, string $lineId): RedirectResponse
    {
        $this->ensureCanManageBilling($request);
        DB::table('document_lines')->where('document_id', $documentId)->where('id', $lineId)->exists() || abort(404);

        [$payload, $subtotal] = $this->validatedDocumentLinePayload($request);

        DB::table('document_lines')
            ->where('document_id', $documentId)
            ->where('id', $lineId)
            ->update([
                ...$payload,
                'subtotal' => $subtotal,
                'updated_at' => now(),
            ]);

        $this->recalculateDocument($documentId);

        return back()->with('status', 'Riga aggiornata.');
    }

    public function destroyDocumentLine(Request $request, string $documentId, string $lineId): RedirectResponse
    {
        $this->ensureCanManageBilling($request);
        DB::table('document_lines')->where('document_id', $documentId)->where('id', $lineId)->delete();
        $this->recalculateDocument($documentId);

        return back()->with('status', 'Riga eliminata.');
    }

    public function storeDocumentPayment(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManageBilling($request);
        DB::table('documents')->where('id', $id)->exists() || abort(404);

        $payload = $this->validatedDocumentPaymentPayload($request);

        DB::table('document_payments')->insert([
            'id' => (string) str()->uuid(),
            'document_id' => $id,
            ...$payload,
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recalculateDocument($id);

        return back()->with('status', 'Pagamento registrato.');
    }

    public function updateDocumentPayment(Request $request, string $documentId, string $paymentId): RedirectResponse
    {
        $this->ensureCanManageBilling($request);
        DB::table('document_payments')->where('document_id', $documentId)->where('id', $paymentId)->exists() || abort(404);

        $payload = $this->validatedDocumentPaymentPayload($request);

        DB::table('document_payments')
            ->where('document_id', $documentId)
            ->where('id', $paymentId)
            ->update([
                ...$payload,
                'updated_at' => now(),
            ]);

        $this->recalculateDocument($documentId);

        return back()->with('status', 'Pagamento aggiornato.');
    }

    public function destroyDocumentPayment(Request $request, string $documentId, string $paymentId): RedirectResponse
    {
        $this->ensureCanManageBilling($request);
        DB::table('document_payments')->where('document_id', $documentId)->where('id', $paymentId)->delete();
        $this->recalculateDocument($documentId);

        return back()->with('status', 'Pagamento eliminato.');
    }

    public function storeSubscription(Request $request, string $clientId): RedirectResponse
    {
        $this->ensureCanManageClients($request);
        DB::table('clients')->where('id', $clientId)->exists() || abort(404);
        $payload = $this->validatedSubscriptionPayload($request);
        $payload['id'] = (string) str()->uuid();
        $payload['client_id'] = $clientId;
        $payload['created_by'] = $request->user()->id;
        $payload['created_at'] = now();
        $payload['updated_at'] = now();

        DB::table('subscriptions')->insert($payload);

        return back()->with('status', 'Abbonamento creato.');
    }

    public function updateSubscription(Request $request, string $clientId, string $subscriptionId): RedirectResponse
    {
        $this->ensureCanManageClients($request);
        $this->subscriptionForClient($clientId, $subscriptionId);
        $payload = $this->validatedSubscriptionPayload($request);
        $payload['updated_at'] = now();

        DB::table('subscriptions')->where('id', $subscriptionId)->update($payload);

        return back()->with('status', 'Abbonamento aggiornato.');
    }

    public function toggleSubscription(Request $request, string $clientId, string $subscriptionId): RedirectResponse
    {
        $this->ensureCanManageClients($request);
        $this->subscriptionForClient($clientId, $subscriptionId);

        DB::table('subscriptions')->where('id', $subscriptionId)->update([
            'active' => $request->boolean('active'),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Stato abbonamento aggiornato.');
    }

    public function destroySubscription(Request $request, string $clientId, string $subscriptionId): RedirectResponse
    {
        $this->ensureCanManageClients($request);
        $this->subscriptionForClient($clientId, $subscriptionId);
        DB::table('subscriptions')->where('id', $subscriptionId)->delete();

        return back()->with('status', 'Abbonamento eliminato.');
    }

    public function generateSubscriptionDocument(Request $request, string $clientId, string $subscriptionId): RedirectResponse
    {
        $this->ensureCanManageClients($request);
        $subscription = $this->subscriptionForClient($clientId, $subscriptionId);
        $settings = DB::table('document_settings')->first();
        $issueDate = now()->toDateString();
        $terms = $subscription->payment_terms_days ?? $settings?->default_payment_terms_days ?? 30;
        $documentId = (string) str()->uuid();

        DB::table('documents')->insert([
            'id' => $documentId,
            'client_id' => $clientId,
            'subscription_id' => $subscription->id,
            'doc_type' => 'fattura',
            'status' => 'draft',
            'issue_date' => $issueDate,
            'due_date' => now()->addDays((int) $terms)->toDateString(),
            'currency' => 'EUR',
            'payment_method' => $settings?->default_payment_method ?? null,
            'payment_terms_days' => $terms,
            'causale' => $subscription->name,
            'notes' => $subscription->notes,
            'footer_notes' => $settings?->footer_notes ?? null,
            'withholding_pct' => $settings?->default_withholding_pct ?? 0,
            'pension_fund_pct' => $settings?->default_pension_fund_pct ?? 0,
            'pension_fund_label' => $settings?->default_pension_fund_label ?? null,
            'apply_bollo' => false,
            'total_taxable' => 0,
            'total_discount' => 0,
            'total_vat' => 0,
            'total_pension_fund' => 0,
            'total_withholding' => 0,
            'total_amount' => 0,
            'total_paid' => 0,
            'year' => now()->year,
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('document_lines')->insert([
            'id' => (string) str()->uuid(),
            'document_id' => $documentId,
            'position' => 0,
            'description' => $subscription->description ?: $subscription->name,
            'quantity' => 1,
            'unit_price' => (float) $subscription->amount,
            'discount_pct' => 0,
            'vat_rate' => (float) $subscription->vat_rate,
            'vat_nature_code' => $subscription->vat_nature_code,
            'subtotal' => (float) $subscription->amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recalculateDocument($documentId);

        DB::table('subscriptions')->where('id', $subscriptionId)->update([
            'next_invoice_date' => $this->nextSubscriptionDate($subscription),
            'updated_at' => now(),
        ]);

        return redirect()->route('billing.show', $documentId)->with('status', 'Fattura generata da abbonamento.');
    }

    private function subscriptionForClient(string $clientId, string $subscriptionId): object
    {
        $subscription = DB::table('subscriptions')
            ->where('client_id', $clientId)
            ->where('id', $subscriptionId)
            ->first();

        abort_if(! $subscription, 404);

        return $subscription;
    }

    private function validatedSubscriptionPayload(Request $request): array
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0'],
            'vat_rate' => ['required', 'numeric', 'min:0'],
            'vat_nature_code' => ['nullable', 'string', 'max:16'],
            'frequency_value' => ['required', 'integer', 'min:1'],
            'frequency_unit' => ['required', Rule::in(['month', 'year'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'next_invoice_date' => ['required', 'date'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'auto_generate' => ['boolean'],
            'active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $payload = $this->nullifyEmptyStrings($payload);
        $payload['amount'] = (float) $payload['amount'];
        $payload['vat_rate'] = (float) $payload['vat_rate'];
        $payload['frequency_value'] = (int) $payload['frequency_value'];
        $payload['payment_terms_days'] = $payload['payment_terms_days'] === null ? null : (int) $payload['payment_terms_days'];
        $payload['auto_generate'] = $request->boolean('auto_generate');
        $payload['active'] = $request->boolean('active', true);

        return $payload;
    }

    private function nextSubscriptionDate(object $subscription): string
    {
        $date = \Carbon\Carbon::parse($subscription->next_invoice_date ?: now());
        $frequency = max(1, (int) $subscription->frequency_value);

        return ($subscription->frequency_unit === 'year' ? $date->addYears($frequency) : $date->addMonths($frequency))->toDateString();
    }

    private function validatedDocumentLinePayload(Request $request): array
    {
        $payload = $request->validate([
            'description' => ['required', 'string'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'vat_rate' => ['required', 'numeric', 'min:0'],
            'discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $quantity = (float) $payload['quantity'];
        $unitPrice = (float) $payload['unit_price'];
        $discount = (float) ($payload['discount_pct'] ?? 0);

        return [[
            'description' => $payload['description'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_pct' => $discount,
            'vat_rate' => (float) $payload['vat_rate'],
        ], round($quantity * $unitPrice * (1 - ($discount / 100)), 2)];
    }

    private function validatedDocumentPaymentPayload(Request $request): array
    {
        $payload = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'paid_at' => ['required', 'date'],
            'method' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        return [
            'amount' => (float) $payload['amount'],
            'paid_at' => $payload['paid_at'],
            'method' => $payload['method'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ];
    }

    private function validatedClientContactPayload(Request $request): array
    {
        $payload = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        foreach ($payload as $key => $value) {
            if ($value === '') {
                $payload[$key] = null;
            }
        }

        return $payload;
    }

    private function recalculateDocument(string $id): void
    {
        $document = DB::table('documents')->where('id', $id)->first();
        if (! $document) {
            return;
        }

        $lines = DB::table('document_lines')->where('document_id', $id)->get();
        $taxable = (float) $lines->sum('subtotal');
        $vat = (float) $lines->sum(fn ($line) => ((float) $line->subtotal) * ((float) $line->vat_rate / 100));
        $paid = (float) DB::table('document_payments')->where('document_id', $id)->sum('amount');
        $pensionFund = round($taxable * ((float) ($document->pension_fund_pct ?? 0) / 100), 2);
        $withholding = round($taxable * ((float) ($document->withholding_pct ?? 0) / 100), 2);
        $bollo = (bool) $document->apply_bollo ? (float) ($document->bollo_amount ?? 0) : 0;
        $total = round($taxable + $pensionFund + $vat + $bollo - $withholding, 2);

        $status = $document->status;
        if ($paid > 0 && $paid < $total) {
            $status = 'partially_paid';
        } elseif ($total > 0 && $paid >= $total) {
            $status = 'paid';
        }

        DB::table('documents')->where('id', $id)->update([
            'total_taxable' => round($taxable, 2),
            'total_vat' => round($vat, 2),
            'total_pension_fund' => $pensionFund,
            'total_withholding' => $withholding,
            'total_amount' => $total,
            'total_paid' => round($paid, 2),
            'status' => $status,
            'updated_at' => now(),
        ]);
    }

    private function taskActivityRows($taskIds)
    {
        $ids = collect($taskIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table('task_activity')
            ->leftJoin('users', 'users.id', '=', 'task_activity.user_id')
            ->whereIn('task_activity.task_id', $ids)
            ->latest('task_activity.created_at')
            ->get([
                'task_activity.id',
                'task_activity.task_id',
                'task_activity.user_id',
                'task_activity.action',
                'task_activity.field',
                'task_activity.old_value',
                'task_activity.new_value',
                'task_activity.created_at',
                'users.name as user_name',
            ])
            ->filter(fn ($activity) => $this->normalizeActivityValue($activity->old_value, $activity->field) !== $this->normalizeActivityValue($activity->new_value, $activity->field)
                || in_array($activity->action, ['comment_created', 'comment_updated', 'comment_deleted', 'subtask_created', 'task_created', 'people_updated', 'dependencies_updated'], true))
            ->groupBy('task_id')
            ->map(fn ($activities) => $activities->take(60)->values());
    }

    private function recordTaskFieldChanges(string $taskId, string $userId, object $oldTask, array $newValues): void
    {
        foreach ($this->changedTaskFields($oldTask, $newValues) as $field) {
            $oldValue = $oldTask->{$field};
            $newValue = $newValues[$field];
            $this->recordTaskActivity($taskId, $userId, 'task_updated', $field, $oldValue, $newValue);
        }
    }

    private function changedTaskFields(object $oldTask, array $newValues): array
    {
        $fields = [];

        foreach ($newValues as $field => $newValue) {
            if (in_array($field, ['updated_at'], true) || ! property_exists($oldTask, $field)) {
                continue;
            }

            if ($this->normalizeActivityValue($oldTask->{$field}, $field) !== $this->normalizeActivityValue($newValue, $field)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    private function changedProjectFields(object $oldProject, array $newValues): array
    {
        $fields = [];

        foreach ($newValues as $field => $newValue) {
            if (in_array($field, ['updated_at'], true) || ! property_exists($oldProject, $field)) {
                continue;
            }

            if ((string) ($oldProject->{$field} ?? '') !== (string) ($newValue ?? '')) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    private function taskFieldLabel(string $field): string
    {
        return [
            'title' => 'titolo',
            'description' => 'descrizione',
            'task_type' => 'tipologia',
            'status' => 'stato',
            'priority' => 'priorità',
            'project_id' => 'progetto',
            'client_id' => 'cliente',
            'service_id' => 'servizio',
            'start_date' => 'inizio',
            'due_date' => 'scadenza',
            'due_time' => 'ora',
            'location' => 'luogo/link',
            'recurring_enabled' => 'ricorrenza',
            'dependencies' => 'dipendenze',
        ][$field] ?? str_replace('_', ' ', $field);
    }

    private function recordTaskPeopleChanges(string $taskId, string $userId, array $oldPeople, array $newPeople): void
    {
        foreach (['assignees' => 'assignee_ids', 'followers' => 'follower_ids'] as $key => $field) {
            $oldIds = collect($oldPeople[$key] ?? [])->sort()->values()->all();
            $newIds = collect($newPeople[$key] ?? [])->sort()->values()->all();

            if ($oldIds === $newIds) {
                continue;
            }

            $this->recordTaskActivity($taskId, $userId, 'people_updated', $field, implode(',', $oldIds), implode(',', $newIds));
        }
    }

    private function recordTaskActivity(string $taskId, ?string $userId, string $action, ?string $field = null, mixed $oldValue = null, mixed $newValue = null): void
    {
        DB::table('task_activity')->insert([
            'id' => (string) str()->uuid(),
            'task_id' => $taskId,
            'user_id' => $userId,
            'action' => $action,
            'field' => $field,
            'old_value' => $this->normalizeActivityValue($oldValue, $field),
            'new_value' => $this->normalizeActivityValue($newValue, $field),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function normalizeActivityValue(mixed $value, ?string $field = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        if ($field === 'due_time') {
            return substr((string) $value, 0, 5);
        }

        if (in_array($field, ['start_date', 'due_date'], true)) {
            return substr((string) $value, 0, 10);
        }

        if (in_array($field, ['recurring_enabled'], true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
        }

        if (in_array($field, ['recurring_interval_value', 'recurring_weekday', 'recurring_month_day'], true)) {
            return (string) (int) $value;
        }

        return (string) $value;
    }

    private function notifyTaskPeople(string $taskId, string $actorId, string $type, string $message): void
    {
        $this->notifyUsers($this->taskNotificationUserIds($taskId), $actorId, $type, $message, $taskId);
    }

    private function notifyProjectPeople(string $projectId, string $actorId, string $type, string $message, ?array $extraUserIds = null): void
    {
        $this->notifyUsers($this->projectNotificationUserIds($projectId, $extraUserIds), $actorId, $type, $message);
    }

    private function projectNotificationUserIds(string $projectId, ?array $extraUserIds = null): \Illuminate\Support\Collection
    {
        $project = DB::table('projects')->where('id', $projectId)->first(['created_by']);

        return DB::table('project_followers')
            ->where('project_id', $projectId)
            ->pluck('user_id')
            ->merge($extraUserIds ?? [])
            ->push($project?->created_by)
            ->filter()
            ->unique()
            ->values();
    }

    private function taskNotificationUserIds(string $taskId): \Illuminate\Support\Collection
    {
        $task = DB::table('tasks')->where('id', $taskId)->first(['id', 'parent_task_id']);
        if (! $task) {
            return collect();
        }

        $rootTaskId = $task->parent_task_id ?: $task->id;
        $taskIds = DB::table('tasks')
            ->where('id', $rootTaskId)
            ->orWhere('parent_task_id', $rootTaskId)
            ->pluck('id');

        return DB::table('task_assignees')
            ->whereIn('task_id', $taskIds)
            ->pluck('user_id')
            ->merge(DB::table('task_followers')->whereIn('task_id', $taskIds)->pluck('user_id'))
            ->filter()
            ->unique()
            ->values();
    }

    private function notifyAbsencePeople(string $requestUserId, ?string $actorId, string $type, string $message): void
    {
        $userIds = DB::table('user_roles')
            ->whereIn('role', ['superadmin', 'admin'])
            ->pluck('user_id')
            ->push($requestUserId)
            ->filter()
            ->unique()
            ->values();

        $this->notifyUsers($userIds, $actorId, $type, $message);
    }

    private function notifyUsers(iterable $userIds, ?string $actorId, string $type, string $message, ?string $taskId = null, ?string $companyDocumentId = null, ?string $companyMessageId = null): void
    {
        app(CentroNotificationService::class)->notifyUsers($userIds, $actorId, $type, $message, $taskId, $companyDocumentId, $companyMessageId);
    }

    private function shouldCoalesceNotification(string $type, ?string $taskId): bool
    {
        return $taskId !== null && in_array($type, ['task_updated'], true);
    }

    private function sendBrowserPushNotification(string $userId, string $notificationId, string $message, ?string $taskId = null, ?string $companyDocumentId = null): void
    {
        $publicKey = config('services.webpush.public_key');
        $privateKey = config('services.webpush.private_key');

        if (! $publicKey || ! $privateKey) {
            return;
        }

        $subscriptions = DB::table('push_subscriptions')
            ->where('user_id', $userId)
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.webpush.subject') ?: config('app.url'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        $payload = json_encode([
            'id' => $notificationId,
            'title' => 'Il Centro',
            'body' => $message,
            'tag' => $notificationId,
            'url' => $taskId ? route('tasks.show', $taskId) : ($companyDocumentId ? route('documents.show', $companyDocumentId) : route('notifications.index')),
        ], JSON_THROW_ON_ERROR);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
                ]),
                $payload,
            );
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            Log::warning('Invio notifica push browser non riuscito.', [
                'user_id' => $userId,
                'notification_id' => $notificationId,
                'endpoint' => $report->getEndpoint(),
                'reason' => $report->getReason(),
            ]);

            if ($report->isSubscriptionExpired()) {
                DB::table('push_subscriptions')
                    ->where('endpoint', $report->getEndpoint())
                    ->delete();
            }
        }
    }

    private function smartworkingDayLabel(?string $day): string
    {
        return [
            'monday' => 'Lunedì',
            'tuesday' => 'Martedì',
            'wednesday' => 'Mercoledì',
            'thursday' => 'Giovedì',
            'friday' => 'Venerdì',
        ][$day] ?? 'non impostato';
    }

    private function storeServiceUpdate(Request $request, string $section): RedirectResponse
    {
        $payload = $this->validatedServiceUpdatePayload($request, $section);
        $payload['id'] = (string) str()->uuid();
        $payload['created_at'] = now();
        $payload['updated_at'] = now();

        DB::table('client_service_updates')->insert($payload);

        return back()->with('status', 'Aggiornamento servizio creato.');
    }

    private function updateServiceUpdate(Request $request, string $id): RedirectResponse
    {
        $payload = $this->validatedServiceUpdatePayload($request);
        $payload['updated_at'] = now();

        DB::table('client_service_updates')->where('id', $id)->update($payload);

        return back()->with('status', 'Aggiornamento servizio salvato.');
    }

    private function validatedServiceUpdatePayload(Request $request, ?string $section = null): array
    {
        $payload = $request->validate([
            'client_id' => ['required', 'uuid', 'exists:clients,id'],
            'responsible_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'cadence' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
            'report_url' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        foreach ($payload as $key => $value) {
            if ($value === '') {
                $payload[$key] = null;
            }
        }

        if ($section) {
            $service = strtoupper(str_replace('updates-', '', $section));
            $serviceRecord = $this->serviceByName($service);
            abort_if(! $serviceRecord, 422, 'Servizio non trovato.');
            $payload['service_id'] = $serviceRecord->id;
        }

        return $payload;
    }
}
