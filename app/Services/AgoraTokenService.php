<?php

namespace App\Services;

require_once __DIR__ . '/Agora/RtcTokenBuilder2.php';

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use RtcTokenBuilder2;

class AgoraTokenService
{
    /**
     * Menghasilkan RTC Token dinamis secara aman di peladen
     */
    public static function generateToken($channelName, $uid = 0, $role = RtcTokenBuilder2::ROLE_PUBLISHER)
    {
        $appId = Setting::where('key', 'agora_app_id')->value('value') ?: config('services.agora.app_id');
        $appCertificate = Setting::where('key', 'agora_app_certificate')->value('value') ?: config('services.agora.app_certificate');

        if (empty($appId)) {
            return null;
        }

        // Jika Certificate kosong, proyek berjalan dalam mode APP ID Only
        if (empty($appCertificate)) {
            return null;
        }

        $tokenExpire = 86400; // Berlaku 24 jam untuk kelancaran sesi dinas
        $privilegeExpire = 86400;

        try {
            return RtcTokenBuilder2::buildTokenWithUid(
                $appId,
                $appCertificate,
                $channelName,
                (int)$uid,
                $role,
                $tokenExpire,
                $privilegeExpire
            );
        } catch (\Throwable $e) {
            Log::error('Gagal membuat token Agora RTC: ' . $e->getMessage());
            return null;
        }
    }
}
