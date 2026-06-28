<?php

namespace App\OpenFinance\Adapters;

use App\OpenFinance\Adapters\Dto\NormalizedAccount;
use App\OpenFinance\Adapters\Dto\NormalizedAccountList;
use App\OpenFinance\Adapters\Dto\NormalizedPaymentResponse;
use App\OpenFinance\Adapters\Dto\PixPaymentRequest;

interface ParticipantAdapterInterface
{
    public function getAccounts(string $consentId): NormalizedAccountList;

    public function initiatePixPayment(PixPaymentRequest $request): NormalizedPaymentResponse;

    public function getPixPayment(string $paymentId): NormalizedPaymentResponse;

    public function cancelPixPayment(string $paymentId): NormalizedPaymentResponse;
}
