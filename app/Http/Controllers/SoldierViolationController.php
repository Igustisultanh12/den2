<?php

namespace App\Http\Controllers;

use App\Models\SoldierViolation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage; // Penting untuk manajemen berkas fisik
use Illuminate\Support\Facades\Log; // Pengawal lacak transmisi data biner

class SoldierViolationController extends Controller
{
    /**
     * Menampilkan daftar pelanggaran prajurit.
     */
    public function index(Request $request)
    {
        $query = SoldierViolation::query();

        // Fitur Pencarian (Nama, NRP, atau Kasus)
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('nrp', 'like', "%{$request->search}%")
                  ->orWhere('case_description', 'like', "%{$request->search}%");
            });
        }

        // Fitur Filter Satuan
        if ($request->satuan) {
            $query->where('unit', $request->satuan);
        }

        return Inertia::render('SoldierViolations/Index', [
            'violations' => $query->latest()->paginate(10)->withQueryString(),
            'filters' => $request->only(['search', 'satuan']),
            'stats' => [
                'total' => SoldierViolation::count(),
                'proses' => SoldierViolation::where('status', 'PROSES')->count(),
                'selesai' => SoldierViolation::where('status', 'SELESAI')->count(),
            ]
        ]);
    }

    /**
     * Menyimpan data kasus pelanggaran baru (Awal).
     * KALIBRASI: Mendukung Multi-upload berkas dokumen (DOCX, JPG, PDF) s.d 150MB
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'nrp' => 'required|string|max:50|unique:soldier_violations,nrp',
            'pangkat' => 'required|string',
            'jabatan' => 'required|string',
            'satuan' => 'required|string',
            'kasus' => 'required|string',
            'tmt' => 'required|date',
            'perkembangan_kasus' => 'required|string',
            'status' => 'required|in:PROSES,SIDANG,SELESAI,DINAS_KEMBALI,PDTH',
            'lampiran' => 'nullable|array',
            'lampiran.*' => 'file|mimes:pdf,jpg,png,doc,docx,zip|max:157286', 
            'putusan' => 'nullable|array',
            'putusan.*' => 'file|mimes:pdf,jpg,png,doc,docx,zip|max:157286',
        ]);

        try {
            $timestamp = now()->format('d/m/Y H:i');
            $riwayatPaket = [];
            $uploadedLampiranPaths = [];

            if ($request->hasFile('lampiran')) {
                foreach ($request->file('lampiran') as $file) {
                    $uploadedLampiranPaths[] = $this->storeSafely($file, 'pelanggaran/lampiran');
                }
            }

            $riwayatPaket[] = [
                'id'         => uniqid(), 
                'tanggal'    => $timestamp,
                'catatan'    => "REGISTRASI AWAL: " . $request->perkembangan_kasus,
                'file_paths' => $uploadedLampiranPaths 
            ];

            $dataToSave = [
                'name'             => strtoupper($request->nama),
                'nrp'              => $request->nrp,
                'rank'             => $request->pangkat,
                'position'         => $request->jabatan,
                'unit'             => $request->satuan,
                'case_description' => $request->kasus,
                'incident_date'    => $request->tmt,
                'case_development' => "[$timestamp] AWAL: " . $request->perkembangan_kasus,
                'status'           => $request->status,
                'lampiran_berkas'  => json_encode($riwayatPaket), 
                'dokumen_putusan'  => null
            ];

            if ($request->hasFile('putusan')) {
                $uploadedPutusanPaths = [];
                foreach ($request->file('putusan') as $file) {
                    $uploadedPutusanPaths[] = $this->storeSafely($file, 'pelanggaran/putusan');
                }
                $dataToSave['dokumen_putusan'] = json_encode($uploadedPutusanPaths);
            }

            SoldierViolation::create($dataToSave);

            return back()->with('success', 'Lapor! Kasus baru dan paket berkas logistik awal berhasil diamankan.');
        } catch (\Exception $e) {
            Log::error('SINDEN ERROR STORE KASUS: ' . $e->getMessage());
            return back()->with('error', 'Gagal registrasi: ' . $e->getMessage());
        }
    }

    /**
     * Update perkembangan kasus secara berkala (PENGUNCIAN FORMAT UPDATE AKURAT).
     */
    public function update(Request $request, $id)
    {
        Log::info('RADAR UPDATE TRIGGERED - ID KASUS: ' . $id);

        $request->validate([
            'catatan_baru' => 'required|string',
            'status' => 'required|in:PROSES,SIDANG,SELESAI,DINAS_KEMBALI,PDTH',
            'lampiran' => 'nullable|array',
            'lampiran.*' => 'file|mimes:pdf,jpg,png,doc,docx,zip|max:157286',
            'putusan' => 'nullable|array',
            'putusan.*' => 'file|mimes:pdf,jpg,png,doc,docx,zip|max:157286',
        ]);

        try {
            $violation = SoldierViolation::findOrFail($id);
            $waktuSekarang = now()->format('d/m/Y H:i');
            
            // 1. Ambil riwayat paket lama dari database, pastikan didecode menjadi array murni
            $riwayatLama = json_decode($violation->lampiran_berkas, true);
            if (!is_array($riwayatLama)) {
                $riwayatLama = [];
            }

            // 2. Proses unggahan berkas lampiran baru (mendukung multi-file array)
            $newJalurPaths = [];
            if ($request->hasFile('lampiran')) {
                foreach ($request->file('lampiran') as $file) {
                    $newJalurPaths[] = $this->storeSafely($file, 'pelanggaran/lampiran');
                }
                Log::info('Multi-Lampiran Baru Berhasil Diparsing: ', $newJalurPaths);
            }

            // 3. Tambahkan objek perkembangan baru ke dalam rumpun array riwayat paket
            $riwayatLama[] = [
                'id'         => uniqid(),
                'tanggal'    => $waktuSekarang,
                'catatan'    => $request->catatan_baru,
                'file_paths' => $newJalurPaths
            ];

            // 4. Struktur data penampung untuk eksekusi update mutlak
            $dataUpdate = [
                'status'          => $request->status,
                'lampiran_berkas' => json_encode(array_values($riwayatLama)),
                'case_development'=> $violation->case_development . "\n[$waktuSekarang] UPDATE: " . $request->catatan_baru
            ];

            // 5. Update Dokumen Putusan Akhir (Multi-file Replacement jika ada)
            if ($request->hasFile('putusan')) {
                if ($violation->dokumen_putusan) {
                    $putusanLama = json_decode($violation->dokumen_putusan, true);
                    if (is_array($putusanLama)) {
                        foreach ($putusanLama as $pathFisik) {
                            Storage::disk('public')->delete($pathFisik);
                        }
                    }
                }

                $newPutusanPaths = [];
                foreach ($request->file('putusan') as $file) {
                    $newPutusanPaths[] = $this->storeSafely($file, 'pelanggaran/putusan');
                }
                $dataUpdate['dokumen_putusan'] = json_encode($newPutusanPaths);
            }
            
            // 6. EKSEKUSI PEMBARUAN KE DATABASE NYATA
            $violation->update($dataUpdate);
            
            Log::info('RADAR UPDATE SUCCESS - Database Updated.');
            return back()->with('success', 'Lapor! Catatan perkembangan berkas beruntun berhasil disinkronkan.');

        } catch (\Exception $e) {
            Log::error('SINDEN ERROR UPDATE KASUS: ' . $e->getMessage());
            return back()->with('error', 'Gagal memperbarui: ' . $e->getMessage());
        }
    }

    /**
     * Simpan lampiran dengan nama acak & ekstensi dari ISI berkas (bukan nama klien),
     * sehingga "dokumen.php" yang menyamar sebagai PDF/JPG tidak pernah tersimpan.
     */
    private function storeSafely(\Illuminate\Http\UploadedFile $file, string $directory): string
    {
        $path = \App\Support\SafeImage::storeUpload($file, $directory, ['pdf', 'jpg', 'png', 'doc', 'docx', 'zip']);
        if ($path === null) {
            throw new \RuntimeException("Berkas {$file->getClientOriginalName()} ditolak: isi berkas tidak sesuai format yang diizinkan (PDF, JPG, PNG, DOC, DOCX, ZIP).");
        }
        return $path;
    }

    /**
     * INTERSEPTOR RADAR: PRATINJAU LANGSUNG FILE .DOCX TANPA DOWNLOAD
     */
    public function previewDocx(Request $request)
    {
        $request->validate(['file_path' => 'required|string|max:500']);

        // Cegah path traversal: berkas harus benar-benar berada di folder pelanggaran.
        $baseDir = realpath(storage_path('app/public/pelanggaran'));
        $fullPathFisik = realpath(storage_path('app/public/' . ltrim($request->file_path, '/\\')));
        if ($baseDir === false || $fullPathFisik === false || !str_starts_with($fullPathFisik, $baseDir . DIRECTORY_SEPARATOR) || !is_file($fullPathFisik)) {
            return abort(404, "Berkas fisik tidak ditemukan di pangkalan storage.");
        }

        $extension = strtolower(pathinfo($fullPathFisik, PATHINFO_EXTENSION));
        
        if (in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'])) {
            return response()->file($fullPathFisik);
        }

        if (in_array($extension, ['docx', 'doc'])) {
            $outputDirTemp = storage_path('app/public/temp_pelanggaran_pdf/');

            if (!file_exists($outputDirTemp)) {
                mkdir($outputDirTemp, 0777, true);
            }

            $pdfGeneratedName = pathinfo($fullPathFisik, PATHINFO_FILENAME) . '_' . time() . '.pdf';
            
            $command = "libreoffice --headless --convert-to pdf --outdir " . escapeshellarg($outputDirTemp) . " " . escapeshellarg($fullPathFisik);
            shell_exec($command);

            $originalNameRaw = pathinfo($fullPathFisik, PATHINFO_FILENAME);
            $expectedOutputPdf = $outputDirTemp . $originalNameRaw . '.pdf';
            $finalOutputPdf = $outputDirTemp . $pdfGeneratedName;

            if (file_exists($expectedOutputPdf)) {
                rename($expectedOutputPdf, $finalOutputPdf);
                return response()->file($finalOutputPdf)->deleteFileAfterSend(true);
            }
        }

        return abort(400, "Format dokumen tidak didukung untuk pratinjau langsung.");
    }

    /**
     * Hapus satu baris riwayat tertentu beserta seluruh file fisiknya.
     */
    public function deleteUpdate(Request $request, $id)
    {
        try {
            $violation = SoldierViolation::findOrFail($id);
            $riwayat = json_decode($violation->lampiran_berkas, true) ?: [];
            
            $filteredRiwayat = array_filter($riwayat, function($item) use ($request) {
                if ($item['id'] === $request->update_id) {
                    if (!empty($item['file_paths']) && is_array($item['file_paths'])) {
                        foreach ($item['file_paths'] as $path) {
                            Storage::disk('public')->delete($path);
                        }
                    }
                    return false; 
                }
                return true; 
            });

            $violation->update([
                'lampiran_berkas' => json_encode(array_values($filteredRiwayat))
            ]);

            return back()->with('success', 'Lapor! Satu baris kronologi beserta seluruh berkas paketnya telah dimusnahkan.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus riwayat: ' . $e->getMessage());
        }
    }

    /**
     * Menghapus data kasus secara permanen beserta seluruh riwayat berkasnya.
     */
    public function destroy($id)
    {
        try {
            $violation = SoldierViolation::findOrFail($id);
            
            $riwayat = json_decode($violation->lampiran_berkas, true) ?: [];
            foreach ($riwayat as $paket) {
                if (!empty($paket['file_paths']) && is_array($paket['file_paths'])) {
                    foreach ($paket['file_paths'] as $path) {
                        Storage::disk('public')->delete($path);
                    }
                }
            }

            if ($violation->dokumen_putusan) {
                $putusanPaths = json_decode($violation->dokumen_putusan, true) ?: [];
                foreach ($putusanPaths as $path) {
                    Storage::disk('public')->delete($path);
                }
            }

            $violation->delete();

            return back()->with('success', 'Lapor! Seluruh data kasus dan arsenal berkas fisik telah dimusnahkan dari server.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus data.');
        }
    }

    private function toRoman($number)
    {
        $map = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];
        return $map[$number] ?? 'I';
    }
}