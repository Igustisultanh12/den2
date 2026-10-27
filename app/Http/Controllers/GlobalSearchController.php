<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\KoperasiLoan;
use App\Models\Letter;
use App\Models\Skhpp;
use App\Models\User;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    /**
     * Endpoint Pencarian Cepat Global SINDEN (Command Palette Ctrl+K)
     */
    public function search(Request $request)
    {
        $query = trim($request->input('q', ''));
        $user = auth()->user();

        $isAnggotaSintel = false;
        if ($user && $user->role !== 'admin') {
            $r = strtolower(preg_replace('/[\s_-]+/', '', $user->role ?? ''));
            $j = strtolower($user->jabatan ?? '');
            $isAnggotaSintel = ($r === 'anggotasintel' || str_contains($j, 'anggota sintel'));
        }

        if ($isAnggotaSintel) {
            $sintelNav = [
                ['title' => 'Tracking Penerbitan SC', 'description' => 'Monitoring alur berkas dan status penerbitan Security Clearance (SC)', 'url' => route('sc-submissions.index')],
                ['title' => 'Surat Perintah (SP) Jaga Siaga', 'description' => 'Daftar penugasan dan surat perintah jaga siaga Sintel', 'url' => route('sp-jaga.index')],
            ];

            if (strlen($query) >= 2) {
                $sintelNav = collect($sintelNav)->filter(function ($item) use ($query) {
                    return str_contains(strtolower($item['title']), strtolower($query))
                        || str_contains(strtolower($item['description']), strtolower($query));
                })->values()->all();
            }

            return response()->json([
                'navigation' => $sintelNav,
                'personnel' => [],
                'letters' => [],
                'skhpp' => [],
                'loans' => [],
            ]);
        }

        if (strlen($query) < 2) {
            return response()->json([
                'navigation' => $this->getDefaultNavigation(),
                'personnel' => [],
                'letters' => [],
                'skhpp' => [],
                'loans' => [],
            ]);
        }

        // 1. Menu Navigasi Cepat
        $navigation = collect($this->getAllNavigation())->filter(function ($item) use ($query) {
            return str_contains(strtolower($item['title']), strtolower($query))
                || str_contains(strtolower($item['description']), strtolower($query));
        })->values()->take(5);

        // 2. Data Personel
        $personnel = User::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('nrp', 'like', "%{$query}%")
                    ->orWhere('pangkat', 'like', "%{$query}%");
            })
            ->take(5)
            ->get(['id', 'name', 'pangkat', 'nrp', 'role', 'phone'])
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'title' => "{$u->pangkat} {$u->name}",
                    'subtitle' => "NRP: {$u->nrp} - Peran: " . strtoupper($u->role),
                    'url' => route('users.index'),
                ];
            });

        // 3. Persuratan & Naskah Dinas
        $letters = Letter::where(function ($q) use ($query) {
                $q->where('letter_number', 'like', "%{$query}%")
                    ->orWhere('subject', 'like', "%{$query}%");
            })
            ->latest('date')
            ->take(5)
            ->get(['id', 'letter_number', 'subject', 'date'])
            ->map(function ($l) {
                return [
                    'id' => $l->id,
                    'title' => $l->letter_number,
                    'subtitle' => $l->subject . " (" . date('d/m/Y', strtotime($l->date)) . ")",
                    'url' => route('letters.index'),
                ];
            });

        // 4. Berkas SKHPP
        $skhpp = Skhpp::where(function ($q) use ($query) {
                $q->where('nomor_skhpp', 'like', "%{$query}%")
                    ->orWhere('nama_pemohon', 'like', "%{$query}%");
            })
            ->latest()
            ->take(5)
            ->get(['id', 'nomor_skhpp', 'nama_pemohon', 'status'])
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'title' => $s->nomor_skhpp ?: "SKHPP Pemohon: {$s->nama_pemohon}",
                    'subtitle' => "Pemohon: {$s->nama_pemohon} - Status: " . strtoupper($s->status),
                    'url' => route('skhpp.index'),
                ];
            });

        // 5. Pinjaman Koperasi
        $loans = KoperasiLoan::with('user')
            ->where(function ($q) use ($query) {
                $q->where('loan_code', 'like', "%{$query}%")
                    ->orWhereHas('user', function ($qu) use ($query) {
                        $qu->where('name', 'like', "%{$query}%")
                           ->orWhere('nrp', 'like', "%{$query}%");
                    });
            })
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($loan) {
                $pangkatName = $loan->user ? "{$loan->user->pangkat} {$loan->user->name}" : "Personel";
                return [
                    'id' => $loan->id,
                    'title' => "{$loan->loan_code} - {$pangkatName}",
                    'subtitle' => "Plafon: Rp " . number_format($loan->amount_requested, 0, ',', '.') . " - Status: " . strtoupper($loan->status),
                    'url' => route('simpan-pinjam.kelola'),
                ];
            });

        return response()->json([
            'navigation' => $navigation,
            'personnel' => $personnel,
            'letters' => $letters,
            'skhpp' => $skhpp,
            'loans' => $loans,
        ]);
    }

    private function getDefaultNavigation()
    {
        return collect($this->getAllNavigation())->take(5)->values();
    }

    private function getAllNavigation()
    {
        return [
            ['title' => 'Dashboard Utama', 'description' => 'Pusat pantau ringkasan operasional satuan', 'url' => route('dashboard')],
            ['title' => 'Agenda Surat & Naskah Dinas', 'description' => 'Buku register nomor surat dan arsip naskah dinas', 'url' => route('letters.index')],
            ['title' => 'Penerbitan SKHPP', 'description' => 'Modul verifikasi dan penerbitan dokumen SKHPP militer/sipil', 'url' => route('skhpp.index')],
            ['title' => 'Simpan Pinjam (Koperasi)', 'description' => 'Pengajuan pinjaman, simpanan anggota dan kartu virtual', 'url' => route('simpan-pinjam.index')],
            ['title' => 'Kelola Koperasi (Pengurus)', 'description' => 'Pusat verifikasi pinjaman, kas operasional, dan cicilan', 'url' => route('simpan-pinjam.kelola')],
            ['title' => 'Otoritas Akses Personel', 'description' => 'Manajemen hak akses, verifikasi akun, dan matriks fitur', 'url' => route('users.index')],
            ['title' => 'Modul Keuangan Mitra', 'description' => 'Monitoring dan verifikasi arus pembayaran mitra kerja', 'url' => route('mitras.index')],
            ['title' => 'Pangkalan Cadangan PC Backup', 'description' => 'Penyimpanan arsip digital dan backup data kedinasan', 'url' => route('backup.index')],
        ];
    }
}
