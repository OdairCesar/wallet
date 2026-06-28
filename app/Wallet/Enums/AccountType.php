<?php

namespace App\Wallet\Enums;

enum AccountType: string
{
    case Personal = 'PERSONAL';
    case Business = 'BUSINESS';
    case Savings = 'SAVINGS';
}
