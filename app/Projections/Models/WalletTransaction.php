<?php

namespace App\Projections\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'account_id',
        'counterparty_account_id',
        'type',
        'amount_cents',
        'currency',
        'status',
        'fraud_status',
        'correlation_id',
        'reference',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }
}
