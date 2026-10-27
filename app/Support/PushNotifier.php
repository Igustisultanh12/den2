<?php

namespace App\Support;

use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\LiveChatMessage;
use App\Models\LiveChatThread;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Notifikasi push ke HP (Firebase Cloud Messaging HTTP v1) — seperti WhatsApp:
 * warta dinas, pesan Chat Dinas, dan panggilan masuk tetap muncul & berdering
 * walau aplikasi sedang ditutup.
 *
 * Kredensial:
 *  - Konfigurasi klien Android (google-services.json) — publik, dikirim ke
 *    aplikasi lewat /api/mobile/config agar tidak perlu build ulang APK.
 *  - Service account (berisi private key) — RAHASIA, disimpan terenkripsi di
 *    tabel settings, hanya dipakai server untuk menandatangani token OAuth.
 *
 * Pengiriman dilakukan setelah respons HTTP selesai (defer) agar tidak
 * memperlambat halaman/aplikasi.
 */
class PushNotifier
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    // ---------------------------------------------------------------- konfigurasi

    private static function setting(string $key): ?string
    {
        try {
            $v = Setting::where('key', $key)->value('value');
            return $v === null || $v === '' ? null : (string) $v;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Konfigurasi klien Android dari google-services.json (publik). */
    public static function clientConfig(): ?array
    {
        $raw = self::setting('fcm_client_config');
        $cfg = $raw ? json_decode($raw, true) : null;
        if (!is_array($cfg) || empty($cfg['api_key']) || empty($cfg['app_id']) || empty($cfg['project_id']) || empty($cfg['sender_id'])) {
            return null;
        }
        return [
            'api_key' => (string) $cfg['api_key'],
            'app_id' => (string) $cfg['app_id'],
            'project_id' => (string) $cfg['project_id'],
            'sender_id' => (string) $cfg['sender_id'],
        ];
    }

    /**
     * Ambil konfigurasi klien dari isi google-services.json untuk paket aplikasi.
     * @return array|string array konfigurasi, atau pesan kesalahan.
     */
    public static function parseGoogleServices(string $json, string $package = 'id.my.sinden2.app')
    {
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['project_info']) || empty($data['client'])) {
            return 'Isi google-services.json tidak valid.';
        }
        foreach ((array) $data['client'] as $client) {
            $pkg = $client['client_info']['android_client_info']['package_name'] ?? null;
            if ($pkg !== $package) {
                continue;
            }
            $cfg = [
                'api_key' => $client['api_key'][0]['current_key'] ?? null,
                'app_id' => $client['client_info']['mobilesdk_app_id'] ?? null,
                'project_id' => $data['project_info']['project_id'] ?? null,
                'sender_id' => (string) ($data['project_info']['project_number'] ?? ''),
            ];
            if (in_array(null, $cfg, true) || $cfg['sender_id'] === '') {
                return 'google-services.json tidak lengkap (api_key/app_id/project).';
            }
            return $cfg;
        }
        return "google-services.json tidak memuat aplikasi Android dengan package {$package}.";
    }

    /** Service account (rahasia) yang tersimpan terenkripsi. */
    private static function serviceAccount(): ?array
    {
        $enc = self::setting('fcm_service_account');
        if (!$enc) {
            return null;
        }
        try {
            $sa = json_decode(Crypt::decryptString($enc), true);
        } catch (\Throwable $e) {
            Log::error('[PUSH] Service account FCM tidak dapat didekripsi (APP_KEY berubah?).');
            return null;
        }
        return (is_array($sa) && !empty($sa['client_email']) && !empty($sa['private_key']) && !empty($sa['project_id'])) ? $sa : null;
    }

    /**
     * Validasi & simpan service account (terenkripsi).
     * @return string|null pesan kesalahan, null bila berhasil.
     */
    public static function storeServiceAccount(string $json): ?string
    {
        $sa = json_decode($json, true);
        if (!is_array($sa) || ($sa['type'] ?? null) !== 'service_account' || empty($sa['client_email']) || empty($sa['private_key']) || empty($sa['project_id'])) {
            return 'Berkas service account tidak valid (harus JSON "type": "service_account").';
        }
        if (!openssl_pkey_get_private($sa['private_key'])) {
            return 'Private key pada service account tidak dapat dibaca.';
        }
        Setting::updateOrCreate(['key' => 'fcm_service_account'], ['value' => Crypt::encryptString(json_encode([
            'type' => 'service_account',
            'project_id' => $sa['project_id'],
            'client_email' => $sa['client_email'],
            'private_key' => $sa['private_key'],
            'token_uri' => $sa['token_uri'] ?? 'https://oauth2.googleapis.com/token',
        ]))]);
        Cache::forget('fcm:access_token');
        return null;
    }

    public static function serviceAccountProject(): ?string
    {
        return self::serviceAccount()['project_id'] ?? null;
    }

    public static function enabled(): bool
    {
        return self::setting('fcm_enabled') !== '0' && self::serviceAccount() !== null && self::clientConfig() !== null;
    }

    // ---------------------------------------------------------------- target

    /** @return int[] id pengguna penerima AppNotification (sama dengan cakupan di aplikasi). */
    public static function recipientsFor(AppNotification $n): array
    {
        if ($n->user_id) {
            return [(int) $n->user_id];
        }
        $q = User::query();
        if ($n->role) {
            $q->where('role', $n->role);
        }
        if (Schema::hasColumn('users', 'is_active')) {
            $q->where('is_active', true);
        }
        return $q->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** Operator yang menerima pesan/panggilan dari personel (sama dengan web). */
    public static function operatorIds(LiveChatThread $thread): array
    {
        if ($thread->operator_id) {
            return [(int) $thread->operator_id];
        }
        return User::whereIn('role', ['admin', 'super_admin', 'superadmin'])->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    // ---------------------------------------------------------------- pemicu

    public static function appNotification(AppNotification $n): void
    {
        self::queue(self::recipientsFor($n), [
            'type' => 'notification',
            'id' => (string) $n->id,
            'title' => (string) ($n->title ?? 'Notifikasi SINDEN'),
            'body' => (string) ($n->message ?? ''),
            'link' => (string) ($n->link ?? ''),
        ]);
    }

    public static function chatMessage(LiveChatMessage $m): void
    {
        if (strtoupper((string) $m->sender_type) === 'SYSTEM') {
            return;
        }
        $thread = LiveChatThread::find($m->thread_id);
        if (!$thread) {
            return;
        }
        $targets = strtoupper((string) $m->sender_type) === 'ADMIN' ? [(int) $thread->user_id] : self::operatorIds($thread);
        $targets = array_values(array_filter($targets, fn ($id) => $id && $id !== (int) $m->sender_id));
        $body = trim((string) $m->message);
        if ($body === '') {
            $body = !empty($m->attachments) ? 'Mengirim lampiran' : 'Pesan baru';
        }
        self::queue($targets, [
            'type' => 'chat',
            'id' => (string) $m->id,
            'title' => (string) ($m->sender_name ?: 'Chat Dinas'),
            'body' => mb_substr($body, 0, 240),
            'thread_uuid' => (string) $thread->uuid,
        ]);
    }

    public static function incomingCall(?int $calleeUserId, array $call): void
    {
        if (!$calleeUserId) {
            return;
        }
        $caller = $call['caller'] ?? [];
        self::queue([$calleeUserId], [
            'type' => 'call',
            'call_id' => (string) ($call['call_id'] ?? ''),
            'thread_uuid' => (string) ($call['thread_uuid'] ?? ''),
            'call_type' => (string) ($call['call_type'] ?? 'video'),
            'caller_name' => trim(($caller['pangkat'] ?? '') . ' ' . ($caller['name'] ?? '')) ?: 'Panggilan Dinas',
            'caller_id' => (string) ($caller['id'] ?? ''),
        ], 45);
    }

    public static function callEnded(?int $calleeUserId, array $call): void
    {
        if (!$calleeUserId) {
            return;
        }
        self::queue([$calleeUserId], [
            'type' => 'call_cancel',
            'call_id' => (string) ($call['call_id'] ?? ''),
            'thread_uuid' => (string) ($call['thread_uuid'] ?? ''),
        ], 60);
    }

    /**
     * Pemberitahuan pembaruan aplikasi ke seluruh personel aktif.
     *
     * @return int jumlah personel yang dituju (0 bila push belum aktif)
     */
    public static function appUpdate(string $version, string $notes): int
    {
        if (!self::enabled()) {
            return 0;
        }
        $q = User::query();
        if (Schema::hasColumn('users', 'is_active')) {
            $q->where('is_active', true);
        }
        $ids = $q->pluck('id')->map(fn ($id) => (int) $id)->all();
        self::queue($ids, [
            'type' => 'app_update',
            'version' => $version,
            'title' => "Pembaruan Sinden 2 {$version} tersedia",
            'body' => mb_substr(trim($notes) !== '' ? trim($notes) : 'Ketuk untuk mengunduh dan memasang versi terbaru.', 0, 240),
        ], 7 * 86400);
        return count($ids);
    }

    // ---------------------------------------------------------------- pengiriman

    /** Kirim setelah respons selesai. Data FCM wajib berupa string. */
    private static function queue(array $userIds, array $data, int $ttl = 86400): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds || !self::enabled()) {
            return;
        }
        $job = function () use ($userIds, $data, $ttl) {
            try {
                self::sendNow($userIds, $data, $ttl);
            } catch (\Throwable $e) {
                Log::warning('[PUSH] Gagal mengirim: ' . $e->getMessage());
            }
        };
        // Perintah artisan/scheduler: kirim langsung. Permintaan web/API: setelah respons terkirim.
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            $job();
        } else {
            defer($job);
        }
    }

    /** @return int jumlah perangkat yang berhasil dikirimi. */
    public static function sendNow(array $userIds, array $data, int $ttl = 86400): int
    {
        if (!Schema::hasTable('device_tokens')) {
            return 0;
        }
        $sa = self::serviceAccount();
        $access = $sa ? self::accessToken($sa) : null;
        if (!$access) {
            return 0;
        }
        $url = "https://fcm.googleapis.com/v1/projects/{$sa['project_id']}/messages:send";
        $sent = 0;
        foreach (DeviceToken::whereIn('user_id', $userIds)->get() as $device) {
            $res = Http::withToken($access)->timeout(10)->post($url, ['message' => [
                'token' => $device->token,
                'data' => array_map('strval', $data),
                'android' => ['priority' => 'high', 'ttl' => $ttl . 's'],
            ]]);
            if ($res->successful()) {
                $sent++;
                continue;
            }
            $status = (string) ($res->json('error.status') ?? '');
            $detail = json_encode($res->json('error.details') ?? []);
            if ($res->status() === 404 || $status === 'NOT_FOUND' || str_contains($detail, 'UNREGISTERED')) {
                $device->delete(); // aplikasi di-uninstall / token kedaluwarsa
            } else {
                Log::warning("[PUSH] FCM {$res->status()} {$status}");
            }
        }
        return $sent;
    }

    /** Token OAuth2 dari service account (JWT RS256), disimpan ±55 menit. */
    private static function accessToken(array $sa): ?string
    {
        return Cache::remember('fcm:access_token', now()->addMinutes(55), function () use ($sa) {
            $now = time();
            $b64 = fn ($v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
            $header = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $b64(json_encode([
                'iss' => $sa['client_email'],
                'scope' => self::SCOPE,
                'aud' => $sa['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            $signature = '';
            if (!openssl_sign("{$header}.{$claims}", $signature, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
                Log::error('[PUSH] Gagal menandatangani JWT service account.');
                return null;
            }
            $res = Http::asForm()->timeout(10)->post($sa['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => "{$header}.{$claims}." . $b64($signature),
            ]);
            if (!$res->successful()) {
                Log::error('[PUSH] Token OAuth FCM ditolak: HTTP ' . $res->status());
                return null;
            }
            return $res->json('access_token');
        }) ?: tap(null, fn () => Cache::forget('fcm:access_token'));
    }
}
