<?php

return [
    'runner' => env('WORDPRESS_PROVISIONING_RUNNER', '/usr/local/sbin/centro-wordpress-provision'),
    'base_url' => env('WORDPRESS_PROVISIONING_BASE_URL', 'https://testing.lu3g.com'),
    'vault_name' => env('WORDPRESS_PROVISIONING_VAULT', 'Generale'),
    'timeout' => (int) env('WORDPRESS_PROVISIONING_TIMEOUT', 2400),
];
