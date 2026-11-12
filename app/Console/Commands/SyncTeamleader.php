<?php

namespace App\Console\Commands;

use App\Services\TeamleaderSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncTeamleader extends Command
{
    protected $signature = 'centro:teamleader-sync {--force : Controlla subito}';
    protected $description = 'Sincronizza i meeting Teamleader nel Centro';
    public function handle(TeamleaderSync $sync): int
    {
        $s = DB::table('teamleader_settings')->find(1);
        if (! $s?->enabled) return self::SUCCESS;
        if (! $this->option('force') && (! $s->requested_at || $s->last_error) && $s->last_synced_at && strtotime($s->last_synced_at) > now()->subMinutes(5)->timestamp) return self::SUCCESS;
        $result = $sync->run();
        $this->info(json_encode($result ?? ['status' => 'occupato o disabilitato']));
        return ($result['errors'] ?? 0) ? self::FAILURE : self::SUCCESS;
    }
}
