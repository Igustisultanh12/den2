<?php

namespace App\Http\Controllers;

use App\Models\Skhpp;
use App\Models\SkhppMember;
use App\Models\SignatureRequest;
use App\Models\SkhppArchive;
use App\Models\User;
use App\Models\AppNotification;
use App\Services\SkhppNumberService;
use App\Services\SkhppPdfService;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class SkhppController extends Controller
{
    /**
     * List Halaman Utama Pengajuan SKHPP
     */
    public function index(Request $request)
    {
        $query = Skhpp::with(['members', 'submitter', 'approver'])
            ->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = strtolower(trim($request->search));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(nama) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(nik) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(pangkat_korps_nrp) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(nomor_skhpp) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('kategori') && $request->kategori !== 'all') {
            $query->where('kategori_personel', $request->kategori);
        }

        $skhpps = $query->paginate(15)->withQueryString();

        return Inertia::render('Skhpp/Index', [
            'skhpps' => $skhpps,
            'filters' => $request->only(['search', 'status', 'kategori'])
        ]);
    }

    /**
     * Halaman Form Pembuatan SKHPP Baru (Operator Input)
     */
    public function create()
    {
        return Inertia::render('Skhpp/Create');
    }

    /**
     * Simpan Permohonan SKHPP Baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'kategori_personel' => 'required|in:militer,sipil_dinas,perusahaan',
            'is_pernikahan' => 'required|boolean',
            'surat_pengantar' => 'required|string',
            'nama' => 'required|string',
            'pangkat_korps_nrp' => 'nullable|string',
            'nik' => 'nullable|string',
            'jabatan_pekerjaan' => 'required|string',
            'tempat_lahir' => 'required|string',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'agama' => 'required|string',
            'alamat' => 'required|string',
            'peruntukan' => 'required|string',
            'foto_1' => 'required|image|mimes:jpeg,jpg,png|max:10240',
            'foto_2' => 'nullable|required_if:is_pernikahan,true|image|mimes:jpeg,jpg,png|max:10240',
            'has_pengikut' => 'boolean',
            'members' => 'nullable|array',
            'members.*.nama' => 'required|string',
            'members.*.pangkat_nrp_nik' => 'required|string',
            'members.*.jabatan' => 'required|string',
        ]);

        $foto1Path = null;
        if ($request->hasFile('foto_1')) {
            $foto1Path = $request->file('foto_1')->store('skhpp_photos', 'public');
        }

        $foto2Path = null;
        if ($request->hasFile('foto_2')) {
            $foto2Path = $request->file('foto_2')->store('skhpp_photos', 'public');
        }

        $user = auth()->user();
        $code = 'SKHPP-' . strtoupper(Str::random(10));

        $skhpp = Skhpp::create([
            'kategori_personel' => $request->kategori_personel,
            'is_pernikahan' => $request->is_pernikahan,
            'surat_pengantar' => $request->surat_pengantar,
            'nama' => $request->nama,
            'pangkat_korps_nrp' => $request->pangkat_korps_nrp,
            'nik' => $request->nik,
            'jabatan_pekerjaan' => $request->jabatan_pekerjaan,
            'tempat_lahir' => $request->tempat_lahir,
            'tanggal_lahir' => $request->tanggal_lahir,
            'jenis_kelamin' => $request->jenis_kelamin,
            'agama' => $request->agama,
            'alamat' => $request->alamat,
            'has_pengikut' => $request->has_pengikut ?? false,
            'peruntukan' => $request->peruntukan,
            'foto_1' => $foto1Path,
            'foto_2' => $foto2Path,
            'label_foto_1' => $request->is_pernikahan ? 'Foto Calon Suami' : 'Foto Utama Personel',
            'label_foto_2' => $request->is_pernikahan ? 'Foto Calon Istri' : null,
            'status' => 'pending',
            'verification_code' => $code,
            'submitted_by' => $user->id,
            'operator_name' => $user->name,
            'operator_nrp_pangkat' => ($user->pangkat ? $user->pangkat . ' ' : '') . ($user->nrp ? 'NRP. ' . $user->nrp : ''),
            'submitted_at' => now(),
        ]);

        // Simpan Anggota Pengikut jika ada
        if ($request->has_pengikut && !empty($request->members)) {
            foreach ($request->members as $idx => $m) {
                SkhppMember::create([
                    'skhpp_id' => $skhpp->id,
                    'no_urut' => $idx + 1,
                    'nama' => $m['nama'],
                    'pangkat_nrp_nik' => $m['pangkat_nrp_nik'],
                    'jabatan' => $m['jabatan'],
                ]);
            }
        }

        // Otomatis masuk Arsip SKHPP dengan status "Menunggu Upload PDF" (menunggu TTE/TTD Komandan)
        try {
            SkhppArchive::syncFromSkhpp($skhpp);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal auto-sync SKHPP ke Arsip SKHPP: " . $e->getMessage());
        }

        // Sinkronisasi ke Antrean TTD Komandan (SignatureRequest)
        try {
            SignatureRequest::create([
                'user_id' => $user->id,
                'subject' => "Pengajuan SKHPP - {$skhpp->nama} (" . strtoupper(str_replace('_', ' ', $skhpp->kategori_personel)) . ")",
                'letter_number' => "DRAFT SKHPP #" . $skhpp->id,
                'file_path' => $foto1Path,
                'status' => 'pending',
                'note' => "Peruntukan: {$skhpp->peruntukan}"
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal auto-sync SKHPP ke SignatureRequest: " . $e->getMessage());
        }

        // Kirim Notifikasi Sistem In-App Bell & WA ke Komandan & Admin
        try {
            $komandan = User::where('role', 'komandan')->whereNotNull('phone')->first() 
                     ?? User::where('role', 'admin')->whereNotNull('phone')->first();
            $katName = ($skhpp->kategori_personel === 'perusahaan') ? 'SKHPP-P (Mitra Kerja/Perusahaan)' : 'SKHPP-D (Dinas Militer & PNS)';
            $directUrl = url('/signature-requests?open_skhpp=' . $skhpp->id . '&tab=skhpp');
            $pesanKomandan = " *SI SINDEN: PENGAJUAN SKHPP BARU*\n\n" .
                             "Mohon izin Komandan, terdapat pengajuan penerbitan SKHPP baru:\n\n" .
                             " *Nama:* {$skhpp->nama}\n" .
                             " *Pangkat/NRP/NIK:* " . ($skhpp->pangkat_korps_nrp ?: ($skhpp->nik ?: '-')) . "\n" .
                             " *Kategori:* {$katName}\n" .
                             " *Peruntukan:* {$skhpp->peruntukan}\n" .
                             " *Operator Pengaju:* {$user->name}\n\n" .
                             "Mohon izin untuk memeriksa & menyetujui berkas langsung di: {$directUrl}";

            AppNotification::notify(
                $komandan?->id,
                'komandan',
                'Pengajuan SKHPP Baru',
                "Pengajuan SKHPP baru atas nama {$skhpp->nama} ({$katName}) oleh {$user->name}.",
                'primary',
                '/signature-requests?open_skhpp=' . $skhpp->id . '&tab=skhpp',
                $pesanKomandan,
                $komandan?->phone
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal kirim notif SKHPP baru: " . $e->getMessage());
        }

        return redirect()->route('skhpp.index')->with('success', 'Permohonan SKHPP berhasil diterbitkan & dikirim ke Antrean TTD Komandan.');
    }

    /**
     * Tampilkan Detail SKHPP & Pratinjau PDF Cetak
     */
    public function show($id)
    {
        $skhpp = Skhpp::with(['members', 'submitter', 'approver'])->findOrFail($id);

        return Inertia::render('Skhpp/Show', [
            'skhpp' => $skhpp,
            'verificationUrl' => route('skhpp.verify', $skhpp->verification_code)
        ]);
    }

    /**
     * Halaman Edit Permohonan SKHPP (Operator Revisi Data)
     */
    public function edit($id)
    {
        $skhpp = Skhpp::with('members')->findOrFail($id);

        return Inertia::render('Skhpp/Edit', [
            'skhpp' => $skhpp
        ]);
    }

    /**
     * Simpan Perubahan & Ajukan Ulang ke TTD Komandan
     */
    public function update(Request $request, $id)
    {
        $skhpp = Skhpp::findOrFail($id);

        $request->validate([
            'kategori_personel' => 'required|in:militer,sipil_dinas,perusahaan',
            'is_pernikahan' => 'required|boolean',
            'surat_pengantar' => 'required|string',
            'nama' => 'required|string',
            'pangkat_korps_nrp' => 'nullable|string',
            'nik' => 'nullable|string',
            'jabatan_pekerjaan' => 'required|string',
            'tempat_lahir' => 'required|string',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'agama' => 'required|string',
            'alamat' => 'required|string',
            'peruntukan' => 'required|string',
            'foto_1' => 'nullable|image|mimes:jpeg,jpg,png|max:10240',
            'foto_2' => 'nullable|image|mimes:jpeg,jpg,png|max:10240',
            'has_pengikut' => 'boolean',
            'members' => 'nullable|array',
            'members.*.nama' => 'required|string',
            'members.*.pangkat_nrp_nik' => 'required|string',
            'members.*.jabatan' => 'required|string',
        ]);

        if ($request->hasFile('foto_1')) {
            if ($skhpp->foto_1 && Storage::disk('public')->exists($skhpp->foto_1)) {
                Storage::disk('public')->delete($skhpp->foto_1);
            }
            $skhpp->foto_1 = $request->file('foto_1')->store('skhpp_photos', 'public');
        }

        if ($request->hasFile('foto_2')) {
            if ($skhpp->foto_2 && Storage::disk('public')->exists($skhpp->foto_2)) {
                Storage::disk('public')->delete($skhpp->foto_2);
            }
            $skhpp->foto_2 = $request->file('foto_2')->store('skhpp_photos', 'public');
        }

        $user = auth()->user();

        $skhpp->update([
            'kategori_personel' => $request->kategori_personel,
            'is_pernikahan' => $request->is_pernikahan,
            'surat_pengantar' => $request->surat_pengantar,
            'nama' => $request->nama,
            'pangkat_korps_nrp' => $request->pangkat_korps_nrp,
            'nik' => $request->nik,
            'jabatan_pekerjaan' => $request->jabatan_pekerjaan,
            'tempat_lahir' => $request->tempat_lahir,
            'tanggal_lahir' => $request->tanggal_lahir,
            'jenis_kelamin' => $request->jenis_kelamin,
            'agama' => $request->agama,
            'alamat' => $request->alamat,
            'has_pengikut' => $request->has_pengikut ?? false,
            'peruntukan' => $request->peruntukan,
            'label_foto_1' => $request->is_pernikahan ? 'Foto Calon Suami' : 'Foto Utama Personel',
            'label_foto_2' => $request->is_pernikahan ? 'Foto Calon Istri' : null,
            'status' => 'pending', // Reset kembali ke pending setelah revisi operator
            'catatan_revisi' => null,
            'submitted_by' => $user->id,
            'operator_name' => $user->name,
            'operator_nrp_pangkat' => ($user->pangkat ? $user->pangkat . ' ' : '') . ($user->nrp ? 'NRP. ' . $user->nrp : ''),
            'submitted_at' => now(),
        ]);

        try {
            SkhppArchive::syncFromSkhpp($skhpp);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal sync revisi SKHPP ke Arsip SKHPP: " . $e->getMessage());
        }

        // Re-sync Anggota Pengikut
        SkhppMember::where('skhpp_id', $skhpp->id)->delete();
        if ($request->has_pengikut && !empty($request->members)) {
            foreach ($request->members as $idx => $m) {
                SkhppMember::create([
                    'skhpp_id' => $skhpp->id,
                    'no_urut' => $idx + 1,
                    'nama' => $m['nama'],
                    'pangkat_nrp_nik' => $m['pangkat_nrp_nik'],
                    'jabatan' => $m['jabatan'],
                ]);
            }
        }

        // Kirim Notifikasi WA ke Komandan
        try {
            $komandan = User::where('role', 'komandan')->whereNotNull('phone')->first() 
                     ?? User::where('role', 'admin')->whereNotNull('phone')->first();
            if ($komandan && $komandan->phone) {
                $katName = ($skhpp->kategori_personel === 'perusahaan') ? 'SKHPP-P (Mitra Kerja/Perusahaan)' : 'SKHPP-D (Dinas Militer & PNS)';
                $directUrl = url('/signature-requests?open_skhpp=' . $skhpp->id . '&tab=skhpp');
                $pesanKomandan = " *SI SINDEN: PERBAIKAN & PENGAJUAN ULANG SKHPP*\n\n" .
                                 "Mohon izin Komandan, terdapat perbaikan data SKHPP oleh Operator yang diajukan ulang:\n\n" .
                                 " *Nama:* {$skhpp->nama}\n" .
                                 " *Pangkat/NRP/NIK:* " . ($skhpp->pangkat_korps_nrp ?: ($skhpp->nik ?: '-')) . "\n" .
                                 " *Kategori:* {$katName}\n" .
                                 " *Peruntukan:* {$skhpp->peruntukan}\n" .
                                 " *Operator:* {$user->name}\n\n" .
                                 "Mohon izin untuk memeriksa & menyetujui berkas langsung di: {$directUrl}";

                WhatsappService::sendMessage($komandan->phone, $pesanKomandan);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal kirim WA notif Komandan perbaikan SKHPP: " . $e->getMessage());
        }

        return redirect()->route('skhpp.index')->with('success', 'Data SKHPP berhasil diperbarui & diajukan ulang ke TTD Komandan.');
    }

    /**
     * Persetujuan Komandan (TTD & Penomoran Terintegrasi Buku Agenda SINDEN)
     */
    public function approve(Request $request, $id)
    {
        $user = auth()->user();
        $isCommander = $user->isAdmin() || $user->role === 'komandan' || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';

        if (!$isCommander) {
            return back()->with('error', 'Otoritas ditolak! Hanya Komandan yang berwenang menandatangani SKHPP.');
        }

        $skhpp = Skhpp::findOrFail($id);

        $currentYear = (int) date('Y');
        $romanMonth = SkhppNumberService::romanMonth((int) date('n'));
        $jenis = SkhppNumberService::jenisFor($skhpp->kategori_personel);

        $priority = SkhppNumberService::PRIORITY; // SKHPP selalu RAHASIA

        // Nomor khusus (boleh sisipan berhuruf, mis. "12a")
        $custom = null;
        if ($request->filled('custom_nomor_urut')) {
            $custom = SkhppNumberService::parse($request->custom_nomor_urut);
            if (!$custom) {
                return back()->with('error', 'Nomor urut SKHPP tidak valid. Gunakan angka, boleh diikuti huruf sisipan (cth: 12 atau 12a).');
            }
            if (SkhppNumberService::isTaken($jenis, $currentYear, $custom[0], null, $skhpp->id, $custom[1])) {
                $label = SkhppNumberService::label(...$custom);
                return back()->with('error', "Nomor urut {$label} SKHPP-{$jenis} tahun {$currentYear} sudah dipakai. Gunakan nomor lain.");
            }
        }

        // PENOMORAN TERPISAH SKHPP-D & SKHPP-P (BERSAMBUNG DENGAN NOMOR MANUAL DI ARSIP SKHPP)
        // & PERTAHANKAN NOMOR SAAT PENGAJUAN ULANG / SUDAH DIATUR ADMIN
        [$nextSeq, $formattedNo] = SkhppNumberService::withLock($jenis, function () use ($request, $skhpp, $user, $jenis, $currentYear, $romanMonth, $priority, $custom) {
            if ($custom) {
                [$nextSeq, $suffix] = $custom;
            } elseif (!empty($skhpp->nomor_urut) && (int) $skhpp->nomor_urut > 0) {
                $nextSeq = (int) $skhpp->nomor_urut;
                $suffix = $skhpp->nomor_sisipan ?: null;
            } else {
                $nextSeq = SkhppNumberService::nextSequence($jenis, $currentYear);
                $suffix = null;
            }
            $label = SkhppNumberService::label($nextSeq, $suffix);

            $formattedNo = $request->filled('custom_nomor_skhpp')
                ? trim($request->custom_nomor_skhpp)
                : SkhppNumberService::format($priority, $label, $romanMonth, $currentYear);

            $skhpp->update([
                'status' => 'approved',
                'nomor_skhpp' => $formattedNo,
                'nomor_urut' => $nextSeq,
                'nomor_sisipan' => $suffix,
                'bulan_romawi' => $romanMonth,
                'tahun' => $currentYear,
                'tanggal_skhpp' => now(),
                'approved_by' => $user->id,
                'approved_at' => now(),
                'catatan_revisi' => null,
            ]);

            $archive = SkhppArchive::syncFromSkhpp($skhpp);
            $archive->update(['priority' => $priority]);

            return [$label, $formattedNo];
        });

        // TTE TERBIT: PDF RESMI LANGSUNG TERSIMPAN DI ARSIP SKHPP
        $pdfStored = SkhppPdfService::storeToArchive($skhpp->fresh(), $user->id);

        // SINKRONISASI OTOMATIS KE FITUR TRACKING PENGAJUAN SECURITY CLEARANCE (SC)
        // Tahap 1 (Pengisian RH), 2 (Pengecekan Dokumen), 3 (Cetak SKHPP), dan 4 (Menunggu TTD) otomatis terlewati!
        try {
            $identifierType = 'nrp';
            $identifierNum = null;
            if (!empty($skhpp->pangkat_korps_nrp)) {
                if (preg_match('/(\d{5,18})/', $skhpp->pangkat_korps_nrp, $m)) {
                    $identifierNum = $m[1];
                    $identifierType = 'nrp';
                }
            }
            if (empty($identifierNum) && !empty($skhpp->nik)) {
                $identifierNum = $skhpp->nik;
                $identifierType = 'nik';
            }
            if (empty($identifierNum)) {
                $identifierNum = 'NRP-' . $skhpp->id;
            }

            $scSub = \App\Models\ScSubmission::where('skhpp_id', $skhpp->id)->first();
            if (!$scSub) {
                $trackingCode = 'SC-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(5));
                $scSub = \App\Models\ScSubmission::create([
                    'skhpp_id' => $skhpp->id,
                    'tracking_code' => $trackingCode,
                    'nama' => $skhpp->nama,
                    'pangkat_korps' => $skhpp->pangkat_korps_nrp,
                    'identifier_type' => $identifierType,
                    'identifier_number' => $identifierNum,
                    'kesatuan' => $skhpp->alamat ?: 'Koarmada 2',
                    'jabatan' => $skhpp->jabatan_pekerjaan,
                    'keperluan' => $skhpp->peruntukan,
                    'nomor_skhpp' => $formattedNo,
                    'current_stage' => 5, // TAHAP 5: SKHPP TERBIT (Tahap 1, 2, 3, 4 terlewati otomatis)
                    'status' => 'proses',
                    'catatan_petugas' => "SKHPP resmi diterbitkan oleh Komandan Denintel (TTE) No. {$formattedNo}. Tahap 1 s/d 4 otomatis terlewati.",
                    'created_by' => $user->id,
                ]);

                $initialLogs = [
                    ['stage' => 1, 'stage_title' => 'Pengisian RH', 'notes' => 'Pengisian Riwayat Hidup telah diproses terintegrasi pada penerbitan SKHPP.'],
                    ['stage' => 2, 'stage_title' => 'Pengecekan Kelengkapan Dokumen', 'notes' => 'Pemeriksaan berkas dan kelengkapan dokumen telah diverifikasi oleh operator Denintel.'],
                    ['stage' => 3, 'stage_title' => 'Proses Cetak SKHPP', 'notes' => 'Proses administrasi dan pencetakan lembar SKHPP selesai.'],
                    ['stage' => 4, 'stage_title' => 'Menunggu TTD Komandan Denintel', 'notes' => 'Persetujuan dan tanda tangan dinas elektronik (TTE) Komandan Denintel telah disahkan.'],
                    ['stage' => 5, 'stage_title' => 'SKHPP Terbit', 'notes' => "SKHPP resmi disahkan dan diterbitkan dengan nomor {$formattedNo}. Berkas beralih ke Staf Intelijen."],
                ];

                foreach ($initialLogs as $logItem) {
                    \App\Models\ScSubmissionLog::create([
                        'sc_submission_id' => $scSub->id,
                        'stage' => $logItem['stage'],
                        'stage_title' => $logItem['stage_title'],
                        'notes' => $logItem['notes'],
                        'user_id' => $user->id,
                        'user_name' => $user->name ?? 'Komandan Denintel',
                    ]);
                }
            } else {
                $scSub->update([
                    'nomor_skhpp' => $formattedNo,
                    'current_stage' => max(5, $scSub->current_stage),
                    'catatan_petugas' => "Pembaruan nomor SKHPP: {$formattedNo} oleh Komandan Denintel.",
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal auto-sync SKHPP ke ScSubmission: " . $e->getMessage());
        }

        // Kirim Notifikasi Sistem In-App Bell & WA ke Operator / Pengaju
        try {
            $operator = User::find($skhpp->submitted_by);
            $katName = ($skhpp->kategori_personel === 'perusahaan') ? 'SKHPP-P' : 'SKHPP-D';
            $pesanOperator = " *SI SINDEN: SKHPP RESMI DISAHKAN & DITANDATANGANI*\n\n" .
                             "Laporan untuk Operator/Pengaju, SKHPP telah disetujui & ditandatangani Komandan:\n\n" .
                             " *Nama:* {$skhpp->nama}\n" .
                             " *Nomor SKHPP:* {$formattedNo}\n" .
                             " *Kategori:* {$katName}\n\n" .
                             "Dokumen resmi & QR Code TTD sudah terbit dan dapat diunduh di Laman : https://sinden2.my.id/skhpp";

            AppNotification::notify(
                $skhpp->submitted_by,
                null,
                'SKHPP Resmi Disahkan & TTD',
                "SKHPP atas nama {$skhpp->nama} telah disetujui & ditandatangani Komandan (Nomor: {$formattedNo}).",
                'success',
                '/skhpp',
                $pesanOperator,
                $operator?->phone
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal kirim notif SKHPP disetujui: " . $e->getMessage());
        }

        return back()->with('success', $pdfStored
            ? "SKHPP Resmi disetujui Komandan! Nomor: {$formattedNo}. PDF otomatis tersimpan di Arsip SKHPP."
            : "SKHPP Resmi disetujui Komandan! Nomor: {$formattedNo}. PDF belum tersimpan otomatis — simpan ulang dari menu Arsip SKHPP.");
    }

    /**
     * Penolakan SKHPP / Minta Revisi
     */
    public function reject(Request $request, $id)
    {
        $user = auth()->user();
        $isCommander = $user->isAdmin() || $user->role === 'komandan' || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';

        if (!$isCommander) {
            return back()->with('error', 'Otoritas ditolak!');
        }

        $request->validate([
            'catatan_revisi' => 'required|string'
        ]);

        $skhpp = Skhpp::findOrFail($id);
        $skhpp->update([
            'status' => 'rejected',
            'catatan_revisi' => $request->catatan_revisi
        ]);

        // Kirim Notifikasi Sistem In-App Bell & WA ke Operator / Pengaju
        try {
            $operator = User::find($skhpp->submitted_by);
            $pesanRevisi = " *SI SINDEN: PERMOHONAN SKHPP MEMERLUKAN REVISI*\n\n" .
                           "Laporan untuk Operator/Pengaju, pengajuan SKHPP dikembalikan Komandan untuk direvisi:\n\n" .
                           " *Nama:* {$skhpp->nama}\n" .
                           " *Catatan Revisi Komandan:* {$request->catatan_revisi}\n\n" .
                           "Silakan lakukan perbaikan data pada Laman : https://sinden2.my.id/skhpp";

            AppNotification::notify(
                $skhpp->submitted_by,
                null,
                'Revisi SKHPP Memerlukan Perbaikan',
                "Permohonan SKHPP atas nama {$skhpp->nama} dikembalikan Komandan untuk direvisi: \"{$request->catatan_revisi}\".",
                'warning',
                '/skhpp',
                $pesanRevisi,
                $operator?->phone
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal kirim notif SKHPP revisi: " . $e->getMessage());
        }

        return back()->with('warning', 'Pengajuan SKHPP ditolak / dikembalikan untuk revisi.');
    }

    /**
     * Halaman Publik Hasil Scan QR Code TTD Komandan (Validasi Legilitas Resmi SKHPP & Dokumen Dinas)
     */
        private function formatUserJabatan($user)
    {
        if (!$user) return 'PERSONEL SATUAN';
        if (!empty($user->jabatan) && !in_array(strtolower(trim($user->jabatan)), ['personel', 'personel satuan'])) {
            return $user->jabatan;
        }

        $role = strtolower(trim($user->role ?? ''));
        $roleMap = [
            'admin'         => 'ADMINISTRATOR SISTEM',
            'komandan'      => 'KOMANDAN',
            'wadan'         => 'WAKIL KOMANDAN',
            'pasops'        => 'PASOPS',
            'pasiops'       => 'PASIOPS',
            'pasimin'       => 'PASIMIN',
            'pasintel'      => 'PASINTEL',
            'pasilog'       => 'PASILOG',
            'dantim'        => 'DANTIM',
            'danunit'       => 'DANUNIT',
            'danunit1'      => 'DAN UNIT I / LID',
            'danunit2'      => 'DAN UNIT II / PAMGAL',
            'danunitteknis' => 'DAN UNIT TEKNIS',
            'kaurmintel'    => 'KAUR MINTEL',
            'paurset'       => 'PAUR SET',
            'staf'          => 'STAF ADMINISTRASI',
            'personel'      => 'PERSONEL SATUAN',
            'bintara'       => 'BINTARA INTEL',
            'tamtama'       => 'TAMTAMA INTEL',
            'pns'           => 'PNS INTEL',
        ];

        return $roleMap[$role] ?? (!empty($role) ? strtoupper($role) : 'PERSONEL SATUAN');
    }

    public function verify($code)
    {
        $code = trim($code);
        $settings = \App\Models\Setting::pluck('value', 'key')->all();

        // 1. Jika kode ternyata milik SignatureRequest (Berkas Dinas), alihkan ke Halaman Verifikasi Berkas Dinas
        $sigReqExists = \App\Models\SignatureRequest::where('verification_code', $code)
            ->orWhere('letter_number', $code)
            ->exists();

        if ($sigReqExists) {
            return redirect()->route('doc.verify', $code);
        }

        // 2. Cari Berkas SKHPP di tabel Skhpp
        $skhpp = Skhpp::with(['members', 'submitter', 'approver'])
            ->where('verification_code', $code)
            ->orWhere('nomor_skhpp', $code)
            ->first();

        // 3. Tampilkan Halaman Verifikasi SKHPP Resmi
        if (!$skhpp || strtolower($skhpp->status) !== 'approved') {
            return Inertia::render('Skhpp/Verify', [
                'skhpp' => null,
                'verify_code' => $code,
                'settings' => $settings
            ]);
        }

        // Logika a.n. Komandan vs Komandan Langsung pada SKHPP
        $approverUser = $skhpp->approver;
        $isKomandanLangsung = false;

        if ($approverUser) {
            $isKomandanLangsung = ($approverUser->role === 'komandan' || str_contains(strtolower($approverUser->name), 'hari bagio'));
        }

        if ($isKomandanLangsung) {
            $skhpp->signer_name = 'HARI BAGIO WIJAYANTO, M.TR.OPSLA.';
            $skhpp->signer_title = 'Komandan Detasemen Intelijen Koarmada 2 - Kolonel Laut (E) NRP 16085/P';
        } else {
            $aName = $approverUser?->name ?? 'Administrator SINDEN';
            $aRank = $approverUser?->pangkat ?? 'Letnan Dua Laut (KC)';
            $aNrp = ($approverUser?->nrp && $approverUser->nrp !== '00000000000000') ? $approverUser->nrp : '12000018012200216';

            $skhpp->signer_name = 'a.n. KOMANDAN DETASEMEN INTELIJEN KOARMADA 2';
            $skhpp->signer_title = "{$aRank} {$aName} NRP {$aNrp}";
        }

        return Inertia::render('Skhpp/Verify', [
            'skhpp' => $skhpp,
            'verify_code' => $code,
            'settings' => $settings
        ]);
    }

    /**
     * Hapus Data Permohonan SKHPP
     */
    public function destroy($id)
    {
        $skhpp = Skhpp::findOrFail($id);

        if ($skhpp->foto_1 && Storage::disk('public')->exists($skhpp->foto_1)) {
            Storage::disk('public')->delete($skhpp->foto_1);
        }
        if ($skhpp->foto_2 && Storage::disk('public')->exists($skhpp->foto_2)) {
            Storage::disk('public')->delete($skhpp->foto_2);
        }

        // AuditLog pencabutan/penghapusan SKHPP
        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'admin_name' => auth()->user()->name,
            'action' => 'DELETE_SKHPP',
            'target_personnel' => $skhpp->nama,
            'description' => "Penghapusan / Pencabutan SKHPP Nomor: " . ($skhpp->nomor_skhpp ?: $skhpp->id),
            'ip_address' => request()->ip(),
        ]);

        // Arsip SKHPP: yang belum bernomor dihapus; yang sudah bernomor ditandai "Dicabut"
        // agar nomor tidak dipakai ulang dan jejaknya tetap ada.
        $archive = SkhppArchive::where('skhpp_id', $skhpp->id)->first();
        if ($archive) {
            if (empty($archive->nomor_urut)) {
                $archive->deleteFile();
                $archive->delete();
            } else {
                $archive->update([
                    'skhpp_id' => null,
                    'status' => SkhppArchive::STATUS_DICABUT,
                    'keterangan' => trim(($archive->keterangan ? $archive->keterangan . "\n" : '') . 'Dicabut/dihapus dari menu Penerbitan SKHPP oleh ' . auth()->user()->name . ' pada ' . now()->format('d/m/Y H:i')),
                ]);
            }
        }

        $skhpp->delete();

        return back()->with('success', 'Data SKHPP dan status validasi legalitas berhasil dihapus/dicabut.');
    }

    /**
     * Export Dokumen Resmi SKHPP ke Format PDF (DomPDF SINDEN Standard)
     */
    public function exportPdf($id)
    {
        $skhpp = Skhpp::with(['members', 'submitter', 'approver'])->findOrFail($id);

        return SkhppPdfService::make($skhpp)->stream(SkhppPdfService::filename($skhpp));
    }

    /**
     * Khusus Admin: Atur / Loncati Nomor Urut SKHPP Tanpa Menyetujui / TTD
     */
    public function updateNumber(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user->isAdmin()) {
            return back()->with('error', 'Hanya Admin yang berwenang mengoperasikan fitur Atur Nomor.');
        }

        // Angka, boleh diikuti huruf sisipan (cth: 12 atau 12a)
        $request->validate([
            'custom_nomor_urut' => ['required', SkhppNumberService::SEQUENCE_RULE],
        ], ['custom_nomor_urut.regex' => 'Nomor urut berupa angka, boleh diikuti huruf sisipan (cth: 12 atau 12a).']);

        $skhpp = Skhpp::findOrFail($id);
        $parsed = SkhppNumberService::parse($request->custom_nomor_urut);
        if (!$parsed) {
            return back()->with('error', 'Nomor urut SKHPP tidak valid.');
        }
        [$seq, $suffix] = $parsed;
        $nextSeq = SkhppNumberService::label($seq, $suffix);

        $currentYear = (int) date('Y');
        $romanMonth = SkhppNumberService::romanMonth((int) date('n'));

        $jenis = SkhppNumberService::jenisFor($skhpp->kategori_personel);
        $targetCode = 'SKHPP-' . $jenis;
        $priority = SkhppNumberService::PRIORITY; // SKHPP selalu RAHASIA

        if (SkhppNumberService::isTaken($jenis, $currentYear, $seq, null, $skhpp->id, $suffix)) {
            return back()->with('error', "Nomor urut {$nextSeq} {$targetCode} tahun {$currentYear} sudah dipakai (SINDEN / manual). Gunakan nomor lain.");
        }

        $formattedNo = SkhppNumberService::format($priority, $nextSeq, $romanMonth, $currentYear);

        SkhppNumberService::withLock($jenis, function () use ($skhpp, $formattedNo, $seq, $suffix, $romanMonth, $currentYear) {
            $skhpp->update([
                'nomor_skhpp' => $formattedNo,
                'nomor_urut' => $seq,
                'nomor_sisipan' => $suffix,
                'bulan_romawi' => $romanMonth,
                'tahun' => $currentYear,
            ]);
            SkhppArchive::syncFromSkhpp($skhpp);
        });

        // Kirim Notifikasi Sistem In-App Bell & WA ke Operator & Komandan bahwa Nomor SKHPP telah dibooking
        try {
            $operator = User::find($skhpp->submitted_by);
            $komandan = User::where('role', 'komandan')->whereNotNull('phone')->first() 
                     ?? User::where('role', 'admin')->whereNotNull('phone')->first();

            $pesanBooking = " *SI SINDEN: NOMOR SKHPP BERHASIL DIBOOKING*\n\n" .
                            "Laporan untuk Operator & Komandan, Admin telah mengatur/booking nomor urut SKHPP:\n\n" .
                            " *Nama Personel:* {$skhpp->nama}\n" .
                            " *Kategori:* {$targetCode}\n" .
                            " *Nomor SKHPP Dibooking:* {$formattedNo}\n" .
                            " *Peruntukan:* {$skhpp->peruntukan}\n" .
                            " *Status Berkas:* Pending TTD Komandan\n" .
                            " *Diatur Oleh Admin:* {$user->name}\n\n" .
                            "Dokumen dapat dipantau di Laman : https://sinden2.my.id/skhpp";

            AppNotification::notify(
                $skhpp->submitted_by,
                null,
                'Nomor SKHPP Berhasil Dibooking',
                "Admin {$user->name} telah mengatur nomor urut {$targetCode} menjadi {$formattedNo} untuk {$skhpp->nama}.",
                'info',
                '/skhpp',
                $pesanBooking,
                $operator?->phone
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal kirim notif Booking Nomor: " . $e->getMessage());
        }

        return back()->with('success', "Nomor urut {$targetCode} berhasil diatur ke {$nextSeq} ({$formattedNo}) tanpa menyetujui TTD.");
    }

    /**
     * Khusus Admin: Ajukan Ulang TTE ke Komandan (Reset status ke pending)
     */
    public function reSubmitTte($id)
    {
        $user = auth()->user();
        if (!$user->isAdmin()) {
            return back()->with('error', 'Hanya Admin yang berwenang mengoperasikan fitur Ajukan TTE Ulang.');
        }

        $skhpp = Skhpp::findOrFail($id);
        $skhpp->update([
            'status' => 'pending',
            'catatan_revisi' => null,
            'submitted_at' => now(),
        ]);

        // Arsip SKHPP kembali "Menunggu Upload PDF" sampai TTE ulang terbit.
        try {
            SkhppArchive::syncFromSkhpp($skhpp);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal sync Ajukan Ulang TTE ke Arsip SKHPP: " . $e->getMessage());
        }

        // Kirim Notifikasi Sistem In-App Bell & WA ke Komandan
        try {
            $komandan = User::where('role', 'komandan')->whereNotNull('phone')->first() 
                     ?? User::where('role', 'admin')->whereNotNull('phone')->first();
            $katName = ($skhpp->kategori_personel === 'perusahaan') ? 'SKHPP-P (Mitra Kerja/Perusahaan)' : 'SKHPP-D (Dinas Militer & PNS)';
            $directUrl = url('/signature-requests?open_skhpp=' . $skhpp->id . '&tab=skhpp');
            $pesan = " *SI SINDEN: PENGAJUAN ULANG TTE SKHPP*\n\n" .
                     "Mohon izin Komandan, terdapat permohonan SKHPP yang DIAJUKAN ULANG oleh Admin untuk otorisasi TTE Komandan:\n\n" .
                     " *Nama:* {$skhpp->nama}\n" .
                     " *Pangkat/NRP/NIK:* " . ($skhpp->pangkat_korps_nrp ?: ($skhpp->nik ?: '-')) . "\n" .
                     " *Kategori:* {$katName}\n" .
                     " *Peruntukan:* {$skhpp->peruntukan}\n" .
                     " *Pengaju (Admin):* {$user->name}\n\n" .
                     "Mohon izin untuk memeriksa & menyetujui berkas langsung di: {$directUrl}";
            
            AppNotification::notify(
                $komandan?->id,
                'komandan',
                'Pengajuan Ulang TTE SKHPP',
                "Admin {$user->name} mengajukan ulang TTE SKHPP atas nama {$skhpp->nama} ke Komandan.",
                'primary',
                '/signature-requests?open_skhpp=' . $skhpp->id . '&tab=skhpp',
                $pesan,
                $komandan?->phone
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Notif Ajukan Ulang failed: " . $e->getMessage());
        }

        return back()->with('success', "Permohonan SKHPP {$skhpp->nama} berhasil diajukan ulang ke TTD Komandan.");
    }
}
