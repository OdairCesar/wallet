<?php

namespace App\OpenFinance\Enums;

enum OpenFinanceScope: string
{
    case AccountsRead = 'ACCOUNTS_READ';
    case AccountsWrite = 'ACCOUNTS_WRITE';
    case PaymentsInitiate = 'PAYMENTS_INITIATE';
    case PaymentsRead = 'PAYMENTS_READ';
    case ResourcesRead = 'RESOURCES_READ';
    case ConsentsRead = 'CONSENTS_READ';
    case ConsentsWrite = 'CONSENTS_WRITE';
}
