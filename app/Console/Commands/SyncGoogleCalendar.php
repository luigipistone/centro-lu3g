<?php

namespace App\Console\Commands;

use App\Services\GoogleCalendarSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncGoogleCalendar extends Command
{
    protected $signature = 'centro:google-calendar-sync {--force : Sincronizza subito}';
    protected $description = 'Sincronizza i Meeting dal calendario Google collegato';

    public function handle(GoogleCalendarSync $sync): int
    {
        $s = DB::table('google_calendar_settings')->first();
        if (! $s || ! $s->enabled) return self::SUCCESS;
        if (! $this->option('force') && $s->last_synced_at && strtotime($s->last_synced_at) > now()->subMinutes($s->last_error ? 5 : ($s->requested_at ? 0 : 5))->timestamp) return self::SUCCESS;
        if ($this->option('force')) DB::table('google_calendar_settings')->where('id', 1)->update(['requested_at' => now()]);
        $result = $sync->run();
        $this->info(json_encode($result ?? ['status' => 'invariato o già in esecuzione']));
        return ($result['errors'] ?? 0) ? self::FAILURE : self::SUCCESS;
    }
}
