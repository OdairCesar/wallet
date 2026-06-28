<?php

namespace App\OpenFinance\Adapters\Dto;

final readonly class NormalizedPaymentResponse
{
    public function __construct(
        public string $paymentId,
        public string $consentId,
        public string $status,
        public int $amountCents,
        public string $currency,
        public ?string $rejectionReason = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'paymentId' => $this->paymentId,
            'consentId' => $this->consentId,
            'status' => $this->status,
            'amountCents' => $this->amountCents,
            'currency' => $this->currency,
            'rejectionReason' => $this->rejectionReason,
        ];
    }
}
