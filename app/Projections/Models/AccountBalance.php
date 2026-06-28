<?php

namespace App\Projections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountBalance extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'account_id';

    protected $keyType = 'string';

    protected $fillable = [
        'account_id',
        'available_amount_cents',
        'blocked_amount_cents',
        'reserved_amount_cents',
        'currency',
        'updated_at',
    ];

    /** @return BelongsTo<WalletAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(WalletAccount::class, 'account_id');
    }
}
