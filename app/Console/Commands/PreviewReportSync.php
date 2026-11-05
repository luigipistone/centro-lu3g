<?php

namespace App\Console\Commands;

use App\Services\ReportSyncPreview;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreviewReportSync extends Command
{
    protected $signature = 'report:preview';

    protected $description = 'Anteprima JSON delle associazioni Report, senza scritture';

    public function handle(ReportSyncPreview $preview): int
    {
        $remote = $preview->fetch();
        $clients = DB::table('clients')->get(['id', 'name', 'vat_number'])->map(fn ($row) => (array) $row)->all();
        $services = DB::table('services')->get(['id', 'name'])->map(fn ($row) => (array) $row)->all();
        $links = Schema::hasTable('report_client_links')
            ? DB::table('report_client_links')->pluck('client_id', 'external_id')->all() : [];
        $this->line(json_encode($preview->build($remote, $clients, $links, $services), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
