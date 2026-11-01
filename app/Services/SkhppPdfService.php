<?php

namespace App\Services;

use App\Models\Skhpp;
use App\Models\SkhppArchive;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pembuatan dokumen PDF resmi SKHPP (DomPDF, ukuran F4) dan penyimpanannya
 * ke Arsip SKHPP setelah TTE Komandan terbit.
 */
class SkhppPdfService
{
    public static function make(Skhpp $skhpp): \Barryvdh\DomPDF\PDF
    {
        $skhpp->loadMissing(['members', 'submitter', 'approver']);

        $foto1_base64 = self::imageBase64($skhpp->foto_1);
        $foto2_base64 = self::imageBase64($skhpp->foto_2);

        // QR Code TTD (hanya untuk SKHPP yang sudah disahkan)
        $qr_base64 = null;
        if ($skhpp->status === 'approved') {
            try {
                $verifyUrl = route('skhpp.verify', $skhpp->verification_code);
                $res = \Illuminate\Support\Facades\Http::timeout(8)->get('https://api.qrserver.com/v1/create-qr-code/', [
                    'size' => '250x250',
                    'data' => $verifyUrl,
                ]);
                $qrData = $res->successful() ? $res->body() : null;
                if ($qrData) {
                    $qr_base64 = 'data:image/png;base64,' . base64_encode($qrData);
                }
            } catch (\Throwable $e) {
                Log::warning('Gagal fetch QR image: ' . $e->getMessage());
            }
        }

        $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $tglLahir = $skhpp->tanggal_lahir ? new \DateTime($skhpp->tanggal_lahir) : null;
        $tanggal_lahir_indo = $tglLahir ? ($tglLahir->format('d') . ' ' . $months[(int) $tglLahir->format('n') - 1] . ' ' . $tglLahir->format('Y')) : '';

        $tglSkhpp = $skhpp->tanggal_skhpp ? new \DateTime($skhpp->tanggal_skhpp) : ($skhpp->approved_at ? new \DateTime($skhpp->approved_at) : new \DateTime());
        $tanggal_skhpp_indo = $tglSkhpp->format('j') . ' ' . $months[(int) $tglSkhpp->format('n') - 1] . ' ' . $tglSkhpp->format('Y');
        $bulan_tahun_indo = $months[(int) $tglSkhpp->format('n') - 1] . ' ' . $tglSkhpp->format('Y');

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.skhpp', compact(
            'skhpp', 'foto1_base64', 'foto2_base64', 'qr_base64',
            'tanggal_lahir_indo', 'tanggal_skhpp_indo', 'bulan_tahun_indo'
        ))->setPaper([0, 0, 609.45, 935.43], 'portrait'); // F4 / FOLIO (21.5cm x 33cm)
    }

    public static function filename(Skhpp $skhpp): string
    {
        return 'SKHPP_' . Str::slug($skhpp->nama) . '.pdf';
    }

    /**
     * Simpan PDF SKHPP yang sudah disahkan ke Arsip SKHPP (status => PDF Tersimpan).
     * Mengembalikan false bila SKHPP belum disahkan atau pembuatan PDF gagal.
     */
    public static function storeToArchive(Skhpp $skhpp, ?int $byUserId = null): bool
    {
        if ($skhpp->status !== 'approved') {
            return false;
        }

        $archive = SkhppArchive::syncFromSkhpp($skhpp);

        try {
            $output = self::make($skhpp)->output();
        } catch (\Throwable $e) {
            Log::warning("Gagal membuat PDF SKHPP #{$skhpp->id} untuk arsip: " . $e->getMessage());
            return false;
        }

        $year = $archive->tahun ?: (int) date('Y');
        $path = "skhpp_archives/{$year}/SKHPP-{$archive->jenis}_" . ($archive->nomor_urut ?: 'x') . '_' . Str::slug($skhpp->nama) . '_' . Str::random(6) . '.pdf';

        $archive->deleteFile();
        Storage::disk('local')->put($path, $output);

        $archive->update([
            'file_path' => $path,
            'file_disk' => 'local',
            'status' => SkhppArchive::STATUS_TERSIMPAN,
            'uploaded_at' => now(),
            'uploaded_by' => $byUserId,
        ]);

        return true;
    }

    private static function imageBase64(?string $path): ?string
    {
        if (!$path || !Storage::disk('public')->exists($path)) {
            return null;
        }
        $full = Storage::disk('public')->path($path);
        return 'data:image/' . pathinfo($full, PATHINFO_EXTENSION) . ';base64,' . base64_encode((string) file_get_contents($full));
    }
}
