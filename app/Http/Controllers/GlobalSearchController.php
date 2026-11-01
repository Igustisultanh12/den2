<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\Skhpp;
use App\Models\SkhppArchive;
use App\Models\User;
use App\Support\FeatureAccess;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    /**
     * Endpoint Pencarian Cepat Global SINDEN (Command Palette Ctrl+K).
     * Hasil disaring sesuai fitur yang diberikan kepada personel.
     */
    public function search(Request $request)
    {
        $query = trim($request->input('q', ''));
        $user = auth()->user();

        $navigation = collect($this->getAllNavigation($user));

        if (strlen($query) < 2) {
            return response()->json([
                'navigation' => $navigation->take(6)->values(),
                'personnel' => [],
                'letters' => [],
                'skhpp' => [],
            ]);
        }

        $needle = strtolower($query);

        // 1. Menu Navigasi Cepat
        $navigation = $navigation->filter(function ($item) use ($needle) {
            return str_contains(strtolower($item['title']), $needle)
                || str_contains(strtolower($item['description']), $needle);
        })->values()->take(5);

        // 2. Data Personel (hanya pengelola pengguna)
        $personnel = collect();
        if ($user->isAdmin()) {
            $personnel = User::where('is_active', true)
                ->visibleTo($user)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('nrp', 'like', "%{$query}%")
                        ->orWhere('pangkat', 'like', "%{$query}%");
                })
                ->take(5)
                ->get(['id', 'name', 'pangkat', 'nrp', 'role'])
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'title' => "{$u->pangkat} {$u->name}",
                    'subtitle' => "NRP: {$u->nrp} - Peran: " . strtoupper((string) $u->role),
                    'url' => route('users.index'),
                ]);
        }

        // 3. Arsip Surat Masuk/Keluar (tanpa SKHPP)
        $letters = collect();
        if (FeatureAccess::allows($user, 'letters')) {
            $letters = Letter::withoutSkhpp()
                ->where(function ($q) use ($query) {
                    $q->where('letter_number', 'like', "%{$query}%")
                        ->orWhere('subject', 'like', "%{$query}%");
                })
                ->latest('date')
                ->take(5)
                ->get(['id', 'letter_number', 'subject', 'date'])
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'title' => $l->letter_number,
                    'subtitle' => $l->subject . ' (' . date('d/m/Y', strtotime($l->date)) . ')',
                    'url' => route('letters.index', ['search' => $l->letter_number]),
                ]);
        }

        // 4. Berkas SKHPP (penerbitan) & Arsip SKHPP
        $skhpp = collect();
        if (FeatureAccess::allows($user, 'skhpp')) {
            $skhpp = Skhpp::where(function ($q) use ($query) {
                    $q->where('nomor_skhpp', 'like', "%{$query}%")
                        ->orWhere('nama', 'like', "%{$query}%");
                })
                ->latest()
                ->take(5)
                ->get(['id', 'nomor_skhpp', 'nama', 'status'])
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'title' => $s->nomor_skhpp ?: "SKHPP: {$s->nama}",
                    'subtitle' => "Nama: {$s->nama} - Status: " . strtoupper((string) $s->status),
                    'url' => route('skhpp.show', $s->id),
                ]);
        }
        if (FeatureAccess::allows($user, 'skhpp_archive')) {
            $archives = SkhppArchive::where(function ($q) use ($query) {
                    $q->where('nomor_skhpp', 'like', "%{$query}%")
                        ->orWhere('nama', 'like', "%{$query}%")
                        ->orWhere('perihal', 'like', "%{$query}%");
                })
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($a) => [
                    'id' => 'arsip-' . $a->id,
                    'title' => $a->nomor_skhpp ?: 'Arsip SKHPP (belum bernomor)',
                    'subtitle' => 'Arsip SKHPP-' . $a->jenis . ' - ' . ($a->nama ?: $a->perihal) . ' - ' . $a->status_label,
                    'url' => route('skhpp-archives.index', ['search' => $a->nomor_skhpp ?: $a->nama]),
                ]);
            $skhpp = $skhpp->concat($archives)->take(8)->values();
        }

        return response()->json([
            'navigation' => $navigation,
            'personnel' => $personnel,
            'letters' => $letters,
            'skhpp' => $skhpp,
        ]);
    }

    /** Menu navigasi cepat yang boleh dibuka personel ini. */
    private function getAllNavigation(User $user): array
    {
        $items = [
            ['feature' => null, 'title' => 'Dashboard Utama', 'description' => 'Pusat pantau ringkasan operasional satuan', 'url' => route('dashboard')],
        ];

        foreach (FeatureAccess::FEATURES as $key => $meta) {
            if (empty($meta['route']) || !FeatureAccess::allows($user, $key)) {
                continue;
            }
            $items[] = [
                'feature' => $key,
                'title' => $meta['label'],
                'description' => $meta['description'],
                'url' => route($meta['route']),
            ];
        }

        if ($user->isAdmin()) {
            $items[] = ['feature' => null, 'title' => 'Kelola Pengguna & Akses Fitur', 'description' => 'Manajemen akun personel dan pengaturan fitur yang boleh diakses', 'url' => route('users.index')];
        }

        return array_map(fn ($i) => array_diff_key($i, ['feature' => true]), $items);
    }
}
