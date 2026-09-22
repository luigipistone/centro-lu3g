<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AccountArchiveService
{
    public function summary(string $userId): array
    {
        $tasks = DB::table('tasks')
            ->where(fn ($query) => $query->where('created_by', $userId)
                ->orWhereIn('id', DB::table('task_assignees')->where('user_id', $userId)->select('task_id'))
                ->orWhereIn('id', DB::table('task_followers')->where('user_id', $userId)->select('task_id')))
            ->orderBy('title')->get(['id', 'title']);
        $projects = DB::table('projects')
            ->where(fn ($query) => $query->where('created_by', $userId)
                ->orWhereIn('id', DB::table('project_followers')->where('user_id', $userId)->select('project_id')))
            ->orderBy('name')->get(['id', 'name']);
        $clients = DB::table('clients')->where('created_by', $userId)->orderBy('name')->get(['id', 'name']);
        $documents = DB::table('company_documents')
            ->where(fn ($query) => $query->where('created_by', $userId)
                ->orWhereIn('id', DB::table('company_document_user')->where('user_id', $userId)->select('company_document_id')))
            ->orderBy('title')->get(['id', 'title']);
        $passwords = DB::table('password_items')
            ->whereIn('id', DB::table('password_item_user')->where('user_id', $userId)->select('password_item_id'))
            ->orWhereIn('password_vault_id', DB::table('password_vault_user')->where('user_id', $userId)->select('password_vault_id'))
            ->orderBy('title')->get(['id', 'title']);
        $requests = DB::table('absence_requests')->where('user_id', $userId)->latest('start_date')->get(['id', 'type', 'start_date']);
        $messages = DB::table('company_messages')
            ->where(fn ($query) => $query->where('created_by', $userId)
                ->orWhereIn('id', DB::table('company_message_user')->where('user_id', $userId)->select('company_message_id')))
            ->orderBy('title')->get(['id', 'title']);
        $logs = Schema::hasTable('audit_logs')
            ? DB::table('audit_logs')->where(fn ($query) => $query->where('user_id', $userId)->orWhere('subject_id', $userId))->count()
            : 0;

        return [
            'tasks' => $this->items($tasks, 'title'),
            'projects' => $this->items($projects, 'name'),
            'clients' => $this->items($clients, 'name'),
            'documents' => $this->items($documents, 'title'),
            'passwords' => $this->items($passwords, 'title'),
            'requests' => $requests->map(fn ($row) => ['id' => $row->id, 'label' => ucfirst($row->type).' · '.$row->start_date])->values()->all(),
            'messages' => $this->items($messages, 'title'),
            'logs' => ['count' => $logs, 'items' => []],
        ];
    }

    public function create(User $user, User $requester, string $reason): string
    {
        $existing = DB::table('account_archive_requests')->where('user_id', $user->id)->where('status', 'pending')->value('id');
        if ($existing) return (string) $existing;

        $id = (string) Str::uuid();
        DB::table('account_archive_requests')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'requested_by' => $requester->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_role' => DB::table('user_roles')->where('user_id', $user->id)->value('role'),
            'status' => 'pending',
            'reason' => $reason,
            'linked_summary' => json_encode($this->summary($user->id), JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function items($rows, string $column): array
    {
        return ['count' => $rows->count(), 'items' => $rows->map(fn ($row) => ['id' => $row->id, 'label' => $row->{$column}])->values()->all()];
    }
}
