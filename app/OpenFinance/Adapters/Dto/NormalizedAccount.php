<?php

namespace App\OpenFinance\Adapters\Dto;

final readonly class NormalizedAccount
{
    public function __construct(
        public string $accountId,
        public string $type,
        public string $brandName,
        public string $currency,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'accountId' => $this->accountId,
            'type' => $this->type,
            'brandName' => $this->brandName,
            'currency' => $this->currency,
        ];
    }
}
