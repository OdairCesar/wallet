<?php

namespace App\OpenFinance\Adapters;

use App\OpenFinance\Adapters\Dto\NormalizedAccount;
use App\OpenFinance\Adapters\Dto\NormalizedAccountList;
use App\OpenFinance\Adapters\Dto\NormalizedPaymentResponse;
use App\OpenFinance\Adapters\Dto\PixPaymentRequest;
use Illuminate\Support\Str;

/**
 * Adapter sandbox — simula respostas OF de um participante externo.
 * Na VM, substitua por OpenBankingBrasilAdapter com HTTP + mTLS real.
 */
final class MockParticipantAdapter implements ParticipantAdapterInterface
{
    public function getAccounts(string $consentId): NormalizedAccountList
    {
        return new NormalizedAccountList([
            new NormalizedAccount(
                accountId: (string) Str::uuid(),
                type: 'PERSONAL',
                brandName: 'Banco Mock',
                currency: 'BRL',
            ),
        ]);
    }

    public function initiatePixPayment(PixPaymentRequest $request): NormalizedPaymentResponse
    {
        return new NormalizedPaymentResponse(
            paymentId: (string) Str::uuid(),
            consentId: $request->consentId,
            status: 'RCVD',
            amountCents: $request->amountCents,
            currency: $request->currency,
        );
    }

    public function getPixPayment(string $paymentId): NormalizedPaymentResponse
    {
        return new NormalizedPaymentResponse(
            paymentId: $paymentId,
            consentId: 'urn:mock:consent',
            status: 'ACSC',
            amountCents: 10000,
            currency: 'BRL',
        );
    }

    public function cancelPixPayment(string $paymentId): NormalizedPaymentResponse
    {
        return new NormalizedPaymentResponse(
            paymentId: $paymentId,
            consentId: 'urn:mock:consent',
            status: 'CANC',
            amountCents: 10000,
            currency: 'BRL',
        );
    }
}
