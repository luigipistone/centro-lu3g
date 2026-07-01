<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_template_tasks')) {
            return;
        }

        Schema::table('project_template_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('project_template_tasks', 'template_key')) {
                $table->string('template_key')->nullable()->after('id');
            }
            if (! Schema::hasColumn('project_template_tasks', 'assignee_ids')) {
                $table->json('assignee_ids')->nullable()->after('service_id');
            }
            if (! Schema::hasColumn('project_template_tasks', 'date_offset_direction')) {
                $table->string('date_offset_direction')->default('after')->after('day_offset');
            }
            if (! Schema::hasColumn('project_template_tasks', 'date_reference_type')) {
                $table->string('date_reference_type')->default('project_start')->after('date_offset_direction');
            }
            if (! Schema::hasColumn('project_template_tasks', 'date_reference_task_key')) {
                $table->string('date_reference_task_key')->nullable()->after('date_reference_type');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('project_template_tasks')) {
            return;
        }

        Schema::table('project_template_tasks', function (Blueprint $table) {
            foreach (['template_key', 'assignee_ids', 'date_offset_direction', 'date_reference_type', 'date_reference_task_key'] as $column) {
                if (Schema::hasColumn('project_template_tasks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
