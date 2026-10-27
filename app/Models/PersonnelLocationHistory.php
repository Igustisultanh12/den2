<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Jejak titik lokasi personel (append-only). */
class PersonnelLocationHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'accuracy',
        'speed',
        'battery',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'double',
        'longitude' => 'double',
        'accuracy' => 'float',
        'speed' => 'float',
        'battery' => 'integer',
        'recorded_at' => 'datetime',
    ];
}
