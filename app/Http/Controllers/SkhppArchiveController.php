<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SkhppArchive;
use App\Services\SkhppNumberService;
use App\Services\SkhppPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Arsip & Nomor SKHPP — terpisah dari Agenda Surat / Arsip Surat Masuk-Keluar.
 *
 * Alur:
 *  1. Generate SKHPP dari menu Penerbitan SKHPP  -> otomatis masuk arsip
 *     (status "Menunggu Upload PDF" selama menunggu TTE/TTD Komandan).
 *  2. TTE Komandan terbit -> nomor terisi & PDF resmi langsung tersimpan.
 *  3. SKHPP manual (dibuat di luar SINDEN) -> minta nomor di sini; nomor
 *     melanjutkan urutan SKHPP-D / SKHPP-P yang sama, lalu unggah PDF-nya.
 */
class SkhppArchiveController extends Controller
{
    public function index(Request $request)
    {
        $query = SkhppArchive::with(['skhpp:id,status,nama,catatan_revisi', 'creator:id,name', 'uploader:id,name', 'subCategory:id,name'])
            ->orderByRaw('CASE WHEN tahun IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('tahun')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $s = trim((string) $request->search);
            $query->where(function ($q) use ($s) {
                $q->where('nomor_skhpp', 'like', "%{$s}%")
                    ->orWhere('nama', 'like', "%{$s}%")
                    ->orWhere('perihal', 'like', "%{$s}%")
                    ->orWhere('peruntukan', 'like', "%{$s}%");
            });
        }
        if (in_array($request->jenis, ['D', 'P'], true)) {
            $query->where('jenis', $request->jenis);
        }
        if (array_key_exists((string) $request->status, SkhppArchive::STATUS_LABELS)) {
            $query->where('status', $request->status);
        }
        if (in_array($request->sumber, ['sinden', 'manual'], true)) {
            $query->where('sumber', $request->sumber);
        }
        if ($request->filled('tahun') && ctype_digit((string) $request->tahun)) {
            $query->where('tahun', (int) $request->tahun);
        }
        if ($request->filled('sub_category_id') && ctype_digit((string) $request->sub_category_id)) {
            $query->where('sub_category_id', (int) $request->sub_category_id);
        }

        $year = (int) date('Y');
        $summary = [];
        foreach (['D', 'P'] as $jenis) {
            $summary[$jenis] = [
                'last' => SkhppNumberService::lastSequence($jenis, $year),
                'next' => SkhppNumberService::nextSequence($jenis, $year),
                'menunggu' => SkhppArchive::where('jenis', $jenis)->where('status', SkhppArchive::STATUS_MENUNGGU_PDF)->count(),
                'tersimpan' => SkhppArchive::where('jenis', $jenis)->where('status', SkhppArchive::STATUS_TERSIMPAN)->count(),
            ];
        }

        return Inertia::render('SkhppArchive/Index', [
            'archives' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'jenis', 'status', 'sumber', 'tahun', 'sub_category_id']),
            'summary' => $summary,
            // Sub-kategori SKHPP-D / SKHPP-P (Dik, Nikah, ...) dari menu Kategori Surat
            'subCategories' => SkhppNumberService::subCategories(),
            'year' => $year,
            'years' => SkhppArchive::whereNotNull('tahun')->distinct()->orderByDesc('tahun')->pluck('tahun'),
            'statusLabels' => SkhppArchive::STATUS_LABELS,
            'canManage' => $request->user()->isAdmin(),
        ]);
    }

    /** Pratinjau nomor berikutnya (belum dipesan). */
    public function nextNumber(Request $request)
    {
        $data = $request->validate([
            'jenis' => 'required|in:D,P',
            'tanggal' => 'nullable|date',
        ]);

        $date = isset($data['tanggal']) ? \Carbon\Carbon::parse($data['tanggal']) : now();
        $year = (int) $date->format('Y');
        $seq = SkhppNumberService::nextSequence($data['jenis'], $year);

        return response()->json([
            'sequence' => $seq,
            'formatted' => SkhppNumberService::format(SkhppNumberService::PRIORITY, $seq, SkhppNumberService::romanMonth((int) $date->format('n')), $year),
        ]);
    }

    /** Minta nomor SKHPP manual (SKHPP dibuat di luar menu SINDEN). */
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'jenis' => 'required|in:D,P',
            'sub_category_id' => 'nullable|integer',
            'tanggal' => 'required|date',
            'nama' => 'nullable|string|max:255',
            'perihal' => 'required|string|max:255',
            'peruntukan' => 'nullable|string|max:2000',
            'keterangan' => 'nullable|string|max:2000',
            // Angka, boleh diikuti huruf sisipan (cth: 12 atau 12a)
            'custom_nomor_urut' => ['nullable', SkhppNumberService::SEQUENCE_RULE],
            'file' => 'nullable|file|mimes:pdf|max:20480',
        ], ['custom_nomor_urut.regex' => 'Nomor urut berupa angka, boleh diikuti huruf sisipan (cth: 12 atau 12a).']);

        $date = \Carbon\Carbon::parse($data['tanggal']);
        $year = (int) $date->format('Y');
        $roman = SkhppNumberService::romanMonth((int) $date->format('n'));
        $jenis = $data['jenis'];

        $subId = !empty($data['sub_category_id']) ? (int) $data['sub_category_id'] : null;
        if (!SkhppNumberService::subCategoryBelongs($subId, $jenis)) {
            return back()->with('error', "Sub-kategori tidak termasuk SKHPP-{$jenis}.");
        }

        $custom = null;
        if (!empty($data['custom_nomor_urut'])) {
            $custom = SkhppNumberService::parse($data['custom_nomor_urut']);
            if (!$custom) {
                return back()->with('error', 'Nomor urut SKHPP tidak valid.');
            }
            $label = SkhppNumberService::label(...$custom);

            // Personel non-Admin hanya boleh nomor sisipan (berhuruf) dari nomor yang sudah terbit,
            // agar urutan otomatis tidak bisa diloncati.
            if (!$user->isAdmin() && ($custom[1] === null || $custom[0] > SkhppNumberService::lastSequence($jenis, $year))) {
                return back()->with('error', "Nomor khusus hanya boleh berupa nomor sisipan dari nomor yang sudah terbit (cth: 12a). Untuk meloncati nomor, hubungi Admin.");
            }
            if (SkhppNumberService::isTaken($jenis, $year, $custom[0], null, null, $custom[1])) {
                return back()->with('error', "Nomor urut {$label} SKHPP-{$jenis} tahun {$year} sudah dipakai.");
            }
        }

        $archive = SkhppNumberService::withLock($jenis, function () use ($data, $user, $date, $year, $roman, $jenis, $custom, $subId) {
            [$seq, $suffix] = $custom ?? [SkhppNumberService::nextSequence($jenis, $year), null];

            return SkhppArchive::create([
                'sumber' => 'manual',
                'jenis' => $jenis,
                'sub_category_id' => $subId,
                'priority' => SkhppNumberService::PRIORITY, // SKHPP selalu RAHASIA
                'nomor_urut' => $seq,
                'nomor_sisipan' => $suffix,
                'bulan_romawi' => $roman,
                'tahun' => $year,
                'nomor_skhpp' => SkhppNumberService::format(SkhppNumberService::PRIORITY, SkhppNumberService::label($seq, $suffix), $roman, $year),
                'tanggal' => $date->toDateString(),
                'nama' => $data['nama'] ?? null,
                'perihal' => $data['perihal'],
                'peruntukan' => $data['peruntukan'] ?? null,
                'keterangan' => $data['keterangan'] ?? null,
                'status' => SkhppArchive::STATUS_MENUNGGU_PDF,
                'created_by' => $user->id,
            ]);
        });

        if ($request->hasFile('file')) {
            $this->storeUpload($archive, $request->file('file'), $user->id);
        }

        $this->audit($request, 'NOMOR_SKHPP_MANUAL', $archive->nomor_skhpp, "Permintaan nomor SKHPP-{$jenis} manual: {$archive->nomor_skhpp} perihal \"{$archive->perihal}\"");

        return back()->with('success', "Nomor SKHPP-{$jenis} berhasil dibuat: {$archive->nomor_skhpp}" . ($request->hasFile('file') ? ' (PDF tersimpan).' : '. Silakan unggah PDF setelah ditandatangani.'));
    }

    /** Unggah / ganti PDF SKHPP (manual maupun hasil TTD basah SKHPP SINDEN). */
    public function upload(Request $request, SkhppArchive $archive)
    {
        $request->validate(['file' => 'required|file|mimes:pdf|max:20480']);

        if ($archive->status === SkhppArchive::STATUS_DICABUT) {
            return back()->with('error', 'Arsip SKHPP yang sudah dicabut tidak dapat diunggah ulang.');
        }
        if (empty($archive->nomor_skhpp)) {
            return back()->with('error', 'SKHPP ini belum bernomor. Tunggu TTE/TTD Komandan terbit di menu Penerbitan SKHPP.');
        }

        $this->storeUpload($archive, $request->file('file'), $request->user()->id);
        $this->audit($request, 'UPLOAD_PDF_SKHPP', $archive->nomor_skhpp, "Unggah PDF Arsip SKHPP No: {$archive->nomor_skhpp}");

        return back()->with('success', "PDF SKHPP {$archive->nomor_skhpp} berhasil disimpan.");
    }

    /** Simpan ulang PDF resmi dari SKHPP SINDEN yang sudah disahkan (mis. arsip lama). */
    public function generatePdf(Request $request, SkhppArchive $archive)
    {
        $skhpp = $archive->skhpp;
        if (!$skhpp || $skhpp->status !== 'approved') {
            return back()->with('error', 'PDF otomatis hanya untuk SKHPP SINDEN yang sudah disahkan TTE Komandan.');
        }

        if (!SkhppPdfService::storeToArchive($skhpp, $request->user()->id)) {
            return back()->with('error', 'Gagal membuat PDF SKHPP. Coba lagi beberapa saat lagi.');
        }

        $this->audit($request, 'GENERATE_PDF_SKHPP', $archive->nomor_skhpp, "Simpan PDF resmi SKHPP SINDEN ke arsip No: {$archive->nomor_skhpp}");

        return back()->with('success', "PDF resmi SKHPP {$archive->fresh()->nomor_skhpp} tersimpan di arsip.");
    }

    /** Tampilkan PDF arsip (inline). */
    public function file(SkhppArchive $archive)
    {
        abort_unless($archive->fileExists(), 404, 'Berkas PDF SKHPP tidak ditemukan.');

        $name = 'SKHPP-' . $archive->jenis . '_' . Str::slug($archive->nomor_skhpp ?: ('arsip-' . $archive->id)) . '.pdf';

        return Storage::disk($archive->file_disk ?: 'local')->response($archive->file_path, $name, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $name . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Ubah keterangan arsip (perihal/nama/peruntukan hanya untuk SKHPP manual). */
    public function update(Request $request, SkhppArchive $archive)
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || (int) $archive->created_by === (int) $user->id, 403, 'Hanya Admin atau pembuat arsip yang dapat mengubah data ini.');

        $rules = [
            'keterangan' => 'nullable|string|max:2000',
            // Sub-kategori boleh dikoreksi untuk SKHPP manual maupun dari menu SINDEN.
            'sub_category_id' => 'nullable|integer',
        ];
        if ($archive->sumber === 'manual') {
            $rules += [
                'nama' => 'nullable|string|max:255',
                'perihal' => 'required|string|max:255',
                'peruntukan' => 'nullable|string|max:2000',
            ];
        }
        $data = $request->validate($rules);
        if ($request->exists('sub_category_id')) {
            $data['sub_category_id'] = !empty($data['sub_category_id']) ? (int) $data['sub_category_id'] : null;
            if (!SkhppNumberService::subCategoryBelongs($data['sub_category_id'], $archive->jenis)) {
                return back()->with('error', "Sub-kategori tidak termasuk SKHPP-{$archive->jenis}.");
            }
        } else {
            unset($data['sub_category_id']);
        }
        $archive->update($data);

        $this->audit($request, 'UPDATE_ARSIP_SKHPP', $archive->nomor_skhpp, "Mengubah data Arsip SKHPP No: " . ($archive->nomor_skhpp ?: $archive->id));

        return back()->with('success', 'Data arsip SKHPP diperbarui.');
    }

    /** Hapus arsip manual / yang sudah dicabut (Admin). */
    public function destroy(Request $request, SkhppArchive $archive)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Hanya Admin yang dapat menghapus arsip SKHPP.');

        if ($archive->sumber === 'sinden' && $archive->skhpp_id && $archive->status !== SkhppArchive::STATUS_DICABUT) {
            return back()->with('error', 'Arsip ini terhubung ke SKHPP SINDEN. Hapus/cabut dari menu Penerbitan SKHPP.');
        }

        $label = $archive->nomor_skhpp ?: ('#' . $archive->id);
        $archive->deleteFile();
        $archive->delete();

        $this->audit($request, 'HAPUS_ARSIP_SKHPP', $label, "Menghapus Arsip SKHPP No: {$label}");

        return back()->with('success', "Arsip SKHPP {$label} dihapus.");
    }

    private function storeUpload(SkhppArchive $archive, \Illuminate\Http\UploadedFile $file, int $userId): void
    {
        $year = $archive->tahun ?: (int) date('Y');
        $path = $file->storeAs(
            "skhpp_archives/{$year}",
            'SKHPP-' . $archive->jenis . '_' . ($archive->nomor_urut ?: 'x') . '_' . Str::random(12) . '.pdf',
            'local'
        );

        $archive->deleteFile();
        $archive->update([
            'file_path' => $path,
            'file_disk' => 'local',
            'status' => SkhppArchive::STATUS_TERSIMPAN,
            'uploaded_at' => now(),
            'uploaded_by' => $userId,
        ]);
    }

    private function audit(Request $request, string $action, ?string $target, string $description): void
    {
        try {
            AuditLog::create([
                'user_id' => $request->user()->id,
                'admin_name' => $request->user()->name,
                'action' => $action,
                'target_personnel' => $target ?? '-',
                'description' => $description,
                'ip_address' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat audit Arsip SKHPP: ' . $e->getMessage());
        }
    }
}
