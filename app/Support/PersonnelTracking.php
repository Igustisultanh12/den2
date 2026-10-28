<?php

namespace App\Support;

use App\Models\PersonnelLocation;
use App\Models\PersonnelLocationHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Logika pelacakan lokasi personel yang dipakai bersama oleh API mobile
 * (pengiriman titik dari aplikasi Android) dan halaman web Pantau Personel.
 */
class PersonnelTracking
{
    /** Personel dianggap "online" bila titik terakhir lebih baru dari batas ini. */
    public const ONLINE_SECONDS = 5 * 60;

    /** Jejak lokasi yang lebih tua dari ini dihapus oleh jadwal harian. */
    public const HISTORY_RETENTION_DAYS = 30;

    /**
     * Simpan satu atau beberapa titik lokasi milik $user.
     * Titik dengan waktu di masa depan dipotong ke waktu server; titik yang lebih
     * lama dari posisi terakhir tetap masuk jejak tetapi tidak menimpa posisi terakhir.
     *
     * @param  array<int, array<string, mixed>>  $points
     */
    public static function record(User $user, array $points, ?string $device = null): ?PersonnelLocation
    {
        if ($points === []) {
            return PersonnelLocation::where('user_id', $user->id)->first();
        }

        $now = now();
        $rows = [];
        foreach ($points as $p) {
            // HP mengirim ISO-8601 UTC ("...Z"). Wajib dikonversi ke zona waktu aplikasi
            // sebelum disimpan; jika tidak, jam UTC tersimpan lalu dibaca sebagai WIB
            // sehingga setiap titik baru tampak "7 jam lalu" (offline).
            $recordedAt = isset($p['recorded_at'])
                ? Carbon::parse($p['recorded_at'])->setTimezone(config('app.timezone'))
                : $now->copy();
            if ($recordedAt->greaterThan($now)) {
                $recordedAt = $now->copy();
            }
            $rows[] = [
                'latitude' => (float) $p['latitude'],
                'longitude' => (float) $p['longitude'],
                'accuracy' => isset($p['accuracy']) ? (float) $p['accuracy'] : null,
                'speed' => isset($p['speed']) ? (float) $p['speed'] : null,
                'heading' => isset($p['heading']) ? (float) $p['heading'] : null,
                'battery' => isset($p['battery']) ? (int) $p['battery'] : null,
                'is_charging' => isset($p['is_charging']) ? (bool) $p['is_charging'] : null,
                'provider' => $p['provider'] ?? null,
                'recorded_at' => $recordedAt,
            ];
        }

        usort($rows, fn ($a, $b) => $a['recorded_at'] <=> $b['recorded_at']);
        $latest = end($rows);

        return DB::transaction(function () use ($user, $rows, $latest, $device, $now) {
            PersonnelLocationHistory::insert(array_map(fn ($r) => [
                'user_id' => $user->id,
                'latitude' => $r['latitude'],
                'longitude' => $r['longitude'],
                'accuracy' => $r['accuracy'],
                'speed' => $r['speed'],
                'battery' => $r['battery'],
                'recorded_at' => $r['recorded_at'],
                'created_at' => $now,
            ], $rows));

            $current = PersonnelLocation::where('user_id', $user->id)->first();
            if ($current && $current->recorded_at && $current->recorded_at->greaterThan($latest['recorded_at'])) {
                return $current;
            }

            return PersonnelLocation::updateOrCreate(
                ['user_id' => $user->id],
                array_merge($latest, ['device' => $device ?? $current?->device])
            );
        });
    }

    /**
     * Posisi terakhir seluruh personel aktif yang pernah mengirim lokasi.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function snapshot(): array
    {
        $now = now();

        return PersonnelLocation::query()
            // Jangan membatasi kolom: tabel users produksi tidak punya kolom "jabatan",
            // dan MySQL menolak kolom yang tidak ada (SQLite di test tidak).
            ->with('user')
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->orderByDesc('recorded_at')
            ->get()
            ->map(fn (PersonnelLocation $loc) => self::present($loc, $now))
            ->values()
            ->all();
    }

    /**
     * Jejak lokasi seorang personel dalam rentang $hours jam terakhir (maks. 500 titik terbaru).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function history(int $userId, int $hours = 12): array
    {
        $hours = max(1, min($hours, 24 * 7));

        return PersonnelLocationHistory::where('user_id', $userId)
            ->where('recorded_at', '>=', now()->subHours($hours))
            ->orderByDesc('recorded_at')
            ->limit(500)
            ->get(['latitude', 'longitude', 'accuracy', 'speed', 'battery', 'recorded_at'])
            ->reverse()
            ->map(fn ($h) => [
                'latitude' => $h->latitude,
                'longitude' => $h->longitude,
                'accuracy' => $h->accuracy,
                'speed' => $h->speed,
                'battery' => $h->battery,
                'recorded_at' => $h->recorded_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    public static function pruneHistory(): int
    {
        return PersonnelLocationHistory::where('recorded_at', '<', now()->subDays(self::HISTORY_RETENTION_DAYS))->delete();
    }

    /** @return array<string, mixed> */
    private static function present(PersonnelLocation $loc, Carbon $now): array
    {
        $ageSeconds = $loc->recorded_at ? max(0, $loc->recorded_at->diffInSeconds($now, false)) : null;
        $user = $loc->user;

        return [
            'user_id' => $loc->user_id,
            'name' => $user?->name,
            'pangkat' => $user?->pangkat,
            'nrp' => $user?->nrp,
            'jabatan' => $user?->jabatan,
            'role' => $user?->role,
            'latitude' => $loc->latitude,
            'longitude' => $loc->longitude,
            'accuracy' => $loc->accuracy,
            'speed' => $loc->speed,
            'heading' => $loc->heading,
            'battery' => $loc->battery,
            'is_charging' => $loc->is_charging,
            'device' => $loc->device,
            'recorded_at' => $loc->recorded_at?->toIso8601String(),
            'age_seconds' => $ageSeconds !== null ? (int) $ageSeconds : null,
            'is_online' => $ageSeconds !== null && $ageSeconds <= self::ONLINE_SECONDS,
        ];
    }
}
