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

        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $user ? [
                    ...$user->only(['id', 'name', 'email', 'email_verified_at']),
                    'avatar_url' => DB::table('profiles')->where('user_id', $user->id)->value('avatar_url'),
                ] : null,
            ],
            'notifications' => fn () => $user ? [
                'unread' => DB::table('notifications')
                    ->where('user_id', $user->id)
                    ->where('read', false)
                    ->count(),
                'latest' => DB::table('notifications')
                    ->where('user_id', $user->id)
                    ->latest()
                    ->limit(8)
                    ->get(['id', 'task_id', 'message', 'read', 'created_at']),
            ] : ['unread' => 0, 'latest' => []],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
