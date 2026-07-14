<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $artifacts = [
            'raccolta informazioni cliente' => 'project_brief',
            'analisi cliente' => 'client_analysis',
            'analisi competitor' => 'competitor_analysis',
            'definizione strategia' => 'strategy',
        ];

        DB::table('ai_agency_runs')->where('status', 'approved')->pluck('id')->each(function ($runId) use ($artifacts) {
            $nextActionableAssigned = false;
            DB::table('ai_agency_steps')->where('run_id', $runId)->orderBy('position')->get()->each(function ($step) use ($artifacts, &$nextActionableAssigned) {
                $artifactType = $artifacts[Str::lower(Str::ascii(trim($step->name)))] ?? null;
                $status = $artifactType ? 'completed' : (! $nextActionableAssigned ? 'todo' : 'blocked');
                if ($status === 'todo') $nextActionableAssigned = true;

                DB::table('ai_agency_steps')->where('id', $step->id)->update([
                    'status' => $status,
                    'output_data' => $artifactType ? json_encode(['artifact_type' => $artifactType]) : null,
                    'completed_at' => $artifactType ? ($step->completed_at ?: now()) : null,
                    'updated_at' => now(),
                ]);
            });
        });
    }

    public function down(): void
    {
        // The previous state cannot be restored without discarding genuine progress.
    }
};
