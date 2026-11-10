<?php

namespace App\Services;

use Carbon\CarbonImmutable as Date;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GoogleCalendarSync
{
    public const LOCK = 'centro:google-calendar';

    public function run(): ?array
    {
        return Cache::lock(self::LOCK, 1800)->get(function () {
            $s = DB::table('google_calendar_settings')->first();
            if (! $s || ! $s->enabled || ! $s->refresh_token || ! $s->calendar_id) return null;
            $api = new GoogleCalendarClient;
            try {
                $this->deliverNotices();
                $this->renewWatch($s, $api);
                $path = 'calendars/'.rawurlencode($s->calendar_id).'/events';
                try {
                    $changes = $api->pages($s, $path, ['maxResults' => 2500, 'showDeleted' => 'true', 'singleEvents' => 'false']
                        + ($s->sync_token ? ['syncToken' => $s->sync_token] : []));
                } catch (RequestException $e) {
                    if ($e->response->status() !== 410) throw $e;
                    $s->sync_token = null;
                    $changes = $api->pages($s, $path, ['maxResults' => 2500, 'showDeleted' => 'true', 'singleEvents' => 'false']);
                }
                if (! $changes['syncToken']) throw new RuntimeException('Google non ha restituito il riferimento della sincronizzazione.');
                $snapshot = ! $s->sync_token || count($changes['items']) || $s->requested_at
                    || ! $s->last_snapshot_at || Date::parse($s->last_snapshot_at)->lt(now()->subDay());
                if (! $snapshot) {
                    DB::table('google_calendar_settings')->where('id', 1)->update(['sync_token' => $changes['syncToken'], 'last_synced_at' => now(), 'last_error' => null]);
                    return null;
                }
                $from = Date::parse($s->import_from, $s->timezone)->startOfDay();
                $until = Date::now($s->timezone)->addMonths($s->months_ahead)->endOfDay();
                // Google expands recurring series and exceptions; never infer deletions from a partial page.
                $events = $api->pages($s, $path, [
                    'maxResults' => 2500, 'singleEvents' => 'true', 'showDeleted' => 'true',
                    'timeMin' => $from->toRfc3339String(), 'timeMax' => $until->toRfc3339String(),
                    'timeZone' => $s->timezone,
                ])['items'];
                $result = $this->reconcile($s, $events, $changes['syncToken'], $from, $until);
                $this->deliverNotices();
                return $result;
            } catch (Throwable $e) {
                $message = $e instanceof RequestException
                    ? 'Google ha risposto con errore '.$e->response->status().'. Verifica collegamento e accesso al calendario.'
                    : 'Sincronizzazione non completata. Nessuna cancellazione applicata; nuovo tentativo automatico.';
                DB::table('google_calendar_settings')->where('id', 1)->update(['last_error' => $message, 'last_synced_at' => now()]);
                // Do not log HTTP payloads or OAuth secrets.
                logger()->warning('Google Calendar sync failed', ['exception' => get_class($e)]);
                return ['errors' => 1];
            }
        });
    }

    public function reconcile(object $s, array $events, string $token, Date $from, Date $until): array
    {
        $users = DB::table('users')->where('account_status', 'active')->get(['id', 'email'])->groupBy(fn ($u) => strtolower(trim($u->email)));
        $unmatched = []; $projected = [];
        foreach ($events as $event) {
            if (($event['status'] ?? '') === 'cancelled' || ($event['eventType'] ?? 'default') !== 'default') continue;
            if (empty($event['id']) || empty($event['start']) || empty($event['end'])) throw new RuntimeException('Evento incompleto.');
            $allDay = isset($event['start']['date']);
            $start = Date::parse($event['start']['dateTime'] ?? $event['start']['date'], $s->timezone)->setTimezone($s->timezone);
            $end = Date::parse($event['end']['dateTime'] ?? $event['end']['date'], $s->timezone)->setTimezone($s->timezone);
            if ($end->lte($start)) throw new RuntimeException('Durata evento non valida.');
            $people = $event['attendees'] ?? [];
            $organizer = $event['organizer']['email'] ?? null;
            if ($organizer && ! collect($people)->contains(fn ($p) => strtolower($p['email'] ?? '') === strtolower($organizer))) $people[] = ['email' => $organizer];
            $assignees = [];
            foreach ($people as $person) {
                if (($person['responseStatus'] ?? '') === 'declined' || ($person['resource'] ?? false)) continue;
                $email = strtolower(trim($person['email'] ?? ''));
                if (! $email) continue;
                $matches = $users->get($email, collect());
                if ($matches->count() === 1) $assignees[] = $matches->first()->id;
                else $unmatched[$email] = true;
            }
            $assignees = array_values(array_unique($assignees)); sort($assignees);
            $values = [
                'title' => Str::limit($event['summary'] ?? 'Meeting Google Calendar', 255, ''),
                // Imported HTML is escaped before it can reach Centro's rich-text renderers.
                'description' => nl2br(e(html_entity_decode(strip_tags($event['description'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
                'location' => Str::limit($event['location'] ?? '', 255, ''),
                'task_type' => 'meeting', 'start_date' => $start->toDateString(),
                'due_date' => $end->subSecond()->toDateString(), 'due_time' => $allDay ? null : $start->format('H:i:s'),
                'google_calendar_id' => $s->calendar_id, 'google_event_id' => $event['id'],
                'google_identity' => hash('sha256', json_encode([$s->calendar_id, $event['id']], JSON_THROW_ON_ERROR)),
                'google_event_url' => $this->safeUrl($event['htmlLink'] ?? null, 'calendar.google.com'),
                'google_meet_url' => $this->safeUrl($event['hangoutLink'] ?? null, 'meet.google.com'),
                'google_starts_at' => $start->utc()->format('Y-m-d H:i:s'), 'google_ends_at' => $end->utc()->format('Y-m-d H:i:s'),
                'google_all_day' => $allDay, 'google_cancelled_at' => null,
            ];
            $values['google_revision'] = hash('sha256', json_encode([$values, $assignees], JSON_THROW_ON_ERROR));
            $projected[$event['id']] = [$values, $assignees];
        }
        return DB::transaction(function () use ($s, $projected, $token, $from, $until, $unmatched) {
            $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'cancelled' => 0, 'errors' => 0, 'unmatched' => array_keys($unmatched)];
            $existing = DB::table('tasks')->where('google_calendar_id', $s->calendar_id)->get()->keyBy('google_event_id');
            foreach ($projected as $eventId => [$values, $people]) {
                $old = $existing->get($eventId);
                if ($old && $old->google_revision === $values['google_revision'] && ! $old->google_cancelled_at) { $result['unchanged']++; continue; }
                $id = $old->id ?? (string) Str::uuid();
                $previousPeople = $old ? DB::table('task_assignees')->where('task_id', $id)->pluck('user_id')->all() : [];
                if ($old) DB::table('tasks')->where('id', $id)->update($values + ['updated_at' => now()]);
                else DB::table('tasks')->insert($values + ['id' => $id, 'status' => 'todo', 'priority' => 'medium', 'created_by' => $s->connected_by, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('task_assignees')->where('task_id', $id)->delete();
                foreach ($people as $userId) DB::table('task_assignees')->insert(['id' => (string) Str::uuid(), 'task_id' => $id, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);
                $result[$old ? 'updated' : 'created']++;
                if ($s->last_snapshot_at && $values['google_ends_at'] > now()->format('Y-m-d H:i:s')) $this->notice($id, array_merge($previousPeople, $people), $old ? 'Meeting aggiornato da Google Calendar' : 'Nuovo meeting da Google Calendar');
            }
            foreach ($existing as $eventId => $old) {
                if (isset($projected[$eventId]) || $old->google_cancelled_at) continue;
                // Only reconcile the range that was successfully fetched, preserving historical meetings.
                if ($old->google_ends_at <= $from->utc()->format('Y-m-d H:i:s') || $old->google_starts_at >= $until->utc()->format('Y-m-d H:i:s')) continue;
                DB::table('tasks')->where('id', $old->id)->update(['google_cancelled_at' => now(), 'updated_at' => now()]);
                if ($s->last_snapshot_at) $this->notice($old->id, DB::table('task_assignees')->where('task_id', $old->id)->pluck('user_id')->all(), 'Meeting annullato o rimosso dal calendario Google sincronizzato');
                $result['cancelled']++;
            }
            $updates = ['sync_token' => $token, 'last_synced_at' => now(), 'last_snapshot_at' => now(), 'last_error' => null, 'last_summary' => json_encode($result), 'updated_at' => now()];
            // A webhook received during this run must not be lost.
            if (DB::table('google_calendar_settings')->where('id', 1)->value('requested_at') === $s->requested_at) $updates['requested_at'] = null;
            DB::table('google_calendar_settings')->where('id', 1)->update($updates);
            return $result;
        });
    }

    private function safeUrl(?string $url, string $host): ?string
    {
        return $url && parse_url($url, PHP_URL_SCHEME) === 'https' && parse_url($url, PHP_URL_HOST) === $host ? $url : null;
    }

    private function notice(string $taskId, array $people, string $message): void
    {
        $people = array_values(array_unique(array_merge($people, DB::table('task_followers')->where('task_id', $taskId)->pluck('user_id')->all())));
        if (! $people) return;
        DB::table('google_calendar_notices')->insert(['id' => (string) Str::uuid(), 'task_id' => $taskId, 'user_ids' => json_encode($people), 'message' => $message, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function deliverNotices(): void
    {
        foreach (DB::table('google_calendar_notices')->whereNull('delivered_at')->orderBy('created_at')->get() as $notice) {
            try {
                app(CentroNotificationService::class)->notifyUsers(json_decode($notice->user_ids, true), null, 'task_updated', $notice->message, $notice->task_id);
                DB::table('google_calendar_notices')->where('id', $notice->id)->update(['delivered_at' => now()]);
            } catch (Throwable) { logger()->warning('Google Calendar notification pending'); }
        }
        DB::table('google_calendar_notices')->where('delivered_at', '<', now()->subDays(30))->delete();
    }

    private function renewWatch(object $s, GoogleCalendarClient $api): void
    {
        if ($s->channel_expires_at && Date::parse($s->channel_expires_at)->gt(now()->addHours(12))) return;
        if (! str_starts_with(config('app.url'), 'https://')) return;
        $id = (string) Str::uuid(); $secret = Str::random(64);
        DB::table('google_calendar_settings')->where('id', 1)->update(['channel_id' => $id, 'channel_token' => $secret, 'resource_id' => null, 'channel_expires_at' => null]);
        try {
            $watch = $api->request($s, 'POST', 'calendars/'.rawurlencode($s->calendar_id).'/events/watch', [
                'id' => $id, 'type' => 'web_hook', 'address' => rtrim(config('app.url'), '/').'/api/google-calendar/watch', 'token' => $secret,
                'params' => ['ttl' => '604800'],
            ]);
            DB::table('google_calendar_settings')->where('id', 1)->update(['resource_id' => $watch['resourceId'], 'channel_expires_at' => Date::createFromTimestampMs($watch['expiration'])->format('Y-m-d H:i:s'), 'watch_error' => null]);
            if ($s->channel_id && $s->resource_id) {
                try { $api->request($s, 'POST', 'channels/stop', ['id' => $s->channel_id, 'resourceId' => $s->resource_id]); } catch (Throwable) { /* Old channel expires automatically. */ }
            }
        } catch (Throwable) {
            DB::table('google_calendar_settings')->where('id', 1)->update(['watch_error' => 'Notifiche Google non disponibili; resta attivo il controllo automatico ogni 5 minuti.']);
        }
    }
}
