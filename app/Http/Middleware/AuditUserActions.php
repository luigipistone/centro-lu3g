<?php

namespace App\Http\Middleware;

use App\Services\AuditStateSnapshot;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuditUserActions
{
    public function handle(Request $request, Closure $next)
    {
        $name = $request->route()?->getName();
        $audit = $request->user() && $this->shouldAudit($request, $name) && Schema::hasTable('audit_logs');
        $snapshot = $audit ? app(AuditStateSnapshot::class) : null;
        $before = $snapshot?->capture($request);
        $response = $next($request);
        $user = $request->user();
        if ($audit && $user) {
            $subjectId = $request->attributes->get('audit_subject_id')
                ?: collect($request->route()?->parameters() ?? [])->first(fn ($value, $key) => in_array($key, ['id', 'user', 'project', 'task'], true));
            [$stateBefore, $stateAfter] = $snapshot->changes($before, $snapshot->capture($request));
            $metadata = ['path' => '/'.ltrim($request->path(), '/')];
            if (Str::startsWith((string) $name, 'users.')) {
                $metadata += array_filter([
                    'status' => $request->input('status'),
                    'decision' => $request->input('decision'),
                    'role' => $request->input('role'),
                    'reason' => $request->input('reason'),
                ], fn ($value) => $value !== null && $value !== '');
            }
            $values = [
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_role' => DB::table('user_roles')->where('user_id', $user->id)->value('role'),
                'permissions_used' => json_encode($request->attributes->get('audit_permissions_used', []), JSON_THROW_ON_ERROR),
                'state_before' => $stateBefore === null ? null : json_encode($stateBefore, JSON_THROW_ON_ERROR),
                'state_after' => $stateAfter === null ? null : json_encode($stateAfter, JSON_THROW_ON_ERROR),
                'action' => $this->action($request->method()),
                'area' => $name ? Str::before($name, '.') : Str::before(trim($request->path(), '/'), '/'),
                'route_name' => $name,
                'method' => $request->method(),
                'subject_id' => $subjectId,
                'status_code' => $response->getStatusCode(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'metadata' => json_encode($metadata),
                'created_at' => now(), 'updated_at' => now(),
            ];
            DB::table('audit_logs')->insert($values);
        }

        return $response;
    }

    private function action(string $method): string
    {
        return match ($method) {
            'POST' => 'creazione', 'PUT', 'PATCH' => 'modifica', 'DELETE' => 'eliminazione', default => strtolower($method)
        };
    }

    private function shouldAudit(Request $request, ?string $routeName): bool
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) || ! $routeName || Str::startsWith($routeName, 'generated::')) {
            return false;
        }

        return Str::startsWith($routeName, [
            'clients.', 'projects.', 'tasks.', 'absences.', 'attendance.', 'documents.', 'document-messages.',
            'document-groups.', 'passwords.', 'modules.', 'ai-agency.', 'updates.', 'updates-',
            'billing.', 'users.', 'settings.', 'profile.',
        ]);
    }
}
