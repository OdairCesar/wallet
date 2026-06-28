<?php

namespace App\OpenFinance\Http\Resources;

use App\Projections\Models\AccountBalance;

final class BalanceResource
{
    public static function fromModel(AccountBalance $balance): array
    {
        return [
            'availableAmount' => self::money($balance->available_amount_cents, $balance->currency),
            'blockedAmount' => self::money($balance->blocked_amount_cents, $balance->currency),
            'automaticallyInvestedAmount' => self::money(0, $balance->currency),
        ];
    }

    private static function money(int $amountCents, string $currency): array
    {
        return [
            'amount' => number_format($amountCents / 100, 2, '.', ''),
            'currency' => $currency,
        ];
    }
}
