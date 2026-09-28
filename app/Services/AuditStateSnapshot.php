<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditStateSnapshot
{
    private const RECORDS = [
        'clients' => ['clients', ['is_pa', 'country', 'city', 'payment_terms_days']],
        'projects' => ['projects', ['client_id', 'service_id', 'status', 'color']],
        'tasks' => ['tasks', ['project_id', 'client_id', 'service_id', 'parent_task_id', 'task_type', 'status', 'priority', 'start_date', 'due_date', 'due_time']],
        'absences' => ['absence_requests', ['type', 'start_date', 'end_date', 'start_time', 'end_time', 'status']],
        'documents' => ['company_documents', ['category', 'audience', 'document_year']],
        'document-messages' => ['company_messages', ['audience']],
    ];

    public function capture(Request $request): ?array
    {
        $routeName = (string) $request->route()?->getName();
        if ($routeName === 'settings.roles.update') {
            return DB::table('role_permissions')->orderBy('role')->orderBy('permission')
                ->get(['role', 'permission', 'allowed'])
                ->mapWithKeys(fn ($row) => [$row->role.'.'.$row->permission => (bool) $row->allowed])->all();
        }

        if (in_array($routeName, ['users.status.update', 'users.sensitive.update'], true)) {
            $id = $request->route('id');
            if (! $id) {
                return null;
            }
            if ($routeName === 'users.status.update') {
                $row = DB::table('users')->where('id', $id)->first(['account_status', 'suspended_at', 'archived_at']);

                return $row ? (array) $row : null;
            }
            if ($request->input('section') === 'security') {
                return ['role' => DB::table('user_roles')->where('user_id', $id)->value('role')];
            }
            $fields = $request->input('section') === 'contract'
                ? ['employment_status', 'hire_date', 'termination_date']
                : ['job_title', 'department', 'manager_user_id', 'office', 'weekly_hours', 'part_time', 'part_time_percentage', 'smartworking_day'];
            $row = DB::table('profiles')->where('user_id', $id)->first($fields);

            return $row ? (array) $row : null;
        }

        $area = explode('.', $routeName)[0];
        if (! isset(self::RECORDS[$area]) || ! $this->isRecordMutation($routeName)) {
            return null;
        }

        [$table, $fields] = self::RECORDS[$area];
        $id = $request->attributes->get('audit_subject_id')
            ?: $request->route('id');
        if (! $id) {
            return null;
        }
        $row = DB::table($table)->where('id', $id)->first($fields);

        return $row ? (array) $row : null;
    }

    public function changes(?array $before, ?array $after): array
    {
        if ($before === null && $after === null) {
            return [null, null];
        }
        if ($before === null || $after === null) {
            return [$before, $after];
        }

        $keys = array_keys(array_filter($after, fn ($value, $key) => ($before[$key] ?? null) != $value, ARRAY_FILTER_USE_BOTH));
        if (! $keys) {
            return [null, null];
        }

        return [array_intersect_key($before, array_flip($keys)), array_intersect_key($after, array_flip($keys))];
    }

    private function isRecordMutation(string $routeName): bool
    {
        return in_array(substr($routeName, strrpos($routeName, '.') + 1), ['store', 'update', 'destroy'], true)
            || in_array($routeName, ['tasks.status.update', 'tasks.schedule.update', 'absences.status.update', 'documents.category.update'], true);
    }
}
