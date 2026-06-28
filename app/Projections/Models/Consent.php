<?php

namespace App\Projections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $consent_id
 * @property string|null $client_id
 * @property string $status
 * @property list<string>|null $permissions
 * @property Carbon|null $expiration_date_time
 * @property Carbon|null $creation_date_time
 * @property string|null $logged_user_document
 * @property Carbon|null $updated_at
 */
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
