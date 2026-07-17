<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['client_id', 'parent_task_id', 'status', 'due_date'], 'tasks_client_parent_status_due_idx');
            $table->index(['project_id', 'parent_task_id', 'status'], 'tasks_project_parent_status_idx');
            $table->index(['due_date', 'status', 'parent_task_id'], 'tasks_due_status_parent_idx');
        });

        Schema::table('task_assignees', function (Blueprint $table) {
            $table->index(['user_id', 'task_id'], 'task_assignees_user_task_idx');
        });

        Schema::table('task_followers', function (Blueprint $table) {
            $table->index(['user_id', 'task_id'], 'task_followers_user_task_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'archived_at', 'read', 'created_at'], 'notifications_user_archive_read_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_archive_read_created_idx');
        });

        Schema::table('task_followers', function (Blueprint $table) {
            $table->dropIndex('task_followers_user_task_idx');
        });

        Schema::table('task_assignees', function (Blueprint $table) {
            $table->dropIndex('task_assignees_user_task_idx');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_due_status_parent_idx');
            $table->dropIndex('tasks_project_parent_status_idx');
            $table->dropIndex('tasks_client_parent_status_due_idx');
        });
    }
};
