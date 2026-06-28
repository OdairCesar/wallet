<?php

namespace App\Projections\Models;

use Illuminate\Database\Eloquent\Model;

class Consent extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'consent_id';

    protected $keyType = 'string';

    protected $fillable = [
        'consent_id',
        'client_id',
        'status',
        'permissions',
        'expiration_date_time',
        'creation_date_time',
        'logged_user_document',
        'correlation_id',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'expiration_date_time' => 'datetime',
            'creation_date_time' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expiration_date_time !== null
            && $this->expiration_date_time->isPast();
    }
}
