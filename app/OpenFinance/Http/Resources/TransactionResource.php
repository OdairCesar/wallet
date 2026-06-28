<?php

namespace App\OpenFinance\Http\Resources;

use App\Projections\Models\WalletTransaction;

final class TransactionResource
{
    public static function fromModel(WalletTransaction $transaction): array
    {
        return [
            'transactionId' => $transaction->id,
            'completedAuthorisedPaymentType' => 'TRANSFER',
            'creditDebitType' => str_contains($transaction->type, 'OUT') ? 'DEBITO' : 'CREDITO',
            'transactionName' => $transaction->type,
            'type' => $transaction->type,
            'transactionAmount' => [
                'amount' => number_format($transaction->amount_cents / 100, 2, '.', ''),
                'currency' => $transaction->currency,
            ],
            'transactionDateTime' => $transaction->occurred_at->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
