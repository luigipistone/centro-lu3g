<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_templates')) {
            Schema::create('project_templates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->longText('description')->nullable();
                $table->string('color')->default('#2563eb');
                $table->boolean('active')->default(true);
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('project_template_sections')) {
            Schema::create('project_template_sections', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('project_template_id');
                $table->string('name');
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->foreign('project_template_id')->references('id')->on('project_templates')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('project_template_tasks')) {
            Schema::create('project_template_tasks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('project_template_section_id');
                $table->string('title');
                $table->longText('description')->nullable();
                $table->unsignedInteger('day_offset')->default(0);
                $table->unsignedInteger('duration_days')->default(1);
                $table->string('priority')->default('medium');
                $table->string('task_type')->default('project');
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->foreign('project_template_section_id')->references('id')->on('project_template_sections')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_template_tasks');
        Schema::dropIfExists('project_template_sections');
        Schema::dropIfExists('project_templates');
    }
};
