<?php

namespace App\Http\Controllers;

use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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

        return Inertia::render('Centro/Index', [
            ...$config,
            'rows' => $rows,
            'billingStats' => $section === 'billing' ? $this->billingStats() : null,
            'clientStats' => $section === 'clients' ? $this->clientStats() : null,
            'documentSettings' => $section === 'settings' ? DB::table('document_settings')->first() : null,
            'emailSettings' => $section === 'settings' ? DB::table('email_settings')->first() : null,
            'numberings' => $section === 'settings' ? DB::table('document_numbering')->orderBy('doc_type')->orderByDesc('year')->get() : [],
            'backupRuns' => $section === 'settings' ? DB::table('backup_runs')->latest('started_at')->limit(8)->get() : [],
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
                'subscriptions' => DB::table('subscriptions')->where('client_id', $id)->latest()->get(),
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

    public function updateDocumentSettings(Request $request): RedirectResponse
    {
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

        return back()->with('status', 'Impostazioni fatturazione aggiornate.');
    }

    public function updateEmailSettings(Request $request): RedirectResponse
    {
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

    public function updateNumbering(Request $request, string $id): RedirectResponse
    {
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

    public function runBackup(): RedirectResponse
    {
        $tables = DB::select('SHOW TABLES');

        DB::table('backup_runs')->insert([
            'id' => (string) str()->uuid(),
            'frequency' => 'manual',
            'status' => 'completed',
            'started_at' => now(),
            'finished_at' => now(),
            'tables_count' => count($tables),
            'storage_path' => 'Plesk/manual-db-backup',
        ]);

        return back()->with('status', 'Backup manuale registrato. Per il dump fisico usa anche il backup Plesk del dominio.');
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
                    ['name' => 'legal_form', 'label' => 'Natura giuridica', 'type' => 'text'],
                    ['name' => 'business_sector', 'label' => 'Settore', 'type' => 'text'],
                    ['name' => 'source', 'label' => 'Sorgente', 'type' => 'text'],
                    ['name' => 'country', 'label' => 'Paese', 'type' => 'text'],
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
                    ['name' => 'vat_treatment', 'label' => 'Trattamento IVA', 'type' => 'text'],
                    ['name' => 'payment_terms_days', 'label' => 'Termini pagamento', 'type' => 'number'],
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

        if ($section === 'clients') {
            $payload['is_pa'] = $request->boolean('is_pa');
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

    public function updateDocumentHeader(Request $request, string $id): RedirectResponse
    {
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

    public function issueDocument(string $id): RedirectResponse
    {
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
        $newId = $this->copyDocument($id, null, $request->user()->id);

        return redirect()->route('billing.show', $newId)->with('status', 'Documento duplicato.');
    }

    public function convertDocument(Request $request, string $id, string $type): RedirectResponse
    {
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

    public function storeSubscription(Request $request, string $clientId): RedirectResponse
    {
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
        $this->subscriptionForClient($clientId, $subscriptionId);
        $payload = $this->validatedSubscriptionPayload($request);
        $payload['updated_at'] = now();

        DB::table('subscriptions')->where('id', $subscriptionId)->update($payload);

        return back()->with('status', 'Abbonamento aggiornato.');
    }

    public function toggleSubscription(Request $request, string $clientId, string $subscriptionId): RedirectResponse
    {
        $this->subscriptionForClient($clientId, $subscriptionId);

        DB::table('subscriptions')->where('id', $subscriptionId)->update([
            'active' => $request->boolean('active'),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Stato abbonamento aggiornato.');
    }

    public function destroySubscription(string $clientId, string $subscriptionId): RedirectResponse
    {
        $this->subscriptionForClient($clientId, $subscriptionId);
        DB::table('subscriptions')->where('id', $subscriptionId)->delete();

        return back()->with('status', 'Abbonamento eliminato.');
    }

    public function generateSubscriptionDocument(Request $request, string $clientId, string $subscriptionId): RedirectResponse
    {
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
