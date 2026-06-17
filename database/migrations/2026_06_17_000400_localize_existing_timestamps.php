<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $timezoneSwitchedAt = '2026-06-17 14:38:16';

    public function up(): void
    {
        foreach ($this->timestampColumns() as $table => $columns) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
                continue;
            }

            $existingColumns = array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));
            if (! $existingColumns) {
                continue;
            }

            DB::table($table)
                ->select(array_merge(['id'], $existingColumns))
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($table, $existingColumns) {
                    foreach ($rows as $row) {
                        $updates = [];

                        foreach ($existingColumns as $column) {
                            $value = $row->{$column};
                            if (! $value || $value >= $this->timezoneSwitchedAt) {
                                continue;
                            }

                            $updates[$column] = CarbonImmutable::parse($value, 'UTC')
                                ->setTimezone('Europe/Rome')
                                ->format('Y-m-d H:i:s');
                        }

                        if ($updates) {
                            DB::table($table)->where('id', $row->id)->update($updates);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        foreach ($this->timestampColumns() as $table => $columns) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
                continue;
            }

            $existingColumns = array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));
            if (! $existingColumns) {
                continue;
            }

            DB::table($table)
                ->select(array_merge(['id'], $existingColumns))
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($table, $existingColumns) {
                    foreach ($rows as $row) {
                        $updates = [];

                        foreach ($existingColumns as $column) {
                            $value = $row->{$column};
                            if (! $value || $value >= $this->timezoneSwitchedAt) {
                                continue;
                            }

                            $updates[$column] = CarbonImmutable::parse($value, 'Europe/Rome')
                                ->setTimezone('UTC')
                                ->format('Y-m-d H:i:s');
                        }

                        if ($updates) {
                            DB::table($table)->where('id', $row->id)->update($updates);
                        }
                    }
                });
        }
    }

    private function timestampColumns(): array
    {
        return [
            'users' => ['email_verified_at', 'created_at', 'updated_at'],
            'password_reset_tokens' => ['created_at'],
            'profiles' => ['created_at', 'updated_at'],
            'services' => ['created_at', 'updated_at'],
            'clients' => ['created_at', 'updated_at'],
            'client_contacts' => ['created_at', 'updated_at'],
            'projects' => ['created_at', 'updated_at'],
            'project_followers' => ['created_at', 'updated_at'],
            'tasks' => ['created_at', 'updated_at'],
            'task_assignees' => ['created_at', 'updated_at'],
            'task_followers' => ['created_at', 'updated_at'],
            'task_comments' => ['created_at', 'updated_at'],
            'task_activity' => ['created_at', 'updated_at'],
            'notifications' => ['archived_at', 'created_at', 'updated_at'],
            'app_settings' => ['created_at', 'updated_at'],
            'dashboard_widgets' => ['created_at', 'updated_at'],
            'user_notes' => ['created_at', 'updated_at'],
            'document_settings' => ['created_at', 'updated_at'],
            'document_numbering' => ['created_at', 'updated_at'],
            'subscriptions' => ['created_at', 'updated_at'],
            'documents' => ['xml_generated_at', 'created_at', 'updated_at'],
            'document_lines' => ['created_at', 'updated_at'],
            'document_payments' => ['created_at', 'updated_at'],
            'document_activity' => ['created_at', 'updated_at'],
            'document_emails' => ['sent_at', 'created_at', 'updated_at'],
            'email_settings' => ['created_at', 'updated_at'],
            'backup_runs' => ['started_at', 'finished_at'],
            'client_service_updates' => ['created_at', 'updated_at'],
        ];
    }
};
