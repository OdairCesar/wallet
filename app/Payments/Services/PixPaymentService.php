<?php

namespace App\Payments\Services;

use App\Contracts\EventPublisherInterface;
use App\Fraud\Enums\FraudEventType;
use App\Fraud\Rules\FraudRulesEngine;
use App\Infrastructure\Events\DomainEventEnvelope;
use App\Payments\Enums\PaymentEventType;
use App\Payments\Enums\PixPaymentStatus;
use App\Projections\Models\AccountBalance;
use App\Projections\Models\Consent;
use App\Projections\Models\ConsentAccount;
use App\Projections\Models\PaymentIntent;
use App\Wallet\Services\WalletCommandService;
use App\OpenFinance\Enums\ConsentStatus;
use App\OpenFinance\Exceptions\OpenFinanceDomainException;
use Illuminate\Support\Str;

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
            throw new OpenFinanceDomainException(
                'CONSENTIMENTO_NAO_ENCONTRADO',
                'Consentimento não encontrado.',
                404,
            );
        }

        if ($consent->status !== ConsentStatus::Authorised->value) {
            throw new OpenFinanceDomainException(
                'CONSENTIMENTO_NAO_AUTORIZADO',
                'Consentimento não está autorizado.',
            );
        }

        if ($consent->isExpired()) {
            throw new OpenFinanceDomainException(
                'CONSENTIMENTO_EXPIRADO',
                'Consentimento expirado.',
            );
        }

        $paymentId = (string) Str::uuid();
        $correlationId = (string) Str::uuid();
        $amountCents = (int) ($paymentData['amountCents'] ?? 0);
        $creditorAccountId = $paymentData['creditorAccountId'] ?? null;
        $debtorAccountId = $paymentData['debtorAccountId'] ?? null;
        $localInstrument = $paymentData['localInstrument'] ?? 'DICT';
        $clientId = $paymentData['clientId'] ?? null;

        if ($debtorAccountId === null) {
            $debtorAccountId = ConsentAccount::query()
                ->where('consent_id', $consentId)
                ->value('account_id');
        }

        if ($debtorAccountId === null) {
            throw new OpenFinanceDomainException(
                'CONTA_DEBITO_NAO_ENCONTRADA',
                'Conta de débito não encontrada.',
            );
        }

        if ($creditorAccountId === null) {
            throw new OpenFinanceDomainException(
                'CONTA_CREDORA_NAO_INFORMADA',
                'Conta credora não informada.',
            );
        }

        $fraudEnvelope = DomainEventEnvelope::create(
            eventType: PaymentEventType::PixInitiated,
            aggregateId: $debtorAccountId,
            aggregateType: 'wallet_account',
            payload: [
                'amountCents' => $amountCents,
                'debtorAccountId' => $debtorAccountId,
                'clientId' => $clientId,
            ],
            correlationId: $correlationId,
        );

        $decision = $this->fraudRules->evaluate($fraudEnvelope);

        if (! $decision->approved) {
            $this->publisher->publish(DomainEventEnvelope::create(
                eventType: FraudEventType::TransactionBlocked,
                aggregateId: $paymentId,
                aggregateType: 'pix_payment',
                payload: [
                    'ruleId' => $decision->ruleId,
                    'reason' => $decision->reason,
                    'clientId' => $clientId,
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
                    'clientId' => $clientId,
                ],
                correlationId: $correlationId,
            ));

            return [
                'paymentId' => $paymentId,
                'correlationId' => $correlationId,
                'status' => PixPaymentStatus::Rejected->value,
            ];
        }

        $this->publisher->publish(DomainEventEnvelope::create(
            eventType: PaymentEventType::PixInitiated,
            aggregateId: $paymentId,
            aggregateType: 'pix_payment',
            payload: [
                'paymentId' => $paymentId,
                'consentId' => $consentId,
                'accountId' => $creditorAccountId,
                'debtorAccountId' => $debtorAccountId,
                'status' => PixPaymentStatus::Received->value,
                'amountCents' => $amountCents,
                'currency' => 'BRL',
                'localInstrument' => $localInstrument,
                'clientId' => $clientId,
            ],
            correlationId: $correlationId,
        ));

        if ($amountCents > 0) {
            $balance = AccountBalance::query()
                ->where('account_id', $debtorAccountId)
                ->value('available_amount_cents');

            if ($balance === null || $balance < $amountCents) {
                throw new OpenFinanceDomainException(
                    'SALDO_INSUFICIENTE',
                    'Saldo insuficiente para o pagamento.',
                );
            }

            $transferred = $this->walletCommands->transfer(
                $debtorAccountId,
                $creditorAccountId,
                $amountCents,
                $correlationId,
                $clientId,
            );

            if (! $transferred) {
                $this->publisher->publish(DomainEventEnvelope::create(
                    eventType: PaymentEventType::PixRejected,
                    aggregateId: $paymentId,
                    aggregateType: 'pix_payment',
                    payload: [
                        'paymentId' => $paymentId,
                        'status' => PixPaymentStatus::Rejected->value,
                        'rejectionReason' => 'TRANSFER_FAILED',
                        'clientId' => $clientId,
                    ],
                    correlationId: $correlationId,
                ));

                return [
                    'paymentId' => $paymentId,
                    'correlationId' => $correlationId,
                    'status' => PixPaymentStatus::Rejected->value,
                ];
            }
        }

        $this->publisher->publish(DomainEventEnvelope::create(
            eventType: PaymentEventType::PixCompleted,
            aggregateId: $paymentId,
            aggregateType: 'pix_payment',
            payload: [
                'paymentId' => $paymentId,
                'status' => PixPaymentStatus::Completed->value,
                'clientId' => $clientId,
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
            throw new OpenFinanceDomainException(
                'PAGAMENTO_NAO_ENCONTRADO',
                'Pagamento não encontrado.',
                404,
            );
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
