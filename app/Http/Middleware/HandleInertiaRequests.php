<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Facades\Redirect;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // --- STATUS PERANGKAT (MOBILE DETECTION) ---
        $userAgent = $request->header('User-Agent');
        $isMobile = preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent);

        // --- TAMBAHAN: AMBIL DATA ARRAY SETTINGS UNTUK INDIKATOR FAVICON DI BLADE ---
        $settingsArray = Setting::pluck('value', 'key')->toArray();
        
        // Ambil favicon dari array, jika kosong fallback ke logo instansi (agency_logo)
        $faviconPath = $settingsArray['favicon'] ?? ($settingsArray['agency_logo'] ?? null);
        
        // Simpan ke session agar app.blade.php bisa mengambil stringnya langsung tanpa crash
        session(['app_favicon' => $faviconPath]);

        return [
            ...parent::share($request),
            
            // --- DATA PENGATURAN SISTEM ---
            'settings' => $settingsArray, // Dioptimasi menggunakan variabel agar tidak query ganda

            // --- STATUS PERANGKAT ---
            'isMobileDevice' => $isMobile,

            // --- PENTING: JALUR NOTIFIKASI (FLASH MESSAGES) ---
            // Digunakan oleh SweetAlert2 di Vue untuk memunculkan lapor aksi (Success/Error)
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'info'    => fn () => $request->session()->get('info'),
                'warning' => fn () => $request->session()->get('warning'),
                'token'   => fn () => $request->session()->get('token') ?? $request->session()->get('flash.token') ?? (is_array($request->session()->get('flash')) ? ($request->session()->get('flash')['token'] ?? null) : null),
                
                // --- KOORDINAT BARU: Menangkap Kode Akses untuk Dashboard Admin ---
                'generatedCode' => fn () => $request->session()->get('generatedCode'),
            ],

            // --- DATA OTENTIKASI KOMANDO ---
            'auth' => [
                'user' => $request->user() ? [
                    'id'         => $request->user()->id,
                    'name'       => $request->user()->name,
                    'email'      => $request->user()->email,
                    'avatar'     => $request->user()->avatar,
                    'avatar_url' => $request->user()->avatar ? asset('storage/' . $request->user()->avatar) : null,
                    'pangkat'    => $request->user()->pangkat,
                    'nrp'        => $request->user()->nrp,
                    'role'       => $request->user()->role, 
                    'is_super_admin' => $request->user()->isSuperAdmin(),
                    'is_admin'   => $request->user()->isAdmin(),
                    'is_active'  => $request->user()->is_active,
                    'can_access_mitra' => (bool)$request->user()->can_access_mitra,
                    'can_manage_koperasi' => (bool)$request->user()->can_manage_koperasi,
                    'google_id'     => $request->user()->google_id ?? null,
                    'google_email'  => $request->user()->google_email ?? null,
                    'google_avatar' => $request->user()->google_avatar ?? null,
                    // Penanda khusus untuk deteksi Admin/Sultan di sisi Frontend
                    'is_commander' => ($request->user()->isAdmin() || $request->user()->name === 'I Gusti Sultan H.A, A.Md.Kom'),
                ] : null,
            ],

            // --- CSRF TOKEN (Cadangan untuk request manual) ---
            'csrf_token' => csrf_token(),
        ];
    }
}