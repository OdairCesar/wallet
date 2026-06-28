<?php

namespace App\Wallet\Aggregates;

use App\Infrastructure\Events\DomainEventEnvelope;
use App\Wallet\Enums\WalletEventType;
use DomainException;
use InvalidArgumentException;

final class WalletAccountAggregate
{
    private string $accountId;

    private string $currency;

    private int $availableAmountCents = 0;

    private int $reservedAmountCents = 0;

    private int $dailyTransferredCents = 0;

    private int $dailyTransferLimitCents;

    /** @var list<DomainEventEnvelope> */
    private array $recordedEvents = [];

    public function __construct(
        string $accountId,
        ?int $dailyTransferLimitCents = null,
    ) {
        $this->accountId = $accountId;
        $this->currency = 'BRL';
        $this->dailyTransferLimitCents = $dailyTransferLimitCents
            ?? (int) config('open_finance.defaults.daily_transfer_limit');
    }

    /**
     * @param  list<DomainEventEnvelope>  $events
     */
    public static function reconstitute(array $events): self
    {
        if ($events === []) {
            throw new InvalidArgumentException('Cannot reconstitute aggregate from empty event stream.');
        }

        $first = $events[0];
        $aggregate = new self($first->aggregateId);
        $aggregate->applyAll($events);

        return $aggregate;
    }

    /**
     * @param  list<DomainEventEnvelope>  $events
     */
    public function applyAll(array $events): void
    {
        foreach ($events as $event) {
            $this->apply($event);
        }
    }

    public function createAccount(
        string $accountType,
        ?int $userId = null,
        ?string $correlationId = null,
    ): void {
        $this->record(WalletEventType::AccountCreated, [
            'userId' => $userId,
            'accountType' => $accountType,
            'currency' => $this->currency,
            'status' => 'ACTIVE',
        ], $correlationId);
    }

    public function deposit(int $amountCents, string $reference, ?string $correlationId = null): void
    {
        $this->assertPositive($amountCents);

        $this->record(WalletEventType::MoneyDeposited, [
            'amountCents' => $amountCents,
            'reference' => $reference,
        ], $correlationId);
    }

    public function transfer(
        int $amountCents,
        string $toAccountId,
        ?string $correlationId = null,
    ): void {
        $this->assertPositive($amountCents);

        if ($this->availableAmountCents < $amountCents) {
            $this->record(WalletEventType::TransferFailed, [
                'amountCents' => $amountCents,
                'toAccountId' => $toAccountId,
                'reason' => 'INSUFFICIENT_BALANCE',
            ], $correlationId);

            return;
        }

        if ($this->dailyTransferredCents + $amountCents > $this->dailyTransferLimitCents) {
            $this->record(WalletEventType::TransferFailed, [
                'amountCents' => $amountCents,
                'toAccountId' => $toAccountId,
                'reason' => 'DAILY_LIMIT_EXCEEDED',
            ], $correlationId);

            return;
        }

        $this->record(WalletEventType::TransferCompleted, [
            'amountCents' => $amountCents,
            'fromAccountId' => $this->accountId,
            'toAccountId' => $toAccountId,
        ], $correlationId);
    }

    public function reverseTransfer(string $transactionId, ?string $correlationId = null): void
    {
        $this->record(WalletEventType::TransferReversed, [
            'transactionId' => $transactionId,
        ], $correlationId);
    }

    public function availableAmountCents(): int
    {
        return $this->availableAmountCents;
    }

    public function reservedAmountCents(): int
    {
        return $this->reservedAmountCents;
    }

    /**
     * @return list<DomainEventEnvelope>
     */
    public function pullRecordedEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    private function apply(DomainEventEnvelope $event): void
    {
        match ($event->eventType) {
            WalletEventType::AccountCreated => $this->applyAccountCreated($event),
            WalletEventType::MoneyDeposited => $this->applyMoneyDeposited($event),
            WalletEventType::TransferCompleted => $this->applyTransferCompleted($event),
            WalletEventType::TransferFailed => null,
            WalletEventType::TransferReversed => $this->applyTransferReversed($event),
            WalletEventType::FundsReserved => $this->applyFundsReserved($event),
            WalletEventType::FundsReleased => $this->applyFundsReleased($event),
            default => throw new DomainException("Unknown event type: {$event->eventType}"),
        };
    }

    private function applyAccountCreated(DomainEventEnvelope $event): void
    {
        $this->currency = $event->payload['currency'];
    }

    private function applyMoneyDeposited(DomainEventEnvelope $event): void
    {
        $this->availableAmountCents += $event->payload['amountCents'];
    }

    private function applyTransferCompleted(DomainEventEnvelope $event): void
    {
        $amount = $event->payload['amountCents'];
        $this->availableAmountCents -= $amount;
        $this->dailyTransferredCents += $amount;
    }

    private function applyTransferReversed(DomainEventEnvelope $event): void
    {
        $amount = $event->payload['amountCents'] ?? 0;
        if ($amount > 0) {
            $this->availableAmountCents += $amount;
        }
    }

    private function applyFundsReserved(DomainEventEnvelope $event): void
    {
        $amount = $event->payload['amountCents'];
        $this->availableAmountCents -= $amount;
        $this->reservedAmountCents += $amount;
    }

    private function applyFundsReleased(DomainEventEnvelope $event): void
    {
        $amount = $event->payload['amountCents'];
        $this->reservedAmountCents -= $amount;
        $this->availableAmountCents += $amount;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(string $eventType, array $payload, ?string $correlationId = null): void
    {
        $envelope = DomainEventEnvelope::create(
            eventType: $eventType,
            aggregateId: $this->accountId,
            aggregateType: 'wallet_account',
            payload: $payload,
            correlationId: $correlationId,
        );

        $this->apply($envelope);
        $this->recordedEvents[] = $envelope;
    }

    private function assertPositive(int $amountCents): void
    {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Amount must be positive.');
        }
    }
}
