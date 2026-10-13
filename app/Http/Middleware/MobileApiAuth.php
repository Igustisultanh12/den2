<?php

namespace App\Http\Middleware;

use App\Support\MobileAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi wajib untuk seluruh endpoint /api/mobile/* (kecuali login,
 * aktivasi, config, dan verifikasi publik).
 *
 * Pemakaian di route:  ->middleware('mobile.auth')              (cukup login)
 *                      ->middleware('mobile.auth:cash.view')    (login + permission)
 *
 * Identitas HANYA diambil dari token Sanctum pada header Authorization.
 * Header X-User-Id / X-User-Nrp / X-User-Email dari klien diabaikan.
 */
class MobileApiAuth
{
    /** Masa berlaku token mobile (hari) jika tidak digunakan. */
    public const IDLE_TOKEN_DAYS = 30;

    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $plain = $request->bearerToken();

        if (empty($plain)) {
            return $this->deny(401, 'unauthenticated', 'Sesi tidak ditemukan. Silakan masuk kembali.');
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('personal_access_tokens')) {
            return $this->deny(503, 'service_unavailable', 'Layanan mobile sedang dalam pemeliharaan database.');
        }

        $token = PersonalAccessToken::findToken($plain);
        if (!$token || !$token->tokenable) {
            return $this->deny(401, 'unauthenticated', 'Sesi tidak valid atau telah berakhir. Silakan masuk kembali.');
        }

        $lastUse = $token->last_used_at ?? $token->created_at;
        $expired = ($token->expires_at && $token->expires_at->isPast())
            || ($lastUse && $lastUse->lt(now()->subDays(self::IDLE_TOKEN_DAYS)));
        if ($expired) {
            $token->delete();
            return $this->deny(401, 'token_expired', 'Sesi Anda telah kedaluwarsa. Silakan masuk kembali.');
        }

        $user = $token->tokenable;
        if (!$user->is_active) {
            return $this->deny(403, 'account_inactive', 'Akun Anda dinonaktifkan oleh Admin.');
        }

        foreach ($abilities as $ability) {
            if (!MobileAccess::can($user, $ability)) {
                return $this->deny(403, 'forbidden', 'Anda tidak memiliki otoritas untuk tindakan ini.', $ability);
            }
        }

        // Catat pemakaian token (dibatasi agar tidak menulis DB di setiap request).
        if (!$token->last_used_at || $token->last_used_at->lt(now()->subMinutes(5))) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        // Jadikan pengguna token sebagai pengguna aktif agar controller web
        // yang memakai auth()->user() (mis. ekspor PDF) bekerja dengan benar.
        $user->withAccessToken($token);
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function deny(int $status, string $code, string $message, ?string $ability = null): Response
    {
        $body = ['status' => 'error', 'code' => $code, 'message' => $message];
        if ($ability) {
            $body['required_permission'] = $ability;
        }
        return response()->json($body, $status);
    }
}
