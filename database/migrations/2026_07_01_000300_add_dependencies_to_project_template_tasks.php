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
            if (! Schema::hasColumn('project_template_tasks', 'dependency_mode')) {
                $table->string('dependency_mode')->default('none')->after('date_reference_task_key');
            }

            if (! Schema::hasColumn('project_template_tasks', 'dependency_task_keys')) {
                $table->json('dependency_task_keys')->nullable()->after('dependency_mode');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('project_template_tasks')) {
            return;
        }

        Schema::table('project_template_tasks', function (Blueprint $table) {
            foreach (['dependency_task_keys', 'dependency_mode'] as $column) {
                if (Schema::hasColumn('project_template_tasks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
