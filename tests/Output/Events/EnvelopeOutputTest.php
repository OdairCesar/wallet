<?php

use App\Infrastructure\Events\DomainEventEnvelope;
use App\Wallet\Aggregates\WalletAccountAggregate;
use App\Wallet\Enums\WalletEventType;
use Tests\Support\SchemaValidator;

describe('Domain event envelope output', function () {
    it('serializes to schema-compliant array', function () {
        $envelope = DomainEventEnvelope::create(
            eventType: WalletEventType::AccountCreated,
            aggregateId: '00000000-0000-4000-8000-000000000001',
            aggregateType: 'wallet_account',
            payload: [
                'userId' => 1,
                'accountType' => 'PERSONAL',
                'currency' => 'BRL',
                'status' => 'ACTIVE',
            ],
        );

        $output = $envelope->toArray();

        SchemaValidator::validate(
            $output,
            base_path('tests/Contracts/Events/envelope.schema.json'),
        );

        expect($output['event_type'])->toBe(WalletEventType::AccountCreated)
            ->and($output['payload']['currency'])->toBe('BRL');
    });
});

describe('Wallet aggregate output events', function () {
    it('emits transfer completed event on successful transfer', function () {
        $accountId = '00000000-0000-4000-8000-000000000010';
        $toAccountId = '00000000-0000-4000-8000-000000000011';

        $aggregate = new WalletAccountAggregate($accountId);
        $aggregate->createAccount('PERSONAL', 1);
        $aggregate->deposit(50000, 'seed');
        $aggregate->transfer(10000, $toAccountId);

        $events = $aggregate->pullRecordedEvents();
        $last = end($events);

        expect($last->eventType)->toBe(WalletEventType::TransferCompleted)
            ->and($last->payload['amountCents'])->toBe(10000)
            ->and($last->payload['toAccountId'])->toBe($toAccountId);
    });

    it('emits transfer failed event on insufficient balance', function () {
        $aggregate = new WalletAccountAggregate('00000000-0000-4000-8000-000000000020');
        $aggregate->createAccount('PERSONAL', 1);
        $aggregate->transfer(100, '00000000-0000-4000-8000-000000000021');

        $events = $aggregate->pullRecordedEvents();
        $last = end($events);

        expect($last->eventType)->toBe(WalletEventType::TransferFailed)
            ->and($last->payload['reason'])->toBe('INSUFFICIENT_BALANCE');
    });
});
