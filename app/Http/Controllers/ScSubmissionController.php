<?php

namespace App\Http\Controllers;

use App\Models\ScSubmission;
use App\Models\ScSubmissionLog;
use App\Models\Skhpp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\WhatsappService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class ScSubmissionController extends Controller
{
    /**
     * Daftar Berkas Pengajuan SC untuk Admin & Petugas
     */
    public function index(Request $request)
    {
        ScSubmission::ensureSchema();
        if (!Schema::hasTable('sc_submissions')) {
            return Inertia::render('ScSubmission/Index', [
                'submissions' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15),
                'stats' => [
                    'total' => 0,
                    'total_dinas' => 0,
                    'total_perusahaan' => 0,
                    'in_progress' => 0,
                    'at_denintel' => 0,
                    'at_sintel' => 0,
                    'completed' => 0,
                    'sudah_diambil' => 0,
                    'belum_diambil' => 0,
                ],
                'sintelStats' => [
                    'stage_6_9' => ['dinas' => 0, 'perusahaan' => 0, 'total' => 0],
                    'stage_5_9' => ['dinas' => 0, 'perusahaan' => 0, 'total' => 0],
                    'dinas' => 0,
                    'perusahaan' => 0,
                    'total' => 0,
                ],
                'sintelUsers' => [],
                'otherUsers' => [],
                'filters' => $request->only(['search', 'stage', 'status', 'pengambilan', 'sort', 'kategori']),
                'stages' => array_values(ScSubmission::STAGES),
            ]);
        }

        // Bersihkan pratinjau yang telah melewati batas 2x24 jam (48 jam) jika kolom tersedia
        if (Schema::hasColumn('sc_submissions', 'file_sc_preview')) {
            $withPreviews = ScSubmission::whereNotNull('file_sc_preview')->get();
            foreach ($withPreviews as $subItem) {
                $subItem->checkAndPurgeExpiredPreview();
            }
        }

        $relations = ['creator'];
        if (Schema::hasTable('sc_submission_logs')) {
            $relations[] = 'logs';
        }
        if (Schema::hasColumn('sc_submissions', 'skhpp_id')) {
            $relations[] = 'skhpp';
        }

        $query = ScSubmission::with($relations);

        // Filter Kategori SC: Dinas (SKHPP-D) vs Perusahaan (SKHPP-P)
        if ($request->filled('kategori') && $request->kategori !== 'all') {
            if ($request->kategori === 'perusahaan') {
                $query->where(function ($q) {
                    $q->where('kategori_sc', 'perusahaan')
                      ->orWhere('nomor_skhpp', 'like', '%SKHPP-P%');
                });
            } elseif ($request->kategori === 'dinas') {
                $query->where(function ($q) {
                    $q->where('kategori_sc', '!=', 'perusahaan')->orWhereNull('kategori_sc');
                })->where(function ($q) {
                    $q->whereNull('nomor_skhpp')->orWhere('nomor_skhpp', 'not like', '%SKHPP-P%');
                });
            }
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $cleanSearch = preg_replace('/[^A-Za-z0-9]/', '', $search);
            $query->where(function ($q) use ($search, $cleanSearch) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('identifier_number', 'like', "%{$search}%")
                  ->orWhere('tracking_code', 'like', "%{$search}%")
                  ->orWhere('kesatuan', 'like', "%{$search}%")
                  ->orWhere('keperluan', 'like', "%{$search}%");

                if (!empty($cleanSearch)) {
                    $q->orWhereRaw("REPLACE(REPLACE(REPLACE(identifier_number, ' ', ''), '-', ''), '/', '') LIKE ?", ["%{$cleanSearch}%"]);
                }

                if (Schema::hasColumn('sc_submissions', 'nomor_sc')) {
                    $q->orWhere('nomor_sc', 'like', "%{$search}%");
                }
            });
        }

        if ($request->filled('stage') && $request->stage !== 'all' && Schema::hasColumn('sc_submissions', 'current_stage')) {
            $query->where('current_stage', (int)$request->stage);
        }

        if ($request->filled('status') && $request->status !== 'all' && Schema::hasColumn('sc_submissions', 'status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('pengambilan') && $request->pengambilan !== 'all' && Schema::hasColumn('sc_submissions', 'is_taken')) {
            if ($request->pengambilan === 'sudah_diambil') {
                $query->where('is_taken', true);
            } elseif ($request->pengambilan === 'belum_diambil') {
                $query->where(function($q) {
                    $q->where('is_taken', false)->orWhereNull('is_taken');
                });
            }
        }

        // Default urutan terbaru ke terlama (id desc) agar berkas terbaru berada di paling atas
        $sort = $request->input('sort', 'terbaru');
        switch ($sort) {
            case 'terlama':
                $query->orderBy('id', 'asc');
                break;
            case 'nama_asc':
                $query->orderBy('nama', 'asc');
                break;
            case 'nama_desc':
                $query->orderBy('nama', 'desc');
                break;
            case 'nomor_sc':
                $query->orderByRaw('CASE WHEN nomor_sc IS NULL OR nomor_sc = "" THEN 1 ELSE 0 END, nomor_sc asc');
                break;
            case 'tanggal_diambil':
                $query->orderByRaw('CASE WHEN taken_at IS NULL THEN 1 ELSE 0 END, taken_at desc');
                break;
            case 'tanggal_sc':
                $query->orderByRaw('CASE WHEN tanggal_sc IS NULL THEN 1 ELSE 0 END, tanggal_sc desc');
                break;
            case 'terbaru':
            default:
                $query->orderBy('id', 'desc');
                break;
        }

        $submissions = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => ScSubmission::count(),
            'total_dinas' => ScSubmission::where(function ($q) {
                $q->where('kategori_sc', '!=', 'perusahaan')->orWhereNull('kategori_sc');
            })->where(function ($q) {
                $q->whereNull('nomor_skhpp')->orWhere('nomor_skhpp', 'not like', '%SKHPP-P%');
            })->count(),
            'total_perusahaan' => ScSubmission::where(function ($q) {
                $q->where('kategori_sc', 'perusahaan')->orWhere('nomor_skhpp', 'like', '%SKHPP-P%');
            })->count(),
            'in_progress' => Schema::hasColumn('sc_submissions', 'current_stage') ? ScSubmission::where('current_stage', '<', 10)->where('status', '!=', 'ditolak')->count() : 0,
            'at_denintel' => Schema::hasColumn('sc_submissions', 'current_stage') ? ScSubmission::where('current_stage', '<=', 5)->where('status', '!=', 'ditolak')->count() : 0,
            'at_sintel' => Schema::hasColumn('sc_submissions', 'current_stage') ? ScSubmission::whereBetween('current_stage', [6, 9])->where('status', '!=', 'ditolak')->count() : 0,
            'completed' => Schema::hasColumn('sc_submissions', 'current_stage') ? ScSubmission::where('current_stage', 10)->orWhere('status', 'selesai')->count() : 0,
            'sudah_diambil' => Schema::hasColumn('sc_submissions', 'is_taken') ? ScSubmission::where('is_taken', true)->count() : 0,
            'belum_diambil' => Schema::hasColumn('sc_submissions', 'is_taken') ? ScSubmission::where('is_taken', false)->orWhereNull('is_taken')->count() : 0,
        ];

        // Statistik berkas yang perlu ditindaklanjuti & diperbarui oleh Staf Intel
        // 1. Cakupan Tahap 6 s/d 9 (Dalam penanganan aktif Staf Intel)
        $sintelStage69Query = ScSubmission::where(function($q) {
            if (Schema::hasColumn('sc_submissions', 'current_stage')) {
                $q->whereBetween('current_stage', [6, 9]);
            }
            if (Schema::hasColumn('sc_submissions', 'status')) {
                $q->where('status', '!=', 'ditolak');
            }
        });

        $sintelDinas69 = (clone $sintelStage69Query)->where(function ($q) {
            $q->where('kategori_sc', '!=', 'perusahaan')->orWhereNull('kategori_sc');
        })->where(function ($q) {
            $q->whereNull('nomor_skhpp')->orWhere('nomor_skhpp', 'not like', '%SKHPP-P%');
        })->count();

        $sintelPerusahaan69 = (clone $sintelStage69Query)->where(function ($q) {
            $q->where('kategori_sc', 'perusahaan')->orWhere('nomor_skhpp', 'like', '%SKHPP-P%');
        })->count();

        // 2. Cakupan Tahap 5 s/d 9 (Termasuk berkas Tahap 5 SKHPP Terbit yang siap ditindaklanjuti Sintel)
        $sintelStage59Query = ScSubmission::where(function($q) {
            if (Schema::hasColumn('sc_submissions', 'current_stage')) {
                $q->whereBetween('current_stage', [5, 9]);
            }
            if (Schema::hasColumn('sc_submissions', 'status')) {
                $q->where('status', '!=', 'ditolak');
            }
        });

        $sintelDinas59 = (clone $sintelStage59Query)->where(function ($q) {
            $q->where('kategori_sc', '!=', 'perusahaan')->orWhereNull('kategori_sc');
        })->where(function ($q) {
            $q->whereNull('nomor_skhpp')->orWhere('nomor_skhpp', 'not like', '%SKHPP-P%');
        })->count();

        $sintelPerusahaan59 = (clone $sintelStage59Query)->where(function ($q) {
            $q->where('kategori_sc', 'perusahaan')->orWhere('nomor_skhpp', 'like', '%SKHPP-P%');
        })->count();

        $sintelStats = [
            'stage_6_9' => [
                'dinas' => $sintelDinas69,
                'perusahaan' => $sintelPerusahaan69,
                'total' => $sintelDinas69 + $sintelPerusahaan69,
            ],
            'stage_5_9' => [
                'dinas' => $sintelDinas59,
                'perusahaan' => $sintelPerusahaan59,
                'total' => $sintelDinas59 + $sintelPerusahaan59,
            ],
            'dinas' => $sintelDinas69,
            'perusahaan' => $sintelPerusahaan69,
            'total' => $sintelDinas69 + $sintelPerusahaan69,
        ];

        // Daftar Personel Staf Intel & Personel Lainnya untuk Pilihan Penerima Notifikasi
        $sintelUsers = User::query()
            ->where(function ($q) {
                $q->where('role', 'anggotasintel')
                  ->orWhere('role', 'like', '%sintel%');
            })
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'pangkat', 'nrp', 'phone', 'role']);

        $otherUsers = User::query()
            ->where(function ($q) {
                $q->where('role', '!=', 'anggotasintel')
                  ->where('role', 'not like', '%sintel%');
            })
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'pangkat', 'nrp', 'phone', 'role']);

        return Inertia::render('ScSubmission/Index', [
            'submissions' => $submissions,
            'stats' => $stats,
            'sintelStats' => $sintelStats,
            'sintelUsers' => $sintelUsers,
            'otherUsers' => $otherUsers,
            'filters' => $request->only(['search', 'stage', 'status', 'pengambilan', 'sort', 'kategori']),
            'stages' => array_values(ScSubmission::STAGES),
        ]);
    }

    /**
     * Mendaftarkan Pengajuan SC Baru secara Manual oleh Petugas (Termasuk Upload PDF SKHPP Basah)
     */
    public function store(Request $request)
    {
        // Jalankan self-healing skema basis data sebelum DDL/DML transaksi
        ScSubmission::ensureSchema();

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'kategori_sc' => 'nullable|string|in:dinas,perusahaan',
            'pangkat_korps' => 'nullable|string|max:100',
            'identifier_type' => 'required|in:nrp,nip,nik,nim',
            'identifier_number' => 'required|string|max:50',
            'kesatuan' => 'nullable|string|max:255',
            'jabatan' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'keperluan' => 'nullable|string|max:255',
            'current_stage' => 'nullable|integer|min:1|max:10',
            'status' => 'nullable|string|in:proses,selesai,perbaikan,ditolak',
            'catatan_petugas' => 'nullable|string|max:1000',
            'nomor_surat_rh' => 'nullable|string|max:100',
            'nomor_skhpp' => 'nullable|string|max:100',
            'nomor_sc' => 'nullable|string|max:100',
            'file_skhpp' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        try {
            DB::beginTransaction();

            $stage = (int)($validated['current_stage'] ?? 1);
            $stageInfo = ScSubmission::STAGES[$stage] ?? ScSubmission::STAGES[1];

            // Format kode tracking: SC-YYYYMMDD-XXXXX
            $trackingCode = 'SC-' . date('Ymd') . '-' . strtoupper(Str::random(5));

            // Penanganan Unggah PDF SKHPP Tanda Tangan Basah
            $fileSkhppPath = null;
            if ($request->hasFile('file_skhpp')) {
                $fileSkhppPath = $request->file('file_skhpp')->store('sc_documents', 'public');
            }

            $allFields = [
                'kategori_sc' => $validated['kategori_sc'] ?? 'dinas',
                'tracking_code' => $trackingCode,
                'nomor_resi' => $trackingCode,
                'nama' => $validated['nama'],
                'identifier_type' => $validated['identifier_type'],
                'identifier_number' => trim($validated['identifier_number']),
                'current_stage' => $stage,
                'status' => $stage === 10 ? 'selesai' : ($validated['status'] ?? 'proses'),
                'pangkat_korps' => $validated['pangkat_korps'] ?? null,
                'kesatuan' => $validated['kesatuan'] ?? null,
                'jabatan' => $validated['jabatan'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'keperluan' => $validated['keperluan'] ?? null,
                'catatan_petugas' => $validated['catatan_petugas'] ?? null,
                'nomor_surat_rh' => $validated['nomor_surat_rh'] ?? null,
                'nomor_skhpp' => $validated['nomor_skhpp'] ?? null,
                'nomor_sc' => $validated['nomor_sc'] ?? null,
                'file_skhpp' => $fileSkhppPath,
                'created_by' => Auth::id(),
            ];

            $submissionData = [];
            foreach ($allFields as $key => $val) {
                if ($val !== null && Schema::hasColumn('sc_submissions', $key)) {
                    $submissionData[$key] = $val;
                }
            }

            // Sanitasi cerdas tipe data dinamis & pengisian nilai bawaan otomatis
            try {
                $dbColumns = DB::select("SHOW COLUMNS FROM sc_submissions");
                foreach ($dbColumns as $col) {
                    $name = $col->Field ?? $col->field ?? null;
                    if (!$name || in_array($name, ['id', 'created_at', 'updated_at'])) {
                        continue;
                    }

                    $type = strtolower($col->Type ?? $col->type ?? '');
                    $isNull = ($col->Null ?? $col->null ?? 'YES') === 'NO';
                    $hasDefault = ($col->Default ?? $col->default ?? null) !== null;

                    // 1. Jika nilai terisi di submissionData, pastikan tipe datanya cocok dengan tipe kolom MySQL
                    if (array_key_exists($name, $submissionData)) {
                        $val = $submissionData[$name];
                        if ($val !== null) {
                            if (str_contains($type, 'int') && !is_numeric($val)) {
                                if ($name === 'status') {
                                    $submissionData[$name] = match ($val) {
                                        'selesai' => 2,
                                        'perbaikan' => 3,
                                        'ditolak' => 4,
                                        default => 1,
                                    };
                                } else {
                                    $submissionData[$name] = (int)$val;
                                }
                            }
                        }
                    } else {
                        // 2. Jika nilai belum terisi dan kolom MySQL berstatus NOT NULL tanpa default
                        if ($isNull && !$hasDefault) {
                            if (str_contains($type, 'int') || str_contains($type, 'decimal') || str_contains($type, 'float')) {
                                $submissionData[$name] = ($name === 'status') ? 1 : 0;
                            } elseif (str_contains($type, 'date') || str_contains($type, 'time')) {
                                $submissionData[$name] = now();
                            } else {
                                if ($name === 'nomor_resi') {
                                    $submissionData[$name] = $trackingCode;
                                } elseif ($name === 'tipe_permohonan') {
                                    $submissionData[$name] = 'baru';
                                } else {
                                    $submissionData[$name] = '-';
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Type-safety sanitizer error: ' . $e->getMessage());
            }

            $submission = ScSubmission::create($submissionData);

            // Catat riwayat log inisialisasi jika tabel logs ada
            if (Schema::hasTable('sc_submission_logs')) {
                $initialNote = $validated['catatan_petugas'] ?? null;
                if (empty($initialNote)) {
                    if ($stage >= 5 && !empty($validated['nomor_skhpp'])) {
                        $initialNote = "Pendaftaran berkas langsung pada Tahap {$stage}: {$stageInfo['title']} (No. SKHPP: {$validated['nomor_skhpp']}).";
                    } else {
                        $initialNote = 'Pendaftaran pengajuan berkas Security Clearance di sistem.';
                    }
                }
                ScSubmissionLog::create([
                    'sc_submission_id' => $submission->id,
                    'stage' => $stage,
                    'stage_title' => $stageInfo['title'],
                    'notes' => $initialNote,
                    'user_id' => Auth::id(),
                    'user_name' => Auth::user()->name ?? 'Petugas Kedinasan',
                ]);
            }

            DB::commit();

            // Kirim Notifikasi WhatsApp ke Pemohon (Hanya diawal saat pendaftaran, tidak berlaku saat update status)
            $targetPhone = $submission->phone ?? ($validated['phone'] ?? null);
            if (empty($targetPhone)) {
                $personel = User::where('nrp', $submission->identifier_number)->first();
                $targetPhone = $personel?->phone;
            }

            if (!empty($targetPhone)) {
                try {
                    $pangkatNama = trim(($submission->pangkat_korps ? $submission->pangkat_korps . ' ' : '') . $submission->nama);
                    $identitasLabel = strtoupper($submission->identifier_type ?? 'NRP');
                    $trackingUrl = route('tracking-sc.index');

                    $waMessage = "*PEMBERITAHUAN PENGAJUAN SECURITY CLEARANCE (SC)*\n" .
                                 "*DENINTEL KOARMADA 2*\n\n" .
                                 "Yth. *{$pangkatNama}*\n" .
                                 "{$identitasLabel}: {$submission->identifier_number}\n\n" .
                                 "Pengajuan berkas Security Clearance (SC) Anda telah berhasil didaftarkan ke dalam sistem SINDEN dengan rincian:\n\n" .
                                 "- *Kode Pelacakan:* {$trackingCode}\n" .
                                 "- *Keperluan:* " . ($submission->keperluan ?: 'Kedinasan') . "\n" .
                                 "- *Satuan/Kesatuan:* " . ($submission->kesatuan ?: '-') . "\n" .
                                 "- *Tahap Saat Ini:* Tahap {$stage}: {$stageInfo['title']}\n\n" .
                                 "Anda dapat memantau posisi dan perkembangan berkas secara transparan tanpa harus login melalui tautan resmi:\n" .
                                 "{$trackingUrl}\n\n" .
                                 "*(Cukup masukkan {$identitasLabel} Anda pada halaman tersebut untuk melihat posisi berkas)*.\n\n" .
                                 "Demikian pemberitahuan ini disampaikan. Terima kasih.";

                    WhatsappService::sendMessage($targetPhone, $waMessage);
                } catch (\Throwable $e) {
                    Log::warning("Gagal mengirim notifikasi WA pengajuan SC: " . $e->getMessage());
                }
            }

            return redirect()->back()->with('success', "Lapor! Pengajuan Security Clearance atas nama {$submission->nama} berhasil didaftarkan dengan Kode Tracking {$trackingCode}.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Gagal mendaftarkan pengajuan SC: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return redirect()->back()->withErrors(['error' => 'Gagal mendaftarkan berkas: ' . $e->getMessage()]);
        }
    }

    /**
     * Memperbarui Tahapan Operasional Berkas SC & Unggah Dokumen Petinjau
     */
    public function updateStage(Request $request, $id)
    {
        $submission = ScSubmission::findOrFail($id);

        $validated = $request->validate([
            'stage' => 'required|integer|min:1|max:10',
            'notes' => 'nullable|string|max:1000',
            'status' => 'nullable|in:proses,selesai,perbaikan,ditolak',
            'nomor_skhpp' => 'nullable|string|max:100',
            'nomor_sc' => 'nullable|string|max:100',
            'file_skhpp' => 'nullable|file|mimes:pdf|max:10240',
            'file_sc_preview' => 'nullable|file|mimes:pdf|max:15360',
        ]);

        $newStage = (int)$validated['stage'];
        $stageInfo = ScSubmission::STAGES[$newStage] ?? ScSubmission::STAGES[1];

        $status = $validated['status'] ?? $submission->status;
        if ($newStage === 10) {
            $status = 'selesai';
        }

        $updateData = [
            'current_stage' => $newStage,
            'status' => $status,
        ];

        if (!empty($validated['notes'])) {
            $updateData['catatan_petugas'] = $validated['notes'];
        }

        if (isset($validated['nomor_skhpp'])) {
            $updateData['nomor_skhpp'] = $validated['nomor_skhpp'];
        }

        if (isset($validated['nomor_sc'])) {
            $updateData['nomor_sc'] = $validated['nomor_sc'];
        }

        // Unggah PDF SKHPP Tanda Tangan Basah
        if ($request->hasFile('file_skhpp')) {
            if ($submission->file_skhpp && Storage::disk('public')->exists($submission->file_skhpp)) {
                Storage::disk('public')->delete($submission->file_skhpp);
            }
            $updateData['file_skhpp'] = $request->file('file_skhpp')->store('sc_documents', 'public');
        }

        // Unggah Softfile PDF Hasil SC oleh Petugas Sintel (Masa Berlaku 2x24 Jam)
        if ($request->hasFile('file_sc_preview')) {
            if ($submission->file_sc_preview && Storage::disk('public')->exists($submission->file_sc_preview)) {
                Storage::disk('public')->delete($submission->file_sc_preview);
            }
            $uploadedPath = $request->file('file_sc_preview')->store('sc_documents', 'public');

            // Terapkan stempel fisik watermark PETINJAU secara permanen ke dalam PDF
            $watermarkedPath = \App\Services\PdfWatermarkService::applyWatermark($uploadedPath);
            $updateData['file_sc_preview'] = $watermarkedPath ?: $uploadedPath;
            $updateData['sc_preview_uploaded_at'] = now();
            $updateData['sc_preview_expired_at'] = null;
        }

        $submission->update($updateData);

        // Catat ke riwayat log
        $logNote = $validated['notes'] ?: "Berkas berhasil dimajukan ke Tahap {$newStage}: {$stageInfo['title']}.";
        if ($request->hasFile('file_sc_preview')) {
            $logNote .= " (Softfile Petinjau SC berhasil diunggah dengan masa aktif 2x24 jam).";
        }
        if ($request->hasFile('file_skhpp')) {
            $logNote .= " (Berkas SKHPP TTD Basah berhasil diunggah).";
        }

        if (Schema::hasTable('sc_submission_logs')) {
            ScSubmissionLog::create([
                'sc_submission_id' => $submission->id,
                'stage' => $newStage,
                'stage_title' => $stageInfo['title'],
                'notes' => $logNote,
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name ?? 'Petugas Kedinasan',
            ]);
        }

        // Kirim Notifikasi WhatsApp ke Pemohon saat Tahapan Diperbarui
        $targetPhone = $submission->phone;
        if (empty($targetPhone)) {
            $personel = User::where('nrp', $submission->identifier_number)->first();
            $targetPhone = $personel?->phone;
        }

        if (!empty($targetPhone)) {
            try {
                $pangkatNama = trim(($submission->pangkat_korps ? $submission->pangkat_korps . ' ' : '') . $submission->nama);
                $identitasLabel = strtoupper($submission->identifier_type ?? 'NRP');
                $trackingUrl = route('tracking-sc.index');

                $waMessage = "*UPDATE TAHAPAN SECURITY CLEARANCE (SC)*\n" .
                             "*DENINTEL KOARMADA 2*\n\n" .
                             "Halo *{$pangkatNama}*,\n\n" .
                             "Status telah di update, silahkan check di link menggunakan NRP,NIP,NIK atau NIM anda.\n\n" .
                             "- *Tahap Baru:* Tahap {$newStage}: {$stageInfo['title']}\n" .
                             "- *Status:* " . strtoupper($status) . "\n" .
                             (!empty($validated['notes']) ? "- *Catatan Petugas:* {$validated['notes']}\n" : "") .
                             "- *Tautan Pengecekan:* {$trackingUrl}\n\n" .
                             "*(Masukkan {$identitasLabel}: {$submission->identifier_number} pada formulir pelacakan)*\n\n" .
                             "Demikian pemberitahuan ini disampaikan. Terima kasih.";

                WhatsappService::sendMessage($targetPhone, $waMessage);
            } catch (\Throwable $e) {
                Log::warning("Gagal mengirim notifikasi WA update tahapan SC: " . $e->getMessage());
            }
        }

        return redirect()->back()->with('success', "Lapor! Berkas {$submission->nama} berhasil diperbarui ke Tahap {$newStage}: {$stageInfo['title']}.");
    }

    /**
     * Memperbarui Data Detail Pemohon
     */
    public function update(Request $request, $id)
    {
        $submission = ScSubmission::findOrFail($id);

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'kategori_sc' => 'nullable|string|in:dinas,perusahaan',
            'pangkat_korps' => 'nullable|string|max:100',
            'identifier_type' => 'required|in:nrp,nip,nik,nim',
            'identifier_number' => 'required|string|max:50',
            'kesatuan' => 'nullable|string|max:255',
            'jabatan' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'keperluan' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:proses,selesai,perbaikan,ditolak',
            'nomor_surat_rh' => 'nullable|string|max:100',
            'nomor_skhpp' => 'nullable|string|max:100',
            'nomor_sc' => 'nullable|string|max:100',
            'file_skhpp' => 'nullable|file|mimes:pdf|max:10240',
            'file_sc_preview' => 'nullable|file|mimes:pdf|max:15360',
        ]);

        if ($request->hasFile('file_skhpp')) {
            if ($submission->file_skhpp && Storage::disk('public')->exists($submission->file_skhpp)) {
                Storage::disk('public')->delete($submission->file_skhpp);
            }
            $validated['file_skhpp'] = $request->file('file_skhpp')->store('sc_documents', 'public');
        }

        if ($request->hasFile('file_sc_preview')) {
            if ($submission->file_sc_preview && Storage::disk('public')->exists($submission->file_sc_preview)) {
                Storage::disk('public')->delete($submission->file_sc_preview);
            }
            $validated['file_sc_preview'] = $request->file('file_sc_preview')->store('sc_documents', 'public');
            $validated['sc_preview_uploaded_at'] = now();
            $validated['sc_preview_expired_at'] = null;
        }

        $submission->update($validated);

        return redirect()->back()->with('success', "Lapor! Perubahan data pemohon {$submission->nama} berhasil disimpan.");
    }

    /**
     * Menghapus Berkas Pengajuan (Khusus Admin / Petugas Berwenang)
     */
    public function destroy($id)
    {
        $submission = ScSubmission::findOrFail($id);
        $nama = $submission->nama;

        if ($submission->file_skhpp && Storage::disk('public')->exists($submission->file_skhpp)) {
            Storage::disk('public')->delete($submission->file_skhpp);
        }
        if ($submission->file_sc_preview) {
            if (Storage::disk('public')->exists($submission->file_sc_preview)) {
                Storage::disk('public')->delete($submission->file_sc_preview);
            }
            $wmPath = 'sc_documents/wm_' . basename($submission->file_sc_preview);
            if (Storage::disk('public')->exists($wmPath)) {
                Storage::disk('public')->delete($wmPath);
            }
        }

        $submission->delete();

        return redirect()->back()->with('success', "Lapor! Data pengajuan SC atas nama {$nama} telah berhasil dihapus.");
    }

    /**
     * Sinkronisasi Seluruh SKHPP yang Telah Disetujui / Terbit ke Sistem Tracking SC
     */
    public function syncFromApprovedSkhpp()
    {
        $approvedSkhpps = Skhpp::where('status', 'approved')->get();
        $syncedCount = 0;

        foreach ($approvedSkhpps as $skhpp) {
            $exists = ScSubmission::where('skhpp_id', $skhpp->id)->first();
            if (!$exists) {
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

                $trackingCode = 'SC-' . date('Ymd') . '-' . strtoupper(Str::random(5));
                $kategoriSc = ($skhpp->kategori_personel === 'perusahaan' || str_contains($skhpp->nomor_skhpp ?? '', 'SKHPP-P')) ? 'perusahaan' : 'dinas';
                $scSub = ScSubmission::create([
                    'skhpp_id' => $skhpp->id,
                    'kategori_sc' => $kategoriSc,
                    'tracking_code' => $trackingCode,
                    'nama' => $skhpp->nama,
                    'pangkat_korps' => $skhpp->pangkat_korps_nrp,
                    'identifier_type' => $identifierType,
                    'identifier_number' => $identifierNum,
                    'kesatuan' => $skhpp->alamat ?: 'Koarmada 2',
                    'jabatan' => $skhpp->jabatan_pekerjaan,
                    'keperluan' => $skhpp->peruntukan,
                    'nomor_skhpp' => $skhpp->nomor_skhpp,
                    'current_stage' => 5, // TAHAP 5: SKHPP TERBIT (Tahap 1, 2, 3, 4 terlewati otomatis)
                    'status' => 'proses',
                    'catatan_petugas' => "Hasil sinkronisasi otomatis dari Penerbitan SKHPP No. {$skhpp->nomor_skhpp}. Tahap 1 s/d 4 terlewati.",
                    'created_by' => Auth::id(),
                ]);

                $initialLogs = [
                    ['stage' => 1, 'stage_title' => 'Pengisian RH', 'notes' => 'Pengisian Riwayat Hidup telah diproses terintegrasi pada penerbitan SKHPP.'],
                    ['stage' => 2, 'stage_title' => 'Pengecekan Kelengkapan Dokumen', 'notes' => 'Pemeriksaan berkas dan kelengkapan dokumen telah diverifikasi oleh operator Denintel.'],
                    ['stage' => 3, 'stage_title' => 'Proses Cetak SKHPP', 'notes' => 'Proses administrasi dan pencetakan lembar SKHPP selesai.'],
                    ['stage' => 4, 'stage_title' => 'Menunggu TTD Komandan Denintel', 'notes' => 'Persetujuan dan tanda tangan dinas elektronik (TTE) Komandan Denintel telah disahkan.'],
                    ['stage' => 5, 'stage_title' => 'SKHPP Terbit', 'notes' => "SKHPP resmi disahkan dan diterbitkan dengan nomor {$skhpp->nomor_skhpp}. Berkas beralih ke Staf Intelijen."],
                ];

                foreach ($initialLogs as $logItem) {
                    ScSubmissionLog::create([
                        'sc_submission_id' => $scSub->id,
                        'stage' => $logItem['stage'],
                        'stage_title' => $logItem['stage_title'],
                        'notes' => $logItem['notes'],
                        'user_id' => Auth::id(),
                        'user_name' => Auth::user()->name ?? 'Petugas Kedinasan',
                    ]);
                }
                $syncedCount++;
            }
        }

        return redirect()->back()->with('success', "Lapor! Sebanyak {$syncedCount} berkas SKHPP yang telah terbit berhasil disinkronkan ke sistem pelacakan SC.");
    }

    /**
     * Cetak Laporan PDF Buku Agenda Pengambilan SC
     * Format sesuai buku agenda surat dinas Denintel Koarmada 2
     */
    public function exportPdf(Request $request)
    {
        ScSubmission::ensureSchema();

        $relations = ['creator'];
        if (Schema::hasTable('sc_submission_logs')) {
            $relations[] = 'logs';
        }
        if (Schema::hasColumn('sc_submissions', 'skhpp_id')) {
            $relations[] = 'skhpp';
        }

        $query = ScSubmission::with($relations);

        // Filter Kategori SC: Dinas (SKHPP-D) vs Perusahaan (SKHPP-P)
        if ($request->filled('kategori') && $request->kategori !== 'all') {
            if ($request->kategori === 'perusahaan') {
                $query->where(function ($q) {
                    $q->where('kategori_sc', 'perusahaan')
                      ->orWhere('nomor_skhpp', 'like', '%SKHPP-P%');
                });
            } elseif ($request->kategori === 'dinas') {
                $query->where(function ($q) {
                    $q->where('kategori_sc', '!=', 'perusahaan')->orWhereNull('kategori_sc');
                })->where(function ($q) {
                    $q->whereNull('nomor_skhpp')->orWhere('nomor_skhpp', 'not like', '%SKHPP-P%');
                });
            }
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $cleanSearch = preg_replace('/[^A-Za-z0-9]/', '', $search);
            $query->where(function ($q) use ($search, $cleanSearch) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('identifier_number', 'like', "%{$search}%")
                  ->orWhere('tracking_code', 'like', "%{$search}%")
                  ->orWhere('kesatuan', 'like', "%{$search}%")
                  ->orWhere('keperluan', 'like', "%{$search}%");

                if (!empty($cleanSearch)) {
                    $q->orWhereRaw("REPLACE(REPLACE(REPLACE(identifier_number, ' ', ''), '-', ''), '/', '') LIKE ?", ["%{$cleanSearch}%"]);
                }

                if (Schema::hasColumn('sc_submissions', 'nomor_sc')) {
                    $q->orWhere('nomor_sc', 'like', "%{$search}%");
                }
            });
        }

        if ($request->filled('stage') && $request->stage !== 'all' && Schema::hasColumn('sc_submissions', 'current_stage')) {
            $query->where('current_stage', (int)$request->stage);
        }

        if ($request->filled('status') && $request->status !== 'all' && Schema::hasColumn('sc_submissions', 'status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('pengambilan') && $request->pengambilan !== 'all' && Schema::hasColumn('sc_submissions', 'is_taken')) {
            if ($request->pengambilan === 'sudah_diambil') {
                $query->where('is_taken', true);
            } elseif ($request->pengambilan === 'belum_diambil') {
                $query->where(function($q) {
                    $q->where('is_taken', false)->orWhereNull('is_taken');
                });
            }
        }

        // Default urutan terbaru ke terlama (id desc) agar berkas terbaru di urutan atas
        $sort = $request->input('sort', 'terbaru');
        switch ($sort) {
            case 'terlama':
                $query->orderBy('id', 'asc');
                break;
            case 'nama_asc':
                $query->orderBy('nama', 'asc');
                break;
            case 'nama_desc':
                $query->orderBy('nama', 'desc');
                break;
            case 'nomor_sc':
                $query->orderByRaw('CASE WHEN nomor_sc IS NULL OR nomor_sc = "" THEN 1 ELSE 0 END, nomor_sc asc');
                break;
            case 'tanggal_diambil':
                $query->orderByRaw('CASE WHEN taken_at IS NULL THEN 1 ELSE 0 END, taken_at desc');
                break;
            case 'tanggal_sc':
                $query->orderByRaw('CASE WHEN tanggal_sc IS NULL THEN 1 ELSE 0 END, tanggal_sc desc');
                break;
            case 'terbaru':
            default:
                $query->orderBy('id', 'desc');
                break;
        }

        $submissions = $query->get();

        $filterPengambilanText = match ($request->pengambilan) {
            'sudah_diambil' => 'Sudah Diambil',
            'belum_diambil' => 'Belum Diambil',
            default => 'Semua Status Pengambilan',
        };

        $filterKategoriText = match ($request->kategori) {
            'dinas' => 'SC DINAS (SKHPP-D)',
            'perusahaan' => 'SC PERUSAHAAN (SKHPP-P)',
            default => 'SEMUA KATEGORI (DINAS & PERUSAHAAN)',
        };

        $periodeText = null;
        if ($request->filled('search')) {
            $periodeText = "Pencarian: " . $request->search;
        }

        $pdf = Pdf::loadView('pdf.sc_submission_agenda', [
            'submissions' => $submissions,
            'filter_pengambilan' => $filterPengambilanText,
            'filter_kategori' => $filterKategoriText,
            'kategori' => $request->kategori ?? 'all',
            'periode_text' => $periodeText,
            'filters' => [
                'search' => $request->search,
                'pengambilan' => $request->pengambilan,
                'kategori' => $request->kategori,
                'stage' => $request->stage,
                'status' => $request->status,
                'sort' => $sort,
            ],
            'printDate' => now()->locale('id')->isoFormat('D MMMM Y'),
        ])->setPaper('a4', 'portrait');

        $kategoriSlug = match ($request->kategori) {
            'dinas' => 'DINAS_SKHPP_D_',
            'perusahaan' => 'PERUSAHAAN_SKHPP_P_',
            default => '',
        };
        $filename = 'BUKU_AGENDA_PENGAMBILAN_SC_' . $kategoriSlug . date('Ymd_His') . '.pdf';
        return $pdf->stream($filename);
    }

    /**
     * Memperbarui status pengambilan SC (Sudah Diambil / Belum Diambil)
     * Beserta tanggal SC, tanggal diambil, nomor SC, dan pengambil
     */
    public function toggleTaken(Request $request, $id)
    {
        ScSubmission::ensureSchema();

        $submission = ScSubmission::findOrFail($id);

        $validated = $request->validate([
            'is_taken' => 'required|boolean',
            'taken_at' => 'nullable|date',
            'tanggal_sc' => 'nullable|date',
            'nomor_sc' => 'nullable|string|max:100',
            'taken_by' => 'nullable|string|max:255',
            'catatan' => 'nullable|string|max:500',
        ]);

        $isTaken = (bool)$validated['is_taken'];
        $takenAt = $isTaken ? ($validated['taken_at'] ?? now()) : null;
        $takenBy = $isTaken ? ($validated['taken_by'] ?? $submission->nama) : null;

        $submission->is_taken = $isTaken;
        $submission->taken_at = $takenAt;
        if (isset($validated['tanggal_sc'])) {
            $submission->tanggal_sc = $validated['tanggal_sc'];
        }
        if (isset($validated['nomor_sc']) && !empty($validated['nomor_sc'])) {
            $submission->nomor_sc = $validated['nomor_sc'];
        }
        $submission->taken_by = $takenBy;
        $submission->save();

        // Catat ke riwayat log
        $statusText = $isTaken ? 'Sudah Diambil' : 'Belum Diambil / Batal Diambil';
        $logNotes = "Status pengambilan SC diperbarui menjadi: {$statusText}.";
        if ($isTaken) {
            $logNotes .= " Pengambil: " . ($submission->taken_by ?: $submission->nama);
            if ($submission->taken_at) {
                $logNotes .= " pada " . \Carbon\Carbon::parse($submission->taken_at)->locale('id')->isoFormat('D MMMM Y, HH:mm') . " WIB.";
            }
        }
        if (!empty($validated['catatan'])) {
            $logNotes .= " Keterangan: " . $validated['catatan'];
        }

        if (Schema::hasTable('sc_submission_logs')) {
            ScSubmissionLog::create([
                'sc_submission_id' => $submission->id,
                'stage' => $submission->current_stage,
                'stage_title' => 'Pencatatan Pengambilan SC',
                'notes' => $logNotes,
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name ?? 'Petugas Kedinasan',
            ]);
        }

        return redirect()->back()->with('success', "Lapor! Status pengambilan berkas SC atas nama {$submission->nama} berhasil diperbarui.");
    }

    /**
     * Kirim Notifikasi Rekap Pengajuan SC ke WhatsApp Staf Intel
     */
    public function sendSintelNotification(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|integer',
            'phone' => 'required|string|max:50',
            'pangkat' => 'nullable|string|max:100',
            'nama' => 'required|string|max:255',
            'nrp' => 'nullable|string|max:50',
            'scope' => 'nullable|string|in:stage_6_9,stage_5_9',
            'catatan_tambahan' => 'nullable|string|max:1000',
        ]);

        $scope = $validated['scope'] ?? 'stage_6_9';
        $minStage = ($scope === 'stage_5_9') ? 5 : 6;

        $baseQuery = ScSubmission::where(function($q) use ($minStage) {
            if (Schema::hasColumn('sc_submissions', 'current_stage')) {
                $q->whereBetween('current_stage', [$minStage, 9]);
            }
            if (Schema::hasColumn('sc_submissions', 'status')) {
                $q->where('status', '!=', 'ditolak');
            }
        });

        $dinasCount = (clone $baseQuery)->where(function ($q) {
            $q->where('kategori_sc', '!=', 'perusahaan')->orWhereNull('kategori_sc');
        })->where(function ($q) {
            $q->whereNull('nomor_skhpp')->orWhere('nomor_skhpp', 'not like', '%SKHPP-P%');
        })->count();

        $perusahaanCount = (clone $baseQuery)->where(function ($q) {
            $q->where('kategori_sc', 'perusahaan')->orWhere('nomor_skhpp', 'like', '%SKHPP-P%');
        })->count();

        $totalCount = $dinasCount + $perusahaanCount;

        // Susun baris sapaan: Yth [pangkat][Nama][Nrp]
        $pangkatStr = trim($validated['pangkat'] ?? '');
        $namaStr = trim($validated['nama'] ?? '');
        $nrpStr = trim($validated['nrp'] ?? '');

        $recipientParts = [];
        if (!empty($pangkatStr)) {
            $recipientParts[] = $pangkatStr;
        }
        if (!empty($namaStr)) {
            $recipientParts[] = $namaStr;
        }
        if (!empty($nrpStr)) {
            $recipientParts[] = "NRP {$nrpStr}";
        }
        $recipientLine = "Yth. " . (empty($recipientParts) ? 'Personel Staf Intelijen' : implode(' ', $recipientParts));

        $trackingUrl = route('sc-submissions.index');

        $message = "*PEMBERITAHUAN BERKAS SECURITY CLEARANCE (SC)*\n" .
                   "*DETASEMEN INTELIJEN KOARMADA 2*\n\n" .
                   "{$recipientLine}\n\n" .
                   "Disampaikan informasi bahwa saat ini terdapat berkas pengajuan Security Clearance (SC) yang perlu ditindaklanjuti dan diperbarui oleh Staf Intelijen:\n\n" .
                   "- Berkas Dinas: {$dinasCount} berkas\n" .
                   "- Berkas Perusahaan: {$perusahaanCount} berkas\n" .
                   "- Total Berkas: {$totalCount} berkas\n\n";

        if (!empty($validated['catatan_tambahan'])) {
            $message .= "Catatan: " . trim($validated['catatan_tambahan']) . "\n\n";
        }

        $message .= "Mohon perkenan untuk melakukan pembaruan berkas melalui tautan sistem SINDEN:\n" .
                   "{$trackingUrl}\n" .
                   "*(Silakan login menggunakan NRP dan Password Anda)*\n\n" .
                   "Demikian pemberitahuan ini disampaikan. Terima kasih.";

        $targetPhone = trim($validated['phone']);
        WhatsappService::sendMessage($targetPhone, $message);

        Log::info("Notifikasi SC Sintel dikirim ke {$recipientLine} ({$targetPhone}) oleh " . (Auth::user()->name ?? 'Petugas'));

        return redirect()->back()->with('success', "Lapor! Notifikasi pembaruan berkas SC berhasil dikirim ke WhatsApp {$recipientLine} ({$targetPhone}).");
    }
}
