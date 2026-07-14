<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_modules', function (Blueprint $table) {
            $table->uuid('service_id')->nullable()->after('parent_module_id');
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
        });

        Schema::table('orchestrator_runs', function (Blueprint $table) {
            $table->json('not_recommended_services')->nullable()->after('recommended_services');
            $table->json('recommended_priorities')->nullable()->after('recommended_priority');
            $table->longText('motivations')->nullable()->after('recommended_priority');
            $table->json('approved_services')->nullable()->after('roadmap');
            $table->json('workflow_module_ids')->nullable()->after('approved_services');
        });

        Schema::table('orchestrator_run_modules', function (Blueprint $table) {
            $table->uuid('workflow_module_id')->nullable()->after('module_id');
            $table->foreign('workflow_module_id')->references('id')->on('admin_modules')->nullOnDelete();
        });

        $services = DB::table('services')->get(['id', 'name']);
        $workflowParents = DB::table('admin_modules')
            ->join('admin_module_folders', 'admin_module_folders.id', '=', 'admin_modules.admin_module_folder_id')
            ->whereRaw('LOWER(admin_module_folders.name) = ?', ['workflow'])
            ->whereNull('admin_modules.parent_module_id')
            ->get(['admin_modules.id', 'admin_modules.name']);

        $aliases = [
            'creazione sito web' => 'web',
        ];

        foreach ($workflowParents as $workflow) {
            $workflowName = $this->normalizeName($workflow->name);
            $serviceName = $aliases[$workflowName] ?? $workflowName;
            $service = $services->first(fn ($item) => $this->normalizeName($item->name) === $serviceName);

            if ($service) {
                DB::table('admin_modules')->where('id', $workflow->id)->update(['service_id' => $service->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('orchestrator_run_modules', function (Blueprint $table) {
            $table->dropForeign(['workflow_module_id']);
            $table->dropColumn('workflow_module_id');
        });

        Schema::table('orchestrator_runs', function (Blueprint $table) {
            $table->dropColumn([
                'not_recommended_services',
                'recommended_priorities',
                'motivations',
                'approved_services',
                'workflow_module_ids',
            ]);
        });

        Schema::table('admin_modules', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropColumn('service_id');
        });
    }

    private function normalizeName(mixed $value): string
    {
        return Str::of((string) $value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }
};
