<?php

namespace App\Http\Controllers;

use App\Services\CalendarRealtime;
use Illuminate\Http\Request;

class CalendarRealtimeController extends Controller
{
    public function authenticate(Request $request, CalendarRealtime $realtime)
    {
        abort_unless(config('calendar-realtime.enabled'), 503);
        abort_unless($request->input('channel_name') === 'private-calendar', 403);
        $data = $request->validate([
            'socket_id' => ['required', 'string', 'regex:/^\d+\.\d+$/'],
            'channel_name' => ['required', 'in:private-calendar'],
        ]);

        return response($realtime->client()->authorizeChannel($data['channel_name'], $data['socket_id']))
            ->header('Content-Type', 'application/json')
            ->header('Cache-Control', 'no-store');
    }
}
