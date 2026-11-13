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
        if ($status === 'pending') $q->whereExists(fn ($m) => $m->selectRaw('1')->from('mail_request_messages')->whereColumn('request_id', 'mr.id')->whereNull('disposition'));
        elseif (in_array($status, ['converted', 'discarded'], true)) $q->where('mr.status', $status);
        TextSearch::apply($q, ['mr.subject', 'mr.sender', 'u.name'], mb_substr((string) $r->query('search', ''), 0, 255));
        return Inertia::render('Centro/MailRequests', ['requests' => $q->orderByDesc('mr.updated_at')->paginate(20)->withQueryString(), 'filters' => $r->only('search', 'status', 'mine')]);
    }
    public function show(Request $r, string $id)
    {
        $this->authorizeManager($r); $record = DB::table('mail_requests')->find($id); abort_unless($record, 404);
        return response()->json(['record' => $record, 'ai_configured' => app(\App\Services\AiAgencyService::class)->isConfigured(), 'internal_emails' => DB::table('users')->pluck('email'),
            'messages' => DB::table('mail_request_messages')->where('request_id', $id)->orderBy('received_at')->get()->map(function ($m) { $m->attachments = json_decode($m->attachments, true); $m->body = \App\Services\MailMessageContent::current($m->body); return $m; }),
            'clients' => DB::table('clients')->where('active', true)->orderBy('name')->get(['id', 'name', 'parent_client_id']),
            'projects' => DB::table('projects')->where('status', '!=', 'archived')->orderBy('name')->get(['id', 'name', 'client_id', 'service_id']),
            'services' => DB::table('services')->orderBy('name')->get(['id', 'name']),
            'tasks' => DB::table('tasks')->whereNull('google_cancelled_at')->whereNull('teamleader_cancelled_at')->orderByDesc('created_at')->get(['id', 'title']),
            'users' => DB::table('users')->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')->where('users.account_status', 'active')->orderBy('users.name')->get(['users.id', 'users.name', 'profiles.avatar_url']),
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
        $data = $r->validate(['version' => 'required|integer', 'action' => 'required|in:create,link,discard', 'task_id' => 'nullable|uuid|exists:tasks,id',
            'message_ids' => 'required|array|min:1', 'message_ids.*' => 'required|uuid|distinct']);
        $taskId = DB::transaction(function () use ($r, $id, $data) {
            $item = DB::table('mail_requests')->lockForUpdate()->find($id); abort_unless($item, 404);
            abort_unless($item->reviewer_id === $r->user()->id, 409, 'Prendi prima in carico la richiesta.');
            abort_unless($item->version === $data['version'], 409, 'Sono arrivate nuove email. Riapri la richiesta prima di confermare.');
            $messages = DB::table('mail_request_messages')->where('request_id', $id)->whereIn('id', $data['message_ids'])->whereNull('disposition')->orderBy('received_at')->lockForUpdate()->get();
            abort_unless($messages->count() === count($data['message_ids']), 409, 'Uno dei messaggi non e disponibile o e gia stato elaborato.');
            $task = null;
            if ($data['action'] === 'create') {
                $r->merge(['task_type' => $r->input('task_type', 'ongoing')]);
                $payload = $r->validate(['title' => 'required|string|max:255', 'description' => 'nullable|string', 'client_id' => 'required|uuid|exists:clients,id',
                    'task_type' => 'required|in:ongoing,task', 'project_id' => 'required_if:task_type,task|nullable|uuid|exists:projects,id', 'service_id' => 'nullable|uuid|exists:services,id', 'priority' => 'required|in:low,medium,high,urgent',
                    'subtasks' => 'sometimes|array', 'subtasks.*' => 'required|string|max:255',
                    'start_date' => 'nullable|date_format:Y-m-d', 'due_date' => 'nullable|date_format:Y-m-d', 'assignee_ids' => 'required|array|min:1', 'assignee_ids.*' => 'uuid|exists:users,id']);
                $client = DB::table('clients')->find($payload['client_id']);
                abort_unless($client->active, 422, 'Seleziona un cliente attivo.');
                if ($payload['task_type'] === 'task') {
                    $project = DB::table('projects')->find($payload['project_id']);
                    abort_unless($project->status !== 'archived' && in_array($project->client_id, [$client->id, $client->parent_client_id], true), 422, 'Il progetto deve appartenere al cliente selezionato.');
                } else { $payload['project_id'] = null; }
                $subtasks = $payload['subtasks'] ?? [];
                unset($payload['subtasks']);
                $sub = Request::create('/tasks', 'POST', $payload + ['status' => 'todo']);
                $sub->setUserResolver(fn () => $r->user()); $sub->setLaravelSession($r->session());
                $sub->attributes->set('common_api_write', true);
                $response = app(CentroPageController::class)->store($sub, 'tasks');
                abort_unless($response instanceof \Illuminate\Http\JsonResponse && $response->status() === 201, 422, 'Creazione non riuscita.');
                $task = $response->getData(true)['id'];
                foreach ($subtasks as $title) {
                    $child = Request::create('/tasks/'.$task.'/subtasks', 'POST', ['title' => $title, 'priority' => $payload['priority'], 'assignee_ids' => $payload['assignee_ids']]);
                    $child->setUserResolver(fn () => $r->user()); $child->setLaravelSession($r->session());
                    app(CentroPageController::class)->storeSubtask($child, $task);
                }
            } elseif ($data['action'] === 'link') {
                abort_unless($data['task_id'] ?? null, 422, 'Seleziona una task.');
                $role = DB::table('user_roles')->where('user_id', $r->user()->id)->value('role');
                abort_unless(app(RolePermissionService::class)->allows($role ?? '', 'tasks.update'), 403);
                $task = $data['task_id'];
                abort_unless(DB::table('tasks')->where('id', $task)->whereNull('google_cancelled_at')->whereNull('teamleader_cancelled_at')->exists(), 422, 'La task e stata cancellata dalla fonte.');
                $content = $messages->map(fn ($m) => '<p><strong>'.e($m->subject).'</strong><br>'.e($m->sender).' · '.e($m->received_at).'<br>'.nl2br(e(\App\Services\MailMessageContent::current($m->body))).'</p>')->implode('');
                $sub = Request::create('/tasks/'.$task.'/comments', 'POST', ['content' => $content]);
                $sub->setUserResolver(fn () => $r->user()); $sub->setLaravelSession($r->session());
                $sub->attributes->set('common_api_write', true);
                app(CentroPageController::class)->storeTaskComment($sub, $task);
            }
            DB::table('mail_request_messages')->whereIn('id', $messages->pluck('id'))->update([
                'disposition' => $data['action'] === 'discard' ? 'ignored' : 'linked', 'task_id' => $task,
                'handled_by' => $r->user()->id, 'handled_at' => now(), 'updated_at' => now(),
            ]);
            $pending = DB::table('mail_request_messages')->where('request_id', $id)->whereNull('disposition')->exists();
            $linked = $task ?: $item->task_id;
            DB::table('mail_requests')->where('id', $id)->update(['task_id' => $linked, 'status' => $pending ? 'pending' : ($linked ? 'converted' : 'discarded'), 'has_updates' => $pending, 'version' => $item->version + 1, 'updated_at' => now()]);
            return $task;
        });
        return response()->json(['task_id' => $taskId]);
    }
    public function prepare(Request $r, string $id)
    {
        $this->authorizeManager($r);
        $data = $r->validate(['message_ids' => 'required|array|min:1', 'message_ids.*' => 'required|uuid|distinct', 'ai' => 'sometimes|boolean', 'version' => 'required_if:ai,true|integer']);
        $messages = DB::table('mail_request_messages')->where('request_id', $id)->whereIn('id', $data['message_ids'])->whereNull('disposition')->orderBy('received_at')->get();
        abort_unless($messages->count() === count($data['message_ids']), 409, 'Aggiorna la richiesta: messaggi non disponibili.');
        $messages->each(function ($message) { $message->body = \App\Services\MailMessageContent::current($message->body); });
        $draft = ['title' => $messages->last()->subject, 'description' => $messages->pluck('body')->implode("\n\n---\n\n"), 'warnings' => [], 'subtasks' => []];
        if ($r->boolean('ai')) {
            $record = DB::table('mail_requests')->find($id);
            abort_unless($record && $record->reviewer_id === $r->user()->id && $record->version === $data['version'], 409, 'Prendi in carico e aggiorna la richiesta prima di preparare con AI.');
            $limit = 'gmail:ai:'.$r->user()->id;
            abort_if(\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($limit, 6), 429, 'Troppe proposte AI ravvicinate. Attendi un minuto.');
            $lock = Cache::lock('gmail:ai:request:'.$id, 90);
            abort_unless($lock->get(), 409, 'Una proposta AI e gia in preparazione.');
            try {
                \Illuminate\Support\Facades\RateLimiter::hit($limit, 60);
                try { $draft = app(\App\Services\AiAgencyService::class)->mailProposal($messages); }
                catch (\RuntimeException $e) { abort(422, $e->getMessage()); }
                $fresh = DB::table('mail_requests')->find($id);
                abort_unless($fresh && $fresh->version === $record->version && $fresh->reviewer_id === $record->reviewer_id, 409, 'La richiesta e cambiata durante la preparazione. Aggiornala prima di continuare.');
            } finally { $lock->release(); }
        }
        $match = app(\App\Services\MailClientMatcher::class)->match($messages, app(CentroPageController::class)->sharedClientSites($r));
        $projects = $match['client_id'] ? DB::table('projects')->where('client_id', $match['client_id'])->where('status', '!=', 'archived')->pluck('id') : collect();
        return response()->json($match + $draft + ['ai_generated' => $r->boolean('ai'), 'project_id' => $projects->count() === 1 ? $projects->first() : null]);
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
