<?php
// config/services.php

return [
    // ... các service khác

    'vietqr' => [
        'client_id' => env('VIETQR_CLIENT_ID'),
        'api_key' => env('VIETQR_API_KEY'),
        'account_no' => env('VIETQR_ACCOUNT_NO', '19038752078015'),
        'account_name' => env('VIETQR_ACCOUNT_NAME', 'DO GIAO LINH'),
        'bank_code' => env('VIETQR_BANK_CODE', '970407'), // TechnBank mặc định
    ],
];