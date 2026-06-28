<?php

namespace App\Projections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentAccount extends Model
{
    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = [
        'consent_id',
        'account_id',
    ];

    public function consent(): BelongsTo
    {
        return $this->belongsTo(Consent::class, 'consent_id', 'consent_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WalletAccount::class, 'account_id', 'id');
    }
}
