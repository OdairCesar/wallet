<?php

namespace App\Wallet\Services;

use App\Contracts\EventPublisherInterface;
use App\Fraud\Enums\FraudEventType;
use App\Fraud\Rules\FraudRulesEngine;
use App\Infrastructure\Events\DomainEventEnvelope;
use App\Infrastructure\Events\InMemoryEventPublisher;
use App\Projections\Models\WalletAccount;
use App\Wallet\Aggregates\WalletAccountAggregate;
use App\Wallet\Enums\AccountType;
use App\Wallet\Enums\WalletEventType;
use Illuminate\Support\Str;
use InvalidArgumentException;

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
    ): string {
        $accountId = (string) Str::uuid();
        $aggregate = new WalletAccountAggregate($accountId);
        $aggregate->createAccount($accountType->value, $userId, $correlationId);

        $this->publishRecorded($aggregate);

        return $accountId;
    }

    public function deposit(
        string $accountId,
        int $amountCents,
        string $reference,
        ?string $correlationId = null,
    ): void {
        $aggregate = $this->loadAggregate($accountId);
        $aggregate->deposit($amountCents, $reference, $correlationId);
        $this->publishRecorded($aggregate);
    }

    public function transfer(
        string $fromAccountId,
        string $toAccountId,
        int $amountCents,
        ?string $correlationId = null,
    ): void {
        if (! WalletAccount::query()->where('id', $toAccountId)->exists()) {
            throw new InvalidArgumentException('Conta destino não encontrada.');
        }

        $requested = DomainEventEnvelope::create(
            eventType: WalletEventType::TransferRequested,
            aggregateId: $fromAccountId,
            aggregateType: 'wallet_account',
            payload: [
                'amountCents' => $amountCents,
                'toAccountId' => $toAccountId,
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
                ],
                correlationId: $correlationId,
            ));

            return;
        }

        $this->publisher->publish(DomainEventEnvelope::create(
            eventType: FraudEventType::TransactionApproved,
            aggregateId: $fromAccountId,
            aggregateType: 'wallet_account',
            payload: ['amountCents' => $amountCents],
            correlationId: $correlationId,
        ));

        $aggregate = $this->loadAggregate($fromAccountId);
        $aggregate->transfer($amountCents, $toAccountId, $correlationId);
        $this->publishRecorded($aggregate);
    }

    private function loadAggregate(string $accountId): WalletAccountAggregate
    {
        if ($this->publisher instanceof InMemoryEventPublisher) {
            $events = array_values(array_filter(
                $this->publisher->published(),
                fn (DomainEventEnvelope $e) => $e->aggregateId === $accountId
                    && str_starts_with($e->eventType, 'wallet.'),
            ));
        } else {
            $events = [];
        }

        if ($events === []) {
            if (! WalletAccount::query()->where('id', $accountId)->exists()) {
                throw new InvalidArgumentException('Conta não encontrada.');
            }

            return new WalletAccountAggregate($accountId);
        }

        return WalletAccountAggregate::reconstitute($events);
    }

    private function publishRecorded(WalletAccountAggregate $aggregate): void
    {
        foreach ($aggregate->pullRecordedEvents() as $event) {
            $this->publisher->publish($event);
        }
    }
}
