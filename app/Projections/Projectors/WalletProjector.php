<?php

namespace App\Projections\Projectors;

use App\Contracts\EventHandlerInterface;
use App\Infrastructure\Events\DomainEventEnvelope;
use App\OpenFinance\Enums\ConsentEventType;
use App\OpenFinance\Enums\ConsentStatus;
use App\Payments\Enums\PaymentEventType;
use App\Projections\Models\AccountBalance;
use App\Projections\Models\Consent;
use App\Projections\Models\Operation;
use App\Projections\Models\PaymentIntent;
use App\Projections\Models\WalletAccount;
use App\Projections\Models\WalletTransaction;
use App\Wallet\Enums\WalletEventType;
use Illuminate\Support\Str;

final class WalletProjector implements EventHandlerInterface
{
    public function subscribedEvents(): array
    {
        return [
            WalletEventType::AccountCreated,
            WalletEventType::MoneyDeposited,
            WalletEventType::TransferCompleted,
            WalletEventType::TransferFailed,
            WalletEventType::TransferReversed,
            ConsentEventType::Requested,
            ConsentEventType::Authorised,
            ConsentEventType::Rejected,
            ConsentEventType::Revoked,
            PaymentEventType::PixInitiated,
            PaymentEventType::PixCompleted,
            PaymentEventType::PixRejected,
            PaymentEventType::PixCancelled,
        ];
    }

    public function handle(DomainEventEnvelope $envelope): void
    {
        match ($envelope->eventType) {
            WalletEventType::AccountCreated => $this->projectAccountCreated($envelope),
            WalletEventType::MoneyDeposited => $this->projectMoneyDeposited($envelope),
            WalletEventType::TransferCompleted => $this->projectTransferCompleted($envelope),
            WalletEventType::TransferFailed => $this->projectTransferFailed($envelope),
            WalletEventType::TransferReversed => $this->projectTransferReversed($envelope),
            ConsentEventType::Requested => $this->projectConsentRequested($envelope),
            ConsentEventType::Authorised => $this->projectConsentAuthorised($envelope),
            ConsentEventType::Rejected => $this->projectConsentStatus($envelope, ConsentStatus::Rejected->value),
            ConsentEventType::Revoked => $this->projectConsentStatus($envelope, ConsentStatus::Revoked->value),
            PaymentEventType::PixInitiated => $this->projectPixInitiated($envelope),
            PaymentEventType::PixCompleted => $this->projectPixStatus($envelope),
            PaymentEventType::PixRejected => $this->projectPixStatus($envelope),
            PaymentEventType::PixCancelled => $this->projectPixStatus($envelope),
            default => null,
        };

        $this->touchOperation($envelope);
    }

    private function projectAccountCreated(DomainEventEnvelope $envelope): void
    {
        WalletAccount::query()->updateOrCreate(
            ['id' => $envelope->aggregateId],
            [
                'user_id' => $envelope->payload['userId'] ?? null,
                'account_type' => $envelope->payload['accountType'],
                'currency' => $envelope->payload['currency'],
                'status' => $envelope->payload['status'],
                'brand_name' => config('open_finance.brand_name'),
                'compe_code' => '001',
                'branch_code' => '0001',
                'account_number' => Str::substr($envelope->aggregateId, 0, 8),
            ],
        );

        AccountBalance::query()->updateOrCreate(
            ['account_id' => $envelope->aggregateId],
            [
                'available_amount_cents' => 0,
                'blocked_amount_cents' => 0,
                'reserved_amount_cents' => 0,
                'currency' => $envelope->payload['currency'],
                'updated_at' => now(),
            ],
        );
    }

    private function projectMoneyDeposited(DomainEventEnvelope $envelope): void
    {
        $balance = AccountBalance::query()->firstOrCreate(
            ['account_id' => $envelope->aggregateId],
            ['currency' => 'BRL'],
        );

        $balance->available_amount_cents += $envelope->payload['amountCents'];
        $balance->updated_at = now();
        $balance->save();

        WalletTransaction::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $envelope->aggregateId,
            'type' => 'DEPOSIT',
            'amount_cents' => $envelope->payload['amountCents'],
            'currency' => 'BRL',
            'status' => 'COMPLETED',
            'correlation_id' => $envelope->correlationId,
            'reference' => $envelope->payload['reference'] ?? null,
            'occurred_at' => $envelope->occurredAt,
        ]);
    }

    private function projectTransferCompleted(DomainEventEnvelope $envelope): void
    {
        $amount = $envelope->payload['amountCents'];
        $fromId = $envelope->payload['fromAccountId'];
        $toId = $envelope->payload['toAccountId'];

        $this->debitAccount($fromId, $amount);
        $this->creditAccount($toId, $amount);

        $txnId = (string) Str::uuid();

        WalletTransaction::query()->create([
            'id' => $txnId,
            'account_id' => $fromId,
            'counterparty_account_id' => $toId,
            'type' => 'TRANSFER_OUT',
            'amount_cents' => $amount,
            'currency' => 'BRL',
            'status' => 'COMPLETED',
            'correlation_id' => $envelope->correlationId,
            'occurred_at' => $envelope->occurredAt,
        ]);

        WalletTransaction::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $toId,
            'counterparty_account_id' => $fromId,
            'type' => 'TRANSFER_IN',
            'amount_cents' => $amount,
            'currency' => 'BRL',
            'status' => 'COMPLETED',
            'correlation_id' => $envelope->correlationId,
            'occurred_at' => $envelope->occurredAt,
        ]);
    }

    private function projectTransferFailed(DomainEventEnvelope $envelope): void
    {
        WalletTransaction::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $envelope->aggregateId,
            'counterparty_account_id' => $envelope->payload['toAccountId'] ?? null,
            'type' => 'TRANSFER_OUT',
            'amount_cents' => $envelope->payload['amountCents'],
            'currency' => 'BRL',
            'status' => 'FAILED',
            'correlation_id' => $envelope->correlationId,
            'reference' => $envelope->payload['reason'] ?? null,
            'occurred_at' => $envelope->occurredAt,
        ]);
    }

    private function projectTransferReversed(DomainEventEnvelope $envelope): void
    {
        WalletTransaction::query()
            ->where('id', $envelope->payload['transactionId'])
            ->update(['status' => 'REVERSED']);
    }

    private function projectConsentRequested(DomainEventEnvelope $envelope): void
    {
        Consent::query()->updateOrCreate(
            ['consent_id' => $envelope->payload['consentId']],
            [
                'status' => $envelope->payload['status'],
                'client_id' => $envelope->payload['clientId'] ?? null,
                'permissions' => $envelope->payload['permissions'],
                'expiration_date_time' => $envelope->payload['expirationDateTime'] ?? null,
                'creation_date_time' => $envelope->payload['creationDateTime'],
                'logged_user_document' => $envelope->payload['loggedUserDocument'] ?? null,
                'correlation_id' => $envelope->correlationId,
            ],
        );
    }

    private function projectConsentAuthorised(DomainEventEnvelope $envelope): void
    {
        Consent::query()
            ->where('consent_id', $envelope->payload['consentId'])
            ->update(['status' => ConsentStatus::Authorised->value]);
    }

    private function projectConsentStatus(DomainEventEnvelope $envelope, string $status): void
    {
        Consent::query()
            ->where('consent_id', $envelope->payload['consentId'])
            ->update(['status' => $status]);
    }

    private function projectPixInitiated(DomainEventEnvelope $envelope): void
    {
        PaymentIntent::query()->updateOrCreate(
            ['payment_id' => $envelope->payload['paymentId']],
            [
                'consent_id' => $envelope->payload['consentId'],
                'account_id' => $envelope->payload['accountId'] ?? null,
                'status' => $envelope->payload['status'],
                'amount_cents' => $envelope->payload['amountCents'],
                'currency' => $envelope->payload['currency'] ?? 'BRL',
                'local_instrument' => $envelope->payload['localInstrument'] ?? 'DICT',
                'correlation_id' => $envelope->correlationId,
            ],
        );
    }

    private function projectPixStatus(DomainEventEnvelope $envelope): void
    {
        PaymentIntent::query()
            ->where('payment_id', $envelope->payload['paymentId'])
            ->update([
                'status' => $envelope->payload['status'],
                'rejection_reason' => $envelope->payload['rejectionReason'] ?? null,
            ]);
    }

    private function debitAccount(string $accountId, int $amountCents): void
    {
        $balance = AccountBalance::query()->firstOrCreate(['account_id' => $accountId]);
        $balance->available_amount_cents -= $amountCents;
        $balance->updated_at = now();
        $balance->save();
    }

    private function creditAccount(string $accountId, int $amountCents): void
    {
        $balance = AccountBalance::query()->firstOrCreate(['account_id' => $accountId]);
        $balance->available_amount_cents += $amountCents;
        $balance->updated_at = now();
        $balance->save();
    }

    private function touchOperation(DomainEventEnvelope $envelope): void
    {
        if ($envelope->correlationId === null) {
            return;
        }

        Operation::query()->updateOrCreate(
            ['correlation_id' => $envelope->correlationId],
            [
                'status' => $this->resolveOperationStatus($envelope),
                'operation_type' => $envelope->eventType,
                'resource_id' => $envelope->aggregateId,
                'metadata' => array_filter([
                    'client_id' => $envelope->payload['clientId'] ?? null,
                ]),
            ],
        );
    }

    private function resolveOperationStatus(DomainEventEnvelope $envelope): string
    {
        return match ($envelope->eventType) {
            WalletEventType::TransferFailed,
            PaymentEventType::PixRejected => 'failed',
            WalletEventType::TransferCompleted,
            PaymentEventType::PixCompleted,
            WalletEventType::MoneyDeposited => 'completed',
            default => 'processing',
        };
    }
}
