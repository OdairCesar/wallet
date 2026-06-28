<?php

namespace App\Payments\Services;

use App\Contracts\EventPublisherInterface;
use App\Fraud\Enums\FraudEventType;
use App\Fraud\Rules\FraudRulesEngine;
use App\Infrastructure\Events\DomainEventEnvelope;
use App\Payments\Enums\PaymentEventType;
use App\Payments\Enums\PixPaymentStatus;
use App\Projections\Models\Consent;
use App\Projections\Models\PaymentIntent;
use App\Wallet\Enums\WalletEventType;
use App\Wallet\Services\WalletCommandService;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PixPaymentService
{
    public function __construct(
        private readonly EventPublisherInterface $publisher,
        private readonly FraudRulesEngine $fraudRules,
        private readonly WalletCommandService $walletCommands,
    ) {}

    /**
     * @param  array<string, mixed>  $paymentData
     * @return array{paymentId: string, correlationId: string, status: string}
     */
    public function initiate(string $consentId, array $paymentData): array
    {
        $consent = Consent::query()->where('consent_id', $consentId)->first();

        if ($consent === null) {
            throw new InvalidArgumentException('CONSENTIMENTO_NAO_ENCONTRADO');
        }

        if ($consent->status !== 'AUTHORISED') {
            throw new InvalidArgumentException('CONSENTIMENTO_NAO_AUTORIZADO');
        }

        $paymentId = (string) Str::uuid();
        $correlationId = (string) Str::uuid();
        $amountCents = (int) ($paymentData['amountCents'] ?? 0);
        $accountId = $paymentData['accountId'] ?? null;
        $localInstrument = $paymentData['localInstrument'] ?? 'DICT';

        $envelope = DomainEventEnvelope::create(
            eventType: PaymentEventType::PixInitiated,
            aggregateId: $paymentId,
            aggregateType: 'pix_payment',
            payload: [
                'paymentId' => $paymentId,
                'consentId' => $consentId,
                'accountId' => $accountId,
                'status' => PixPaymentStatus::Received->value,
                'amountCents' => $amountCents,
                'currency' => 'BRL',
                'localInstrument' => $localInstrument,
            ],
            correlationId: $correlationId,
        );

        $decision = $this->fraudRules->evaluate($envelope);

        if (! $decision->approved) {
            $this->publisher->publish(DomainEventEnvelope::create(
                eventType: FraudEventType::TransactionBlocked,
                aggregateId: $paymentId,
                aggregateType: 'pix_payment',
                payload: [
                    'ruleId' => $decision->ruleId,
                    'reason' => $decision->reason,
                ],
                correlationId: $correlationId,
            ));

            $this->publisher->publish(DomainEventEnvelope::create(
                eventType: PaymentEventType::PixRejected,
                aggregateId: $paymentId,
                aggregateType: 'pix_payment',
                payload: [
                    'paymentId' => $paymentId,
                    'status' => PixPaymentStatus::Rejected->value,
                    'rejectionReason' => $decision->ruleId,
                ],
                correlationId: $correlationId,
            ));

            return [
                'paymentId' => $paymentId,
                'correlationId' => $correlationId,
                'status' => PixPaymentStatus::Rejected->value,
            ];
        }

        $this->publisher->publish($envelope);

        if ($accountId !== null && $amountCents > 0) {
            $this->walletCommands->deposit($accountId, $amountCents, "pix:{$paymentId}", $correlationId);
        }

        $this->publisher->publish(DomainEventEnvelope::create(
            eventType: PaymentEventType::PixCompleted,
            aggregateId: $paymentId,
            aggregateType: 'pix_payment',
            payload: [
                'paymentId' => $paymentId,
                'status' => PixPaymentStatus::Completed->value,
            ],
            correlationId: $correlationId,
        ));

        return [
            'paymentId' => $paymentId,
            'correlationId' => $correlationId,
            'status' => PixPaymentStatus::Completed->value,
        ];
    }

    public function cancel(string $paymentId): void
    {
        $payment = PaymentIntent::query()->where('payment_id', $paymentId)->first();

        if ($payment === null) {
            throw new InvalidArgumentException('Pagamento não encontrado.');
        }

        $this->publisher->publish(DomainEventEnvelope::create(
            eventType: PaymentEventType::PixCancelled,
            aggregateId: $paymentId,
            aggregateType: 'pix_payment',
            payload: [
                'paymentId' => $paymentId,
                'status' => PixPaymentStatus::Cancelled->value,
            ],
        ));
    }
}
