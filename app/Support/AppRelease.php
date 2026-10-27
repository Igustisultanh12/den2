<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Pembaruan aplikasi Android yang diunggah Admin di Pengaturan web.
 *
 * Berkas APK disimpan di disk privat (storage/app/private/app-updates) dan
 * metadatanya di tabel settings (kunci `app_update_meta`). Aplikasi Android
 * memeriksa /api/mobile/app-update, lalu mengunduh dari
 * /api/mobile/app-update/download dan mencocokkan SHA-256 + tanda tangan APK
 * sebelum memasangnya.
 */
class AppRelease
{
    public const PACKAGE = 'id.my.sinden2.app';
    public const DISK = 'local';
    public const DIR = 'app-updates';
    public const KEY = 'app_update_meta';

    /** @return array<string, mixed>|null */
    public static function current(): ?array
    {
        $raw = Setting::where('key', self::KEY)->value('value');
        $meta = $raw ? json_decode((string) $raw, true) : null;
        if (!is_array($meta) || empty($meta['path']) || empty($meta['version'])) {
            return null;
        }
        if (!Storage::disk(self::DISK)->exists($meta['path'])) {
            return null;
        }
        return $meta;
    }

    /** Data publik untuk aplikasi (tanpa lokasi berkas di server). */
    public static function publicPayload(): array
    {
        $m = self::current();
        if (!$m) {
            return ['status' => 'success', 'available' => false];
        }
        return [
            'status' => 'success',
            'available' => true,
            'version' => (string) $m['version'],
            'version_code' => (int) ($m['version_code'] ?? 0),
            'notes' => (string) ($m['notes'] ?? ''),
            'size' => (int) ($m['size'] ?? 0),
            'sha256' => (string) ($m['sha256'] ?? ''),
            'file_name' => 'Sinden2-' . $m['version'] . '.apk',
            'uploaded_at' => (string) ($m['uploaded_at'] ?? ''),
            'download_url' => url('/api/mobile/app-update/download') . '?v=' . rawurlencode((string) $m['version']),
        ];
    }

    /** Ringkasan untuk halaman Pengaturan web. */
    public static function adminSummary(): ?array
    {
        $m = self::current();
        if (!$m) {
            return null;
        }
        return [
            'version' => $m['version'],
            'version_code' => (int) ($m['version_code'] ?? 0),
            'notes' => (string) ($m['notes'] ?? ''),
            'size' => (int) ($m['size'] ?? 0),
            'sha256' => (string) ($m['sha256'] ?? ''),
            'uploaded_at' => (string) ($m['uploaded_at'] ?? ''),
            'uploaded_by' => (string) ($m['uploaded_by'] ?? ''),
            'notified' => (int) ($m['notified'] ?? 0),
        ];
    }

    /**
     * Simpan APK baru. Mengembalikan pesan error (string) atau metadata (array).
     *
     * @return array<string, mixed>|string
     */
    public static function store(UploadedFile $file, string $notes, string $uploader, ?string $manualVersion = null)
    {
        $tmp = $file->getRealPath();
        if (!$tmp || !is_file($tmp)) {
            return 'Berkas APK tidak terbaca. Coba unggah ulang.';
        }
        $info = ApkInspector::inspect($tmp);
        if ($info === null) {
            return 'Berkas bukan APK Android yang valid.';
        }
        if ($info['package'] !== self::PACKAGE) {
            return 'APK ini bukan aplikasi Sinden 2 (paket ' . $info['package'] . ').';
        }
        $version = $info['version_name'] !== '' ? $info['version_name'] : trim((string) $manualVersion);
        if (!preg_match('/^\d+(\.\d+){1,3}$/', $version)) {
            return 'Nomor versi APK tidak terbaca. Isi kolom versi (contoh 1.7.0).';
        }
        $prev = self::current();
        if ($prev && version_compare($version, (string) $prev['version'], '<')) {
            return "Versi {$version} lebih lama dari versi yang sudah diunggah ({$prev['version']}).";
        }

        $sha = hash_file('sha256', $tmp);
        $size = (int) filesize($tmp);
        $path = self::DIR . '/Sinden2-' . $version . '-' . substr($sha, 0, 8) . '.apk';
        $stored = Storage::disk(self::DISK)->putFileAs(self::DIR, $file, basename($path));
        if (!$stored) {
            return 'APK gagal disimpan di server (periksa izin folder storage).';
        }
        if ($prev && $prev['path'] !== $stored) {
            Storage::disk(self::DISK)->delete($prev['path']);
        }

        $meta = [
            'version' => $version,
            'version_code' => (int) $info['version_code'],
            'notes' => mb_substr(trim($notes), 0, 2000),
            'size' => $size,
            'sha256' => $sha,
            'path' => $stored,
            'uploaded_at' => now()->toIso8601String(),
            'uploaded_by' => $uploader,
            'notified' => 0,
        ];
        Setting::updateOrCreate(['key' => self::KEY], ['value' => json_encode($meta)]);
        return $meta;
    }

    public static function markNotified(int $count): void
    {
        $m = self::current();
        if ($m) {
            $m['notified'] = $count;
            Setting::updateOrCreate(['key' => self::KEY], ['value' => json_encode($m)]);
        }
    }

    public static function clear(): void
    {
        $m = self::current();
        if ($m) {
            Storage::disk(self::DISK)->delete($m['path']);
        }
        Setting::where('key', self::KEY)->delete();
    }
}
