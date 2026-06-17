<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tasks', 'position')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->unsignedInteger('position')->default(0)->after('parent_task_id');
            });
        }

        DB::table('tasks')
            ->whereNotNull('parent_task_id')
            ->orderBy('parent_task_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'parent_task_id'])
            ->groupBy('parent_task_id')
            ->each(function ($subtasks) {
                foreach ($subtasks->values() as $index => $subtask) {
                    DB::table('tasks')->where('id', $subtask->id)->update(['position' => $index]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('tasks', 'position')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('position');
            });
        }
    }
};
