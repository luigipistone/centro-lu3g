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
        $rows = DB::table($config['table'])
            ->when($config['table'] === 'projects', fn ($query) => $query->leftJoin('clients', 'clients.id', '=', 'projects.client_id')->select('projects.*', 'clients.name as client_name'))
            ->when($config['table'] === 'tasks', fn ($query) => $query->leftJoin('projects', 'projects.id', '=', 'tasks.project_id')->leftJoin('clients', 'clients.id', '=', 'tasks.client_id')->select('tasks.*', 'projects.name as project_name', 'clients.name as client_name'))
            ->when($config['table'] === 'documents', fn ($query) => $query->leftJoin('clients', 'clients.id', '=', 'documents.client_id')->select('documents.*', 'clients.name as client_name'))
            ->when($config['table'] === 'users', fn ($query) => $query->leftJoin('user_roles', 'user_roles.user_id', '=', 'users.id')->select('users.*', 'user_roles.role'))
            ->latest($config['table'].'.created_at')
            ->limit(100)
            ->get();

        return Inertia::render('Centro/Index', [
            ...$config,
            'rows' => $rows,
            'clients' => DB::table('clients')->orderBy('name')->get(['id', 'name']),
            'projects' => DB::table('projects')->orderBy('name')->get(['id', 'name']),
            'services' => DB::table('services')->where('active', true)->orderBy('name')->get(['id', 'name']),
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
            ],
            'projects' => [
                'tasks' => DB::table('tasks')->where('project_id', $id)->latest()->limit(40)->get(),
                'client' => $record->client_id ? DB::table('clients')->where('id', $record->client_id)->first() : null,
            ],
            'tasks' => [
                'comments' => DB::table('task_comments')->where('task_id', $id)->latest()->limit(30)->get(),
                'project' => $record->project_id ? DB::table('projects')->where('id', $record->project_id)->first() : null,
                'client' => $record->client_id ? DB::table('clients')->where('id', $record->client_id)->first() : null,
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
                    ['name' => 'project_id', 'label' => 'Progetto', 'type' => 'project'],
                    ['name' => 'client_id', 'label' => 'Cliente', 'type' => 'client'],
                    ['name' => 'status', 'label' => 'Stato', 'type' => 'select', 'options' => ['todo', 'in_progress', 'in_review', 'done']],
                    ['name' => 'priority', 'label' => 'Priorita', 'type' => 'select', 'options' => ['low', 'medium', 'high', 'urgent']],
                    ['name' => 'due_date', 'label' => 'Scadenza', 'type' => 'date'],
                    ['name' => 'due_time', 'label' => 'Ora', 'type' => 'time'],
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
            'columns' => ['cadence', 'contact', 'report_url', 'notes', 'updated_at'],
            'fields' => [],
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
                'status' => ['required', Rule::in(['todo', 'in_progress', 'in_review', 'done'])],
                'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
                'due_date' => ['nullable', 'date'],
                'due_time' => ['nullable', 'date_format:H:i'],
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
}
