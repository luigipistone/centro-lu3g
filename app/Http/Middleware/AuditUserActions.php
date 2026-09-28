<?php

namespace App\Http\Middleware;

use App\Services\AuditStateSnapshot;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuditUserActions
{
    public function handle(Request $request, Closure $next)
    {
        $name = $request->route()?->getName();
        $actor = $request->user();
        $audit = $actor && $this->shouldAudit($request, $name) && Schema::hasTable('audit_logs');
        $snapshot = $audit ? app(AuditStateSnapshot::class) : null;
        $before = $snapshot?->capture($request);
        try {
            $response = $next($request);
        } catch (Throwable $error) {
            if ($audit) {
                $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode()
                    : ($error instanceof ValidationException ? 422 : 500);
                $this->record($request, $actor, $name, $snapshot, $before, $status, class_basename($error));
            }

            throw $error;
        }
        if ($audit) {
            $status = $response->getStatusCode();
            $failureType = $status >= 400 ? 'HttpError' : null;
            if (($actor->account_status ?? 'active') !== 'active') {
                $status = 403;
                $failureType = 'AccountInactive';
            } elseif ($status === 302 && $request->session()->get('errors')) {
                $status = 422;
                $failureType = 'ValidationException';
            }
            $this->record($request, $actor, $name, $snapshot, $before, $status, $failureType);
        }

        return $response;
    }

    private function record(Request $request, $actor, ?string $name, AuditStateSnapshot $snapshot, ?array $before, int $status, ?string $failureType = null): void
    {
        $user = $actor;
        if ($user) {
            $subjectId = $request->attributes->get('audit_subject_id')
                ?: collect($request->route()?->parameters() ?? [])->first(fn ($value, $key) => in_array($key, ['id', 'user', 'project', 'task'], true));
            [$stateBefore, $stateAfter] = $failureType ? [null, null] : $snapshot->changes($before, $snapshot->capture($request));
            $metadata = ['path' => '/'.ltrim($request->path(), '/')];
            if (Str::startsWith((string) $name, 'users.')) {
                $metadata += array_filter([
                    'status' => $request->input('status'),
                    'decision' => $request->input('decision'),
                    'role' => $request->input('role'),
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
                'status_code' => $status,
                'related_request_id' => $request->attributes->get('audit_related_request_id'),
                'reviewed_by' => $request->attributes->get('audit_reviewed_by'),
                'reason' => $request->attributes->get('audit_reason'),
                'failure_type' => $failureType,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'metadata' => json_encode($metadata),
                'created_at' => now(), 'updated_at' => now(),
            ];
            DB::table('audit_logs')->insert($values);
        }
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
