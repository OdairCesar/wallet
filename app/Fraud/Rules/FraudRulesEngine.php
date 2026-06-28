<?php

namespace App\Fraud\Rules;

use App\Infrastructure\Events\DomainEventEnvelope;
use App\Payments\Enums\PaymentEventType;
use App\Projections\Models\WalletTransaction;
use App\Wallet\Enums\WalletEventType;

final class FraudRulesEngine
{
    public function evaluate(DomainEventEnvelope $envelope): FraudDecision
    {
        if ($envelope->eventType !== WalletEventType::TransferRequested
            && $envelope->eventType !== PaymentEventType::PixInitiated) {
            return FraudDecision::approved();
        }

        $amount = $envelope->payload['amountCents'] ?? 0;
        $accountId = $envelope->payload['debtorAccountId']
            ?? $envelope->payload['accountId']
            ?? $envelope->aggregateId;

        $maxAmount = config('open_finance.fraud.max_amount_cents');

        if ($amount > $maxAmount) {
            return FraudDecision::blocked('AMOUNT_THRESHOLD', 'Valor acima do limite antifraude.');
        }

        $maxPerHour = config('open_finance.fraud.max_transactions_per_hour');
        $recentCount = WalletTransaction::query()
            ->where('account_id', $accountId)
            ->where('occurred_at', '>=', now()->subHour())
            ->count();

        if ($recentCount >= $maxPerHour) {
            return FraudDecision::blocked('VELOCITY', 'Limite de transações por hora excedido.');
        }

        return FraudDecision::approved();
    }
}
