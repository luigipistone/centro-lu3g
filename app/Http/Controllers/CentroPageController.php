<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CentroPageController extends Controller
{
    public function dashboard(): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'clients' => DB::table('clients')->count(),
                'activeProjects' => DB::table('projects')->where('status', 'active')->count(),
                'openTasks' => DB::table('tasks')->where('status', '!=', 'done')->count(),
                'urgentTasks' => DB::table('tasks')->where('priority', 'urgent')->where('status', '!=', 'done')->count(),
            ],
            'recentClients' => DB::table('clients')->latest()->limit(6)->get(['id', 'name', 'email', 'phone', 'created_at']),
            'upcomingTasks' => DB::table('tasks')->whereNotNull('due_date')->orderBy('due_date')->limit(8)->get(['id', 'title', 'status', 'priority', 'due_date']),
        ]);
    }

    public function index(string $section): Response
    {
        $config = $this->config($section);
        $limit = $section === 'billing' ? 500 : 100;
        $rows = DB::table($config['table'])
            ->when($config['table'] === 'projects', fn ($query) => $query->leftJoin('clients', 'clients.id', '=', 'projects.client_id')->select('projects.*', 'clients.name as client_name'))
            ->when($config['table'] === 'tasks' && $section !== 'calendar', fn ($query) => $query->leftJoin('projects', 'projects.id', '=', 'tasks.project_id')->leftJoin('clients', 'clients.id', '=', 'tasks.client_id')->select('tasks.*', 'projects.name as project_name', 'clients.name as client_name'))
            ->when($section === 'calendar', fn ($query) => $query
                ->leftJoin('projects', 'projects.id', '=', 'tasks.project_id')
                ->leftJoin('clients', 'clients.id', '=', 'tasks.client_id')
                ->whereNull('tasks.parent_task_id')
                ->whereNotNull('tasks.due_date')
                ->select('tasks.*', 'projects.name as project_name', 'projects.color as project_color', 'clients.name as client_name')
            )
            ->when($config['table'] === 'documents', fn ($query) => $query->leftJoin('clients', 'clients.id', '=', 'documents.client_id')->select('documents.*', 'clients.name as client_name'))
            ->when($config['table'] === 'users', fn ($query) => $query->leftJoin('user_roles', 'user_roles.user_id', '=', 'users.id')->select('users.*', 'user_roles.role'))
            ->when($config['table'] === 'client_service_updates', fn ($query) => $query
                ->leftJoin('clients', 'clients.id', '=', 'client_service_updates.client_id')
                ->leftJoin('services', 'services.id', '=', 'client_service_updates.service_id')
                ->leftJoin('users', 'users.id', '=', 'client_service_updates.responsible_user_id')
                ->select('client_service_updates.*', 'clients.name as client_name', 'services.name as service_name', 'users.name as responsible_name')
            )
            ->latest($config['table'].'.created_at')
            ->limit($limit)
            ->get();

        if ($section === 'calendar') {
            $subtaskCounts = DB::table('tasks')
                ->whereNotNull('parent_task_id')
                ->select('parent_task_id', DB::raw('count(*) as aggregate'))
                ->groupBy('parent_task_id')
                ->pluck('aggregate', 'parent_task_id');

            $rows = $rows->map(function ($row) use ($subtaskCounts) {
                $row->subtask_count = (int) ($subtaskCounts[$row->id] ?? 0);

                return $row;
            });
        }

        return Inertia::render('Centro/Index', [
            ...$config,
            'rows' => $rows,
            'billingStats' => $section === 'billing' ? $this->billingStats() : null,
            'clients' => DB::table('clients')->orderBy('name')->get(['id', 'name']),
            'projects' => DB::table('projects')->orderBy('name')->get(['id', 'name']),
            'services' => DB::table('services')->where('active', true)->orderBy('name')->get(['id', 'name']),
            'users' => DB::table('users')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function notifications(Request $request): Response
    {
        return Inertia::render('Centro/Notifications', [
            'notifications' => DB::table('notifications')
                ->leftJoin('tasks', 'tasks.id', '=', 'notifications.task_id')
                ->where('notifications.user_id', $request->user()->id)
                ->latest('notifications.created_at')
                ->limit(100)
                ->get([
                    'notifications.id',
                    'notifications.task_id',
                    'notifications.type',
                    'notifications.message',
                    'notifications.read',
                    'notifications.created_at',
                    'tasks.title as task_title',
                ]),
        ]);
    }

    public function show(string $section, string $id): Response
    {
        $config = $this->config($section);
        $record = DB::table($config['table'])->where('id', $id)->first();
        abort_if(! $record, 404);

        $related = match ($section) {
            'clients' => [
                'projects' => DB::table('projects')->where('client_id', $id)->latest()->get(),
                'tasks' => DB::table('tasks')->where('client_id', $id)->latest()->limit(20)->get(),
                'documents' => DB::table('documents')->where('client_id', $id)->latest()->limit(20)->get(),
                'contacts' => DB::table('client_contacts')->where('client_id', $id)->latest()->get(),
                'clientServices' => DB::table('client_services')->where('client_id', $id)->pluck('service_id'),
                'services' => DB::table('services')->where('active', true)->orderBy('name')->get(['id', 'name', 'color']),
            ],
            'projects' => [
                'tasks' => DB::table('tasks')->where('project_id', $id)->latest()->limit(40)->get(),
                'client' => $record->client_id ? DB::table('clients')->where('id', $record->client_id)->first() : null,
            ],
            'tasks' => [
                'comments' => DB::table('task_comments')
                    ->leftJoin('users', 'users.id', '=', 'task_comments.user_id')
                    ->where('task_id', $id)
                    ->latest('task_comments.created_at')
                    ->limit(30)
                    ->get(['task_comments.*', 'users.name as user_name']),
                'assignees' => DB::table('task_assignees')->where('task_id', $id)->pluck('user_id'),
                'followers' => DB::table('task_followers')->where('task_id', $id)->pluck('user_id'),
                'users' => DB::table('users')->orderBy('name')->get(['id', 'name', 'email']),
                'subtasks' => DB::table('tasks')
                    ->where('parent_task_id', $id)
                    ->latest()
                    ->get(['id', 'title', 'status', 'priority', 'due_date', 'due_time']),
                'project' => $record->project_id ? DB::table('projects')->where('id', $record->project_id)->first() : null,
                'client' => $record->client_id ? DB::table('clients')->where('id', $record->client_id)->first() : null,
            ],
            'billing' => [
                'client' => DB::table('clients')->where('id', $record->client_id)->first(),
                'lines' => DB::table('document_lines')->where('document_id', $id)->orderBy('position')->get(),
                'payments' => DB::table('document_payments')->where('document_id', $id)->latest('paid_at')->get(),
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
        $payload['id'] = (string) str()->uuid();
        $payload['created_at'] = now();
        $payload['updated_at'] = now();

        if (in_array($section, ['clients', 'projects', 'tasks'], true)) {
            $payload['created_by'] = $request->user()->id;
        }

        DB::table($this->config($section)['table'])->insert($payload);

        return back()->with('status', 'Creato.');
    }

    public function update(Request $request, string $section, string $id): RedirectResponse
    {
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
        $payload['updated_at'] = now();

        DB::table($this->config($section)['table'])->where('id', $id)->update($payload);

        return back()->with('status', 'Aggiornato.');
    }

    public function destroy(string $section, string $id): RedirectResponse
    {
        if ($section === 'users') {
            User::query()->whereKey($id)->delete();

            return back()->with('status', 'Utente eliminato.');
        }

        DB::table($this->config($section)['table'])->where('id', $id)->delete();

        return back()->with('status', 'Eliminato.');
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
                    ['name' => 'city', 'label' => 'Citta', 'type' => 'text'],
                    ['name' => 'province', 'label' => 'Provincia', 'type' => 'text'],
                    ['name' => 'website', 'label' => 'Sito web', 'type' => 'text'],
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
                    ['name' => 'task_type', 'label' => 'Tipo', 'type' => 'select', 'options' => ['task', 'ongoing', 'meeting']],
                    ['name' => 'project_id', 'label' => 'Progetto', 'type' => 'project'],
                    ['name' => 'client_id', 'label' => 'Cliente', 'type' => 'client'],
                    ['name' => 'status', 'label' => 'Stato', 'type' => 'select', 'options' => ['todo', 'in_progress', 'in_review', 'done']],
                    ['name' => 'priority', 'label' => 'Priorita', 'type' => 'select', 'options' => ['low', 'medium', 'high', 'urgent']],
                    ['name' => 'start_date', 'label' => 'Inizio', 'type' => 'date'],
                    ['name' => 'due_date', 'label' => 'Scadenza', 'type' => 'date'],
                    ['name' => 'due_time', 'label' => 'Ora', 'type' => 'time'],
                    ['name' => 'location', 'label' => 'Luogo/link', 'type' => 'text'],
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
                ['name' => 'cadence', 'label' => 'Cadenza', 'type' => 'text'],
                ['name' => 'contact', 'label' => 'Contatto', 'type' => 'text'],
                ['name' => 'report_url', 'label' => 'Report URL', 'type' => 'text'],
                ['name' => 'notes', 'label' => 'Note', 'type' => 'textarea'],
            ],
            'serviceName' => $service,
        ];
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
                'city' => ['nullable', 'string', 'max:255'],
                'province' => ['nullable', 'string', 'max:255'],
                'website' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string'],
            ],
            'projects' => [
                'name' => ['required', 'string', 'max:255'],
                'client_id' => ['nullable', 'uuid', 'exists:clients,id'],
                'status' => ['required', Rule::in(['active', 'completed', 'on_hold', 'archived'])],
                'color' => ['required', 'string', 'max:20'],
                'description' => ['nullable', 'string'],
            ],
            'tasks' => [
                'title' => ['required', 'string', 'max:255'],
                'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
                'client_id' => ['nullable', 'uuid', 'exists:clients,id'],
                'task_type' => ['required', Rule::in(['task', 'project', 'ongoing', 'meeting'])],
                'status' => ['required', Rule::in(['todo', 'in_progress', 'in_review', 'done'])],
                'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
                'start_date' => ['nullable', 'date'],
                'due_date' => ['nullable', 'date'],
                'due_time' => ['nullable', 'date_format:H:i'],
                'location' => ['nullable', 'string', 'max:255'],
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

        $payload = $request->validate($rules);

        foreach ($payload as $key => $value) {
            if ($value === '') {
                $payload[$key] = null;
            }
        }

        if ($section === 'settings') {
            $payload['active'] = $request->boolean('active');
        }

        return $payload;
    }

    private function storeUser(Request $request): RedirectResponse
    {
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
        $user = User::query()->findOrFail($id);
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(['superadmin', 'admin', 'editor', 'guest'])],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $user->name = $payload['name'];
        $user->email = $payload['email'];
        if (! empty($payload['password'])) {
            $user->password = Hash::make($payload['password']);
        }
        $user->save();

        $this->syncProfileAndRole($user, $payload['role']);

        return back()->with('status', 'Utente aggiornato.');
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

        $this->notifyTaskPeople(
            $id,
            $request->user()->id,
            'task_comment',
            $request->user()->name.' ha commentato il task "'.$task->title.'".',
        );

        return back()->with('status', 'Commento aggiunto.');
    }

    public function storeSubtask(Request $request, string $id): RedirectResponse
    {
        $task = DB::table('tasks')->where('id', $id)->first();
        abort_if(! $task, 404);

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'due_date' => ['nullable', 'date'],
        ]);

        foreach ($payload as $key => $value) {
            if ($value === '') {
                $payload[$key] = null;
            }
        }

        DB::table('tasks')->insert([
            'id' => (string) str()->uuid(),
            'title' => $payload['title'],
            'priority' => $payload['priority'],
            'due_date' => $payload['due_date'] ?? null,
            'status' => 'todo',
            'project_id' => $task->project_id,
            'client_id' => $task->client_id,
            'service_id' => $task->service_id,
            'parent_task_id' => $id,
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->notifyTaskPeople(
            $id,
            $request->user()->id,
            'subtask_created',
            $request->user()->name.' ha creato una sottoattivita in "'.$task->title.'".',
        );

        return back()->with('status', 'Sottoattivita creata.');
    }

    public function syncTaskPeople(Request $request, string $id, string $type): RedirectResponse
    {
        DB::table('tasks')->where('id', $id)->exists() || abort(404);
        abort_unless(in_array($type, ['assignees', 'followers'], true), 404);

        $payload = $request->validate([
            'user_ids' => ['array'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
        ]);

        $table = $type === 'assignees' ? 'task_assignees' : 'task_followers';
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

        return back()->with('status', $type === 'assignees' ? 'Assegnatari aggiornati.' : 'Follower aggiornati.');
    }

    public function storeClientContact(Request $request, string $id): RedirectResponse
    {
        DB::table('clients')->where('id', $id)->exists() || abort(404);

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

        DB::table('client_contacts')->insert([
            ...$payload,
            'id' => (string) str()->uuid(),
            'client_id' => $id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Referente aggiunto.');
    }

    public function destroyClientContact(string $clientId, string $contactId): RedirectResponse
    {
        DB::table('client_contacts')
            ->where('client_id', $clientId)
            ->where('id', $contactId)
            ->delete();

        return back()->with('status', 'Referente eliminato.');
    }

    public function attachClientService(string $clientId, string $serviceId): RedirectResponse
    {
        DB::table('clients')->where('id', $clientId)->exists() || abort(404);
        DB::table('services')->where('id', $serviceId)->exists() || abort(404);

        DB::table('client_services')->insertOrIgnore([
            'id' => (string) str()->uuid(),
            'client_id' => $clientId,
            'service_id' => $serviceId,
        ]);

        return back()->with('status', 'Servizio collegato.');
    }

    public function detachClientService(string $clientId, string $serviceId): RedirectResponse
    {
        DB::table('client_services')
            ->where('client_id', $clientId)
            ->where('service_id', $serviceId)
            ->delete();

        return back()->with('status', 'Servizio scollegato.');
    }

    public function updateTaskStatus(Request $request, string $id): RedirectResponse
    {
        $task = DB::table('tasks')->where('id', $id)->first();
        abort_if(! $task, 404);

        $payload = $request->validate([
            'status' => ['required', Rule::in(['todo', 'in_progress', 'in_review', 'done'])],
        ]);

        DB::table('tasks')->where('id', $id)->update([
            'status' => $payload['status'],
            'updated_at' => now(),
        ]);

        $this->notifyTaskPeople(
            $id,
            $request->user()->id,
            'task_status',
            $request->user()->name.' ha impostato "'.$task->title.'" su '.$payload['status'].'.',
        );

        return back()->with('status', 'Stato task aggiornato.');
    }

    public function markNotificationRead(Request $request, string $id): RedirectResponse
    {
        DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->update(['read' => true, 'updated_at' => now()]);

        return back()->with('status', 'Notifica letta.');
    }

    public function markAllNotificationsRead(Request $request): RedirectResponse
    {
        DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->where('read', false)
            ->update(['read' => true, 'updated_at' => now()]);

        return back()->with('status', 'Notifiche segnate come lette.');
    }

    public function destroyNotification(Request $request, string $id): RedirectResponse
    {
        DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->delete();

        return back()->with('status', 'Notifica eliminata.');
    }

    public function destroyAllNotifications(Request $request): RedirectResponse
    {
        DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->delete();

        return back()->with('status', 'Notifiche svuotate.');
    }

    public function storeDocumentLine(Request $request, string $id): RedirectResponse
    {
        DB::table('documents')->where('id', $id)->exists() || abort(404);

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
        $subtotal = round($quantity * $unitPrice * (1 - ($discount / 100)), 2);

        DB::table('document_lines')->insert([
            'id' => (string) str()->uuid(),
            'document_id' => $id,
            'position' => DB::table('document_lines')->where('document_id', $id)->count(),
            'description' => $payload['description'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_pct' => $discount,
            'vat_rate' => (float) $payload['vat_rate'],
            'subtotal' => $subtotal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recalculateDocument($id);

        return back()->with('status', 'Riga aggiunta.');
    }

    public function destroyDocumentLine(string $documentId, string $lineId): RedirectResponse
    {
        DB::table('document_lines')->where('document_id', $documentId)->where('id', $lineId)->delete();
        $this->recalculateDocument($documentId);

        return back()->with('status', 'Riga eliminata.');
    }

    public function storeDocumentPayment(Request $request, string $id): RedirectResponse
    {
        DB::table('documents')->where('id', $id)->exists() || abort(404);

        $payload = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'paid_at' => ['required', 'date'],
            'method' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::table('document_payments')->insert([
            'id' => (string) str()->uuid(),
            'document_id' => $id,
            'amount' => (float) $payload['amount'],
            'paid_at' => $payload['paid_at'],
            'method' => $payload['method'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recalculateDocument($id);

        return back()->with('status', 'Pagamento registrato.');
    }

    public function destroyDocumentPayment(string $documentId, string $paymentId): RedirectResponse
    {
        DB::table('document_payments')->where('document_id', $documentId)->where('id', $paymentId)->delete();
        $this->recalculateDocument($documentId);

        return back()->with('status', 'Pagamento eliminato.');
    }

    private function recalculateDocument(string $id): void
    {
        $lines = DB::table('document_lines')->where('document_id', $id)->get();
        $taxable = (float) $lines->sum('subtotal');
        $vat = (float) $lines->sum(fn ($line) => ((float) $line->subtotal) * ((float) $line->vat_rate / 100));
        $paid = (float) DB::table('document_payments')->where('document_id', $id)->sum('amount');
        $total = round($taxable + $vat, 2);

        $status = DB::table('documents')->where('id', $id)->value('status');
        if ($paid > 0 && $paid < $total) {
            $status = 'partially_paid';
        } elseif ($total > 0 && $paid >= $total) {
            $status = 'paid';
        }

        DB::table('documents')->where('id', $id)->update([
            'total_taxable' => round($taxable, 2),
            'total_vat' => round($vat, 2),
            'total_amount' => $total,
            'total_paid' => round($paid, 2),
            'status' => $status,
            'updated_at' => now(),
        ]);
    }

    private function notifyTaskPeople(string $taskId, string $actorId, string $type, string $message): void
    {
        $task = DB::table('tasks')->where('id', $taskId)->first();
        if (! $task) {
            return;
        }

        $userIds = collect([$task->created_by])
            ->merge(DB::table('task_assignees')->where('task_id', $taskId)->pluck('user_id'))
            ->merge(DB::table('task_followers')->where('task_id', $taskId)->pluck('user_id'))
            ->filter()
            ->unique()
            ->reject(fn ($userId) => $userId === $actorId)
            ->values();

        foreach ($userIds as $userId) {
            DB::table('notifications')->insert([
                'id' => (string) str()->uuid(),
                'user_id' => $userId,
                'actor_id' => $actorId,
                'task_id' => $taskId,
                'type' => $type,
                'message' => $message,
                'read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
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
            $serviceId = DB::table('services')->where('name', $service)->value('id');
            abort_if(! $serviceId, 422, 'Servizio non trovato.');
            $payload['service_id'] = $serviceId;
        }

        return $payload;
    }
}
