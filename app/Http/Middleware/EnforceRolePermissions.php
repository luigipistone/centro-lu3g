<?php

namespace App\Http\Middleware;

use App\Services\RolePermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnforceRolePermissions
{
    public function handle(Request $request, Closure $next)
    {
        $permission = $this->permissionFor($request);
        if ($permission && $request->user()) {
            $role = (string) (DB::table('user_roles')->where('user_id', $request->user()->id)->value('role') ?: 'guest');
            abort_unless(app(RolePermissionService::class)->allows($role, $permission), 403);
        }

        return $next($request);
    }

    private function permissionFor(Request $request): ?string
    {
        $name = (string) ($request->route()?->getName() ?? '');
        if (Str::startsWith($name, 'profile.absences.')) {
            return 'absences.request';
        }
        if ($name === '' || Str::startsWith($name, ['profile.', 'notifications.', 'push.', 'push-subscriptions.'])) {
            return null;
        }

        if ($name === 'dashboard' || Str::startsWith($name, 'dashboard.')) {
            return 'dashboard.view';
        }
        if (Str::startsWith($name, 'calendar.')) {
            return 'calendar.view';
        }
        if (Str::startsWith($name, ['document-messages.', 'document-groups.'])) {
            if (in_array($name, ['document-messages.show', 'document-messages.read'], true)) {
                return 'documents.view';
            }

            return 'documents.manage';
        }
        if (Str::startsWith($name, 'documents.')) {
            if ($request->isMethod('GET') || $name === 'documents.read') {
                return 'documents.view';
            }

            return 'documents.manage';
        }
        if (Str::startsWith($name, 'passwords.')) {
            return 'passwords.view';
        }
        if (Str::startsWith($name, 'absences.')) {
            return 'absences.manage';
        }
        if (Str::startsWith($name, ['updates.', 'updates-'])) {
            return 'updates.'.($request->isMethod('GET') ? 'view' : 'manage');
        }
        if (Str::startsWith($name, 'billing.')) {
            return 'billing.'.($request->isMethod('GET') ? 'view' : 'manage');
        }
        if ($name === 'users.update' || $name === 'users.avatar.update') {
            return 'users.profile.personal.update';
        }
        if ($name === 'users.sensitive.update') {
            return match ($request->input('section')) {
                'operational' => 'users.profile.operational.update',
                'contract' => 'users.profile.contract.update',
                'security' => 'users.profile.security.update',
                default => 'users.manage',
            };
        }
        if ($name === 'users.status.update') {
            return 'users.profile.security.update';
        }
        if (Str::startsWith($name, 'users.')) {
            return 'users.'.($request->isMethod('GET') ? 'view' : 'manage');
        }
        if (Str::startsWith($name, 'modules.')) {
            return 'modules.'.($request->isMethod('GET') ? 'view' : 'manage');
        }
        if (Str::startsWith($name, 'ai-agency.')) {
            return 'ai_agency.'.($request->isMethod('GET') ? 'view' : 'manage');
        }
        if (Str::startsWith($name, 'settings.')) {
            return 'settings.manage';
        }

        foreach (['clients', 'projects', 'tasks'] as $area) {
            if (! Str::startsWith($name, $area.'.')) {
                continue;
            }
            $action = match (true) {
                $request->isMethod('GET') => 'view',
                $request->isMethod('DELETE') => 'delete',
                $name === $area.'.store' => 'create',
                default => 'update',
            };

            return $area.'.'.$action;
        }

        return null;
    }
}
