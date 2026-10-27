<?php

namespace App\Http\Controllers;

use App\Models\SpJaga;
use App\Models\SpJagaPerwira;
use App\Models\SpJagaAnggota;
use App\Models\User;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SpJagaController extends Controller
{
    private function ensureNomorUrutColumnType(): void
    {
        try {
            static $checked = false;
            if (!$checked) {
                DB::statement("ALTER TABLE sp_jagas MODIFY COLUMN nomor_urut VARCHAR(50) NULL DEFAULT NULL");
                $checked = true;
            }
        } catch (\Throwable $e) {
            // Abaikan jika sudah VARCHAR(50) atau database tidak mengizinkan ALTER langsung
        }
    }
    private function getRomanMonth($month)
    {
        $map = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];
        return $map[(int)$month] ?? 'I';
    }

    private function getNamaBulanIndo($month)
    {
        $map = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        return $map[(int)$month] ?? 'Januari';
    }

    private function formatTanggalIndo($dateStr)
    {
        if (!$dateStr) return '';
        $bulanIndo = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $c = Carbon::parse($dateStr);
        $m = (int)$c->format('n');
        $namaBulan = $bulanIndo[$m] ?? $c->format('F');
        return $c->format('j') . ' ' . $namaBulan . ' ' . $c->format('Y');
    }

    private function terbilangAngka($angka)
    {
        $angka = (int)$angka;
        $bilangan = [
            '', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima',
            'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'
        ];
        
        if ($angka < 12) {
            return $bilangan[$angka];
        } elseif ($angka < 20) {
            return $this->terbilangAngka($angka - 10) . ' Belas';
        } elseif ($angka < 100) {
            $hasil = $this->terbilangAngka((int)($angka / 10)) . ' Puluh';
            if ($angka % 10 > 0) {
                $hasil .= ' ' . $bilangan[$angka % 10];
            }
            return $hasil;
        }
        return (string)$angka;
    }

    /**
     * Halaman Utama Menu SP Jaga
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isPasops = in_array(strtolower(trim($user->role ?? '')), ['pasops', 'admin', 'komandan']) 
                    || str_contains(strtolower($user->name), 'roni sumantri')
                    || str_contains(strtolower($user->name), 'sultan');

        $isAdmin = ($user->isAdmin() || $user->name === 'Suma Nurhasanah' || $user->name === 'I Gusti Sultan H.A, A.Md.Kom');

        // 1. Ambil Jadwal Jaga Saya (Bulan Berjalan / Terbaru yang Published)
        $activeSp = SpJaga::with(['perwiras', 'anggotas'])
            ->where('status', 'published')
            ->orderBy('tahun', 'desc')
            ->orderBy('bulan', 'desc')
            ->first();

        $mySchedule = null;
        if ($activeSp) {
            // Cek di Perwira Jaga
            $perwiraMatch = $activeSp->perwiras->first(function($p) use ($user) {
                return $p->user_id == $user->id 
                    || (!empty($user->nrp) && $p->nrp == $user->nrp)
                    || (str_contains(strtolower($p->nama), strtolower($user->name)));
            });

            if ($perwiraMatch) {
                $dates = [];
                for ($i = 1; $i <= 7; $i++) {
                    $val = $perwiraMatch->{"tgl_{$i}"};
                    if (!empty($val) && $val !== '-') {
                        $dates[] = $val;
                    }
                }
                $mySchedule = [
                    'sp_id' => $activeSp->id,
                    'nomor_sprin' => $activeSp->nomor_sprin,
                    'bulan' => $activeSp->bulan,
                    'tahun' => $activeSp->tahun,
                    'peran' => 'Perwira Siaga Sintel',
                    'pangkat_korps' => $perwiraMatch->pangkat_korps,
                    'tanggal_jaga' => implode(', ', $dates),
                    'pdf_url' => route('sp-jaga.download-pdf', $activeSp->id)
                ];
            } else {
                // Cek di Anggota Jaga Divisi
                foreach ($activeSp->anggotas as $divisi) {
                    $items = is_array($divisi->anggota_items) ? $divisi->anggota_items : json_decode($divisi->anggota_items, true) ?? [];
                    foreach ($items as $item) {
                        $isMatch = ($item['user_id'] ?? null) == $user->id
                                || (!empty($user->nrp) && ($item['nrp_nip'] ?? '') == $user->nrp)
                                || str_contains(strtolower($item['nama'] ?? ''), strtolower($user->name));
                        
                        if ($isMatch) {
                            $mySchedule = [
                                'sp_id' => $activeSp->id,
                                'nomor_sprin' => $activeSp->nomor_sprin,
                                'bulan' => $activeSp->bulan,
                                'tahun' => $activeSp->tahun,
                                'divisi_no' => $divisi->divisi_no,
                                'peran' => ($item['role_jaga'] ?? 'ANGGOTA') === 'BAGA' ? 'Bintara Jaga (BAGA)' : 'Anggota Siaga Sintel',
                                'pangkat_korps' => $item['pangkat_korps'] ?? '',
                                'tanggal_jaga' => $divisi->tanggal_list_text,
                                'pdf_url' => route('sp-jaga.download-pdf', $activeSp->id)
                            ];
                            break 2;
                        }
                    }
                }
            }
        }

        // 2. Daftar Semua SP Jaga
        $query = SpJaga::with(['creator', 'approver', 'perwiras', 'anggotas'])->orderBy('created_at', 'desc');

        if (!$isAdmin && !$isPasops) {
            $query->where('status', 'published');
        }

        $spJagas = $query->paginate(15)->withQueryString();

        // 3. Antrean Verifikasi Pasops (Khusus Pasops & Admin)
        $pendingSignatures = SpJaga::with(['creator', 'perwiras', 'anggotas'])
            ->where('status', 'pending_signature')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('SpJaga/Index', [
            'mySchedule' => $mySchedule,
            'spJagas' => $spJagas,
            'pendingSignatures' => $pendingSignatures,
            'isPasops' => $isPasops,
            'isAdmin' => $isAdmin,
        ]);
    }

    /**
     * Form Pembuatan SP Jaga Baru
     */
    public function create()
    {
        $personels = User::select('id', 'name', 'pangkat', 'nrp', 'role', 'phone')
            ->orderBy('name', 'asc')
            ->get();

        $currentMonth = (int)date('n');
        $currentYear = (int)date('Y');

        return Inertia::render('SpJaga/Create', [
            'personels' => $personels,
            'defaultMonth' => $currentMonth,
            'defaultYear' => $currentYear,
        ]);
    }

    /**
     * Simpan SP Jaga Baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:2024',
            'nomor_urut' => 'nullable|string|max:50',
            'tmt_mulai' => 'required|date',
            'tmt_selesai' => 'required|date',
            'tanggal_surat' => 'required|date',
            'ttd_type' => 'required|in:tte,manual',
            'perwiras' => 'required|array|min:1',
            'anggotas' => 'required|array|min:1',
        ]);

        $user = auth()->user();

        $bulanInput = (int)$request->bulan;
        $tahunInput = (int)$request->tahun;
        $bulanRomawi = $this->getRomanMonth($bulanInput);
        $nomorUrut = trim((string)($request->nomor_urut ?: '29'));
        $nomorSprin = "Sprin/ {$nomorUrut} /{$bulanRomawi}/{$tahunInput}";

        // Hitung total personel
        $totalPersonel = count($request->perwiras);
        foreach ($request->anggotas as $div) {
            $items = $div['anggota_items'] ?? [];
            $totalPersonel += count($items);
        }
        $terbilang = $this->terbilangAngka($totalPersonel);

        // Perwira Tertua (Pater / Penerima Perintah)
        $firstPerwira = $request->perwiras[0] ?? [];
        $perwiraTertuaUserId = $request->perwira_tertua_user_id ?: ($firstPerwira['user_id'] ?? null);
        $perwiraTertuaNama = $request->perwira_tertua_nama ?: ($firstPerwira['nama'] ?? 'Kapten Laut (P) Indra Gunawan T.Z');
        $perwiraTertuaPangkatNrp = $request->perwira_tertua_pangkat_nrp ?: (($firstPerwira['pangkat_korps'] ?? 'Kapten Laut (P)') . ' NRP ' . ($firstPerwira['nrp'] ?? '19739/P'));
        $perwiraTertuaJabatan = $request->perwira_tertua_jabatan ?: 'Dan Unit 1 Lid Den Intel Koarmada 2';

        $uniqueCode = 'TTE-SPJAGA-' . $tahunInput . str_pad($bulanInput, 2, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

        $status = ($request->ttd_type === 'tte') ? 'pending_signature' : 'draft';

        $this->ensureNomorUrutColumnType();

        $createData = [
            'nomor_sprin' => $nomorSprin,
            'nomor_urut' => $nomorUrut,
            'bulan' => $bulanInput,
            'tahun' => $tahunInput,
            'bulan_romawi' => $bulanRomawi,
            'tmt_mulai' => $request->tmt_mulai,
            'tmt_selesai' => $request->tmt_selesai,
            'tanggal_surat' => $request->tanggal_surat,
            'perwira_tertua_user_id' => $perwiraTertuaUserId,
            'perwira_tertua_nama' => $perwiraTertuaNama,
            'perwira_tertua_pangkat_nrp' => $perwiraTertuaPangkatNrp,
            'perwira_tertua_jabatan' => $perwiraTertuaJabatan,
            'total_personel_count' => $totalPersonel,
            'total_personel_terbilang' => $terbilang,
            'ttd_type' => $request->ttd_type,
            'status' => $status,
            'verification_code' => $uniqueCode,
            'penandatangan_nama' => 'Roni Sumantri',
            'penandatangan_pangkat_nrp' => 'Mayor Laut (P) NRP 17456/P',
            'penandatangan_jabatan' => 'Pasiops',
            'created_by' => $user->id,
        ];

        try {
            $spJaga = SpJaga::create($createData);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'nomor_urut') || str_contains($e->getMessage(), '1265')) {
                DB::statement("ALTER TABLE sp_jagas MODIFY COLUMN nomor_urut VARCHAR(50) NULL DEFAULT NULL");
                $spJaga = SpJaga::create($createData);
            } else {
                throw $e;
            }
        }

        // Simpan Perwira Jaga (Lampiran 1)
        foreach ($request->perwiras as $idx => $p) {
            SpJagaPerwira::create([
                'sp_jaga_id' => $spJaga->id,
                'user_id' => $p['user_id'] ?? null,
                'no_urut' => $idx + 1,
                'nama' => $p['nama'],
                'pangkat_korps' => $p['pangkat_korps'] ?? '',
                'nrp' => $p['nrp'] ?? '',
                'tgl_1' => $p['tgl_1'] ?? '-',
                'tgl_2' => $p['tgl_2'] ?? '-',
                'tgl_3' => $p['tgl_3'] ?? '-',
                'tgl_4' => $p['tgl_4'] ?? '-',
                'tgl_5' => $p['tgl_5'] ?? '-',
                'tgl_6' => $p['tgl_6'] ?? '-',
                'tgl_7' => $p['tgl_7'] ?? '-',
                'tgl_list' => $p['tgl_list'] ?? [],
            ]);
        }

        // Simpan Anggota Jaga Divisi (Lampiran 2)
        foreach ($request->anggotas as $idx => $a) {
            SpJagaAnggota::create([
                'sp_jaga_id' => $spJaga->id,
                'divisi_no' => $idx + 1,
                'tanggal_list_text' => $a['tanggal_list_text'] ?? '',
                'tanggal_array' => $a['tanggal_array'] ?? [],
                'anggota_items' => $a['anggota_items'] ?? [],
            ]);
        }

        // Jika TTE, kirim notifikasi ke Pasops
        if ($spJaga->ttd_type === 'tte') {
            try {
                $pasops = User::where('role', 'pasops')->whereNotNull('phone')->first()
                       ?? User::where('name', 'like', '%Roni Sumantri%')->first()
                       ?? User::where('role', 'admin')->whereNotNull('phone')->first();

                if ($pasops && $pasops->phone) {
                    $bulanNama = strtoupper($this->getNamaBulanIndo($spJaga->bulan) . ' ' . $spJaga->tahun);
                    $pesan = "*SI SINDEN: PENGAJUAN TTE SP JAGA BARU*\n\n" .
                             "Mohon izin Pasops, terdapat pengajuan Surat Perintah Jaga Siaga Sintel baru yang memerlukan pemeriksaan & tanda tangan elektronik (TTE):\n\n" .
                             "- *Nomor Sprin:* " . $spJaga->nomor_sprin . "\n" .
                             "- *Bulan/Tahun:* " . $bulanNama . "\n" .
                             "- *Total Personel:* " . $totalPersonel . " Orang\n" .
                             "- *Operator Pengaju:* " . $user->name . "\n\n" .
                             "Silakan periksa & bubuhkan TTE digital melalui Portal SINDEN:\n" .
                             route('sp-jaga.index');

                    WhatsappService::sendMessage($pasops->phone, $pesan);
                }
            } catch (\Exception $e) {
                Log::warning("Gagal kirim notif Pasops TTE SP Jaga: " . $e->getMessage());
            }
        }

        return redirect()->route('sp-jaga.index')->with('success', 'Surat Perintah Jaga berhasil dibuat.');
    }

    /**
     * Halaman Edit & Koreksi Jadwal Jaga
     */
    public function edit($id)
    {
        $spJaga = SpJaga::with(['perwiras', 'anggotas'])->findOrFail($id);
        $personels = User::select('id', 'name', 'pangkat', 'nrp', 'role', 'phone')
            ->orderBy('name', 'asc')
            ->get();

        return Inertia::render('SpJaga/Edit', [
            'spJaga' => $spJaga,
            'personels' => $personels,
        ]);
    }

    /**
     * Simpan Pembaruan / Koreksi Jadwal Jaga
     */
    public function update(Request $request, $id)
    {
        $spJaga = SpJaga::findOrFail($id);

        $request->validate([
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:2024',
            'nomor_urut' => 'nullable|string|max:50',
            'tmt_mulai' => 'required|date',
            'tmt_selesai' => 'required|date',
            'tanggal_surat' => 'required|date',
            'ttd_type' => 'required|in:tte,manual',
            'perwiras' => 'required|array|min:1',
            'anggotas' => 'required|array|min:1',
        ]);

        $bulanInput = (int)$request->bulan;
        $tahunInput = (int)$request->tahun;
        $bulanRomawi = $this->getRomanMonth($bulanInput);
        $nomorUrut = trim((string)($request->nomor_urut ?: $spJaga->nomor_urut ?: '29'));
        $nomorSprin = "Sprin/ {$nomorUrut} /{$bulanRomawi}/{$tahunInput}";

        $totalPersonel = count($request->perwiras);
        foreach ($request->anggotas as $div) {
            $items = $div['anggota_items'] ?? [];
            $totalPersonel += count($items);
        }
        $terbilang = $this->terbilangAngka($totalPersonel);

        // Perwira Tertua (Pater / Penerima Perintah)
        $firstPerwira = $request->perwiras[0] ?? [];
        $perwiraTertuaUserId = $request->perwira_tertua_user_id ?: ($firstPerwira['user_id'] ?? null);
        $perwiraTertuaNama = $request->perwira_tertua_nama ?: ($firstPerwira['nama'] ?? 'Kapten Laut (P) Indra Gunawan T.Z');
        $perwiraTertuaPangkatNrp = $request->perwira_tertua_pangkat_nrp ?: (($firstPerwira['pangkat_korps'] ?? 'Kapten Laut (P)') . ' NRP ' . ($firstPerwira['nrp'] ?? '19739/P'));
        $perwiraTertuaJabatan = $request->perwira_tertua_jabatan ?: 'Dan Unit 1 Lid Den Intel Koarmada 2';

        $this->ensureNomorUrutColumnType();

        $updateData = [
            'nomor_sprin' => $nomorSprin,
            'nomor_urut' => $nomorUrut,
            'bulan' => $bulanInput,
            'tahun' => $tahunInput,
            'bulan_romawi' => $bulanRomawi,
            'tmt_mulai' => $request->tmt_mulai,
            'tmt_selesai' => $request->tmt_selesai,
            'tanggal_surat' => $request->tanggal_surat,
            'perwira_tertua_user_id' => $perwiraTertuaUserId,
            'perwira_tertua_nama' => $perwiraTertuaNama,
            'perwira_tertua_pangkat_nrp' => $perwiraTertuaPangkatNrp,
            'perwira_tertua_jabatan' => $perwiraTertuaJabatan,
            'total_personel_count' => $totalPersonel,
            'total_personel_terbilang' => $terbilang,
            'ttd_type' => $request->ttd_type,
        ];

        try {
            $spJaga->update($updateData);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'nomor_urut') || str_contains($e->getMessage(), '1265')) {
                DB::statement("ALTER TABLE sp_jagas MODIFY COLUMN nomor_urut VARCHAR(50) NULL DEFAULT NULL");
                $spJaga->update($updateData);
            } else {
                throw $e;
            }
        }

        // Sync Perwira
        SpJagaPerwira::where('sp_jaga_id', $spJaga->id)->delete();
        foreach ($request->perwiras as $idx => $p) {
            SpJagaPerwira::create([
                'sp_jaga_id' => $spJaga->id,
                'user_id' => $p['user_id'] ?? null,
                'no_urut' => $idx + 1,
                'nama' => $p['nama'],
                'pangkat_korps' => $p['pangkat_korps'] ?? '',
                'nrp' => $p['nrp'] ?? '',
                'tgl_1' => $p['tgl_1'] ?? '-',
                'tgl_2' => $p['tgl_2'] ?? '-',
                'tgl_3' => $p['tgl_3'] ?? '-',
                'tgl_4' => $p['tgl_4'] ?? '-',
                'tgl_5' => $p['tgl_5'] ?? '-',
                'tgl_6' => $p['tgl_6'] ?? '-',
                'tgl_7' => $p['tgl_7'] ?? '-',
                'tgl_list' => $p['tgl_list'] ?? [],
            ]);
        }

        // Sync Anggota Divisi
        SpJagaAnggota::where('sp_jaga_id', $spJaga->id)->delete();
        foreach ($request->anggotas as $idx => $a) {
            SpJagaAnggota::create([
                'sp_jaga_id' => $spJaga->id,
                'divisi_no' => $idx + 1,
                'tanggal_list_text' => $a['tanggal_list_text'] ?? '',
                'tanggal_array' => $a['tanggal_array'] ?? [],
                'anggota_items' => $a['anggota_items'] ?? [],
            ]);
        }

        return redirect()->route('sp-jaga.index')->with('success', 'Perubahan jadwal SP Jaga berhasil disimpan.');
    }

    /**
     * Persetujuan & TTE Digital oleh Pasops
     */
    public function approveTte(Request $request, $id)
    {
        $user = auth()->user();
        $spJaga = SpJaga::with(['perwiras', 'anggotas'])->findOrFail($id);

        $pasopsUser = User::where('role', 'pasops')->first()
                   ?? User::where('name', 'like', '%Roni Sumantri%')->first()
                   ?? ($user->role === 'pasops' ? $user : null);

        $pName = $pasopsUser ? $pasopsUser->name : 'Roni Sumantri';
        $pRank = $pasopsUser ? $pasopsUser->pangkat : 'Mayor Laut (P)';
        $pNrp = ($pasopsUser && $pasopsUser->nrp) ? 'NRP ' . $pasopsUser->nrp : 'NRP 17456/P';

        $spJaga->update([
            'status' => 'published',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'penandatangan_user_id' => $pasopsUser?->id ?: $user->id,
            'penandatangan_nama' => $pName,
            'penandatangan_pangkat_nrp' => trim("{$pRank} {$pNrp}"),
            'penandatangan_jabatan' => 'Pasiops',
        ]);

        // Kirim Broadcast Notifikasi WhatsApp ke Semua Personel Terdaftar
        $this->sendBroadcastWa($spJaga);

        return back()->with('success', 'Surat Perintah Jaga berhasil disahkan atas nama Pasiops & Notifikasi WA telah dikirimkan ke seluruh personel.');
    }

    /**
     * Unggah Berkas PDF Scan TTD Basah (Manual)
     */
    public function uploadManualSigned(Request $request, $id)
    {
        $request->validate([
            'signed_file' => 'required|mimes:pdf|max:20480'
        ]);

        $spJaga = SpJaga::with(['perwiras', 'anggotas'])->findOrFail($id);

        $path = $request->file('signed_file')->storeAs(
            'sp_jaga_signed',
            'SP_JAGA_' . $spJaga->tahun . '_' . $spJaga->bulan . '_' . time() . '.pdf',
            'public'
        );

        $spJaga->update([
            'status' => 'published',
            'signed_file_path' => $path,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // Kirim Broadcast Notifikasi WhatsApp ke Semua Personel Terdaftar
        $this->sendBroadcastWa($spJaga);

        return back()->with('success', 'Berkas PDF bertanda tangan basah berhasil diunggah & Notifikasi WA telah dikirimkan ke seluruh personel.');
    }

    /**
     * Cetak & Unduh PDF Resmi 3 Halaman
     */
    public function downloadPdf($id)
    {
        $spJaga = SpJaga::with(['perwiras', 'anggotas'])->findOrFail($id);

        // Jika mode manual dan sudah ada file scan fisik yang diupload
        if ($spJaga->ttd_type === 'manual' && $spJaga->signed_file_path && Storage::disk('public')->exists($spJaga->signed_file_path)) {
            return response()->download(storage_path('app/public/' . $spJaga->signed_file_path), "SP_JAGA_{$spJaga->tahun}_{$spJaga->bulan}.pdf");
        }

        // Generate QR Code TTD Image
        $qr_base64 = null;
        if ($spJaga->ttd_type === 'tte' && $spJaga->status === 'published' && $spJaga->verification_code) {
            try {
                $verifyUrl = route('doc.verify', $spJaga->verification_code);
                $qrData = @file_get_contents('https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($verifyUrl));
                if ($qrData) {
                    $qr_base64 = 'data:image/png;base64,' . base64_encode($qrData);
                }
            } catch (\Exception $e) {
                Log::warning("Gagal fetch QR image: " . $e->getMessage());
            }
        }

        // Ambil Data Pejabat Perwira Tertua (Pater / Penerima Perintah)
        $perwiraTertuaNama = $spJaga->perwira_tertua_nama ?: 'Indra Gunawan';
        $perwiraTertuaPangkatNrp = $spJaga->perwira_tertua_pangkat_nrp ?: 'Kapten Laut (P) NRP 19739/P';
        $perwiraTertuaJabatan = $spJaga->perwira_tertua_jabatan ?: 'Dan Unit 1 Lid Den Intel Koarmada 2';

        // Hitung personel pengikut (total dikurangi 1 perwira tertua)
        $totalPengikut = ($spJaga->total_personel_count > 1) ? ($spJaga->total_personel_count - 1) : 26;
        $totalPengikutTerbilang = $this->terbilangAngka($totalPengikut);

        // Format penerima Diktum Kepada
        if (stripos($perwiraTertuaPangkatNrp, 'NRP') !== false) {
            [$pangkatPart, $nrpPart] = explode('NRP', $perwiraTertuaPangkatNrp, 2);
            $pangkatPart = trim($pangkatPart);
            $nrpPart = trim($nrpPart);
            if ($pangkatPart && stripos($perwiraTertuaNama, $pangkatPart) === 0) {
                $penerimaDiktumKepada = $perwiraTertuaNama . ($nrpPart ? ' NRP ' . $nrpPart : '');
            } else {
                $penerimaDiktumKepada = ($pangkatPart ? $pangkatPart . ' ' : '') . $perwiraTertuaNama . ($nrpPart ? ' NRP ' . $nrpPart : '');
            }
        } else {
            $penerimaDiktumKepada = ($perwiraTertuaPangkatNrp ? $perwiraTertuaPangkatNrp . ' ' : '') . $perwiraTertuaNama;
        }

        // Kompatibilitas variabel lama
        $danunitPangkat = $perwiraTertuaPangkatNrp;
        $danunitNama = $perwiraTertuaNama;
        $danunitNrp = '';
        $danunitJabatan = $perwiraTertuaJabatan;

        // Ambil Data Pejabat Pasops / Pasiops dari Database Users (role: pasops)
        $pasopsUser = User::where('role', 'pasops')->first()
                   ?? User::where('role', 'like', '%pasops%')->first()
                   ?? User::where('role', 'like', '%pasiops%')->first()
                   ?? User::where('name', 'like', '%Roni%')->first();

        $pasiopsPangkat = $pasopsUser?->pangkat ?? 'Mayor Laut (P)';
        $pasiopsNama = $pasopsUser?->name ?? 'Roni Sumantri';
        $pasiopsNrp = $pasopsUser?->nrp ? 'NRP ' . $pasopsUser->nrp : 'NRP 17456/P';
        $pasiopsPangkatNrp = "$pasiopsPangkat $pasiopsNrp";

        // Generate PDF Dinamis 3 Halaman via DomPDF
        $pdf = Pdf::loadView('pdf.sp_jaga', compact(
            'spJaga', 
            'qr_base64', 
            'perwiraTertuaNama',
            'perwiraTertuaPangkatNrp',
            'perwiraTertuaJabatan',
            'penerimaDiktumKepada',
            'totalPengikut',
            'totalPengikutTerbilang',
            'danunitPangkat', 
            'danunitNama', 
            'danunitNrp', 
            'danunitJabatan',
            'pasiopsNama',
            'pasiopsPangkatNrp'
        ));
        $pdf->setPaper([0, 0, 609.45, 935.43], 'portrait'); // Ukuran Folio / F4

        $blnAngka = (int)$spJaga->bulan;
        $tahunAngka = (int)$spJaga->tahun;
        if (!empty($spJaga->tmt_mulai)) {
            $tmtCarbon = \Carbon\Carbon::parse($spJaga->tmt_mulai);
            $blnAngka = (int)$tmtCarbon->format('n');
            $tahunAngka = (int)$tmtCarbon->format('Y');
        }

        $filename = "SP_JAGA_" . strtoupper($this->getNamaBulanIndo($blnAngka) . '_' . $tahunAngka) . ".pdf";

        return $pdf->stream($filename);
    }

    /**
     * Hapus SP Jaga
     */
    public function destroy($id)
    {
        $spJaga = SpJaga::findOrFail($id);
        if ($spJaga->signed_file_path && Storage::disk('public')->exists($spJaga->signed_file_path)) {
            Storage::disk('public')->delete($spJaga->signed_file_path);
        }
        $spJaga->delete();
        return back()->with('success', 'Data SP Jaga berhasil dihapus.');
    }

    /**
     * Broadcast WhatsApp Massal ke Seluruh Personel Terdaftar
     */
    private function sendBroadcastWa($spJaga)
    {
        $blnAngka = (int)$spJaga->bulan;
        $tahunAngka = (int)$spJaga->tahun;
        if (!empty($spJaga->tmt_mulai)) {
            $tmtCarbon = \Carbon\Carbon::parse($spJaga->tmt_mulai);
            $blnAngka = (int)$tmtCarbon->format('n');
            $tahunAngka = (int)$tmtCarbon->format('Y');
        }
        $namaBulanTahun = strtoupper($this->getNamaBulanIndo($blnAngka) . ' ' . $tahunAngka);
        $notifiedPhones = [];

        // 1. Broadcast ke Perwira Jaga
        foreach ($spJaga->perwiras as $perwira) {
            $user = $perwira->user ?: User::where('name', $perwira->nama)->orWhere('nrp', $perwira->nrp)->first();
            $phone = $user?->phone;

            if ($phone && !in_array($phone, $notifiedPhones)) {
                $notifiedPhones[] = $phone;
                $dates = [];
                for ($i = 1; $i <= 7; $i++) {
                    $val = $perwira->{"tgl_{$i}"};
                    if (!empty($val) && $val !== '-') {
                        $dates[] = $val;
                    }
                }
                $tanggalText = implode(', ', $dates) . ' ' . $namaBulanTahun;

                $pesan = "*DETASEMEN INTELIJEN KOARMADA 2*\n" .
                         "*PEMBERITAHUAN JADWAL JAGA SIAGA SINTEL*\n\n" .
                         "Yth. {$perwira->pangkat_korps} {$perwira->nama} (NRP: {$perwira->nrp})\n\n" .
                         "Diberitahukan bahwa Surat Perintah Jaga Siaga Sintel Bulan {$namaBulanTahun} ({$spJaga->nomor_sprin}) telah resmi diterbitkan.\n\n" .
                         "*Rincian Jadwal Jaga Anda:*\n" .
                         "- *Peran:* Perwira Siaga Sintel\n" .
                         "- *Tanggal Jaga:* {$tanggalText}\n" .
                         "- *Lokasi:* Kantor Sintel / Tim Intel Koarmada 2\n\n" .
                         "Untuk rincian jadwal lengkap dan mengunduh berkas PDF resmi, silakan login ke Portal SINDEN:\n" .
                         route('sp-jaga.index') . "\n\n" .
                         "Demikian untuk dipedomani dan dilaksanakan dengan penuh rasa tanggung jawab.";

                WhatsappService::sendMessage($phone, $pesan);
            }
        }

        // 2. Broadcast ke Anggota Jaga Divisi
        foreach ($spJaga->anggotas as $divisi) {
            $items = is_array($divisi->anggota_items) ? $divisi->anggota_items : json_decode($divisi->anggota_items, true) ?? [];
            foreach ($items as $item) {
                $user = !empty($item['user_id']) ? User::find($item['user_id']) : User::where('name', $item['nama'] ?? '')->orWhere('nrp', $item['nrp_nip'] ?? '')->first();
                $phone = $user?->phone;

                if ($phone && !in_array($phone, $notifiedPhones)) {
                    $notifiedPhones[] = $phone;
                    $roleLabel = ($item['role_jaga'] ?? 'ANGGOTA') === 'BAGA' ? 'Bintara Jaga (BAGA)' : 'Anggota Siaga Sintel';

                    $pesan = "*DETASEMEN INTELIJEN KOARMADA 2*\n" .
                             "*PEMBERITAHUAN JADWAL JAGA SIAGA SINTEL*\n\n" .
                             "Yth. " . ($item['pangkat_korps'] ?? '') . " " . ($item['nama'] ?? '') . " (NRP/NIP: " . ($item['nrp_nip'] ?? '-') . ")\n\n" .
                             "Diberitahukan bahwa Surat Perintah Jaga Siaga Sintel Bulan {$namaBulanTahun} ({$spJaga->nomor_sprin}) telah resmi diterbitkan.\n\n" .
                             "*Rincian Jadwal Jaga Anda:*\n" .
                             "- *Peran:* {$roleLabel}\n" .
                             "- *Divisi:* Kelompok Divisi {$divisi->divisi_no}\n" .
                             "- *Tanggal Jaga:* {$divisi->tanggal_list_text}\n" .
                             "- *Lokasi:* Kantor Sintel / Tim Intel Koarmada 2\n\n" .
                             "Untuk rincian jadwal lengkap dan mengunduh berkas PDF resmi, silakan login ke Portal SINDEN:\n" .
                             route('sp-jaga.index') . "\n\n" .
                             "Demikian untuk dipedomani dan dilaksanakan dengan penuh rasa tanggung jawab.";

                    WhatsappService::sendMessage($phone, $pesan);
                }
            }
        }
    }
}