<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL; // Wajib ditambahkan untuk akses HTTPS

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set Standar Locale Indonesia untuk Tanggal & Waktu (Baku)
        \Carbon\Carbon::setLocale('id');
        \Illuminate\Support\Carbon::setLocale('id');
        setlocale(LC_TIME, 'id_ID.utf8', 'id_ID', 'id', 'indonesian');

        /**
         * SOLUSI MIXED CONTENT
         * Memaksa semua URL aset (JS/CSS) menggunakan HTTPS agar tidak diblokir browser.
         * Kode ini memastikan tombol SI SINDEN kembali aktif.
         */
        if (config('app.env') === 'production' || env('APP_ENV') === 'production') {
            URL::forceScheme('https');
        }

        Vite::prefetch(concurrency: 3);

        $this->configureRateLimiting();
    }

    /**
     * Batas percobaan login & aktivasi.
     *
     * Dihitung per AKUN + IP, bukan hanya per IP: seluruh personel di satu kantor keluar
     * ke internet dengan IP publik yang sama (NAT), sehingga batas per IP membuat
     * personel saling menghabiskan jatah ("Too Many Attempts" saat aktivasi bersama).
     * Batas per IP tetap ada (lebih longgar) untuk meredam serangan massal.
     */
    protected function configureRateLimiting(): void
    {
        $tooMany = function (string $what) {
            return function (\Illuminate\Http\Request $request, array $headers) use ($what) {
                $wait = (int) ($headers['Retry-After'] ?? 60);
                $message = "Terlalu banyak percobaan {$what}. Coba lagi dalam {$wait} detik.";
                return $request->expectsJson() || $request->is('api/*')
                    ? response()->json(['status' => 'error', 'code' => 'too_many_attempts', 'message' => $message, 'retry_after' => $wait], 429, $headers)
                    : back()->withErrors(['nrp' => $message])->withInput($request->except('password', 'password_confirmation', 'token'));
            };
        };
        $account = fn (\Illuminate\Http\Request $r, string ...$keys) => strtolower(trim((string) collect($keys)->map(fn ($k) => $r->input($k))->first(fn ($v) => filled($v))));

        \Illuminate\Support\Facades\RateLimiter::for('activation', fn (\Illuminate\Http\Request $r) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by('activate:' . $account($r, 'nrp', 'email') . '|' . $r->ip())->response($tooMany('aktivasi')),
            \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by('activate-ip:' . $r->ip())->response($tooMany('aktivasi')),
        ]);

        \Illuminate\Support\Facades\RateLimiter::for('mobile-login', fn (\Illuminate\Http\Request $r) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by('login:' . $account($r, 'username', 'email', 'nrp') . '|' . $r->ip())->response($tooMany('masuk')),
            \Illuminate\Cache\RateLimiting\Limit::perMinute(120)->by('login-ip:' . $r->ip())->response($tooMany('masuk')),
        ]);
    }
}