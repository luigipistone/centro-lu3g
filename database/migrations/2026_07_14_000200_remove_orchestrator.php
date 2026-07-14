<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('orchestrator_run_modules');
        Schema::dropIfExists('orchestrator_runs');

        if (Schema::hasTable('admin_modules') && Schema::hasColumn('admin_modules', 'service_id')) {
            Schema::table('admin_modules', function (Blueprint $table) {
                $table->dropForeign(['service_id']);
                $table->dropColumn('service_id');
            });
        }
    }

    public function down(): void
    {
        // L'Orchestratore e i suoi dati sono stati rimossi definitivamente.
    }
};
