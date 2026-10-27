<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Posisi terakhir seorang personel (satu baris per personel). */
class PersonnelLocation extends Model
{
    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'accuracy',
        'speed',
        'heading',
        'battery',
        'is_charging',
        'provider',
        'device',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'double',
        'longitude' => 'double',
        'accuracy' => 'float',
        'speed' => 'float',
        'heading' => 'float',
        'battery' => 'integer',
        'is_charging' => 'boolean',
        'recorded_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
