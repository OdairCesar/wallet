<?php

namespace App\OpenFinance\Support;

final class Money
{
    public static function toCents(string|float $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
