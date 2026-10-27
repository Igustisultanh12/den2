<?php

namespace App\Support;

use App\Models\User;

/**
 * Sumber kebenaran tunggal hak akses aplikasi mobile SISINDEN.
 *
 * Aturan di kelas ini meniru aturan otorisasi yang SUDAH berlaku di web
 * (controller + AuthenticatedLayout.vue). Daftar permission yang dihasilkan
 * dipakai untuk dua hal sekaligus:
 *   1. Dikirim ke aplikasi Android agar menu dirender sesuai hak akses.
 *   2. Ditegakkan oleh middleware `mobile.auth:<ability>` di setiap endpoint
 *      /api/mobile/* sehingga backend tetap menjadi otoritas akhir.
 *
 * Jika admin mengubah role atau flag can_access_* seorang personel di web,
 * perubahan otomatis berlaku pada permintaan API berikutnya (dihitung ulang
 * per-request, tidak di-cache di token).
 */
class MobileAccess
{
    /**
     * Pengecualian berbasis nama yang dipusatkan di sini agar UI mobile konsisten.
     */
    public const DEVELOPER_NAMES = ['I Gusti Sultan H.A, A.Md.Kom'];
    public const SP_JAGA_ADMIN_NAMES = ['Suma Nurhasanah', 'I Gusti Sultan H.A, A.Md.Kom'];

    /** Daftar role yang dikenal sistem (dari form Kelola Pengguna di web). */
    public const KNOWN_ROLES = [
        'super_admin', 'superadmin', 'admin', 'personel', 'personel_sintel',
        'komandan', 'wadan', 'pasops', 'staf', 'anggotasintel',
        'kaurmintel', 'paurset', 'danunit1', 'danunit2', 'user',
    ];

    public static function normalizedRole(?User $user): string
    {
        return strtolower(preg_replace('/[\s_-]+/', '', (string) ($user?->role ?? '')));
    }

    public static function isSuperAdmin(User $user): bool
    {
        $r = self::normalizedRole($user);
        return in_array($r, ['superadmin', 'super_admin'], true);
    }

    public static function isAdmin(User $user): bool
    {
        $r = self::normalizedRole($user);
        return in_array($r, ['superadmin', 'super_admin', 'admin'], true);
    }

    public static function isDeveloper(User $user): bool
    {
        return in_array($user->name, self::DEVELOPER_NAMES, true);
    }

    public static function isAnggotaSintel(User $user): bool
    {
        if (self::isAdmin($user)) {
            return false;
        }
        $r = self::normalizedRole($user);
        $jabatan = strtolower((string) ($user->jabatan ?? ''));
        return in_array($r, ['anggotasintel', 'personelsintel', 'personel_sintel'], true) || str_contains($jabatan, 'sintel');
    }

    public static function isKomandan(User $user): bool
    {
        return self::normalizedRole($user) === 'komandan';
    }

    /** Admin, Super Admin, dan Komandan boleh memantau posisi realtime personel. */
    public static function canViewTracking(User $user): bool
    {
        return self::isAdmin($user) || self::isKomandan($user);
    }

    public static function isPasops(User $user): bool
    {
        $name = strtolower((string) $user->name);
        return in_array(self::normalizedRole($user), ['pasops', 'admin', 'komandan', 'superadmin', 'super_admin'], true)
            || str_contains($name, 'roni sumantri')
            || str_contains($name, 'sultan');
    }

    /**
     * Hitung seluruh permission untuk satu personel.
     *
     * @return array<string, bool>
     */
    public static function permissions(User $user): array
    {
        $superAdmin = self::isSuperAdmin($user);
        $admin = self::isAdmin($user);
        $dev = self::isDeveloper($user);
        $sintel = self::isAnggotaSintel($user);
        $komandan = self::isKomandan($user);
        $regular = !$sintel; // menu umum web disembunyikan untuk Anggota Sintel

        // CommanderAccountController@checkAccess
        $commanderView = $dev || $komandan || in_array($user->role, ['super_admin', 'admin', 'user'], true);
        $commanderManage = in_array($user->role, ['super_admin', 'admin', 'user'], true);
        // MitraPaymentController@index
        $mitra = $admin || $dev || (bool) $user->can_access_mitra;
        // SkhppController@approve / SignatureRequestController
        $commanderApproval = $admin || $komandan || $dev;
        // SpJagaController@index
        $spJagaAdmin = $admin || in_array($user->name, self::SP_JAGA_ADMIN_NAMES, true);

        return [
            'dashboard.view' => true,
            'profile.view' => true,
            'notifications.view' => true,

            // LiveChatController: semua personel; operator (admin/developer) mengelola utas.
            'chat.view' => true,
            'chat.manage' => $admin || $dev,

            'attendance.view' => true,
            'attendance.create' => true,
            'attendance.view_all' => in_array(self::normalizedRole($user), ['superadmin', 'super_admin', 'admin', 'komandan', 'pasops'], true),

            // Pelacakan lokasi realtime: semua personel melapor, hanya pimpinan yang memantau.
            'location.report' => true,
            'tracking.view' => self::canViewTracking($user),

            'duty_roster.view' => true,
            'duty_roster.view_unpublished' => $spJagaAdmin || self::isPasops($user),
            'duty_roster.pdf' => true,
            // SpJagaController@index ($isPasops): penandatangan TTE SP Jaga.
            'duty_roster.sign' => self::isPasops($user),

            'letter_logs.view' => $regular,
            'letter_logs.create' => $regular,
            'letter_logs.update' => $regular,
            'letter_logs.delete' => $regular,
            'letter_logs.pdf' => $regular,

            'letters.view' => $regular,
            'letters.create' => $regular,

            'skhpp.view' => $regular,
            'skhpp.create' => $regular,
            'skhpp.approve' => $commanderApproval,
            'skhpp.delete' => $admin,
            'skhpp.pdf' => $regular,

            'signature.view' => $regular,
            'signature.approve' => $commanderApproval,

            'commander_account.view' => $commanderView,
            'commander_account.manage' => $commanderManage,
            'commander_account.pdf' => $commanderView,

            'mitra.view' => $mitra,
            'mitra.manage' => $mitra,

            'violations.view' => $regular,
            'violations.manage' => $regular,

            'activities.view' => $regular,
            'activities.manage' => $regular,

            'categories.view' => $regular,

            'users.view' => $admin,
            'users.manage' => $admin,

            'settings.view' => $superAdmin,
            'settings.manage' => $superAdmin,

            'audit_logs.view' => $admin,
            'backup.view' => $admin,
        ];
    }

    public static function can(User $user, string $ability): bool
    {
        return (bool) (self::permissions($user)[$ability] ?? false);
    }

    /** @return list<string> */
    public static function grantedAbilities(User $user): array
    {
        return array_keys(array_filter(self::permissions($user)));
    }

    /**
     * Profil pengguna yang aman dikirim ke aplikasi (tanpa token/rahasia).
     */
    public static function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email ?? '',
            'username' => $user->username ?? $user->nrp ?? '',
            'nrp' => $user->nrp ?? '',
            'role' => $user->role ?? 'user',
            'pangkat' => $user->pangkat ?? '',
            'korps' => $user->korps ?? '',
            'jabatan' => $user->jabatan ?? '',
            'phone' => $user->phone ?? '',
            'avatar_url' => !empty($user->avatar) ? asset('storage/' . $user->avatar) : null,
            'is_active' => (bool) ($user->is_active ?? false),
        ];
    }

    /**
     * Profil + permission + flag kompatibilitas lama (can_access_*) yang dibaca
     * aplikasi Android v1.0.x dan Flutter.
     */
    public static function sessionPayload(User $user): array
    {
        $p = self::permissions($user);

        return array_merge(self::profile($user), [
            'permissions' => self::grantedAbilities($user),
            'is_super_admin' => self::isSuperAdmin($user),
            'is_admin' => self::isAdmin($user),
            // Kompatibilitas mundur untuk klien lama:
            'can_access_agenda' => $p['letter_logs.view'],
            'can_access_letters' => $p['letters.view'],
            'can_access_skhpp' => $p['skhpp.view'],
            'can_access_categories' => $p['categories.view'],
            'can_access_signature' => $p['signature.view'],
            'can_access_commander' => $p['commander_account.view'],
            'can_access_mitra' => $p['mitra.view'],
            'can_access_violations' => $p['violations.view'],
            'can_access_activities' => $p['activities.view'],
            'can_access_users' => $p['users.view'],
            'can_access_settings' => self::isSuperAdmin($user),
        ]);
    }
}
