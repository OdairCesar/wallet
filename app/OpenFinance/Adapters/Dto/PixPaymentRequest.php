<?php

namespace App\OpenFinance\Adapters\Dto;

final readonly class PixPaymentRequest
{
    public function __construct(
        public string $consentId,
        public int $amountCents,
        public string $currency,
        public string $localInstrument,
        public ?string $creditorAccountId = null,
    ) {}
}
