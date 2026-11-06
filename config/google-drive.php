<?php

return [
    'credentials' => env('GOOGLE_DRIVE_CREDENTIALS', dirname(base_path()).'/private/centro-integrations/google-drive.json'),
    'shared_drive_id' => env('GOOGLE_SHARED_DRIVE_ID', '0ABIRxelO2xXrUk9PVA'),
];
