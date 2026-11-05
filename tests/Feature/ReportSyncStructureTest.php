<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReportSyncStructureTest extends TestCase
{
    public function test_structure_preserves_existing_clients_and_can_be_rolled_back(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
        });
        Schema::create('services', fn (Blueprint $table) => $table->uuid('id')->primary());
        DB::table('clients')->insert(['id' => 'existing', 'name' => 'Existing client']);
        $migration = require database_path('migrations/2026_11_05_180000_create_report_sync_structure.php');
        try {
            $migration->up();
            $this->assertTrue(Schema::hasColumn('clients', 'electronic_invoicing_address'));
            foreach (['report_client_links', 'report_client_services', 'report_area_mappings', 'report_sync_runs'] as $table) {
                $this->assertTrue(Schema::hasTable($table));
                $this->assertSame(0, DB::table($table)->count());
            }
            $this->assertSame('Existing client', DB::table('clients')->value('name'));
            $migration->down();
            $this->assertFalse(Schema::hasTable('report_client_links'));
            $this->assertFalse(Schema::hasColumn('clients', 'electronic_invoicing_address'));
            $this->assertSame('Existing client', DB::table('clients')->value('name'));
        } finally {
            Schema::dropIfExists('clients');
            Schema::dropIfExists('services');
        }
    }
}
