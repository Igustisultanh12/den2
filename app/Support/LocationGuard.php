<?php

namespace App\Support;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\PersonnelLocation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Keamanan lokasi personel:
 *  - Login (web & Android) DITOLAK bila GPS tidak aktif / koordinat tidak dikirim.
 *  - Lokasi palsu (aplikasi fake GPS / mock location) ditolak saat login dan,
 *    bila terdeteksi saat sesi berjalan, token dicabut & Admin diberi tahu.
 *
 * Admin & Super Admin dikecualikan agar selalu ada yang dapat masuk untuk
 * memperbaiki sistem (mis. dari PC kantor tanpa GPS).
 */
class LocationGuard
{
    /** Versi aplikasi Android pertama yang mengirim lokasi saat login. */
    public const MIN_ANDROID_VERSION = '1.8.1';

    public const MSG_GPS_OFF = 'Login ditolak: GPS / lokasi belum aktif. Aktifkan GPS dan izinkan akses lokasi, lalu coba lagi.';
    public const MSG_MOCK = 'Login ditolak: terdeteksi aplikasi lokasi palsu (Fake GPS). Hapus / matikan aplikasi tersebut dan nonaktifkan "lokasi tiruan" di Opsi Pengembang.';
    public const MSG_MOCK_SESSION = 'Sesi diakhiri: terdeteksi aplikasi lokasi palsu (Fake GPS). Kejadian ini dilaporkan ke Admin.';

    public static function appliesTo(?User $user): bool
    {
        return $user !== null && !$user->isAdmin();
    }

    /**
     * Periksa lokasi yang dikirim saat login.
     *
     * @param  array{latitude?:mixed, longitude?:mixed, accuracy?:mixed, is_mock?:mixed, gps_enabled?:mixed}|null  $loc
     * @return array{ok:bool, code?:string, message?:string}
     */
    public static function checkLogin(?array $loc): array
    {
        $loc = $loc ?? [];

        if (filter_var($loc['is_mock'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return ['ok' => false, 'code' => 'mock_location', 'message' => self::MSG_MOCK];
        }

        if (array_key_exists('gps_enabled', $loc) && !filter_var($loc['gps_enabled'], FILTER_VALIDATE_BOOLEAN)) {
            return ['ok' => false, 'code' => 'location_required', 'message' => self::MSG_GPS_OFF];
        }

        $lat = $loc['latitude'] ?? null;
        $lng = $loc['longitude'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lng)
            || (float) $lat < -90 || (float) $lat > 90 || (float) $lng < -180 || (float) $lng > 180
            || ((float) $lat === 0.0 && (float) $lng === 0.0)) {
            return ['ok' => false, 'code' => 'location_required', 'message' => self::MSG_GPS_OFF];
        }

        return ['ok' => true];
    }

    /** Simpan titik lokasi saat login agar personel langsung tampil di Pantau Personel. */
    public static function recordLoginPoint(User $user, array $loc, string $provider, ?string $device = null): void
    {
        try {
            PersonnelTracking::record($user, [[
                'latitude' => (float) $loc['latitude'],
                'longitude' => (float) $loc['longitude'],
                'accuracy' => isset($loc['accuracy']) && is_numeric($loc['accuracy']) ? (float) $loc['accuracy'] : null,
                'provider' => $provider,
            ]], $device);
        } catch (\Throwable $e) {
            Log::warning('[LOCATION_GUARD] Gagal menyimpan lokasi login: ' . $e->getMessage());
        }
    }

    /**
     * Apakah klien Android cukup baru untuk diwajibkan mengirim lokasi saat login.
     * Versi lama tetap boleh login (sudah ada gerbang GPS di aplikasi) agar personel
     * dapat memperbarui aplikasi; atur "Versi minimum" di Pengaturan untuk memaksa update.
     */
    public static function androidClientSendsLocation(?string $userAgent): bool
    {
        if (!$userAgent || !preg_match('#Sinden2-Android/(\d+(?:\.\d+){0,2})#', $userAgent, $m)) {
            return false;
        }
        return version_compare($m[1], self::MIN_ANDROID_VERSION, '>=');
    }

    /** Catat & laporkan lokasi palsu ke Admin (dibatasi 1 notifikasi / 15 menit / personel). */
    public static function reportMock(User $user, string $source, ?array $point = null): void
    {
        try {
            PersonnelLocation::where('user_id', $user->id)->update(['mock_detected_at' => now()]);

            AuditLog::create([
                'user_id' => $user->id,
                'admin_name' => $user->name,
                'action' => 'LOKASI_PALSU',
                'target_personnel' => $user->name,
                'description' => "Terdeteksi aplikasi lokasi palsu (Fake GPS) dari {$source}"
                    . ($point && isset($point['latitude'], $point['longitude']) ? " di {$point['latitude']}, {$point['longitude']}" : '')
                    . '. Sesi/login ditolak.',
                'ip_address' => request()?->ip(),
            ]);

            if (Cache::add('mock-location-notified-' . $user->id, 1, now()->addMinutes(15))) {
                $who = trim(($user->pangkat ? $user->pangkat . ' ' : '') . $user->name . ($user->nrp ? " (NRP {$user->nrp})" : ''));
                AppNotification::notify(
                    null,
                    'admin',
                    'Peringatan: Lokasi Palsu Terdeteksi',
                    "{$who} terdeteksi memakai aplikasi lokasi palsu (Fake GPS) dari {$source}. Login/sesi personel ditolak otomatis.",
                    'warning',
                    '/pantau-personel'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('[LOCATION_GUARD] Gagal melaporkan lokasi palsu: ' . $e->getMessage());
        }
    }
}
