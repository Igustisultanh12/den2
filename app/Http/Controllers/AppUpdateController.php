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

        $notified = 0;
        if ($request->boolean('notify', true)) {
            $notified = PushNotifier::appUpdate($result['version'], $result['notes']);
            AppRelease::markNotified($notified);
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'admin_name' => $request->user()->name,
            'action' => 'UNGGAH PEMBARUAN APLIKASI ANDROID',
            'target_personnel' => 'Aplikasi Android SiNDEN',
            'description' => "Mengunggah APK versi {$result['version']} (" . round($result['size'] / 1048576, 1) . ' MB)',
            'ip_address' => $request->ip(),
        ]);

        $msg = "APK SiNDEN versi {$result['version']} berhasil diunggah.";
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
                'target_personnel' => 'Aplikasi Android SiNDEN',
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
        return response()->download($path, 'SISINDEN-' . $m['version'] . '.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Cache-Control' => 'no-store',
            'X-Content-SHA256' => (string) $m['sha256'],
        ]);
    }
}
