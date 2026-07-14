<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ai_agency_artifacts')->where('type', 'analysis')->update([
            'type' => 'preliminary_analysis',
            'title' => 'Pre-analisi storica',
            'updated_at' => now(),
        ]);

        DB::table('ai_agency_runs')->get(['id'])->each(function ($run) {
            DB::table('ai_agency_steps')->where('run_id', $run->id)->delete();
            DB::table('ai_agency_runs')->where('id', $run->id)->update([
                'status' => 'draft',
                'proposal' => null,
                'approved_services' => null,
                'approved_at' => null,
                'error_message' => null,
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Il vecchio flusso approvava i servizi prima delle analisi e non viene ripristinato.
    }
};
