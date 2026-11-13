<?php

return [
    'enabled' => env('CALENDAR_REALTIME_ENABLED', false),
    'key' => env('REVERB_APP_KEY'),
    'host' => env('REVERB_PUBLIC_HOST', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
    'port' => (int) env('REVERB_PUBLIC_PORT', 443),
    'tls' => env('REVERB_PUBLIC_TLS', true),
];
