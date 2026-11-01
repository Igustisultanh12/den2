<?php

namespace App\Support;

use App\Models\User;

/**
 * Sumber kebenaran tunggal fitur menu SINDEN yang dapat diaktifkan /
 * dinonaktifkan Admin & Super Admin untuk tiap personel.
 *
 * - Admin & Super Admin selalu memiliki seluruh fitur.
 * - users.feature_access = null  => fitur bawaan sesuai peran (perilaku lama).
 * - users.feature_access = [...] => hanya fitur yang tercantum yang aktif.
 *
 * Ditegakkan di tiga tempat sekaligus: middleware rute `feature:<kunci>`,
 * menu sidebar (props Inertia auth.features), dan permission API mobile.
 */
class FeatureAccess
{
    /**
     * Urutan di sini = urutan tampilan pada menu & matriks akses.
     * `route` = rute halaman utama fitur (dipakai pencarian global).
     */
    public const FEATURES = [
        'chat' => [
            'label' => 'Komunikasi & VC',
            'group' => 'Utama',
            'description' => 'Live chat dinas dan panggilan video/suara antar personel.',
            'route' => 'chat.index',
        ],
        'letter_logs' => [
            'label' => 'Agenda Surat',
            'group' => 'Surat & Naskah',
            'description' => 'Buku agenda & booking nomor surat masuk/keluar.',
            'route' => 'letter-logs.index',
        ],
        'letters' => [
            'label' => 'Buat & Arsip Surat',
            'group' => 'Surat & Naskah',
            'description' => 'Arsip digital surat masuk, keluar, dan telegram.',
            'route' => 'letters.index',
        ],
        'skhpp' => [
            'label' => 'Penerbitan SKHPP',
            'group' => 'Surat & Naskah',
            'description' => 'Generate permohonan SKHPP dan pengajuan TTE Komandan.',
            'route' => 'skhpp.index',
        ],
        'skhpp_archive' => [
            'label' => 'Arsip & Nomor SKHPP',
            'group' => 'Surat & Naskah',
            'description' => 'Arsip PDF SKHPP-D / SKHPP-P dan permintaan nomor SKHPP manual.',
            'route' => 'skhpp-archives.index',
        ],
        'sc_submissions' => [
            'label' => 'Tracking Pengajuan SC',
            'group' => 'Surat & Naskah',
            'description' => 'Pemantauan alur berkas Security Clearance.',
            'route' => 'sc-submissions.index',
        ],
        'sp_jaga' => [
            'label' => 'SP Jaga Siaga',
            'group' => 'Surat & Naskah',
            'description' => 'Surat perintah & jadwal jaga siaga.',
            'route' => 'sp-jaga.index',
        ],
        'categories' => [
            'label' => 'Kategori Surat',
            'group' => 'Surat & Naskah',
            'description' => 'Kelola kategori & kode penomoran surat.',
            'route' => 'categories.index',
        ],
        'signature' => [
            'label' => 'Tanda Tangan Digital',
            'group' => 'Validasi & TTE',
            'description' => 'Pengajuan & persetujuan TTE/TTD Komandan serta stempel digital.',
            'route' => 'signature.index',
        ],
        'printing' => [
            'label' => 'Layanan Printing',
            'group' => 'Validasi & TTE',
            'description' => 'Cetak dokumen ke printer jaringan kantor.',
            'route' => 'printing.index',
        ],
        'mitra' => [
            'label' => 'Pencatatan Mitra',
            'group' => 'Logistik & Keuangan',
            'description' => 'Matriks pembayaran kontribusi mitra kerja.',
            'route' => 'mitras.index',
        ],
        'backup' => [
            'label' => 'Explorer Backup',
            'group' => 'Logistik & Keuangan',
            'description' => 'Pangkalan backup PC & berbagi folder (hanya dari jaringan kantor).',
            'route' => 'backup.index',
        ],
        'violations' => [
            'label' => 'Catatan Pelanggaran',
            'group' => 'Pengamanan',
            'description' => 'Pencatatan pelanggaran disiplin prajurit.',
            'route' => 'soldier-violations.index',
        ],
        'activities' => [
            'label' => 'Radar Kegiatan',
            'group' => 'Pengamanan',
            'description' => 'Rencana pengamanan kegiatan masyarakat.',
            'route' => 'activities.index',
        ],
        'tracking' => [
            'label' => 'Pantau Personel',
            'group' => 'Sistem',
            'description' => 'Peta posisi realtime personel dari aplikasi Android.',
            'route' => 'tracking.index',
        ],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::FEATURES);
    }

    /** Katalog untuk UI matriks akses. */
    public static function catalog(): array
    {
        $out = [];
        foreach (self::FEATURES as $key => $meta) {
            $out[] = [
                'key' => $key,
                'label' => $meta['label'],
                'group' => $meta['group'],
                'description' => $meta['description'],
            ];
        }
        return $out;
    }

    /**
     * Fitur bawaan per peran bila Admin belum mengatur akses secara khusus.
     * Meniru menu yang sebelumnya tampil untuk peran tersebut.
     *
     * @return list<string>
     */
    public static function defaultsFor(User $user): array
    {
        if (MobileAccess::isAdmin($user)) {
            return self::keys();
        }

        if (MobileAccess::isAnggotaSintel($user)) {
            return ['sc_submissions', 'sp_jaga'];
        }

        // Role Personel: bawaannya hanya Chat, melihat SP Jaga & Backup. Fitur lain
        // muncul hanya bila diberikan Admin / Super Admin di Kelola Pengguna.
        if ($user->isPersonel()) {
            $features = ['chat', 'sp_jaga', 'backup'];
            if ((bool) $user->can_access_mitra) {
                $features[] = 'mitra'; // akses Mitra yang sudah pernah diberikan Admin tetap berlaku
            }
            return self::normalize($features);
        }

        $features = [
            'chat', 'letter_logs', 'letters', 'skhpp', 'skhpp_archive',
            'sc_submissions', 'sp_jaga', 'signature', 'printing', 'violations',
        ];

        if ((bool) $user->can_access_mitra || MobileAccess::isDeveloper($user)) {
            $features[] = 'mitra';
        }
        if (MobileAccess::isKomandan($user)) {
            $features[] = 'tracking';
        }

        return self::normalize($features);
    }

    /**
     * Fitur yang benar-benar aktif untuk personel.
     *
     * @return list<string>
     */
    public static function enabledFor(User $user): array
    {
        if (MobileAccess::isAdmin($user)) {
            return self::keys();
        }

        $custom = $user->feature_access;
        if (is_string($custom)) {
            $custom = json_decode($custom, true);
        }

        if (is_array($custom)) {
            return self::normalize($custom);
        }

        return self::defaultsFor($user);
    }

    public static function allows(?User $user, string $feature): bool
    {
        if (!$user) {
            return false;
        }
        if (MobileAccess::isAdmin($user)) {
            return true;
        }
        return in_array($feature, self::enabledFor($user), true);
    }

    /**
     * Buang kunci tak dikenal & duplikat, urutkan sesuai FEATURES.
     *
     * @return list<string>
     */
    public static function normalize(array $features): array
    {
        $wanted = array_flip(array_map('strval', $features));
        return array_values(array_filter(self::keys(), fn ($k) => isset($wanted[$k])));
    }
}
