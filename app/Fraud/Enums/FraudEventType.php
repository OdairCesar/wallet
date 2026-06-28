<?php

namespace App\Fraud\Enums;

final class FraudEventType
{
    public const TransactionApproved = 'fraud.transaction.approved';

    public const TransactionBlocked = 'fraud.transaction.blocked';
}
