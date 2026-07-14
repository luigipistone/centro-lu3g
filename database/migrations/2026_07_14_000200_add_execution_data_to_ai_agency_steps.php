<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agency_steps', function (Blueprint $table) {
            $table->json('input_data')->nullable()->after('status');
            $table->json('output_data')->nullable()->after('input_data');
            $table->timestamp('started_at')->nullable()->after('position');
            $table->timestamp('submitted_at')->nullable()->after('started_at');
            $table->timestamp('completed_at')->nullable()->after('submitted_at');
        });

        DB::table('ai_agency_runs')->where('status', 'approved')->pluck('id')->each(function ($runId) {
            $steps = DB::table('ai_agency_steps')->where('run_id', $runId)->orderBy('position')->get(['id']);
            foreach ($steps as $index => $step) {
                DB::table('ai_agency_steps')->where('id', $step->id)->update([
                    'status' => $index === 0 ? 'todo' : 'blocked',
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_agency_steps', function (Blueprint $table) {
            $table->dropColumn(['input_data', 'output_data', 'started_at', 'submitted_at', 'completed_at']);
        });
    }
};
