<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Token FCM satu perangkat. Token tidak pernah dikirim balik ke klien. */
class DeviceToken extends Model
{
    protected $fillable = ['user_id', 'token_hash', 'token', 'platform', 'app_version', 'device_name', 'last_seen_at'];

    protected $hidden = ['token'];

    protected $casts = ['last_seen_at' => 'datetime'];

    public static function hashOf(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Daftarkan/pindahkan token ke pengguna (satu token = satu pemilik). */
    public static function register(int $userId, string $token, ?string $version = null, ?string $device = null): self
    {
        return self::updateOrCreate(
            ['token_hash' => self::hashOf($token)],
            ['user_id' => $userId, 'token' => $token, 'platform' => 'android', 'app_version' => $version, 'device_name' => $device, 'last_seen_at' => now()]
        );
    }
}
