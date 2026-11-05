<?php

return [
    'enabled' => env('REPORT_SYNC_ENABLED', false),
    'token' => env('CENTRO_READ_TOKEN'),
    'endpoint' => 'https://report.lu3g.com/api/integrations/centro/v1/clients',
    'status' => 'all',
    'page_size' => 100,
    'max_pages' => 200,
    'excluded_areas' => ['PEC', 'PLUGIN'],
    'profile_fields' => [
        'name', 'vat_number', 'email', 'phone', 'website',
        'street', 'postal_code', 'city', 'country', 'legal_form',
        'province', 'electronic_invoicing_address',
    ],
];
