<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Event bus driver
    |--------------------------------------------------------------------------
    |
    | inmemory — desenvolvimento local / testes (sem Kafka)
    | kafka    — produção na VM (requer ext-rdkafka + mateusjunges/laravel-kafka)
    |
    */

    'driver' => env('EVENT_BUS_DRIVER', 'inmemory'),

    'kafka' => [
        'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),
        'consumer_group' => env('KAFKA_CONSUMER_GROUP', 'wallet'),
    ],

    'topics' => [
        'commands_wallet' => env('KAFKA_TOPIC_COMMANDS', 'commands.wallet'),
        'wallet_events' => env('KAFKA_TOPIC_WALLET', 'wallet.events'),
        'consent_events' => env('KAFKA_TOPIC_CONSENT', 'consent.events'),
        'payments_events' => env('KAFKA_TOPIC_PAYMENTS', 'payments.events'),
        'fraud_decisions' => env('KAFKA_TOPIC_FRAUD', 'fraud.decisions'),
        'wallet_snapshots' => env('KAFKA_TOPIC_SNAPSHOTS', 'wallet.snapshots'),
    ],

];
