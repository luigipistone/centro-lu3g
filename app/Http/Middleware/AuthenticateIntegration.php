<?php

namespace App\Http\Middleware;

use App\Models\ApiConnection;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;

class AuthenticateIntegration
{
    public function handle(Request $request, Closure $next)
    {
        $token = PersonalAccessToken::findToken($request->bearerToken() ?? '');
        $connection = $token?->tokenable;
        abort_unless($connection instanceof ApiConnection && $connection->enabled() && (! $token->expires_at || $token->expires_at->isFuture()), 401);
        $owner = DB::table('users')->where('id', $connection->created_by)->first();
        abort_unless($owner && ($owner->account_status ?? 'active') === 'active'
            && DB::table('user_roles')->where('user_id', $owner->id)->where('role', 'superadmin')->exists(), 403);
        $key = 'integration-api:'.$connection->id;
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return response()->json(['error' => ['code' => 'rate_limited', 'message' => 'Troppe richieste.']], 429)
                ->header('Retry-After', RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);
        $request->attributes->set('api_connection', $connection);
        $request->attributes->set('api_token', $token);
        $response = $next($request);
        DB::table('api_connections')->where('id', $connection->id)->update(['last_used_at' => now()]);
        PersonalAccessToken::whereKey($token->id)->update(['last_used_at' => now()]);

        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function terminate(Request $request, $response): void
    {
        if ($connection = $request->attributes->get('api_connection')) {
            DB::table('api_request_logs')->insert(['connection_id' => $connection->id, 'method' => $request->method(), 'path' => substr($request->path(), 0, 500), 'status' => $response->getStatusCode(), 'created_at' => now()]);
        }
    }
}
