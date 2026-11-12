<?php

namespace App\Services;

use Carbon\CarbonImmutable as Date;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TeamleaderSync
{
    public const LOCK = 'centro:teamleader';

    public function run(): ?array
    {
        return Cache::lock(self::LOCK, 1800)->get(function () {
            $s = DB::table('teamleader_settings')->find(1);
            if (! $s?->enabled || ! $s->refresh_token || ! $s->import_from) return null;
            $deadline = microtime(true) + 1500;
            $checkDeadline = function () use ($deadline) {
                if (microtime(true) > $deadline) throw new RuntimeException('Tempo di lettura esaurito.');
            };
            try {
                $this->deliverNotices();
                $api = new TeamleaderPreviewClient;
                $me = $api->read('users.me', []);
                $account = data_get($me, 'account.id');
                if (! is_string($account) || ! $account || ($s->account_id && $s->account_id !== $account)) throw new RuntimeException('Account Teamleader diverso.');
                $events = []; $page = 1;
                do {
                    $checkDeadline();
                    $batch = $api->read('meetings.list', ['filter' => ['start_date' => $s->import_from, 'end_date' => Date::now('Europe/Rome')->addMonths($s->months_ahead)->toDateString()],
                        'page' => ['size' => 100, 'number' => $page++], 'sort' => [['field' => 'scheduled_at', 'order' => 'asc']]]);
                    if (! is_array($batch) || ! array_is_list($batch)) throw new RuntimeException('Elenco non valido.');
                    foreach ($batch as $m) {
                        if (empty($m['id']) || isset($events[$m['id']])) throw new RuntimeException('Elenco cambiato durante la lettura.');
                        $events[$m['id']] = $m;
                    }
                } while (count($batch) === 100);
                $cancelled = [];
                // Absence from a date-filtered list is not deletion: check every missing linked record.
                $known = DB::table('tasks')->where('teamleader_account_id', $account)->whereNull('teamleader_cancelled_at')->get();
                foreach ($known as $task) {
                    if (isset($events[$task->teamleader_meeting_id])) continue;
                    $checkDeadline();
                    $meeting = $api->read('meetings.info', ['id' => $task->teamleader_meeting_id]);
                    if ($meeting === null) $cancelled[] = $task->teamleader_meeting_id;
                    elseif (($meeting['id'] ?? null) === $task->teamleader_meeting_id) $events[$meeting['id']] = $meeting;
                    else throw new RuntimeException('Identita meeting non valida.');
                }
                $people = [];
                foreach ($events as $m) {
                    if (! is_array($m['attendees'] ?? null)) throw new RuntimeException('Partecipanti mancanti.');
                    foreach ($m['attendees'] as $a) {
                        if (($a['type'] ?? '') !== 'user') continue;
                        if (empty($a['id'])) throw new RuntimeException('Partecipante non valido.');
                        if (! isset($people[$a['id']])) {
                            $checkDeadline();
                            $people[$a['id']] = $api->read('users.info', ['id' => $a['id']]);
                        }
                    }
                }
                $result = $this->reconcile($s, $account, array_values($events), $people, $cancelled);
                $this->deliverNotices();
                return $result;
            } catch (Throwable $e) {
                DB::table('teamleader_settings')->where('id', 1)->update(['last_synced_at' => now(),
                    'last_error' => 'Sincronizzazione non completata. Nessuna modifica parziale applicata. Verifica collegamento e permessi; nuovo tentativo tra 5 minuti.']);
                logger()->warning('Teamleader sync failed', ['exception' => get_class($e)]);
                return ['errors' => 1];
            }
        });
    }

    public function reconcile(object $s, string $account, array $events, array $remoteUsers, array $cancelled): array
    {
        $users = DB::table('users')->where('account_status', 'active')->get(['id', 'email'])->groupBy(fn ($u) => strtolower(trim($u->email)));
        $projected = []; $unmatched = [];
        foreach ($events as $m) {
            if (empty($m['id']) || empty($m['scheduled_at']) || ! is_array($m['attendees'] ?? null)
                || ($m['duration']['unit'] ?? '') !== 'min' || ! is_numeric($m['duration']['value'] ?? null)
                || $m['duration']['value'] <= 0 || ! in_array($m['status'] ?? '', ['open', 'done'], true)) throw new RuntimeException('Meeting incompleto.');
            $start = Date::parse($m['scheduled_at'])->setTimezone('Europe/Rome');
            $end = $start->addSeconds((int) round($m['duration']['value'] * 60));
            $assignees = [];
            foreach ($m['attendees'] as $a) {
                if (($a['type'] ?? '') !== 'user') continue;
                $remote = $remoteUsers[$a['id']] ?? null;
                if (! $remote) throw new RuntimeException('Utente Teamleader non disponibile.');
                $email = strtolower(trim($remote['email'] ?? ''));
                $matches = $users->get($email, collect());
                if ($email && $matches->count() === 1 && ($remote['status'] ?? '') === 'active') $assignees[] = $matches->first()->id;
                else $unmatched[$email ?: $a['id']] = true;
            }
            $assignees = array_values(array_unique($assignees)); sort($assignees);
            $customer = $m['customer'] ?? null;
            $client = $customer ? DB::table('report_client_links')->where('teamleader_id', $customer['id'])->where('source_type', $customer['type'])->value('client_id') : null;
            $values = [
                'title' => Str::limit($m['title'] ?? 'Meeting Teamleader', 255, ''),
                'description' => nl2br(e(html_entity_decode(strip_tags($m['description'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
                'task_type' => 'meeting', 'start_date' => $start->toDateString(), 'due_date' => $end->subSecond()->toDateString(), 'due_time' => $start->format('H:i:s'),
                'teamleader_account_id' => $account, 'teamleader_meeting_id' => $m['id'],
                'teamleader_identity' => hash('sha256', json_encode([$account, $m['id']], JSON_THROW_ON_ERROR)),
                'teamleader_starts_at' => $start->utc()->format('Y-m-d H:i:s'), 'teamleader_ends_at' => $end->utc()->format('Y-m-d H:i:s'), 'teamleader_cancelled_at' => null,
            ];
            $values['teamleader_revision'] = hash('sha256', json_encode([$values, $assignees], JSON_THROW_ON_ERROR));
            $projected[$m['id']] = [$values, $assignees, $client, $m['status'] === 'done' ? 'done' : 'todo'];
        }
        return DB::transaction(function () use ($s, $account, $projected, $cancelled, $unmatched) {
            $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'cancelled' => 0, 'errors' => 0, 'unmatched' => array_keys($unmatched)];
            $existing = DB::table('tasks')->where('teamleader_account_id', $account)->lockForUpdate()->get()->keyBy('teamleader_meeting_id');
            foreach ($projected as $externalId => [$values, $assignees, $client, $status]) {
                $old = $existing->get($externalId);
                $complete = $old && $status === 'done' && $old->status !== 'done';
                if ($old && ! $complete && $old->teamleader_revision === $values['teamleader_revision'] && ! $old->teamleader_cancelled_at) { $result['unchanged']++; continue; }
                // Remote completion is authoritative; remote open never resets local status.
                if ($complete) $values['status'] = 'done';
                $id = $old->id ?? (string) Str::uuid();
                $previous = $old ? DB::table('task_assignees')->where('task_id', $id)->pluck('user_id')->all() : [];
                if ($old) DB::table('tasks')->where('id', $id)->update($values + ['updated_at' => now()]);
                else DB::table('tasks')->insert($values + ['id' => $id, 'client_id' => $client, 'status' => $status, 'priority' => 'medium',
                    'created_by' => $s->connected_by, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('task_assignees')->where('task_id', $id)->delete();
                foreach ($assignees as $person) DB::table('task_assignees')->insert(['id' => (string) Str::uuid(), 'task_id' => $id, 'user_id' => $person, 'created_at' => now(), 'updated_at' => now()]);
                $result[$old ? 'updated' : 'created']++;
                if ($s->last_snapshot_at) $this->notice($id, array_merge($previous, $assignees), $complete ? 'Meeting completato su Teamleader' : ($old ? 'Meeting aggiornato da Teamleader' : 'Nuovo meeting da Teamleader'));
            }
            foreach (array_unique($cancelled) as $externalId) {
                $old = $existing->get($externalId);
                if (! $old || $old->teamleader_cancelled_at || isset($projected[$externalId])) continue;
                DB::table('tasks')->where('id', $old->id)->update(['teamleader_cancelled_at' => now(), 'updated_at' => now()]);
                $this->notice($old->id, DB::table('task_assignees')->where('task_id', $old->id)->pluck('user_id')->all(), 'Meeting annullato su Teamleader');
                $result['cancelled']++;
            }
            DB::table('teamleader_settings')->where('id', 1)->update(['account_id' => $account, 'last_synced_at' => now(), 'last_snapshot_at' => now(),
                'last_error' => null, 'last_summary' => json_encode($result), 'requested_at' => null, 'updated_at' => now()]);
            return $result;
        });
    }

    private function notice(string $task, array $people, string $message): void
    {
        $people = array_values(array_unique(array_merge($people, DB::table('task_followers')->where('task_id', $task)->pluck('user_id')->all())));
        if ($people) DB::table('teamleader_notices')->insert(['id' => (string) Str::uuid(), 'task_id' => $task, 'user_ids' => json_encode($people), 'message' => $message, 'created_at' => now(), 'updated_at' => now()]);
    }
    private function deliverNotices(): void
    {
        foreach (DB::table('teamleader_notices')->whereNull('delivered_at')->get() as $notice) {
            try {
                app(CentroNotificationService::class)->notifyUsers(json_decode($notice->user_ids, true), null, 'task_updated', $notice->message, $notice->task_id);
                DB::table('teamleader_notices')->where('id', $notice->id)->update(['delivered_at' => now()]);
            } catch (Throwable) { logger()->warning('Teamleader notification pending'); }
        }
    }
}
