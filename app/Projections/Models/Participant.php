<?php

namespace App\Projections\Models;

use Illuminate\Database\Eloquent\Model;

class Participant extends Model
{
    protected $fillable = [
        'organisation_id',
        'status',
        'adapter',
    ];
}
