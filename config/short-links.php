<?php

$appUrl = env('APP_URL', 'http://localhost');

return [
    'base_url' => env(
        'SHORT_LINK_BASE_URL',
        parse_url($appUrl, PHP_URL_HOST) === 'centro.lu3g.com' ? 'https://lu3g.com' : $appUrl,
    ),
];
