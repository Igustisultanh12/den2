<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\WhatsappService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    /**
     * Cek apakah integrasi login Google diaktifkan oleh Administrator
     */
    private function isGoogleLoginEnabled(): bool
    {
        $enabled = Setting::where('key', 'google_login_enabled')->value('value');
        if ($enabled !== null && $enabled !== '') {
            return $enabled === '1' || $enabled === 1 || $enabled === 'true';
        }
        return true;
    }

    /**
     * Dapatkan Client ID Google (dari database setting atau fallback ke .env)
     */
    private function getClientId(): ?string
    {
        $db = Setting::where('key', 'google_client_id')->value('value');
        if (!empty($db) && trim($db) !== '') {
            return trim($db);
        }
        $id = config('services.google.client_id');
        return !empty($id) ? trim($id) : null;
    }

    /**
     * Dapatkan Client Secret Google (dari database setting atau fallback ke .env)
     */
    private function getClientSecret(): ?string
    {
        $db = Setting::where('key', 'google_client_secret')->value('value');
        if (!empty($db) && trim($db) !== '') {
            return trim($db);
        }
        $secret = config('services.google.client_secret');
        return !empty($secret) ? trim($secret) : null;
    }

    /**
     * Dapatkan Callback URI yang valid
     */
    private function getRedirectUri(): string
    {
        $redirect = config('services.google.redirect');
        if (!empty($redirect) && filter_var($redirect, FILTER_VALIDATE_URL)) {
            return $redirect;
        }
        return url('/auth/google/callback');
    }

    /**
     * 1. Arahkan pengguna ke Google OAuth Consent Screen
     * Mendukung ?action=login (dari halaman login) atau ?action=link (dari halaman profil)
     */
    public function redirectToGoogle(Request $request): RedirectResponse
    {
        $action = $request->query('action', 'login');

        // Validasi apakah fitur Google OAuth diaktifkan
        if (!$this->isGoogleLoginEnabled()) {
            $msg = 'Fitur Autentikasi Google saat ini sedang dinonaktifkan oleh Administrator.';
            if ($action === 'link') {
                return redirect()->route('profile.edit')->with('error', $msg);
            }
            return redirect()->route('login')->with('error', $msg);
        }

        $clientId = $this->getClientId();

        // Validasi jika kredensial Google belum disiapkan
        if (empty($clientId)) {
            $msg = 'Autentikasi Google belum dikonfigurasi. Mohon lengkapi Client ID & Secret di menu Pengaturan Sistem (Admin) atau file .env.';
            if ($action === 'link') {
                return redirect()->route('profile.edit')->with('error', $msg);
            }
            return redirect()->route('login')->with('error', $msg);
        }

        if ($action === 'link' && !Auth::check()) {
            return redirect()->route('login')->with('error', 'Silakan masuk ke akun Anda terlebih dahulu untuk menautkan akun Google.');
        }

        // Generate token state CSRF yang unik dan aman
        $state = Str::random(40);
        session([
            'google_oauth_state' => $state,
            'google_oauth_action' => $action,
            'google_oauth_redirect' => $request->query('redirect'),
        ]);

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $this->getRedirectUri(),
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ];

        $googleUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

        return redirect()->away($googleUrl);
    }

    /**
     * 2. Tangani Callback dari Google setelah pengguna memberikan persetujuan
     */
    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $action = session('google_oauth_action', 'login');

        // Validasi apakah fitur Google OAuth diaktifkan
        if (!$this->isGoogleLoginEnabled()) {
            if ($action === 'link') {
                return redirect()->route('profile.edit')->with('error', 'Fitur Google OAuth sedang dinonaktifkan oleh Administrator.');
            }
            return redirect()->route('login')->with('error', 'Fitur Masuk dengan Google sedang dinonaktifkan oleh Administrator.');
        }

        // A. Cek jika pengguna membatalkan atau terjadi error dari Google
        if ($request->has('error')) {
            $errorDesc = $request->get('error_description') ?: 'Otoritas Google dibatalkan.';
            Log::warning("[GOOGLE_OAUTH] Pengguna membatalkan atau gagal otentikasi Google: {$errorDesc}");
            if ($action === 'link') {
                return redirect()->route('profile.edit')->with('error', 'Penautan akun Google dibatalkan.');
            }
            return redirect()->route('login')->with('error', 'Proses masuk akun Google dibatalkan.');
        }

        // B. Validasi State CSRF
        $savedState = session('google_oauth_state');
        if (empty($savedState) || $savedState !== $request->query('state')) {
            Log::error("[GOOGLE_OAUTH] State CSRF mismatch pada Google Callback.");
            session()->forget(['google_oauth_state', 'google_oauth_action', 'google_oauth_redirect']);
            if ($action === 'link') {
                return redirect()->route('profile.edit')->with('error', 'Sesi verifikasi keamanan kedaluwarsa. Silakan ulangi penautan akun.');
            }
            return redirect()->route('login')->with('error', 'Sesi keamanan kedaluwarsa. Silakan ulangi login.');
        }

        // Hapus token state setelah verifikasi sukses
        session()->forget('google_oauth_state');

        $code = $request->query('code');
        if (empty($code)) {
            if ($action === 'link') {
                return redirect()->route('profile.edit')->with('error', 'Kode otorisasi Google tidak ditemukan.');
            }
            return redirect()->route('login')->with('error', 'Kode otorisasi Google tidak valid.');
        }

        // Pastikan Client ID & Secret tersedia
        $clientId = $this->getClientId();
        $clientSecret = $this->getClientSecret();
        if (empty($clientId) || empty($clientSecret)) {
            Log::error("[GOOGLE_OAUTH] Kredensial Client ID / Secret belum dikonfigurasi.");
            if ($action === 'link') {
                return redirect()->route('profile.edit')->with('error', 'Kredensial Google OAuth belum dikonfigurasi di Pengaturan Admin.');
            }
            return redirect()->route('login')->with('error', 'Kredensial Google OAuth belum dikonfigurasi di Pengaturan Admin.');
        }

        // C. Tukar Code dengan Access Token ke Google OAuth Endpoint
        try {
            $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $this->getRedirectUri(),
                'grant_type' => 'authorization_code',
            ]);

            if ($tokenResponse->failed()) {
                Log::error("[GOOGLE_OAUTH] Gagal menukar token Google: " . $tokenResponse->body());
                if ($action === 'link') {
                    return redirect()->route('profile.edit')->with('error', 'Gagal memverifikasi token dengan Google.');
                }
                return redirect()->route('login')->with('error', 'Gagal memverifikasi token dengan Google.');
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'] ?? null;

            if (!$accessToken) {
                if ($action === 'link') {
                    return redirect()->route('profile.edit')->with('error', 'Access token Google tidak valid.');
                }
                return redirect()->route('login')->with('error', 'Access token Google tidak valid.');
            }

            // D. Ambil Informasi Profil Pengguna dari Google Userinfo Endpoint
            $userResponse = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v3/userinfo');

            if ($userResponse->failed()) {
                Log::error("[GOOGLE_OAUTH] Gagal mengambil userinfo: " . $userResponse->body());
                if ($action === 'link') {
                    return redirect()->route('profile.edit')->with('error', 'Gagal mengambil data profil Google.');
                }
                return redirect()->route('login')->with('error', 'Gagal mengambil data profil Google.');
            }

            $googleData = $userResponse->json();
            $googleId = $googleData['sub'] ?? null;
            $googleEmail = strtolower($googleData['email'] ?? '');
            $googleAvatar = $googleData['picture'] ?? null;
            $googleName = $googleData['name'] ?? 'Google User';

            if (!$googleId || !$googleEmail) {
                if ($action === 'link') {
                    return redirect()->route('profile.edit')->with('error', 'Data identitas Google tidak lengkap.');
                }
                return redirect()->route('login')->with('error', 'Data identitas Google tidak lengkap.');
            }

            // =========================================================================
            // KASUS 1: PENGGUNA SEDANG LOGIN DAN INGIN MENAUTKAN AKUN GOOGLE (ACTION === LINK)
            // =========================================================================
            if ($action === 'link') {
                $currentUser = Auth::user();

                if (!$currentUser) {
                    return redirect()->route('login')->with('error', 'Sesi Anda telah berakhir. Silakan login kembali.');
                }

                // Cek apakah akun Google ini sudah ditautkan ke akun personel lain
                $existingUser = User::where('google_id', $googleId)
                    ->where('id', '!=', $currentUser->id)
                    ->first();

                if ($existingUser) {
                    return redirect()->route('profile.edit')->with('error', "Akun Google ({$googleEmail}) sudah tertaut ke akun personel lain ({$existingUser->name} - NRP: {$existingUser->nrp}).");
                }

                $currentUser->update([
                    'google_id' => $googleId,
                    'google_email' => $googleEmail,
                    'google_avatar' => $googleAvatar,
                ]);

                AuditLog::create([
                    'user_id' => $currentUser->id,
                    'admin_name' => $currentUser->name,
                    'action' => 'GOOGLE_LINK_SUCCESS',
                    'target_personnel' => $currentUser->name,
                    'description' => "Berhasil menautkan Akun Google ({$googleEmail}) ke profil personel",
                    'ip_address' => $request->ip(),
                ]);

                return redirect()->route('profile.edit')->with('success', "Akun Google ({$googleEmail}) berhasil ditautkan ke profil Anda! Sekarang Anda bisa masuk sistem menggunakan Google.");
            }

            // =========================================================================
            // KASUS 2: PENGGUNA MASUK DARI HALAMAN LOGIN (ACTION === LOGIN)
            // =========================================================================
            // Cari user berdasarkan google_id terlebih dahulu
            $user = User::where('google_id', $googleId)->first();

            // Jika belum ada google_id, cari berdasarkan email kedinasan yang sama
            if (!$user) {
                $user = User::where('email', $googleEmail)->first();

                // Jika ditemukan email cocok, lakukan auto-link google_id
                if ($user) {
                    $user->update([
                        'google_id' => $googleId,
                        'google_email' => $googleEmail,
                        'google_avatar' => $googleAvatar,
                    ]);
                }
            }

            // Jika akun tidak ditemukan sama sekali di basis data SINDEN:
            // Kebijakan Keamanan: Tolak registrasi liar tanpa NRP/Pangkat kedinasan
            if (!$user) {
                return redirect()->route('login')->with('error', "Akun Google ({$googleEmail}) belum terdaftar pada sistem SINDEN. Silakan masuk terlebih dahulu dengan NRP / Email kedinasan, lalu tautkan Akun Google di menu Pengaturan Profil.");
            }

            // Cek status keaktifan akun personel
            if (!$user->is_active) {
                return redirect()->route('login')->with('error', "Akses Ditolak: Akun personel Anda ({$user->name}) belum aktif atau sedang ditangguhkan oleh Administrator.");
            }

            // Otentikasi dan Login Sesi Pengguna
            Auth::login($user, remember: true);
            $request->session()->regenerate();

            // OTORITAS KEAMANAN: 2FA WhatsApp ditentukan oleh status mfa_enabled pada akun personel (dikelola oleh Admin)
            $requiresMfa = (bool)$user->mfa_enabled;

            $targetRedirect = session()->pull('google_oauth_redirect') ?: session('url.intended');

            if ($requiresMfa && !empty($user->phone)) {
                $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                $expiresAt = now()->addMinutes(5)->timestamp;

                session([
                    'mfa_user_id' => $user->id,
                    'mfa_otp' => $otp,
                    'mfa_expires_at' => $expiresAt,
                    'mfa_attempts' => 0,
                    'url.intended' => $targetRedirect,
                ]);

                Auth::guard('web')->logout();

                try {
                    $waMsg = "*OTORITAS KEAMANAN SINDEN*\n"
                        . "KODE VERIFIKASI DUA LANGKAH (2FA) GOOGLE LOGIN\n"
                        . "=====================================\n"
                        . "Yth. {$user->pangkat} {$user->name}\n\n"
                        . "Anda mencoba masuk menggunakan Akun Google ({$googleEmail}).\n"
                        . "Kode OTP verifikasi Anda adalah:\n"
                        . "*{$otp}*\n\n"
                        . "Kode ini berlaku selama 5 menit. Jangan berikan kode ini kepada siapapun.";
                    WhatsappService::sendMessage($user->phone, $waMsg);
                } catch (\Exception $e) {
                    Log::error("[2FA_WA_ERROR] Gagal mengirim OTP Google Login: " . $e->getMessage());
                }

                return redirect()->route('mfa.verify');
            }

            session(['mfa_verified' => true]);

            // Catat AuditLog keberhasilan login via Google
            AuditLog::create([
                'user_id' => $user->id,
                'admin_name' => $user->name,
                'action' => 'LOGIN_GOOGLE_SUCCESS',
                'target_personnel' => $user->name,
                'description' => "Masuk sistem menggunakan Akun Google sukses ({$googleEmail})",
                'ip_address' => $request->ip(),
            ]);

            // Pengalihan ke target redirect atau dashboard
            if (!empty($targetRedirect) && is_string($targetRedirect)) {
                return redirect()->to($targetRedirect);
            }

            if (!$user->isAdmin()) {
                $r = strtolower(preg_replace('/[\s_-]+/', '', $user->role ?? ''));
                $j = strtolower($user->jabatan ?? '');
                if ($r === 'anggotasintel' || str_contains($j, 'anggota sintel')) {
                    return redirect()->route('sc-submissions.index');
                }
            }

            return redirect()->intended(route('dashboard'));

        } catch (\Exception $e) {
            Log::error("[GOOGLE_OAUTH_FATAL] Error pada proses callback Google: " . $e->getMessage());
            if ($action === 'link') {
                return redirect()->route('profile.edit')->with('error', 'Terjadi kesalahan sistem saat memproses akun Google: ' . $e->getMessage());
            }
            return redirect()->route('login')->with('error', 'Terjadi kesalahan sistem saat masuk dengan Google: ' . $e->getMessage());
        }
    }

    /**
     * 3. Lepas Tautan Akun Google dari Profil Pengguna
     */
    public function unlinkGoogle(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        $oldGoogleEmail = $user->google_email ?: 'Akun Google';

        $user->update([
            'google_id' => null,
            'google_email' => null,
            'google_avatar' => null,
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'admin_name' => $user->name,
            'action' => 'GOOGLE_UNLINK_SUCCESS',
            'target_personnel' => $user->name,
            'description' => "Melepas tautan Akun Google ({$oldGoogleEmail}) dari profil",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('profile.edit')->with('success', "Tautan Akun Google ({$oldGoogleEmail}) berhasil dilepas dari profil Anda.");
    }
}
