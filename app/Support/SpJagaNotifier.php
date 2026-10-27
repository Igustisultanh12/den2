<?php

namespace App\Support;

use App\Models\AppNotification;
use App\Models\SpJaga;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi in-app (lonceng web + notifikasi HP) untuk alur SP Jaga.
 * Aplikasi Android membaca tabel app_notifications dan menampilkan
 * notifikasi bersuara di bilah notifikasi perangkat.
 */
class SpJagaNotifier
{
    private const BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    private static function periode(SpJaga $sp): string
    {
        return (self::BULAN[(int) $sp->bulan] ?? $sp->bulan) . ' ' . $sp->tahun;
    }

    /** Pengajuan SP Jaga dengan TTE → Pasops diminta menandatangani. */
    public static function pendingSignature(SpJaga $sp, ?User $pengaju = null): void
    {
        try {
            $hasPasopsRole = User::where('role', 'pasops')->exists();
            $fallback = $hasPasopsRole ? null : User::where('name', 'like', '%Roni Sumantri%')->first();
            AppNotification::notify(
                $fallback?->id,
                $hasPasopsRole ? 'pasops' : null,
                'Pengajuan TTE SP Jaga',
                'SP Jaga ' . self::periode($sp) . ' (' . ($sp->nomor_sprin ?: 'tanpa nomor') . ') menunggu tanda tangan elektronik Pasops'
                    . ($pengaju ? ' — diajukan oleh ' . $pengaju->name : '') . '.',
                'warning',
                '/sp-jaga'
            );
        } catch (\Throwable $e) {
            Log::warning('[SP_JAGA_NOTIF] Gagal membuat notifikasi Pasops: ' . $e->getMessage());
        }
    }

    /** SP Jaga disahkan → seluruh personel diberi tahu (pengingat H-1 di HP ikut terjadwal). */
    public static function published(SpJaga $sp): void
    {
        try {
            AppNotification::notify(
                null,
                null,
                'Jadwal jaga terbit',
                'SP Jaga ' . self::periode($sp) . ' telah disahkan. Cek tanggal jaga Anda di menu Daftar Jaga.',
                'success',
                '/sp-jaga'
            );
        } catch (\Throwable $e) {
            Log::warning('[SP_JAGA_NOTIF] Gagal membuat notifikasi terbit: ' . $e->getMessage());
        }
    }
}
