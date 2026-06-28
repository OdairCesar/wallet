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

    'jwt' => [
        'secret' => env('OF_JWT_SECRET'),
        'algorithm' => env('OF_JWT_ALGORITHM', 'HS256'),
        'issuer' => env('OF_JWT_ISSUER', ''),
        'audience' => env('OF_JWT_AUDIENCE', ''),
        'jwks_uri' => env('OF_JWT_JWKS_URI', ''),
        'clock_skew_seconds' => (int) env('OF_JWT_CLOCK_SKEW', 60),
    ],

    'defaults' => [
        'currency' => 'BRL',
        'daily_transfer_limit' => (int) env('WALLET_DAILY_TRANSFER_LIMIT', 50000),
    ],

    'fraud' => [
        'max_amount_cents' => (int) env('WALLET_FRAUD_MAX_AMOUNT_CENTS', 10_000_000),
        'max_transactions_per_hour' => (int) env('WALLET_FRAUD_MAX_TX_PER_HOUR', 20),
    ],

    'idempotency' => [
        'ttl_hours' => (int) env('OF_IDEMPOTENCY_TTL_HOURS', 24),
    ],

    'rate_limit' => [
        'per_minute' => (int) env('OF_RATE_LIMIT_PER_MINUTE', 120),
    ],

    'docs' => [
        'access_token' => env('DOCS_ACCESS_TOKEN'),
    ],

];
