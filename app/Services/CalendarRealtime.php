<?php

namespace App\Services;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;
use Pusher\Pusher;

class CalendarRealtime
{
    private bool $dirty = false;

    public function client(): Pusher
    {
        return new Pusher(
            (string) config('reverb.apps.apps.0.key'),
            (string) config('reverb.apps.apps.0.secret'),
            (string) config('reverb.apps.apps.0.app_id'),
            ['host' => '127.0.0.1', 'port' => (int) config('reverb.servers.reverb.port'),
                'scheme' => 'http', 'useTLS' => false, 'timeout' => 2],
        );
    }

    public function observe(QueryExecuted $query): void
    {
        if (! config('calendar-realtime.enabled')) return;
        // Query-builder writes are also used by API and calendar integrations.
        if (! preg_match('/^\s*(?:insert(?:\s+ignore)?\s+into|replace\s+into|update|delete\s+from)\s+[`"]?(tasks|task_assignees|task_followers|task_dependencies|task_comments|task_activity|projects|clients|services)[`"]?(?=\s|\()/i', $query->sql)) return;
        $mark = function () { $this->dirty = true; };
        if ($query->connection->transactionLevel() > 0) {
            $query->connection->afterCommit($mark);
        } else {
            $mark();
        }
    }

    public function flush(): void
    {
        if (! $this->dirty) return;
        $this->dirty = false;
        try {
            // No task identifiers or content leave the authorization boundary.
            $this->client()->trigger('private-calendar', 'calendar.changed', []);
        } catch (\Throwable $error) {
            Log::warning('Calendar realtime delivery failed', ['exception' => $error::class]);
        }
    }
}
