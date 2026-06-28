<?php

namespace App\Projections\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WalletAccount extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'account_type',
        'currency',
        'status',
        'brand_name',
        'compe_code',
        'branch_code',
        'account_number',
    ];

    /** @return HasOne<AccountBalance, $this> */
    public function balance(): HasOne
    {
        return $this->hasOne(AccountBalance::class, 'account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
