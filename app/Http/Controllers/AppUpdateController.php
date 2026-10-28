<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\AppRelease;
use App\Support\PushNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Pembaruan aplikasi Android: Admin mengunggah APK di Pengaturan web,
 * aplikasi di HP personel memeriksa & mengunduhnya dari sini.
 */
class AppUpdateController extends Controller
{
    /** Admin: unggah APK versi baru (Pengaturan → Pembaruan Aplikasi Android). */
    public function store(Request $request)
    {
        // Berkas melebihi batas PHP (upload_max_filesize / post_max_size): $_FILES kosong.
        if (!$request->hasFile('apk')) {
            $limit = ini_get('upload_max_filesize') ?: '?';
            $post = ini_get('post_max_size') ?: '?';
            $f = $request->files->get('apk');
            $tooBig = $f instanceof \Symfony\Component\HttpFoundation\File\UploadedFile
                && in_array($f->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            return back()->with('error', $tooBig
                ? "APK gagal diterima server: ukuran melebihi batas unggah PHP (upload_max_filesize {$limit}, post_max_size {$post}). Naikkan keduanya (mis. 100M) di aaPanel → PHP → Konfigurasi, serta client_max_body_size di Nginx."
                : 'Pilih berkas APK yang akan diunggah.');
        }

        $request->validate([
            'apk' => 'required|file|max:204800',
            'notes' => 'nullable|string|max:2000',
            'version' => 'nullable|string|max:20',
            'notify' => 'nullable|boolean',
        ], [
            'apk.uploaded' => 'APK gagal diunggah (melebihi batas upload_max_filesize server).',
            'apk.max' => 'Ukuran APK maksimal 200 MB.',
        ]);

        $file = $request->file('apk');
        if (strtolower($file->getClientOriginalExtension()) !== 'apk') {
            return back()->with('error', 'Berkas harus berekstensi .apk.');
        }

        $result = AppRelease::store($file, (string) $request->input('notes', ''), (string) $request->user()->name, $request->input('version'));
        if (is_string($result)) {
            return back()->with('error', $result);
        }

        return $this->afterStored($request, $result);
    }

    /**
     * Ambil APK rilis terbaru langsung dari GitHub Releases (server yang mengunduh),
     * sehingga Admin tidak perlu mengunggah ±20 MB lewat koneksi kantor yang lambat.
     * Repo sumber ditetapkan di server (services.app_release.github_repo), bukan dari input.
     * Integritas: SHA-256 dicocokkan dengan SHA256SUMS.txt pada rilis yang sama, lalu paket
     * APK diperiksa seperti unggahan biasa.
     */
    public function fetchFromGithub(Request $request)
    {
        $repo = (string) config('services.app_release.github_repo', 'Igustisultanh12/den2');
        if (!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo)) {
            return back()->with('error', 'Repo GitHub rilis tidak valid di konfigurasi server.');
        }
        @set_time_limit(600);

        try {
            $release = \Illuminate\Support\Facades\Http::withHeaders(['Accept' => 'application/vnd.github+json', 'User-Agent' => 'Sinden2-Server'])
                ->timeout(20)->get("https://api.github.com/repos/{$repo}/releases/latest");
        } catch (\Throwable $e) {
            return back()->with('error', 'Server tidak dapat menghubungi GitHub. Periksa koneksi internet server.');
        }
        if (!$release->successful()) {
            return back()->with('error', 'Rilis terbaru tidak ditemukan di GitHub (' . $release->status() . ').');
        }

        $assets = collect($release->json('assets', []));
        $apk = $assets->first(fn ($a) => preg_match('/\.apk$/i', $a['name'] ?? '') && !preg_match('/debug|unsigned/i', $a['name']));
        $sums = $assets->first(fn ($a) => strcasecmp($a['name'] ?? '', 'SHA256SUMS.txt') === 0);
        if (!$apk || !$sums) {
            return back()->with('error', 'Rilis GitHub belum memiliki APK bertanda tangan & SHA256SUMS.txt. Tunggu proses build selesai.');
        }
        if ((int) ($apk['size'] ?? 0) > 200 * 1024 * 1024) {
            return back()->with('error', 'Ukuran APK di GitHub melebihi 200 MB.');
        }

        $tag = ltrim((string) $release->json('tag_name', ''), 'vV');
        $current = AppRelease::current();
        if ($current && $tag !== '' && version_compare($tag, (string) $current['version'], '<=')) {
            return back()->with('success', "Versi {$current['version']} di server sudah yang terbaru (rilis GitHub: {$tag}).");
        }

        $tmp = tempnam(sys_get_temp_dir(), 'apk');
        try {
            $download = \Illuminate\Support\Facades\Http::withHeaders(['User-Agent' => 'Sinden2-Server'])
                ->withOptions(['sink' => $tmp])->timeout(540)->get($apk['browser_download_url']);
            $sumsText = \Illuminate\Support\Facades\Http::withHeaders(['User-Agent' => 'Sinden2-Server'])->timeout(20)->get($sums['browser_download_url'])->body();
        } catch (\Throwable $e) {
            @unlink($tmp);
            return back()->with('error', 'Unduhan APK dari GitHub terputus. Coba lagi.');
        }
        if (!$download->successful() || !is_file($tmp) || filesize($tmp) === 0) {
            @unlink($tmp);
            return back()->with('error', 'Unduhan APK dari GitHub gagal (' . $download->status() . ').');
        }

        $expected = null;
        foreach (preg_split('/\R/', $sumsText) as $line) {
            if (preg_match('/^([a-f0-9]{64})\s+\*?(.+)$/i', trim($line), $m) && trim($m[2]) === $apk['name']) {
                $expected = strtolower($m[1]);
            }
        }
        if ($expected === null || !hash_equals($expected, hash_file('sha256', $tmp))) {
            @unlink($tmp);
            return back()->with('error', 'Checksum APK dari GitHub tidak cocok dengan SHA256SUMS.txt. APK tidak dipasang.');
        }

        $notes = trim((string) $request->input('notes', '')) ?: mb_substr(trim((string) $release->json('name', '')), 0, 2000);
        $result = AppRelease::store($tmp, $notes, (string) $request->user()->name, $tag ?: null);
        @unlink($tmp);
        if (is_string($result)) {
            return back()->with('error', $result);
        }

        return $this->afterStored($request, $result, 'dari GitHub');
    }

    /** Notifikasi push, catatan audit, dan pesan sukses setelah APK tersimpan. */
    private function afterStored(Request $request, array $result, string $source = '')
    {
        $notified = 0;
        if ($request->boolean('notify', true)) {
            $notified = PushNotifier::appUpdate($result['version'], $result['notes']);
            AppRelease::markNotified($notified);
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'admin_name' => $request->user()->name,
            'action' => 'UNGGAH PEMBARUAN APLIKASI ANDROID',
            'target_personnel' => 'Aplikasi Android Sinden 2',
            'description' => "Mengunggah APK versi {$result['version']} (" . round($result['size'] / 1048576, 1) . ' MB)' . ($source ? " {$source}" : ''),
            'ip_address' => $request->ip(),
        ]);

        $msg = "APK Sinden 2 versi {$result['version']} berhasil " . ($source ? "diambil {$source}." : 'diunggah.');
        $msg .= $notified > 0
            ? " Pemberitahuan pembaruan dikirim ke {$notified} personel."
            : ($request->boolean('notify', true) ? ' Notifikasi push belum aktif; HP akan menemukan pembaruan saat aplikasi dibuka.' : '');
        return back()->with('success', $msg);
    }

    /** Admin: hapus APK yang diunggah (aplikasi berhenti menawarkan pembaruan). */
    public function destroy(Request $request)
    {
        $prev = AppRelease::current();
        AppRelease::clear();
        if ($prev) {
            AuditLog::create([
                'user_id' => $request->user()->id,
                'admin_name' => $request->user()->name,
                'action' => 'HAPUS PEMBARUAN APLIKASI ANDROID',
                'target_personnel' => 'Aplikasi Android Sinden 2',
                'description' => "Menghapus APK versi {$prev['version']} dari server",
                'ip_address' => $request->ip(),
            ]);
        }
        return back()->with('success', 'APK pembaruan dihapus dari server.');
    }

    /** Batas unggah efektif PHP (byte) untuk peringatan di halaman Pengaturan. */
    public static function uploadLimitBytes(): int
    {
        $toBytes = function ($v): int {
            $v = trim((string) $v);
            if ($v === '' || $v === '-1' || $v === '0') {
                return PHP_INT_MAX;
            }
            $n = (int) $v;
            return match (strtolower(substr($v, -1))) {
                'g' => $n * 1073741824,
                'm' => $n * 1048576,
                'k' => $n * 1024,
                default => $n,
            };
        };
        return min($toBytes(ini_get('upload_max_filesize')), $toBytes(ini_get('post_max_size')));
    }

    /** Aplikasi: informasi versi terbaru. */
    public function latest()
    {
        return response()->json(AppRelease::publicPayload())->header('Cache-Control', 'no-store');
    }

    /** Aplikasi: unduh APK terbaru. */
    public function download()
    {
        $m = AppRelease::current();
        if (!$m) {
            return response()->json(['status' => 'error', 'message' => 'Belum ada pembaruan aplikasi di server.'], 404);
        }
        $path = Storage::disk(AppRelease::DISK)->path($m['path']);
        return response()->download($path, 'Sinden2-' . $m['version'] . '.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Cache-Control' => 'no-store',
            'X-Content-SHA256' => (string) $m['sha256'],
        ]);
    }
}
