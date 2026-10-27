<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Anda tidak memiliki otoritas untuk mengakses halaman ini.');
        }

        $userRole = strtolower(str_replace([' ', '-'], '_', (string) ($user->role ?? '')));

        // Super Admin selalu memiliki otoritas penuh di seluruh sistem tanpa pengecualian
        if ($userRole === 'super_admin' || $userRole === 'superadmin') {
            return $next($request);
        }

        // Normalisasi role yang diminta oleh route
        $normalizedRoles = array_map(function ($r) {
            return strtolower(str_replace([' ', '-'], '_', trim($r)));
        }, $roles);

        // Jika rute dikhususkan untuk Super Admin, maka Admin & role lain ditolak
        if (in_array('super_admin', $normalizedRoles, true) || in_array('superadmin', $normalizedRoles, true)) {
            abort(403, 'Akses terbatas khusus Super Admin.');
        }

        // Admin memiliki otoritas penuh untuk semua rute non-super-admin
        if ($userRole === 'admin') {
            return $next($request);
        }

        // Dukung alias role Personel & Personel Sintel
        $userRoles = [$userRole];
        if ($userRole === 'personel_sintel' || $userRole === 'anggotasintel') {
            $userRoles = ['personel_sintel', 'anggotasintel'];
        } elseif ($userRole === 'personel' || $userRole === 'user') {
            $userRoles = ['personel', 'user'];
        }

        // Cek jika role pengguna cocok dengan salah satu role yang dizinkan
        if (empty($roles) || count(array_intersect($userRoles, $normalizedRoles)) > 0) {
            return $next($request);
        }

        abort(403, 'Anda tidak memiliki otoritas untuk mengakses halaman ini.');
    }
}