<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PESSReceiverController extends Controller
{
    /**
     * GATEWAY UTAMA: Menerima Berkas Fisik & Data Ringkas dari Portal PESS
     * DIOPTIMALKAN: Validasi ketat, auto-overwrite agresif, dan pembersihan file fisik usang dari storage
     */
    public function receive(Request $request)
    {
        // =====================================================================
        // SULTAN CONFIG: PENGISIAN TOKEN AMAN DARI FILE .env SINDEN
        // =====================================================================
        // Token dibaca lewat config (aman saat `config:cache`) tanpa nilai bawaan:
        // bila PESS_API_TOKEN belum diisi di .env, semua kiriman ditolak.
        $expectedToken = (string) config('services.pess.token');
        $incomingToken = (string) $request->bearerToken();
        if ($expectedToken === '' || !hash_equals($expectedToken, $incomingToken)) {
            return response()->json(['message' => 'Akses ilegal terdeteksi oleh Radar Sinden'], 401);
        }

        $request->validate([
            // Dipakai sebagai nama folder/berkas: batasi karakter agar tidak ada path traversal.
            'nomor_identitas'        => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-]+$/'],
            'nama_lengkap'           => 'required',
            'submission_category_id' => 'required',
            'answers_json'           => 'required',
            'master_drh_pdf'         => 'required|mimes:pdf|max:12288'
        ]);

        try {
            // 1. Cek atau Daftarkan Personel Baru di Database Sinden
            $applicant = Applicant::firstOrCreate(
                ['nomor_identitas' => $request->nomor_identitas],
                [
                    'nama_lengkap' => $request->nama_lengkap,
                    'no_wa'        => $request->no_wa,
                    'status'       => 'approved' // Otomatis aktifkan akses akun
                ]
            );

            // =========================================================================
            // SULTAN PROTECTION: AUTO-OVERWRITE RECORD SINDEN SEBELUM SAVE BARU (FIXED)
            // =========================================================================
            // Menggunakan pembacaan request input secara agresif untuk menjamin flag overwrite terbaca murni
            if ($request->input('overwrite_mode') == true || $request->has('overwrite_mode')) {
                
                // 1. Ambil data pengajuan lama yang masih menggantung ('verifying' or 'rejected_files')
                $oldSubmissions = Submission::where('applicant_id', $applicant->id)
                                            ->whereIn('status', ['verifying', 'rejected_files'])
                                            ->get();

                // 2. Musnahkan file PDF & Berkas Lampiran lama dari storage fisik Sinden agar tidak menumpuk sampah file
                foreach ($oldSubmissions as $oldSub) {
                    // Hapus Master PDF DRH Lama
                    if ($oldSub->generated_pdf_path && Storage::disk('public')->exists($oldSub->generated_pdf_path)) {
                        Storage::disk('public')->delete($oldSub->generated_pdf_path);
                    }
                    
                    // SULTAN ADD-ON: Iterasi pembersihan seluruh file lampiran syarat fisik lama (SKCK, Foto, dll)
                    if (!empty($oldSub->attachment_paths) && is_array($oldSub->attachment_paths)) {
                        foreach ($oldSub->attachment_paths as $oldFilePath) {
                            if ($oldFilePath && Storage::disk('public')->exists($oldFilePath)) {
                                Storage::disk('public')->delete($oldFilePath);
                            }
                        }
                    }
                }

                // 3. Hapus baris record lama dari tabel submissions database kantor.sql
                Submission::where('applicant_id', $applicant->id)
                          ->whereIn('status', ['verifying', 'rejected_files'])
                          ->delete();
            }

            // 2. Amankan File Master PDF DRH Baru ke Storage Lokal Sinden
            $pdfFile = $request->file('master_drh_pdf');
            $pdfPath = $pdfFile->storeAs(
                "generated_rh", 
                "RH_PESS_{$applicant->nomor_identitas}_" . time() . ".pdf", 
                'public'
            );

            // 3. Amankan Seluruh Lampiran Tambahan (Foto, SKCK, Ijazah, dll)
            $attachmentPaths = [];
            foreach ($request->allFiles() as $key => $file) {
                // Lewati file master PDF karena sudah diproses di atas
                if ($key === 'master_drh_pdf') continue;

                // Bersihkan prefiks 'files_' untuk mengembalikan nama key asli (Contoh: files_pas_foto_pemohon -> pas_foto_pemohon)
                $cleanKey = str_replace('files_', '', $key);
                
                $cleanKey = preg_replace('/[^A-Za-z0-9_\-]/', '_', $cleanKey);

                // Nama acak + ekstensi dari isi berkas (whitelist); gambar di-encode ulang.
                $storedPath = \App\Support\SafeImage::storeUpload($file, "submissions/{$applicant->nomor_identitas}/attachments", ['pdf', 'jpg', 'png', 'webp']);
                if ($storedPath === null) {
                    return response()->json(['message' => "Lampiran {$cleanKey} ditolak: hanya PDF/JPG/PNG yang diizinkan."], 422);
                }
                $attachmentPaths[$cleanKey] = $storedPath;
            }

            // 4. Suntik Record Baru ke Database Sinden (Status: Menunggu Verifikasi)
            $submission = Submission::create([
                'applicant_id'           => $applicant->id,
                'submission_category_id' => $request->submission_category_id,
                'answers_json'           => json_decode($request->answers_json, true),
                'generated_pdf_path'     => $pdfPath, // Mengarah lokal Sinden
                'attachment_paths'       => $attachmentPaths, // Mengarah lokal Sinden
                'status'                 => 'verifying' // Masuk baris antrean teratas PESSAdmin.vue
            ]);

            Log::info('Sinkronisasi File Fisik PESS Berhasil Mendarat di Sinden.', ['pendaftar' => $applicant->nama_lengkap]);

            return response()->json([
                'status' => 'success',
                'message' => 'Berkas fisik berhasil disinkronisasi penuh ke storage lokal Si Sinden.'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Gagal memproses kiriman file PESS: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Kesalahan internal server Sinden: ' . $e->getMessage()
            ], 500);
        }
    }
}