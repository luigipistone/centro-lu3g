<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
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
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'notifications' => fn () => $request->user() ? [
                'unread' => \Illuminate\Support\Facades\DB::table('notifications')
                    ->where('user_id', $request->user()->id)
                    ->where('read', false)
                    ->count(),
                'latest' => \Illuminate\Support\Facades\DB::table('notifications')
                    ->where('user_id', $request->user()->id)
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
