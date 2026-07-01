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
            if (! Schema::hasColumn('project_template_tasks', 'status')) {
                $table->string('status')->default('todo')->after('task_type');
            }
            if (! Schema::hasColumn('project_template_tasks', 'service_id')) {
                $table->uuid('service_id')->nullable()->after('description');
            }
            if (! Schema::hasColumn('project_template_tasks', 'due_time')) {
                $table->time('due_time')->nullable()->after('duration_days');
            }
            if (! Schema::hasColumn('project_template_tasks', 'location')) {
                $table->string('location')->nullable()->after('due_time');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('project_template_tasks')) {
            return;
        }

        Schema::table('project_template_tasks', function (Blueprint $table) {
            foreach (['status', 'service_id', 'due_time', 'location'] as $column) {
                if (Schema::hasColumn('project_template_tasks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
