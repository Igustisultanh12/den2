<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attendance_date',
        'time_in',
        'status',
        'ip_address',
        'device',
        'user_agent',
        'latitude',
        'longitude',
        'location_name',
        'notes',
        'photo',
        'smile_score',
    ];

    protected $casts = [
        'attendance_date' => 'date:Y-m-d', // hindari pergeseran tanggal akibat konversi UTC saat serialisasi JSON
        'latitude' => 'double',
        'longitude' => 'double',
        'smile_score' => 'integer',
    ];

    protected $appends = [
        'photo_url',
    ];

    public function getPhotoUrlAttribute()
    {
        if (!$this->photo) {
            return null;
        }
        if (str_starts_with($this->photo, 'http://') || str_starts_with($this->photo, 'https://')) {
            return $this->photo;
        }
        return asset('storage/' . $this->photo);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
