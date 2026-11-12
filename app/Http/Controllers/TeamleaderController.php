<?php

namespace App\Http\Controllers;

use App\Services\TeamleaderPreviewClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TeamleaderController extends Controller
{
    private function authorized(Request $r): void
    {
        abort_unless($r->user() && DB::table('user_roles')->where('user_id', $r->user()->id)->where('role', 'superadmin')->exists(), 403);
    }
    private function locked(callable $callback)
    {
        $lock = Cache::lock(\App\Services\TeamleaderSync::LOCK, 1800);
        abort_unless($lock->get(), 409, 'Teamleader occupato. Riprova tra poco.');
        try { return $callback(); } finally { $lock->release(); }
    }
    public function show(Request $r)
    {
        $this->authorized($r);
        $s = DB::table('teamleader_settings')->find(1);
        return response()->json(['client_id' => $s?->client_id ?? '', 'has_secret' => (bool) $s?->client_secret,
            'connected' => (bool) $s?->refresh_token, 'connection_id' => $s?->connection_id,
            'enabled' => (bool) $s?->enabled, 'import_from' => $s?->import_from ?? now('Europe/Rome')->toDateString(), 'months_ahead' => $s?->months_ahead ?? 12,
            'last_synced_at' => $s?->last_synced_at ? \Carbon\CarbonImmutable::parse($s->last_synced_at, config('app.timezone'))->toIso8601String() : null,
            'last_error' => $s?->last_error, 'summary' => json_decode($s?->last_summary ?? 'null', true),
            'pending' => (bool) $s?->requested_at && ! $s?->last_error,
            'redirect_uri' => route('settings.teamleader.callback')]);
    }
    public function credentials(Request $r)
    {
        $this->authorized($r);
        $data = $r->validate(['client_id' => 'required|string|max:255', 'client_secret' => 'nullable|string|max:4000']);
        return $this->locked(function () use ($r, $data) {
            $s = DB::table('teamleader_settings')->find(1);
            abort_if($s?->refresh_token, 422, 'Scollega Teamleader prima di modificare le credenziali.');
            abort_if(empty($data['client_secret']) && (! $s?->client_secret || $s->client_id !== trim($data['client_id'])), 422, 'Inserisci il Client secret.');
            DB::table('teamleader_settings')->updateOrInsert(['id' => 1], ['client_id' => trim($data['client_id']),
                'client_secret' => empty($data['client_secret']) ? $s->client_secret : Crypt::encryptString(trim($data['client_secret'])),
                'connection_id' => (string) Str::uuid(), 'updated_at' => now()] + ($s ? [] : ['created_at' => now()]));
            return $this->show($r);
        });
    }
    public function connect(Request $r)
    {
        $this->authorized($r);
        $s = DB::table('teamleader_settings')->find(1);
        abort_unless($s?->client_secret, 422, 'Salva prima le credenziali.');
        $state = Str::random(64);
        $r->session()->put('teamleader_oauth', ['state' => $state, 'expires' => now()->addMinutes(10)->timestamp, 'connection' => $s->connection_id]);
        return redirect()->away('https://focus.teamleader.eu/oauth2/authorize?'.http_build_query([
            'client_id' => $s->client_id, 'redirect_uri' => route('settings.teamleader.callback'), 'response_type' => 'code', 'state' => $state,
        ]));
    }
    public function callback(Request $r, TeamleaderPreviewClient $api)
    {
        $this->authorized($r);
        $state = $r->session()->pull('teamleader_oauth');
        abort_unless($state && $state['expires'] >= now()->timestamp && hash_equals($state['state'], (string) $r->query('state')), 403);
        if ($r->query('error')) return redirect('/settings?tab=teamleader')->with('status', 'Collegamento annullato.');
        $r->validate(['code' => 'required|string|max:4096']);
        try {
            $this->locked(function () use ($r, $state, $api) {
                $s = DB::table('teamleader_settings')->find(1);
                abort_unless($s && $s->connection_id === $state['connection'], 409);
                $api->exchange($s, ['grant_type' => 'authorization_code', 'code' => $r->query('code'), 'redirect_uri' => route('settings.teamleader.callback')]);
                DB::table('teamleader_settings')->where('id', 1)->update(['connected_by' => $r->user()->id]);
            });
        } catch (\Throwable) {
            return redirect('/settings?tab=teamleader')->with('status', 'Collegamento non riuscito. Verifica credenziali e URI di reindirizzamento.');
        }
        return redirect('/settings?tab=teamleader')->with('status', 'Teamleader collegato. Puoi eseguire la simulazione.');
    }
    public function disconnect(Request $r)
    {
        $this->authorized($r);
        return $this->locked(function () use ($r) {
            DB::table('teamleader_settings')->where('id', 1)->update(['access_token' => null, 'refresh_token' => null, 'expires_at' => null, 'enabled' => false, 'requested_at' => null, 'connection_id' => (string) Str::uuid(), 'updated_at' => now()]);
            $r->session()->forget('teamleader_oauth');
            return $this->show($r);
        });
    }
    public function simulate(Request $r, TeamleaderPreviewClient $api)
    {
        $this->authorized($r);
        $data = $r->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from', 'page' => 'required|integer|min:1', 'connection_id' => 'required|uuid']);
        return $this->locked(function () use ($api, $data) {
            $s = DB::table('teamleader_settings')->find(1);
            abort_unless($s?->refresh_token && $s->connection_id === $data['connection_id'], 409, 'Collegamento cambiato. Ricarica la pagina.');
            try {
                $meetings = $api->read('meetings.list', ['filter' => ['start_date' => $data['from'], 'end_date' => $data['to']], 'page' => ['size' => 20, 'number' => $data['page']], 'sort' => [['field' => 'scheduled_at', 'order' => 'asc']]]);
                if (! array_is_list($meetings)) throw new RuntimeException('Risposta Teamleader non valida.');
                $users = DB::table('users')->where('account_status', 'active')->get(['id', 'name', 'email'])->groupBy(fn ($u) => strtolower(trim($u->email)));
                $rows = [];
                foreach ($meetings as $m) {
                    if (empty($m['id']) || empty($m['scheduled_at']) || ! is_array($m['attendees'] ?? null)) throw new RuntimeException('Meeting incompleto nella risposta.');
                    $assigned = []; $missing = [];
                    foreach ($m['attendees'] as $a) {
                        if (($a['type'] ?? '') !== 'user') continue;
                        if (empty($a['id'])) throw new RuntimeException('Partecipante non valido.');
                        $remote = Cache::remember('teamleader:user:'.$s->connection_id.':'.$a['id'], 300, fn () => $api->read('users.info', ['id' => $a['id']]));
                        $email = strtolower(trim($remote['email'] ?? ''));
                        $matches = $users->get($email, collect());
                        if ($email && $matches->count() === 1 && ($remote['status'] ?? '') === 'active') $assigned[$matches->first()->id] = $matches->first()->name;
                        else $missing[] = $email ?: ($remote['first_name'] ?? 'Partecipante senza email');
                    }
                    $customer = $m['customer'] ?? null;
                    $client = $customer ? DB::table('report_client_links')->join('clients', 'clients.id', '=', 'report_client_links.client_id')->where('teamleader_id', $customer['id'])->where('source_type', $customer['type'])->value('clients.name') : null;
                    $rows[] = ['id' => $m['id'], 'title' => $m['title'] ?? '', 'scheduled_at' => $m['scheduled_at'], 'duration' => $m['duration']['value'] ?? null,
                        'status' => $m['status'] ?? '', 'assignees' => array_values($assigned), 'unmatched' => array_values(array_unique($missing)),
                        'client' => $client, 'has_customer' => (bool) $customer];
                }
                return response()->json(['rows' => $rows, 'has_more' => count($meetings) === 20, 'read_only' => true]);
            } catch (RuntimeException $e) { return response()->json(['message' => $e->getMessage()], 502); }
        });
    }

    public function update(Request $r)
    {
        $this->authorized($r);
        $data = $r->validate(['enabled' => 'required|boolean', 'import_from' => 'required|date_format:Y-m-d', 'months_ahead' => 'required|integer|in:3,6,12,24']);
        return $this->locked(function () use ($r, $data) {
            abort_unless(DB::table('teamleader_settings')->where('id', 1)->whereNotNull('refresh_token')->exists(), 422, 'Collega prima Teamleader.');
            DB::table('teamleader_settings')->where('id', 1)->update($data + ['connected_by' => $r->user()->id, 'requested_at' => $data['enabled'] ? now() : null, 'last_error' => null, 'updated_at' => now()]);
            return $this->show($r);
        });
    }
    public function sync(Request $r)
    {
        $this->authorized($r);
        return $this->locked(function () use ($r) {
            abort_unless(DB::table('teamleader_settings')->where('id', 1)->where('enabled', true)->whereNotNull('refresh_token')->exists(), 422, 'Abilita prima la sincronizzazione.');
            DB::table('teamleader_settings')->where('id', 1)->update(['requested_at' => now(), 'last_error' => null]);
            return $this->show($r);
        });
    }
}
