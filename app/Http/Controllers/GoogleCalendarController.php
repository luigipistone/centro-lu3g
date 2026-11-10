<?php

namespace App\Http\Controllers;

use App\Services\GoogleCalendarClient;
use App\Services\GoogleCalendarSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class GoogleCalendarController extends Controller
{
    private function locked(callable $callback)
    {
        $lock = Cache::lock(GoogleCalendarSync::LOCK, 120);
        abort_unless($lock->get(), 409, 'Sincronizzazione in corso. Attendi il completamento prima di modificare il collegamento.');
        try { return $callback(); } finally { $lock->release(); }
    }

    private function authorizeAdmin(Request $r): void
    {
        abort_unless($r->user() && DB::table('user_roles')->where('user_id', $r->user()->id)->where('role', 'superadmin')->exists(), 403);
    }

    public function show(Request $r)
    {
        $this->authorizeAdmin($r);
        $s = DB::table('google_calendar_settings')->first();
        return response()->json([
            'client_id' => $s->client_id ?? '', 'has_secret' => (bool) ($s->client_secret ?? false),
            'connected' => (bool) ($s->refresh_token ?? false), 'enabled' => (bool) ($s->enabled ?? false),
            'calendar_id' => $s->calendar_id ?? '', 'calendar_name' => $s->calendar_name ?? '',
            'import_from' => $s->import_from ?? now()->startOfMonth()->toDateString(), 'months_ahead' => $s->months_ahead ?? 12,
            'last_synced_at' => $s->last_synced_at ?? null, 'last_error' => $s->last_error ?? null,
            'watch_error' => $s->watch_error ?? null, 'summary' => json_decode($s->last_summary ?? 'null', true),
            'pending' => (bool) ($s->enabled ?? false) && (bool) ($s->requested_at ?? false) && ! ($s->last_error ?? null), 'redirect_uri' => route('settings.google-calendar.callback'),
        ]);
    }

    public function credentials(Request $r)
    {
        $this->authorizeAdmin($r);
        $data = $r->validate(['client_id' => 'required|string|max:255', 'client_secret' => 'nullable|string|max:4000']);
        return $this->locked(function () use ($r, $data) {
            $s = DB::table('google_calendar_settings')->first();
            abort_if($s?->refresh_token, 422, 'Scollega Google prima di cambiare credenziali.');
            abort_if(empty($data['client_secret']) && ! $s?->client_secret, 422, 'Inserisci il segreto client Google.');
            $values = ['client_id' => trim($data['client_id']), 'updated_at' => now()];
            if (! empty($data['client_secret'])) $values['client_secret'] = Crypt::encryptString(trim($data['client_secret']));
            DB::table('google_calendar_settings')->updateOrInsert(['id' => 1], $values + ($s ? [] : ['created_at' => now()]));
            return $this->show($r);
        });
    }

    public function connect(Request $r)
    {
        $this->authorizeAdmin($r);
        $s = DB::table('google_calendar_settings')->first();
        abort_unless($s?->client_secret, 422, 'Configura prima le credenziali Google.');
        $state = Str::random(64);
        $r->session()->put('google_calendar_oauth', ['state' => $state, 'expires' => now()->addMinutes(10)->timestamp, 'client' => $s->client_id]);
        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $s->client_id, 'redirect_uri' => route('settings.google-calendar.callback'),
            'response_type' => 'code', 'scope' => GoogleCalendarClient::SCOPES, 'access_type' => 'offline',
            'prompt' => 'consent', 'state' => $state,
        ]));
    }

    public function callback(Request $r)
    {
        $this->authorizeAdmin($r);
        $state = $r->session()->pull('google_calendar_oauth');
        abort_unless($state && $state['expires'] >= now()->timestamp && hash_equals($state['state'], (string) $r->query('state')), 403, 'Collegamento scaduto. Ripeti la connessione.');
        if ($r->query('error')) return redirect('/settings?tab=google-calendar')->with('status', 'Collegamento Google annullato.');
        $r->validate(['code' => 'required|string|max:4096']);
        try {
            $this->locked(function () use ($r, $state) {
                $s = DB::table('google_calendar_settings')->first();
                abort_unless($s->client_id === $state['client'], 409);
                $tokens = (new GoogleCalendarClient)->exchange($s, ['code' => $r->query('code'), 'grant_type' => 'authorization_code', 'redirect_uri' => route('settings.google-calendar.callback')]);
                if (empty($tokens['refresh_token'])) throw new \RuntimeException('Credenziale offline assente');
                DB::table('google_calendar_settings')->where('id', 1)->update(['refresh_token' => Crypt::encryptString($tokens['refresh_token']), 'connected_by' => $r->user()->id, 'last_error' => null, 'updated_at' => now()]);
            });
        } catch (Throwable) {
            return redirect('/settings?tab=google-calendar')->with('status', 'Connessione non riuscita. Verifica credenziali, consenso e indirizzo di reindirizzamento Google.');
        }
        return redirect('/settings?tab=google-calendar')->with('status', 'Google collegato. Seleziona il calendario da sincronizzare.');
    }

    public function calendars(Request $r)
    {
        $this->authorizeAdmin($r);
        $s = DB::table('google_calendar_settings')->first();
        abort_unless($s?->refresh_token, 422, 'Collega prima Google.');
        try {
            $calendars = (new GoogleCalendarClient)->pages($s, 'users/me/calendarList', ['minAccessRole' => 'reader', 'maxResults' => 250])['items'];
            return response()->json(['calendars' => collect($calendars)->map(fn ($c) => ['value' => $c['id'], 'label' => $c['summaryOverride'] ?? $c['summary'], 'timezone' => $c['timeZone'] ?? 'Europe/Rome'])->values()]);
        } catch (Throwable) { return response()->json(['message' => 'Impossibile leggere i calendari. Verifica il collegamento Google.'], 422); }
    }

    public function update(Request $r)
    {
        $this->authorizeAdmin($r);
        $data = $r->validate(['calendar_id' => 'required|string|max:255', 'import_from' => 'required|date_format:Y-m-d|before_or_equal:today', 'months_ahead' => 'required|integer|min:1|max:24', 'enabled' => 'required|boolean']);
        return $this->locked(function () use ($r, $data) {
            $s = DB::table('google_calendar_settings')->first();
            abort_unless($s?->refresh_token, 422, 'Collega prima Google.');
            // A calendar switch is explicit: old imported tasks remain in history, never as duplicates.
            try { $calendar = (new GoogleCalendarClient)->request($s, 'GET', 'users/me/calendarList/'.rawurlencode($data['calendar_id'])); }
            catch (Throwable) { abort(422, 'Calendario non accessibile.'); }
            abort_unless(in_array($calendar['accessRole'] ?? '', ['reader', 'writer', 'owner']), 422, 'Serve accesso in lettura agli eventi.');
            DB::transaction(function () use ($data, $s, $calendar) {
                $changed = $s->calendar_id !== $data['calendar_id'];
                if ($changed && $s->calendar_id) DB::table('tasks')->where('google_calendar_id', $s->calendar_id)->whereNull('google_cancelled_at')->update(['google_cancelled_at' => now(), 'updated_at' => now()]);
                DB::table('google_calendar_settings')->where('id', 1)->update($data + [
                    'calendar_name' => $calendar['summaryOverride'] ?? $calendar['summary'], 'timezone' => $calendar['timeZone'] ?? 'Europe/Rome',
                    'sync_token' => $changed ? null : $s->sync_token, 'last_snapshot_at' => $changed ? null : $s->last_snapshot_at,
                    'channel_expires_at' => $changed ? null : $s->channel_expires_at, 'requested_at' => now(), 'updated_at' => now(),
                ]);
            });
            return $this->show($r);
        });
    }

    public function sync(Request $r)
    {
        $this->authorizeAdmin($r);
        abort_unless(DB::table('google_calendar_settings')->where('id', 1)->where('enabled', true)->exists(), 422, 'Attiva prima la sincronizzazione.');
        DB::table('google_calendar_settings')->where('id', 1)->update(['requested_at' => now()]);
        return response()->json(['message' => 'Sincronizzazione richiesta.']);
    }

    public function disconnect(Request $r)
    {
        $this->authorizeAdmin($r);
        return $this->locked(function () use ($r) {
            $s = DB::table('google_calendar_settings')->first();
            if ($s?->refresh_token) {
                try { Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/revoke', ['token' => Crypt::decryptString($s->refresh_token)]); } catch (Throwable) { }
            }
            DB::table('google_calendar_settings')->where('id', 1)->update(['refresh_token' => null, 'enabled' => false, 'channel_token' => null, 'requested_at' => null, 'sync_token' => null, 'channel_expires_at' => null]);
            return $this->show($r);
        });
    }

    public function watch(Request $r)
    {
        $s = DB::table('google_calendar_settings')->first();
        abort_unless($s && $s->enabled && $s->channel_token && hash_equals($s->channel_id ?? '', (string) $r->header('X-Goog-Channel-ID'))
            && hash_equals($s->channel_token, (string) $r->header('X-Goog-Channel-Token'))
            && (! $s->resource_id || hash_equals($s->resource_id, (string) $r->header('X-Goog-Resource-ID'))), 403);
        DB::table('google_calendar_settings')->where('id', 1)->update(['requested_at' => now()]);
        return response()->noContent();
    }
}
