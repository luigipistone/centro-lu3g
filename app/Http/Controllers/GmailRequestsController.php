<?php

namespace App\Http\Controllers;

use App\Services\GmailRequests;
use App\Services\RolePermissionService;
use App\Support\TextSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class GmailRequestsController extends Controller
{
    private function authorizeManager(Request $r): void
    {
        $role = DB::table('user_roles')->where('user_id', $r->user()->id)->value('role');
        abort_unless(in_array($role, ['admin', 'superadmin'], true) && app(RolePermissionService::class)->allows($role ?? '', 'requests.manage'), 403);
    }
    private function admin(Request $r): bool
    {
        return DB::table('user_roles')->where('user_id', $r->user()->id)->where('role', 'superadmin')->exists();
    }
    private function connection(Request $r): ?object { return DB::table('gmail_connections')->where('user_id', $r->user()->id)->first(); }
    private function locked(string $id, callable $callback)
    {
        $lock = Cache::lock('gmail:'.$id, 1500);
        abort_unless($lock->get(), 409, 'Controllo Gmail in corso. Riprova tra poco.');
        try { return $callback(); } finally { $lock->release(); }
    }
    public function settings(Request $r)
    {
        $this->authorizeManager($r); $s = DB::table('gmail_settings')->first(); $c = $this->connection($r);
        return response()->json(['configured' => (bool) $s, 'client_id' => $this->admin($r) ? ($s->client_id ?? '') : '',
            'admin' => $this->admin($r), 'redirect_uri' => route('requests.gmail.callback'),
            'connected' => (bool) ($c?->refresh_token), 'email' => $c->email ?? null, 'enabled' => (bool) ($c->enabled ?? false),
            'label_id' => $c->label_id ?? '', 'label_name' => $c->label_name ?? '', 'last_error' => $c->last_error ?? null,
            'last_synced_at' => $c?->last_synced_at ? \Carbon\CarbonImmutable::parse($c->last_synced_at)->toIso8601String() : null]);
    }
    public function credentials(Request $r)
    {
        abort_unless($this->admin($r), 403);
        $data = $r->validate(['client_id' => 'required|string|max:255', 'client_secret' => 'required|string|max:4000']);
        return $this->locked('credentials', function () use ($r, $data) {
            abort_if(DB::table('gmail_connections')->whereNotNull('refresh_token')->exists(), 422, 'Scollega prima tutte le caselle.');
            DB::table('gmail_settings')->updateOrInsert(['id' => 1], ['client_id' => trim($data['client_id']), 'client_secret' => Crypt::encryptString(trim($data['client_secret'])), 'created_at' => now(), 'updated_at' => now()]);
            return $this->settings($r);
        });
    }
    public function connect(Request $r)
    {
        $this->authorizeManager($r); $s = DB::table('gmail_settings')->first(); abort_unless($s, 422, 'Configura prima Gmail.');
        $state = Str::random(64);
        $r->session()->put('gmail_requests_oauth', ['state' => $state, 'user' => $r->user()->id, 'client' => $s->client_id, 'expires' => now()->addMinutes(10)->timestamp]);
        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $s->client_id, 'redirect_uri' => route('requests.gmail.callback'), 'response_type' => 'code',
            'scope' => GmailRequests::SCOPE, 'access_type' => 'offline', 'prompt' => 'consent', 'state' => $state, 'login_hint' => $r->user()->email,
        ]));
    }
    public function callback(Request $r)
    {
        $this->authorizeManager($r); $state = $r->session()->pull('gmail_requests_oauth');
        abort_unless($state && $state['user'] === $r->user()->id && $state['expires'] >= now()->timestamp && hash_equals($state['state'], (string) $r->query('state')), 403);
        if ($r->query('error')) return redirect('/requests?connections=1')->with('status', 'Collegamento annullato.');
        $r->validate(['code' => 'required|string|max:4096']);
        try {
            $this->locked('credentials', function () use ($r, $state) {
                abort_unless(DB::table('gmail_settings')->value('client_id') === $state['client'], 409);
                $client = new GmailRequests;
                $tokens = $client->exchange(['grant_type' => 'authorization_code', 'code' => $r->query('code'), 'redirect_uri' => route('requests.gmail.callback')]);
                if (empty($tokens['refresh_token']) || ! in_array(GmailRequests::SCOPE, explode(' ', $tokens['scope'] ?? ''), true)) throw new \RuntimeException('Consenso incompleto');
                $encrypted = Crypt::encryptString($tokens['refresh_token']);
                $profile = (new GmailRequests)->get((object) ['refresh_token' => $encrypted], 'profile');
                abort_unless(strtolower($profile['emailAddress'] ?? '') === strtolower($r->user()->email), 422, 'Collega la casella corrispondente al tuo account Centro.');
                $old = $this->connection($r); $id = $old->id ?? (string) Str::uuid();
                $this->locked($id, function () use ($r, $id, $old, $profile, $encrypted) {
                    DB::table('gmail_connections')->updateOrInsert(['user_id' => $r->user()->id], ['id' => $id, 'email' => strtolower($profile['emailAddress']),
                        'refresh_token' => $encrypted, 'enabled' => false, 'last_error' => null, 'updated_at' => now()] + ($old ? [] : ['created_at' => now()]));
                });
            });
        } catch (\Throwable) { return redirect('/requests?connections=1')->with('status', 'Collegamento non riuscito: verifica credenziali, consenso Gmail e corrispondenza tra la casella Google e la tua email nel Centro.'); }
        return redirect('/requests?connections=1')->with('status', 'Gmail collegato. Seleziona l’etichetta e attiva il controllo.');
    }
    public function labels(Request $r)
    {
        $this->authorizeManager($r); $c = $this->connection($r); abort_unless($c?->refresh_token, 422);
        $key = 'gmail:labels:'.$c->id;
        if ($cached = Cache::get($key)) return response()->json(['labels' => $cached]);
        $fallback = $c->label_id ? [['value' => $c->label_id, 'label' => $c->label_name]] : [];
        if (GmailRequests::coolingDown($c->id)) return response()->json(['labels' => $fallback, 'warning' => 'Google ha limitato temporaneamente le richieste. Attendi 5 minuti; non serve ricollegare Gmail.']);
        try {
            $labels = collect((new GmailRequests)->get($c, 'labels')['labels'] ?? [])->where('type', 'user')->map(fn ($l) => ['value' => $l['id'], 'label' => $l['name']])->values()->all();
            Cache::put($key, $labels, now()->addMinute());
            return response()->json(['labels' => $labels]);
        } catch (\Throwable $e) {
            if (GmailRequests::limited($e)) {
                GmailRequests::pause($c->id);
                return response()->json(['labels' => $fallback, 'warning' => 'Google ha limitato temporaneamente le richieste. Attendi 5 minuti; non serve ricollegare Gmail.']);
            }
            abort(422, 'Etichette non disponibili. Riprova tra poco; se il problema persiste verifica il collegamento Gmail.');
        }
    }
    public function update(Request $r)
    {
        $this->authorizeManager($r); $c = $this->connection($r); abort_unless($c?->refresh_token, 422);
        $data = $r->validate(['label_id' => 'required|string|max:255', 'enabled' => 'required|boolean']);
        return $this->locked($c->id, function () use ($c, $r, $data) {
            try { $label = (new GmailRequests)->get($c, 'labels/'.rawurlencode($data['label_id'])); }
            catch (\Throwable) { abort(422, 'Etichetta non disponibile.'); }
            abort_unless(($label['type'] ?? '') === 'user', 422, 'Scegli un’etichetta dedicata.');
            DB::table('gmail_connections')->where('id', $c->id)->update($data + ['label_name' => $label['name'], 'requested_at' => now(), 'updated_at' => now()]);
            return $this->settings($r);
        });
    }
    public function sync(Request $r)
    {
        $this->authorizeManager($r); $c = $this->connection($r); abort_unless($c?->enabled, 422, 'Attiva prima il controllo.');
        DB::table('gmail_connections')->where('id', $c->id)->update(['requested_at' => now()]);
        return response()->json(['message' => 'Controllo richiesto.']);
    }
    public function disconnect(Request $r)
    {
        $this->authorizeManager($r); $c = $this->connection($r);
        if ($c) $this->locked($c->id, fn () => DB::table('gmail_connections')->where('id', $c->id)->update(['refresh_token' => null, 'enabled' => false]));
        // Do not revoke the Google grant: it may also serve Calendar/Drive for this client.
        return $this->settings($r);
    }
    public function index(Request $r)
    {
        $this->authorizeManager($r);
        $q = DB::table('mail_requests as mr')->join('gmail_connections as gc', 'gc.id', '=', 'mr.connection_id')->join('users as u', 'u.id', '=', 'gc.user_id')
            ->leftJoin('users as reviewer', 'reviewer.id', '=', 'mr.reviewer_id')->select('mr.*', 'u.name as manager_name', 'reviewer.name as reviewer_name');
        if ($r->query('mine')) $q->where('gc.user_id', $r->user()->id);
        $status = $r->query('status', 'pending');
        if (in_array($status, ['pending', 'converted', 'discarded'], true)) $q->where('mr.status', $status);
        TextSearch::apply($q, ['mr.subject', 'mr.sender', 'u.name'], mb_substr((string) $r->query('search', ''), 0, 255));
        return Inertia::render('Centro/MailRequests', ['requests' => $q->orderByDesc('mr.updated_at')->paginate(20)->withQueryString(), 'filters' => $r->only('search', 'status', 'mine')]);
    }
    public function show(Request $r, string $id)
    {
        $this->authorizeManager($r); $record = DB::table('mail_requests')->find($id); abort_unless($record, 404);
        return response()->json(['record' => $record,
            'messages' => DB::table('mail_request_messages')->where('request_id', $id)->orderBy('received_at')->get()->map(function ($m) { $m->attachments = json_decode($m->attachments, true); return $m; }),
            'clients' => DB::table('clients')->where('active', true)->orderBy('name')->get(['id', 'name', 'parent_client_id']),
            'projects' => DB::table('projects')->where('status', '!=', 'archived')->orderBy('name')->get(['id', 'name', 'client_id', 'service_id']),
            'services' => DB::table('services')->orderBy('name')->get(['id', 'name']),
            'tasks' => DB::table('tasks')->whereNull('google_cancelled_at')->whereNull('teamleader_cancelled_at')->orderByDesc('created_at')->get(['id', 'title']),
            'users' => DB::table('users')->where('account_status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }
    public function claim(Request $r, string $id)
    {
        $this->authorizeManager($r);
        DB::transaction(function () use ($r, $id) {
            $item = DB::table('mail_requests')->lockForUpdate()->find($id); abort_unless($item, 404);
            abort_if($item->reviewer_id && $item->reviewer_id !== $r->user()->id, 409, 'Richiesta gia presa in carico.');
            DB::table('mail_requests')->where('id', $id)->update(['reviewer_id' => $r->boolean('release') ? null : $r->user()->id]);
        });
        return $this->show($r, $id);
    }
    public function resolve(Request $r, string $id)
    {
        $this->authorizeManager($r);
        $data = $r->validate(['version' => 'required|integer', 'action' => 'required|in:create,link,discard,reviewed', 'task_id' => 'nullable|uuid|exists:tasks,id']);
        $taskId = DB::transaction(function () use ($r, $id, $data) {
            $item = DB::table('mail_requests')->lockForUpdate()->find($id); abort_unless($item, 404);
            abort_unless($item->reviewer_id === $r->user()->id, 409, 'Prendi prima in carico la richiesta.');
            abort_unless($item->version === $data['version'], 409, 'Sono arrivate nuove email. Riapri la richiesta prima di confermare.');
            if ($data['action'] === 'reviewed') {
                DB::table('mail_requests')->where('id', $id)->update(['has_updates' => false]);
                return $item->task_id;
            }
            abort_unless($item->status === 'pending', 409, 'Richiesta gia elaborata.');
            $task = null;
            if ($data['action'] === 'create') {
                $payload = $r->validate(['title' => 'required|string|max:255', 'description' => 'nullable|string', 'client_id' => 'required|uuid|exists:clients,id',
                    'project_id' => 'required|uuid|exists:projects,id', 'service_id' => 'nullable|uuid|exists:services,id', 'priority' => 'required|in:low,medium,high,urgent',
                    'start_date' => 'nullable|date_format:Y-m-d', 'due_date' => 'nullable|date_format:Y-m-d', 'assignee_ids' => 'required|array|min:1', 'assignee_ids.*' => 'uuid|exists:users,id']);
                $client = DB::table('clients')->find($payload['client_id']);
                $project = DB::table('projects')->find($payload['project_id']);
                abort_unless($client->active && $project->status !== 'archived' && in_array($project->client_id, [$client->id, $client->parent_client_id], true), 422, 'Il progetto deve appartenere al cliente selezionato.');
                $sub = Request::create('/tasks', 'POST', $payload + ['task_type' => 'task', 'status' => 'todo']);
                $sub->setUserResolver(fn () => $r->user()); $sub->setLaravelSession($r->session());
                $sub->attributes->set('common_api_write', true);
                $response = app(CentroPageController::class)->store($sub, 'tasks');
                abort_unless($response instanceof \Illuminate\Http\JsonResponse && $response->status() === 201, 422, 'Creazione non riuscita.');
                $task = $response->getData(true)['id'];
            } elseif ($data['action'] === 'link') {
                abort_unless($data['task_id'] ?? null, 422, 'Seleziona una task.');
                $role = DB::table('user_roles')->where('user_id', $r->user()->id)->value('role');
                abort_unless(app(RolePermissionService::class)->allows($role ?? '', 'tasks.update'), 403);
                $task = $data['task_id'];
            }
            DB::table('mail_requests')->where('id', $id)->update(['task_id' => $task, 'status' => $data['action'] === 'discard' ? 'discarded' : 'converted', 'has_updates' => false, 'updated_at' => now()]);
            return $task;
        });
        return response()->json(['task_id' => $taskId]);
    }
    public function attachment(Request $r, string $id, string $messageId, int $index)
    {
        $this->authorizeManager($r);
        $message = DB::table('mail_request_messages')->where('request_id', $id)->where('id', $messageId)->first(); abort_unless($message, 404);
        $attachment = json_decode($message->attachments, true)[$index] ?? null; abort_unless($attachment, 404);
        $c = DB::table('gmail_connections')->find($message->connection_id); abort_unless($c?->refresh_token, 422, 'Casella scollegata.');
        $client = new GmailRequests;
        try {
            if ($attachment['id']) $body = $client->get($c, 'messages/'.rawurlencode($message->gmail_id).'/attachments/'.rawurlencode($attachment['id']))['data'];
            else {
                $mail = $client->get($c, 'messages/'.rawurlencode($message->gmail_id), ['format' => 'full']);
                $find = function ($part) use (&$find, $attachment) {
                    if (($part['partId'] ?? '') === $attachment['part']) return $part['body']['data'] ?? null;
                    foreach ($part['parts'] ?? [] as $child) { $data = $find($child); if ($data !== null) return $data; }
                    return null;
                };
                $body = $find($mail['payload']);
            }
            $bytes = GmailRequests::decode($body ?? '');
        } catch (\Throwable) { abort(422, 'Allegato non disponibile. Verifica la casella Gmail.'); }
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '_', $attachment['name']);
        return response()->streamDownload(fn () => print($bytes), $name, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
