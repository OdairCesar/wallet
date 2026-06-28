<?php

namespace App\Projections\Models;

use Illuminate\Database\Eloquent\Model;

class Operation extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'correlation_id';

    protected $keyType = 'string';

    protected $fillable = [
        'correlation_id',
        'status',
        'operation_type',
        'resource_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
