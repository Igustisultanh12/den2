<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Sanitasi gambar & berkas unggahan.
 *
 * Gambar di-decode lalu di-encode ulang dengan GD sehingga payload yang
 * disisipkan (polyglot "JPG berisi PHP", EXIF berbahaya, data tambahan setelah
 * marker akhir) tidak pernah ikut tersimpan. Berkas non-gambar disimpan dengan
 * nama acak dan ekstensi dari whitelist berdasarkan ISI berkas, bukan nama klien.
 */
class SafeImage
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_PIXELS = 40_000_000; // cegah decompression bomb

    private const ALLOWED_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    /**
     * Decode data URL gambar (jpeg/png/webp) dan kembalikan biner hasil encode ulang,
     * atau null bila bukan gambar valid.
     */
    public static function fromDataUrl(?string $dataUrl, string $format = 'jpeg', int $maxBytes = self::MAX_BYTES): ?string
    {
        if (!is_string($dataUrl) || !preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#i', $dataUrl)) {
            return null;
        }
        $payload = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $binary = base64_decode(str_replace(' ', '+', $payload), true);
        if ($binary === false) {
            return null;
        }

        return self::reencode($binary, $format, $maxBytes);
    }

    /** Encode ulang biner gambar; null bila bukan JPEG/PNG/WEBP valid atau terlalu besar. */
    public static function reencode(string $binary, string $format = 'jpeg', int $maxBytes = self::MAX_BYTES): ?string
    {
        if ($binary === '' || strlen($binary) > $maxBytes) {
            return null;
        }
        $info = @getimagesizefromstring($binary);
        if ($info === false || !in_array($info[2], self::ALLOWED_TYPES, true)) {
            return null;
        }
        if ($info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_PIXELS) {
            return null;
        }

        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return null;
        }

        ob_start();
        if ($format === 'png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagepng($image, null, 6);
        } else {
            // JPEG tidak punya alpha: tempel di atas latar putih agar area transparan tidak hitam.
            $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            imagejpeg($canvas, null, 85);
            imagedestroy($canvas);
        }
        $out = ob_get_clean();
        imagedestroy($image);

        return $out !== false && $out !== '' ? $out : null;
    }

    /**
     * Simpan berkas unggahan ke disk dengan nama acak + ekstensi yang ditebak dari ISI berkas.
     * Ekstensi yang tidak ada di $allowedExtensions ditolak (mis. "foto.jpg" berisi kode PHP
     * akan terdeteksi sebagai php dan ditolak). Gambar sekaligus di-encode ulang.
     *
     * @param  list<string>  $allowedExtensions
     * @return string|null path relatif di disk, atau null bila ditolak
     */
    public static function storeUpload(UploadedFile $file, string $directory, array $allowedExtensions, string $disk = 'public'): ?string
    {
        $ext = strtolower((string) $file->guessExtension());
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $allowed = array_map('strtolower', $allowedExtensions);
        if ($ext === '' || !in_array($ext, $allowed, true)) {
            return null;
        }

        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'berkas';
        $name = now()->format('YmdHis') . '_' . Str::random(12) . '_' . Str::limit($base, 60, '') . '.' . $ext;
        $directory = trim($directory, '/');

        if (in_array($ext, ['jpg', 'png', 'webp'], true)) {
            $clean = self::reencode((string) $file->get(), $ext === 'png' ? 'png' : 'jpeg', 50 * 1024 * 1024);
            if ($clean === null) {
                return null;
            }
            if ($ext === 'webp') {
                $name = substr($name, 0, -4) . 'jpg';
            }
            $path = $directory . '/' . $name;
            \Illuminate\Support\Facades\Storage::disk($disk)->put($path, $clean);
            return $path;
        }

        return $file->storeAs($directory, $name, $disk) ?: null;
    }
}
