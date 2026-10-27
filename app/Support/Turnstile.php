<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifikasi Cloudflare Turnstile untuk login & aktivasi aplikasi mobile.
 *
 * Kunci diatur Admin di web (Pengaturan → Cloudflare Turnstile) dan disimpan
 * di tabel settings:
 *   turnstile_enabled    — "1" aktif, "0" nonaktif
 *   turnstile_site_key   — kunci publik widget
 *   turnstile_secret_key — kunci rahasia, DISIMPAN TERENKRIPSI (Crypt)
 * Cadangan bila belum diatur di web: TURNSTILE_SITE_KEY / TURNSTILE_SECRET_KEY
 * pada .env. Secret tidak pernah dikirim ke aplikasi maupun ke halaman web.
 */
class Turnstile
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private static function setting(string $key): ?string
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                return null;
            }
            $v = \App\Models\Setting::where('key', $key)->value('value');
            return filled($v) ? (string) $v : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function siteKey(): ?string
    {
        $key = self::setting('turnstile_site_key') ?? config('services.turnstile.site_key');
        return filled($key) ? (string) $key : null;
    }

    public static function secretKey(): ?string
    {
        $stored = self::setting('turnstile_secret_key');
        if ($stored !== null) {
            try {
                return \Illuminate\Support\Facades\Crypt::decryptString($stored);
            } catch (\Throwable $e) {
                Log::error('[TURNSTILE] Secret key tersimpan tidak dapat didekripsi (APP_KEY berubah?).');
                return null;
            }
        }
        $env = config('services.turnstile.secret_key');
        return filled($env) ? (string) $env : null;
    }

    /** Simpan secret key secara terenkripsi (dipanggil dari SettingController). */
    public static function storeSecret(string $plain): void
    {
        \App\Models\Setting::updateOrCreate(
            ['key' => 'turnstile_secret_key'],
            ['value' => \Illuminate\Support\Facades\Crypt::encryptString($plain)]
        );
    }

    /**
     * Aktif bila Admin menyalakannya di web DAN site key + secret key tersedia.
     * Bila Admin belum pernah mengatur, aktif mengikuti keberadaan secret di .env.
     */
    public static function enabled(): bool
    {
        $flag = self::setting('turnstile_enabled');
        if ($flag === '0') {
            return false;
        }
        if ($flag === null && self::setting('turnstile_secret_key') === null) {
            return filled(config('services.turnstile.secret_key')) && self::siteKey() !== null;
        }
        return self::siteKey() !== null && self::secretKey() !== null;
    }

    /**
     * @return array{ok: bool, message: string|null}
     */
    public static function verify(?string $token, ?string $ip, string $expectedAction): array
    {
        if (!self::enabled()) {
            return ['ok' => true, 'message' => null];
        }

        $token = trim((string) $token);
        if ($token === '' || strlen($token) > 2048) {
            return ['ok' => false, 'message' => 'Verifikasi keamanan diperlukan. Silakan ulangi pemeriksaan keamanan.'];
        }

        try {
            $res = Http::asForm()->timeout(8)->post(self::VERIFY_URL, array_filter([
                'secret' => self::secretKey(),
                'response' => $token,
                'remoteip' => $ip,
            ]));
            $body = $res->json() ?? [];
        } catch (\Throwable $e) {
            Log::error('[TURNSTILE] Gagal menghubungi siteverify: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Layanan verifikasi keamanan tidak dapat dihubungi. Coba lagi.'];
        }

        if (($body['success'] ?? false) !== true) {
            Log::warning('[TURNSTILE] Token ditolak', ['codes' => $body['error-codes'] ?? [], 'ip' => $ip]);
            return ['ok' => false, 'message' => 'Verifikasi keamanan gagal atau kedaluwarsa. Silakan ulangi.'];
        }

        $action = $body['action'] ?? null;
        if ($action !== null && $action !== '' && $action !== $expectedAction) {
            Log::warning('[TURNSTILE] Action tidak cocok', ['expected' => $expectedAction, 'got' => $action]);
            return ['ok' => false, 'message' => 'Verifikasi keamanan tidak valid untuk permintaan ini.'];
        }

        return ['ok' => true, 'message' => null];
    }
}
