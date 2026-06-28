<?php

namespace App\Wallet\Services;

use App\Contracts\EventPublisherInterface;
use App\Fraud\Enums\FraudEventType;
use App\Fraud\Rules\FraudRulesEngine;
use App\Infrastructure\Events\DomainEventEnvelope;
use App\OpenFinance\Exceptions\OpenFinanceDomainException;
use App\Projections\Models\WalletAccount;
use App\Wallet\Aggregates\WalletAccountAggregate;
use App\Wallet\Enums\AccountType;
use App\Wallet\Enums\WalletEventType;
use Illuminate\Support\Str;

final class WalletCommandService
{
    public function __construct(
        private readonly EventPublisherInterface $publisher,
        private readonly FraudRulesEngine $fraudRules,
    ) {}

    public function createAccount(
        ?int $userId,
        AccountType $accountType,
        ?string $correlationId = null,
        ?string $clientId = null,
    ): string {
        $accountId = (string) Str::uuid();
        $aggregate = new WalletAccountAggregate($accountId);
        $aggregate->createAccount($accountType->value, $userId, $correlationId);

        $this->publishRecorded($aggregate, $clientId);

        return $accountId;
    }

    public function deposit(
        string $accountId,
        int $amountCents,
        string $reference,
        ?string $correlationId = null,
        ?string $clientId = null,
    ): void {
        $aggregate = $this->loadAggregate($accountId);
        $aggregate->deposit($amountCents, $reference, $correlationId);
        $this->publishRecorded($aggregate, $clientId);
    }

    public function transfer(
        string $fromAccountId,
        string $toAccountId,
        int $amountCents,
        ?string $correlationId = null,
        ?string $clientId = null,
    ): bool {
        if (! WalletAccount::query()->where('id', $toAccountId)->exists()) {
            throw new OpenFinanceDomainException(
                'CONTA_NAO_ENCONTRADA',
                'Conta destino não encontrada.',
                404,
            );
        }

        $requested = DomainEventEnvelope::create(
            eventType: WalletEventType::TransferRequested,
            aggregateId: $fromAccountId,
            aggregateType: 'wallet_account',
            payload: [
                'amountCents' => $amountCents,
                'toAccountId' => $toAccountId,
                'clientId' => $clientId,
            ],
            correlationId: $correlationId,
        );

        $decision = $this->fraudRules->evaluate($requested);

        if (! $decision->approved) {
            $this->publisher->publish(DomainEventEnvelope::create(
                eventType: FraudEventType::TransactionBlocked,
                aggregateId: $fromAccountId,
                aggregateType: 'wallet_account',
                payload: [
                    'ruleId' => $decision->ruleId,
                    'reason' => $decision->reason,
                    'amountCents' => $amountCents,
                    'clientId' => $clientId,
                ],
                correlationId: $correlationId,
            ));

            $this->publisher->publish(DomainEventEnvelope::create(
                eventType: WalletEventType::TransferFailed,
                aggregateId: $fromAccountId,
                aggregateType: 'wallet_account',
                payload: [
                    'amountCents' => $amountCents,
                    'toAccountId' => $toAccountId,
                    'reason' => 'FRAUD_BLOCKED',
                    'clientId' => $clientId,
                ],
                correlationId: $correlationId,
            ));

            return false;
        }

        $this->publisher->publish(DomainEventEnvelope::create(
            eventType: FraudEventType::TransactionApproved,
            aggregateId: $fromAccountId,
            aggregateType: 'wallet_account',
            payload: [
                'amountCents' => $amountCents,
                'clientId' => $clientId,
            ],
            correlationId: $correlationId,
        ));

        $aggregate = $this->loadAggregate($fromAccountId);
        $aggregate->transfer($amountCents, $toAccountId, $correlationId);

        return $this->publishRecorded($aggregate, $clientId);
    }

    private function loadAggregate(string $accountId): WalletAccountAggregate
    {
        $events = array_values(array_filter(
            $this->publisher->published(),
            fn (DomainEventEnvelope $e) => $e->aggregateId === $accountId
                && str_starts_with($e->eventType, 'wallet.'),
        ));

        if ($events === []) {
            if (! WalletAccount::query()->where('id', $accountId)->exists()) {
                throw new OpenFinanceDomainException(
                    'CONTA_NAO_ENCONTRADA',
                    'Conta não encontrada.',
                    404,
                );
            }

            return new WalletAccountAggregate($accountId);
        }

        return WalletAccountAggregate::reconstitute($events);
    }

    private function publishRecorded(WalletAccountAggregate $aggregate, ?string $clientId = null): bool
    {
        $completed = false;

        foreach ($aggregate->pullRecordedEvents() as $event) {
            if ($clientId !== null) {
                $event = DomainEventEnvelope::create(
                    eventType: $event->eventType,
                    aggregateId: $event->aggregateId,
                    aggregateType: $event->aggregateType,
                    payload: array_merge($event->payload, ['clientId' => $clientId]),
                    correlationId: $event->correlationId,
                    causationId: $event->causationId,
                    eventVersion: $event->eventVersion,
                );
            }

            $this->publisher->publish($event);

            if ($event->eventType === WalletEventType::TransferCompleted) {
                $completed = true;
            }
        }

        return $completed;
    }
}
