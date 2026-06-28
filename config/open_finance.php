<?php

return [

    'organisation_id' => env('OF_ORGANISATION_ID', ''),
    'brand_name' => env('OF_BRAND_NAME', env('APP_NAME', 'Wallet')),
    'software_statement_id' => env('OF_SOFTWARE_STATEMENT_ID', ''),

    'fapi' => [
        'enabled' => env('FAPI_ENABLED', false),
        'mtls_enabled' => env('MTLS_ENABLED', false),
        'auth_server_url' => env('OF_AUTH_SERVER_URL', ''),
    ],

    'defaults' => [
        'currency' => 'BRL',
        'daily_transfer_limit' => env('WALLET_DAILY_TRANSFER_LIMIT', 50000),
    ],

];
