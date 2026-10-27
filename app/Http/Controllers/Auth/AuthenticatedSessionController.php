<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response
    {
        $rawSettings = \App\Models\Setting::pluck('value', 'key')->toArray();
        $clientId = !empty($rawSettings['google_client_id']) ? $rawSettings['google_client_id'] : config('services.google.client_id');
        $googleLoginEnabled = isset($rawSettings['google_login_enabled']) ? ($rawSettings['google_login_enabled'] === '1' || $rawSettings['google_login_enabled'] === 1) : true;

        $settings = [
            'app_name' => $rawSettings['agency_name'] ?? 'SINDEN',
            'agency_logo' => isset($rawSettings['agency_logo']) && $rawSettings['agency_logo'] ? asset('storage/' . $rawSettings['agency_logo']) : null,
            'login_background' => isset($rawSettings['login_background']) && $rawSettings['login_background'] ? asset('storage/' . $rawSettings['login_background']) : null,
            'google_login_enabled' => $googleLoginEnabled && !empty($clientId),
        ];

        $redirect = $request->query('redirect') ?: session('url.intended');
        if ($redirect) {
            session()->put('url.intended', $redirect);
        }

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'settings' => $settings,
            'redirect' => $redirect,
        ]);
    }

    public function store(LoginRequest $request)
    {
        \Illuminate\Support\Facades\Log::info('[WEB_LOGIN_ATTEMPT] Request masuk ke /login', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'wants_json' => $request->wantsJson(),
            'expects_json' => $request->expectsJson(),
            'input_email' => $request->input('email'),
            'input_username' => $request->input('username'),
        ]);
        $request->authenticate();

        $user = Auth::user();

        if (!$user->is_active) {
            $userName = $user->name;

            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Akses Ditolak: Akun ({$userName}) belum aktif atau sedang ditangguhkan oleh Admin.",
                ], 403);
            }

            return back()->withErrors([
                'email' => "Akses Ditolak: Akun ({$userName}) belum aktif atau sedang ditangguhkan oleh Admin.",
            ]);
        }

        if ($request->wantsJson() || $request->expectsJson() || $request->is('api/*')) {
            $token = method_exists($user, 'createToken') 
                ? $user->createToken('sinden_mobile_token')->plainTextToken 
                : (session()->getId() ?: 'sinden_token_' . \Illuminate\Support\Str::random(40));

            return response()->json([
                'status' => 'success',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email ?? '',
                    'username' => $user->username ?? $user->nrp ?? '',
                    'nrp' => $user->nrp ?? $user->username ?? '',
                    'role' => $user->role ?? 'user',
                    'pangkat' => $user->pangkat ?? 'Prajurit',
                    'korps' => $user->korps ?? '',
                    'jabatan' => $user->jabatan ?? '',
                ],
            ]);
        }

        // OTORITAS KEAMANAN: 2FA WhatsApp ditentukan oleh status mfa_enabled pada akun personel (dikelola oleh Admin)
        $requiresMfa = (bool)$user->mfa_enabled;

        // Ambil target pengalihan awal dari form post, query string, atau session
        $targetRedirect = $request->input('redirect') 
            ?: $request->query('redirect') 
            ?: session('url.intended');

        if ($requiresMfa && !empty($user->phone) && !$request->wantsJson() && !$request->expectsJson() && !$request->is('api/*')) {
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
                    . "KODE VERIFIKASI DUA LANGKAH (2FA)\n"
                    . "=================================\n"
                    . "Yth. {$user->pangkat} {$user->name}\n\n"
                    . "Kode OTP verifikasi masuk Anda adalah:\n"
                    . "*{$otp}*\n\n"
                    . "Kode ini berlaku selama 5 menit.\n"
                    . "Peringatan: Dokumen intelijen bersifat rahasia. Jangan berikan kode ini kepada siapa pun demi keamanan kedinasan.";
                \App\Services\WhatsappService::sendMessage($user->phone, $waMsg);
            } catch (\Exception $waErr) {
                \Illuminate\Support\Facades\Log::error("Gagal kirim OTP 2FA: " . $waErr->getMessage());
            }

            return redirect()->route('mfa.verify');
        }

        $request->session()->regenerate();

        if ($request->filled('latitude') && $request->filled('longitude')) {
            session([
                'user_lat' => $request->latitude,
                'user_lng' => $request->longitude
            ]);
        }

        if (empty($user->phone)) {
            session(['warning_wa' => 'Nomor WhatsApp belum terdaftar. Notifikasi sistem tidak akan terkirim.']);
        }

        // PRIORITAS 1: Jika personel mengakses tautan khusus (seperti Folder Berbagi / Shared Folder)
        if (!empty($targetRedirect) && is_string($targetRedirect)) {
            session()->forget('url.intended');

            if (str_contains($targetRedirect, '%')) {
                $decoded = rawurldecode($targetRedirect);
                if (str_starts_with($decoded, 'http') || str_starts_with($decoded, '/')) {
                    $targetRedirect = $decoded;
                }
            }

            $parsedPath = parse_url($targetRedirect, PHP_URL_PATH);
            if ($parsedPath && !in_array($parsedPath, ['/login', '/logout', '/register', '/password/reset'])) {
                $appHost = parse_url(config('app.url'), PHP_URL_HOST);
                $targetHost = parse_url($targetRedirect, PHP_URL_HOST);
                if (empty($targetHost) || $targetHost === $appHost || $targetHost === $request->getHost()) {
                    return redirect()->to($targetRedirect);
                }
            }
        }

        // PRIORITAS 2: Wajib ganti password pada login biasa jika flag aktif
        if ($user->must_change_password) {
            return redirect()->route('profile.edit')->with('info', 'Otoritas Keamanan: Ini adalah login pertama Anda. Mohon perbarui password default Anda segera.');
        }

        if (!$user->isAdmin()) {
            $r = strtolower(preg_replace('/[\s_-]+/', '', $user->role ?? ''));
            $j = strtolower($user->jabatan ?? '');
            if ($r === 'anggotasintel' || str_contains($j, 'anggota sintel')) {
                return redirect()->route('sc-submissions.index');
            }
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Tampilan Verifikasi 2FA WhatsApp Kedinasan
     */
    public function showMfaVerify()
    {
        $userId = session('mfa_user_id');
        $expiresAt = session('mfa_expires_at');

        if (!$userId || !$expiresAt || now()->timestamp > $expiresAt) {
            session()->forget(['mfa_user_id', 'mfa_otp', 'mfa_expires_at', 'mfa_attempts']);
            return redirect()->route('login')->withErrors(['email' => 'Sesi verifikasi OTP telah kedaluwarsa. Silakan login kembali.']);
        }

        $user = \App\Models\User::find($userId);
        if (!$user) {
            return redirect()->route('login');
        }

        $phone = $user->phone ?: '';
        $maskedPhone = (strlen($phone) > 6) 
            ? substr($phone, 0, 4) . '****' . substr($phone, -3) 
            : $phone;

        return Inertia::render('Auth/MfaVerify', [
            'maskedPhone' => $maskedPhone,
            'expiresAt' => $expiresAt,
            'userName' => $user->name,
            'userPangkat' => $user->pangkat,
        ]);
    }

    /**
     * Proses Verifikasi Kode OTP 2FA
     */
    public function verifyMfa(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $userId = session('mfa_user_id');
        $storedOtp = session('mfa_otp');
        $expiresAt = session('mfa_expires_at');

        if (!$userId || !$expiresAt || now()->timestamp > $expiresAt) {
            session()->forget(['mfa_user_id', 'mfa_otp', 'mfa_expires_at', 'mfa_attempts']);
            return redirect()->route('login')->withErrors(['email' => 'Sesi verifikasi OTP telah kedaluwarsa.']);
        }

        $attempts = (int)session('mfa_attempts', 0);
        if ($attempts >= 5) {
            session()->forget(['mfa_user_id', 'mfa_otp', 'mfa_expires_at', 'mfa_attempts']);
            return redirect()->route('login')->withErrors(['email' => 'Terlalu banyak percobaan salah. Silakan login kembali.']);
        }

        if (trim($request->otp) !== (string)$storedOtp) {
            session(['mfa_attempts' => $attempts + 1]);
            return back()->withErrors(['otp' => 'Kode OTP tidak sesuai. Sisa kesempatan: ' . (4 - $attempts)]);
        }

        $user = \App\Models\User::findOrFail($userId);

        Auth::login($user);
        session()->forget(['mfa_user_id', 'mfa_otp', 'mfa_expires_at', 'mfa_attempts']);
        $request->session()->regenerate();
        session(['mfa_verified' => true]);

        \App\Models\AuditLog::create([
            'user_id' => $user->id,
            'admin_name' => $user->name,
            'action' => 'LOGIN_2FA_SUCCESS',
            'target_personnel' => $user->name,
            'description' => "Verifikasi 2FA WhatsApp sukses ({$user->role})",
            'ip_address' => $request->ip(),
        ]);

        // PRIORITAS 1: Target redirect khusus (Shared folder dll)
        $targetRedirect = session()->pull('url.intended');
        if (!empty($targetRedirect) && is_string($targetRedirect)) {
            if (str_contains($targetRedirect, '%')) {
                $decoded = rawurldecode($targetRedirect);
                if (str_starts_with($decoded, 'http') || str_starts_with($decoded, '/')) {
                    $targetRedirect = $decoded;
                }
            }

            $parsedPath = parse_url($targetRedirect, PHP_URL_PATH);
            if ($parsedPath && !in_array($parsedPath, ['/login', '/logout', '/register', '/password/reset'])) {
                $appHost = parse_url(config('app.url'), PHP_URL_HOST);
                $targetHost = parse_url($targetRedirect, PHP_URL_HOST);
                if (empty($targetHost) || $targetHost === $appHost || $targetHost === $request->getHost()) {
                    return redirect()->to($targetRedirect);
                }
            }
        }

        // PRIORITAS 2: Wajib ganti password pada login biasa jika flag aktif
        if ($user->must_change_password) {
            return redirect()->route('profile.edit')->with('info', 'Otoritas Keamanan: Ini adalah login pertama Anda. Mohon perbarui password default Anda segera.');
        }

        if (!$user->isAdmin()) {
            $r = strtolower(preg_replace('/[\s_-]+/', '', $user->role ?? ''));
            $j = strtolower($user->jabatan ?? '');
            if ($r === 'anggotasintel' || str_contains($j, 'anggota sintel')) {
                return redirect()->route('sc-submissions.index');
            }
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Kirim Ulang OTP 2FA
     */
    public function resendMfa(Request $request)
    {
        $userId = session('mfa_user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $user = \App\Models\User::find($userId);
        if (!$user || empty($user->phone)) {
            return back()->withErrors(['otp' => 'Nomor WhatsApp tidak valid.']);
        }

        $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(5)->timestamp;

        session([
            'mfa_otp' => $otp,
            'mfa_expires_at' => $expiresAt,
            'mfa_attempts' => 0,
        ]);

        try {
            $waMsg = "*OTORITAS KEAMANAN SINDEN*\n"
                . "KODE VERIFIKASI DUA LANGKAH (2FA) BARU\n"
                . "=====================================\n"
                . "Yth. {$user->pangkat} {$user->name}\n\n"
                . "Kode OTP baru Anda adalah:\n"
                . "*{$otp}*\n\n"
                . "Kode ini berlaku selama 5 menit.";
            \App\Services\WhatsappService::sendMessage($user->phone, $waMsg);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Gagal kirim ulang OTP 2FA: " . $e->getMessage());
        }

        return back()->with('status', 'Kode OTP baru telah dikirimkan ke WhatsApp Anda.');
    }
}
