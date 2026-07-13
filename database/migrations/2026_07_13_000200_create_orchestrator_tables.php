<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orchestrator_runs')) {
            Schema::create('orchestrator_runs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('project_id');
                $table->uuid('workflow_module_id')->nullable();
                $table->string('status')->default('draft');
                $table->json('recommended_services')->nullable();
                $table->string('recommended_priority')->nullable();
                $table->longText('roadmap')->nullable();
                $table->json('workflow_options')->nullable();
                $table->longText('decision_prompt')->nullable();
                $table->longText('decision_output')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('workflow_module_id')->references('id')->on('admin_modules')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('orchestrator_run_modules')) {
            Schema::create('orchestrator_run_modules', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('orchestrator_run_id');
                $table->uuid('module_id');
                $table->unsignedInteger('position')->default(0);
                $table->string('status')->default('blocked');
                $table->longText('prompt')->nullable();
                $table->longText('output')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->foreign('orchestrator_run_id')->references('id')->on('orchestrator_runs')->cascadeOnDelete();
                $table->foreign('module_id')->references('id')->on('admin_modules')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('orchestrator_run_modules');
        Schema::dropIfExists('orchestrator_runs');
    }
};
