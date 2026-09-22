<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuditUserActions
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $user = $request->user();
        if ($user && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && Schema::hasTable('audit_logs')) {
            $route = $request->route();
            $name = $route?->getName();
            $subjectId = collect($route?->parameters() ?? [])->first(fn ($value, $key) => in_array($key, ['id', 'user', 'project', 'task'], true));
            $recent = DB::table('audit_logs')
                ->where('user_id', $user->id)
                ->where('route_name', $name)
                ->where('method', $request->method())
                ->where('subject_id', $subjectId)
                ->where('created_at', '>=', now()->subMinute())
                ->latest('created_at')
                ->first();
            $values = [
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_role' => DB::table('user_roles')->where('user_id', $user->id)->value('role'),
                'action' => $this->action($request->method()),
                'area' => $name ? Str::before($name, '.') : Str::before(trim($request->path(), '/'), '/'),
                'route_name' => $name,
                'method' => $request->method(),
                'subject_id' => $subjectId,
                'status_code' => $response->getStatusCode(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'metadata' => json_encode(['path' => '/'.ltrim($request->path(), '/')]),
                'created_at' => now(), 'updated_at' => now(),
            ];
            if ($recent) {
                DB::table('audit_logs')->where('id', $recent->id)->update(collect($values)->except(['id'])->all());
            } else {
                DB::table('audit_logs')->insert($values);
            }
            if (random_int(1, 50) === 1) {
                DB::table('audit_logs')->where('created_at', '<', now()->subDays(3))->delete();
            }
        }

        return $response;
    }

    private function action(string $method): string
    {
        return match ($method) {
            'POST' => 'creazione', 'PUT', 'PATCH' => 'modifica', 'DELETE' => 'eliminazione', default => strtolower($method)
        };
    }
}
