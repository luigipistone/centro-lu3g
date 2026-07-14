<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agency_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('created_by')->nullable();
            $table->string('status')->default('draft');
            $table->longText('project_snapshot')->nullable();
            $table->json('proposal')->nullable();
            $table->json('approved_services')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedSmallInteger('web_searches')->default(0);
            $table->decimal('estimated_cost_eur', 10, 4)->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('ai_agency_artifacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->string('type')->default('analysis');
            $table->string('title');
            $table->longText('content');
            $table->timestamps();

            $table->foreign('run_id')->references('id')->on('ai_agency_runs')->cascadeOnDelete();
        });

        Schema::create('ai_agency_service_workflows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('service_id')->unique();
            $table->uuid('workflow_module_id');
            $table->timestamps();

            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            $table->foreign('workflow_module_id')->references('id')->on('admin_modules')->cascadeOnDelete();
        });

        Schema::create('ai_agency_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->uuid('service_id')->nullable();
            $table->uuid('workflow_module_id')->nullable();
            $table->uuid('module_id')->nullable();
            $table->string('name');
            $table->string('agent_role')->nullable();
            $table->string('status')->default('planned');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('run_id')->references('id')->on('ai_agency_runs')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
            $table->foreign('workflow_module_id')->references('id')->on('admin_modules')->nullOnDelete();
            $table->foreign('module_id')->references('id')->on('admin_modules')->nullOnDelete();
        });

        $webService = DB::table('services')
            ->whereRaw('LOWER(name) IN (?, ?, ?)', ['web', 'sito web', 'creazione sito web'])
            ->first(['id']);
        $websiteWorkflow = DB::table('admin_modules')
            ->join('admin_module_folders', 'admin_module_folders.id', '=', 'admin_modules.admin_module_folder_id')
            ->whereRaw('LOWER(admin_module_folders.name) = ?', ['workflow'])
            ->whereNull('admin_modules.parent_module_id')
            ->whereRaw('LOWER(admin_modules.name) LIKE ?', ['%sito web%'])
            ->first(['admin_modules.id']);

        if ($webService && $websiteWorkflow) {
            DB::table('ai_agency_service_workflows')->insert([
                'id' => (string) Str::uuid(),
                'service_id' => $webService->id,
                'workflow_module_id' => $websiteWorkflow->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agency_steps');
        Schema::dropIfExists('ai_agency_service_workflows');
        Schema::dropIfExists('ai_agency_artifacts');
        Schema::dropIfExists('ai_agency_runs');
    }
};
