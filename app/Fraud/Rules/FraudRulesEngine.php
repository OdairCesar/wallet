<?php

namespace App\Fraud\Rules;

use App\Infrastructure\Events\DomainEventEnvelope;
use App\Wallet\Enums\WalletEventType;

final class FraudRulesEngine
{
    private const int MAX_TRANSACTIONS_PER_HOUR = 20;

    private const int MAX_AMOUNT_CENTS = 10_000_000;

    /** @var array<string, list<int>> */
    private array $velocityTracker = [];

    public function evaluate(DomainEventEnvelope $envelope): FraudDecision
    {
        if ($envelope->eventType !== WalletEventType::TransferRequested
            && $envelope->eventType !== 'payments.pix.initiated') {
            return FraudDecision::approved();
        }

        $amount = $envelope->payload['amountCents'] ?? 0;
        $accountId = $envelope->aggregateId;

        if ($amount > self::MAX_AMOUNT_CENTS) {
            return FraudDecision::blocked('AMOUNT_THRESHOLD', 'Valor acima do limite antifraude.');
        }

        $this->velocityTracker[$accountId] ??= [];
        $this->velocityTracker[$accountId][] = time();

        $recent = array_filter(
            $this->velocityTracker[$accountId],
            fn (int $ts) => $ts >= time() - 3600,
        );

        if (count($recent) > self::MAX_TRANSACTIONS_PER_HOUR) {
            return FraudDecision::blocked('VELOCITY', 'Limite de transações por hora excedido.');
        }

        return FraudDecision::approved();
    }
}
