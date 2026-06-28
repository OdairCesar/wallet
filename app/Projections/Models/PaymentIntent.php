<?php

namespace App\Projections\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentIntent extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'payment_id';

    protected $keyType = 'string';

    protected $fillable = [
        'payment_id',
        'consent_id',
        'account_id',
        'status',
        'amount_cents',
        'currency',
        'local_instrument',
        'rejection_reason',
        'correlation_id',
    ];
}
