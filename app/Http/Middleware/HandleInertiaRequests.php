<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        if ($user) {
            $this->maintainNotifications($user->id);
        }

        $role = $user ? DB::table('user_roles')->where('user_id', $user->id)->value('role') : null;
        $completionEffect = $user ? DB::table('profiles')->where('user_id', $user->id)->value('completion_effect') : null;
        if (! in_array($completionEffect, ['balloons', 'fireworks', 'snow', 'glitch'], true)) {
            $completionEffect = 'balloons';
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $user ? [
                    ...$user->only(['id', 'name', 'email', 'email_verified_at']),
                    'avatar_url' => DB::table('profiles')->where('user_id', $user->id)->value('avatar_url'),
                    'role' => $role ?: 'guest',
                    'completion_effect' => $completionEffect,
                ] : null,
            ],
            'notifications' => fn () => $user ? [
                'active' => DB::table('notifications')
                    ->where('user_id', $user->id)
                    ->whereNull('archived_at')
                    ->count(),
                'unread' => DB::table('notifications')
                    ->where('user_id', $user->id)
                    ->whereNull('archived_at')
                    ->where('read', false)
                    ->count(),
                'latest' => DB::table('notifications')
                    ->where('user_id', $user->id)
                    ->whereNull('archived_at')
                    ->where('read', false)
                    ->latest()
                    ->limit(8)
                    ->get(['id', 'task_id', 'company_document_id', 'company_message_id', 'type', 'message', 'read', 'created_at']),
            ] : ['active' => 0, 'unread' => 0, 'latest' => []],
            'push' => [
                'vapidPublicKey' => config('services.webpush.public_key'),
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'created_id' => fn () => $request->session()->get('created_id'),
            ],
        ];
    }

    private function maintainNotifications(string $userId): void
    {
        DB::table('notifications')
            ->where('user_id', $userId)
            ->whereNull('archived_at')
            ->where('created_at', '<', now()->subDays(30))
            ->update(['archived_at' => now(), 'read' => true, 'updated_at' => now()]);

        DB::table('notifications')
            ->where('user_id', $userId)
            ->whereNotNull('archived_at')
            ->where('archived_at', '<', now()->subDays(30))
            ->delete();
    }
}
