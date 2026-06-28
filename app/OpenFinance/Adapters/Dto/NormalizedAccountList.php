<?php

namespace App\OpenFinance\Adapters\Dto;

final readonly class NormalizedAccountList
{
    /**
     * @param  list<NormalizedAccount>  $accounts
     */
    public function __construct(
        public array $accounts,
    ) {}

    /**
     * @return list<array<string, string>>
     */
    public function toArray(): array
    {
        return array_map(fn (NormalizedAccount $a) => $a->toArray(), $this->accounts);
    }
}
