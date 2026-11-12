<?php

namespace App\Console\Commands;

use App\Services\GmailRequests;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncGmailRequests extends Command
{
    protected $signature = 'centro:gmail-requests-sync';
    protected $description = 'Importa le email etichettate nelle richieste da verificare';
    public function handle(): int
    {
        foreach (DB::table('gmail_connections')->where('enabled', true)->whereNotNull('refresh_token')->get() as $connection) {
            if (! $connection->requested_at && $connection->last_synced_at && \Carbon\CarbonImmutable::parse($connection->last_synced_at)->gt(now()->subMinutes(2))) continue;
            (new GmailRequests)->sync($connection->id);
        }
        return self::SUCCESS;
    }
}
