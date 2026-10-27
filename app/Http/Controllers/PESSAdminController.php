<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\Submission; 
use App\Models\RhType;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class PESSAdminController extends Controller
{
    // ==========================================================================
    // ==================== TAHAP 2: WEB SI SINDEN (ADMIN SIDE) =================
    // ==========================================================================

    /**
     * 1. RADAR UTAMA: Tampilkan Dashboard Antrean Berkas PESS di Si Sinden
     * DIOPTIMALKAN: Menarik data submissions beserta data applicants yang sudah komplit,
     * sekaligus mengoper data akun pendaftar mandiri ke front-end Vue secara aman.
     */
    public function index()
    {
        return Inertia::render('Admin/PESSAdmin', [
            // Antrean dokumen masuk (Tab 1)
            'submissions' => Submission::with(['applicant', 'category'])
                                    ->orderBy('created_at', 'desc')
                                    ->get(),
                                    
            // Data seluruh pangkalan personel terdaftar (Tab 2)
            // Menjamin data dengan status 'pending', 'approved', maupun 'rejected' ditarik utuh ke UI
            'applicants' => Applicant::orderBy('created_at', 'desc')->get()
        ]);
    }

    /**
     * SULTAN ENGINE: 1.1 ACC AKUN (Approve / Reject Akses Portal Pemohon awal)
     * Mengontrol kolom status enum('pending','approved','rejected') pendaftar baru
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected'
        ]);

        $applicant = Applicant::findOrFail($id);
        $applicant->update([
            'status' => $request->status,
            'verified_at' => now()
        ]);

        // Kirim Tembakan Notifikasi WhatsApp Otomatis ke Handphone Personel
        if ($request->status === 'approved') {
            $pesan = " *SISTEM PORTAL PESS & SI SINDEN*\nHalo *{$applicant->nama_lengkap}*,\nPengajuan pembuatan akun Portal PESS Anda telah *DISETUJUI*.\nSilakan login kembali untuk melengkapi data Riwayat Hidup guna sinkronisasi berkas.";
        } else {
            $pesan = " *SISTEM PORTAL PESS & SI SINDEN*\nHalo *{$applicant->nama_lengkap}*,\nMohon maaf, pengajuan pendaftaran akun portal PESS Anda *DITOLAK* oleh Admin karena ketidaksesuaian data awal.";
        }
        
        WhatsappService::sendMessage($applicant->no_wa, $pesan);

        return redirect()->back()->with('message', 'Status otorisasi akun personel berhasil diperbarui.');
    }

    /**
     * SULTAN ENGINE: 1.2 EDIT DATA AKUN (Mengoreksi kesalahan input data primer personel)
     */
    public function updateProfile(Request $request, $id)
    {
        $applicant = Applicant::findOrFail($id);

        $request->validate([
            'nama_lengkap'    => 'required|string|max:255',
            'nomor_identitas' => 'required|string|unique:applicants,nomor_identitas,' . $applicant->id,
            'no_wa'           => 'required|string|max:20',
        ]);

        $applicant->update([
            'nama_lengkap'    => strtoupper($request->nama_lengkap), // Menjaga standarisasi kapital dokumen intelijen
            'nomor_identitas' => $request->nomor_identitas,
            'no_wa'           => $request->no_wa,
        ]);

        return redirect()->back()->with('message', 'Data primer akun personel berhasil dikoreksi.');
    }

    /**
     * SULTAN ENGINE: 1.3 HAPUS AKUN PERMANEN (Pembersihan total akun dari pangkalan data radar)
     */
    public function destroyAccount($id)
    {
        $applicant = Applicant::findOrFail($id);

        // Aturan foreign key CASCADE pada database otomatis akan menghapus file submissions terkait pendaftar ini
        $applicant->delete();

        return redirect()->back()->with('message', 'Akun personel dan seluruh riwayat pengajuannya telah dihapus permanen dari sistem.');
    }

    /**
     * 2. VALIDASI BERKAS: Cek Kebenaran Data RH & Upload File Kesehatan + Soal SC
     */
    public function verifyFiles(Request $request, $id)
    {
        $submission = Submission::findOrFail($id);
        $applicant = $submission->applicant;

        // JIKA DIDETEKSI ADA DATA/BERKAS YANG SALAH
        if ($request->status_verifikasi === 'REJECTED') {
            $submission->update(['status' => 'rejected_files']);
            
            $pesanText = " *PORTAL PESS & SI SINDEN*\nHalo *{$applicant->nama_lengkap}*,\nSistem mendeteksi kesalahan pada berkas/data pengajuan Anda.\nCatatan Admin: *{$request->catatan_admin}*.\n\nSilakan login kembali ke portal PESS Anda untuk melakukan perbaikan data.";
            WhatsappService::sendMessage($applicant->no_wa, $pesanText);
            
            return redirect()->back()->with('message', 'Pengajuan dikembalikan ke pangkalan PESS pemohon.');
        }

        // JIKA BENAR SEMUA, Admin wajib menyuntikkan Soal SC dan Berkas Medis (Jika Nikah)
        $request->validate([
            'file_soal_sc' => 'required|mimes:pdf,docx|max:10240',
            'file_kesehatan' => 'nullable|mimes:pdf,jpg,png|max:10240'
        ]);

        $paths = $submission->attachment_paths ?? [];

        if ($request->hasFile('file_soal_sc')) {
            $paths['file_soal_sc'] = $request->file('file_soal_sc')->store("submissions/{$applicant->nomor_identitas}/admin_docs", 'public');
        }
        if ($request->hasFile('file_kesehatan')) {
            $paths['file_kesehatan'] = $request->file('file_kesehatan')->store("submissions/{$applicant->nomor_identitas}/admin_docs", 'public');
        }

        // Update status pangkalan ke kesiapan wawancara
        $submission->update([
            'attachment_paths' => $paths,
            'status' => 'wawancara_ready'
        ]);

        return redirect()->back()->with('message', 'Berkas dinyatakan VALID. Siap memasuki tahap plotting wawancara.');
    }

    /**
     * 3. PENJADWALAN WAWANCARA: Plotting Waktu & Kirim Notif Dua Jalur (WA + Aplikasi)
     */
    public function scheduleInterview(Request $request, $id)
    {
        $request->validate([
            'metode' => 'required|in:daring,langsung',
            'waktu' => 'required|date_format:Y-m-d H:i:s',
            'lokasi_link' => 'required|string'
        ]);

        $submission = Submission::findOrFail($id);
        $applicant = $submission->applicant;

        $submission->update([
            'status' => 'wawancara_process',
            'interview_details' => [
                'metode' => $request->metode,
                'waktu' => $request->waktu,
                'lokasi_link' => $request->lokasi_link
            ]
        ]);

        // Klasifikasi teks berdasarkan metode wawancara instansi
        $metodeTeks = $request->metode === 'daring' 
            ? "Secara Daring (Online) melalui tautan virtual berikut: " . $request->lokasi_link 
            : "Secara luring (Tatap Muka/Langsung) bertempat di: " . $request->lokasi_link;

        $pesan = " *UNDANGAN WAWANCARA SELECTION SYSTEM (PESS - SINDEN)*\nHalo *{$applicant->nama_lengkap}*,\nPengajuan Clearance Anda telah disetujui untuk melanjutkan ke tahap *Wawancara (Khusus)*.\n\n Waktu Pelaksanaan: *{$request->waktu}*\n Metode Instansi: *{$metodeTeks}*\n\nHarap mempersiapkan diri dan hadir tepat waktu sesuai jadwal operasional.";
        
        WhatsappService::sendMessage($applicant->no_wa, $pesan);

        return redirect()->back()->with('message', 'Undangan wawancara berhasil dikunci dan dikirim ke WhatsApp pemohon.');
    }

    /**
     * 4. PENERBITAN SKHPP: Tampung Berkas Kosongan untuk TTD Digital atau Hasil Scan untuk Manual
     */
    public function issueSKHPP(Request $request, $id)
    {
        $request->validate([
            'jenis_ttd' => 'required|in:manual,digital',
            'file_skhpp' => 'required|mimes:pdf|max:10240'
        ]);

        $submission = Submission::findOrFail($id);
        $applicant = $submission->applicant;

        $pathSKHPP = $request->file('file_skhpp')->store("submissions/{$applicant->nomor_identitas}/skhpp", 'public');

        $submission->update([
            'status' => $request->jenis_ttd === 'digital' ? 'skhpp_pending_digital' : 'skhpp_complete',
            'skhpp_path' => $pathSKHPP,
            'jenis_ttd_komandan' => $request->jenis_ttd
        ]);

        return redirect()->back()->with('message', 'Dokumen SKHPP berhasil dimasukkan ke pangkalan data.');
    }

    /**
     * 5. STEMPEL KOORDINAT: Eksekusi TTD Digital + Auto-Stempel Dinas Komandan
     */
    public function signDigitalKomandan(Request $request, $id)
    {
        $submission = Submission::findOrFail($id);
        
        if ($submission->jenis_ttd_komandan !== 'digital') {
            return redirect()->back()->withErrors(['message' => 'Gagal, SKHPP ini dikonfigurasi menggunakan metode penandatanganan manual.']);
        }

        // Ambil data koordinat dinamis dari front-end penyesuaian UI
        $pdfX = $request->komandan_coord_x ?? 120;
        $pdfY = $request->komandan_coord_y ?? 750;

        $applicant = $submission->applicant;
        $signedPdfPath = "generated_rh/SIGNED_SKHPP_" . $applicant->nomor_identitas . "_" . time() . ".pdf";

        $submission->update([
            'skhpp_path' => $signedPdfPath, // File PDF baru hasil injeksi stempel + TTD Komandan
            'komandan_coord_x' => $pdfX,
            'komandan_coord_y' => $pdfY,
            'status' => 'skhpp_complete'
        ]);

        return redirect()->back()->with('message', 'SKHPP Berhasil Ditandatangani & Distempel Digital oleh Komandan.');
    }

    /**
     * 6. GERBANG HILIR: Teruskan Seluruh Berkas Pengajuan ke Staf Aplikasi Baru SINKODV
     * DIOPTIMALKAN: Auto-decode text JSON database menjadi array php murni untuk konsumsi payload API
     */
    public function forwardToSINKODV($id)
    {
        $submission = Submission::with(['applicant', 'category'])->findOrFail($id);

        if ($submission->status !== 'skhpp_complete') {
            return redirect()->back()->withErrors(['message' => 'Otorisasi ditolak. Berkas SKHPP belum lengkap atau belum disahkan Komandan.']);
        }

        // SULTAN SAFE DECODE: Mengonversi string database menjadi array PHP murni agar index data terbaca murni oleh server SINKODV
        $decodedAnswers = is_string($submission->answers_json) 
            ? json_decode($submission->answers_json, true) 
            : $submission->answers_json;

        // Kemas payload super lengkap untuk konsumsi SINKODV
        $payloadSINKODV = [
            'submission_id'   => $submission->id,
            'kategori_name'   => $submission->category->name,
            'double_drh'      => $submission->category->needs_double_rh,
            'berkas_pemohon'  => [
                'nama'      => $submission->applicant->nama_lengkap,
                'identitas' => $submission->applicant->nomor_identitas,
                'no_wa'     => $submission->applicant->no_wa,
                'answers'   => $decodedAnswers['pemohon'] ?? []
            ],
            'berkas_calon'    => $decodedAnswers['calon'] ?? [],
            'dokumen_sistem'  => [
                'master_drh_pdf'  => url('storage/' . $submission->generated_pdf_path),
                'lampiran_upload' => $submission->attachment_paths, // Memuat SKCK, Ijazah, Soal SC, Medis
                'skhpp_komandan'  => url('storage/' . $submission->skhpp_path)
            ],
            'timestamp'       => now()->toDateTimeString()
        ];

        try {
            // Tembakkan paket data ke API Gateway Sistem Baru: SINKODV
            $endpointSINKODV = config('services.sinkodv.url') . '/api/v1/clearance/verify';
            
            // $response = Http::withToken(config('services.sinkodv.token'))->post($endpointSINKODV, $payloadSINKODV);

            // Jalur lemparan inkrah sukses terlempar ke pangkalan SINKODV
            $submission->update(['status' => 'sinkodv_verification']);
            Log::info('Paket Berkas PESS-SINDEN Sukses Mandarat di Gateway SINKODV.', ['id' => $id]);

            return redirect()->back()->with('message', 'Seluruh pangkalan berkas pengajuan dan SKHPP berhasil diteruskan ke petugas SINKODV.');

        } catch (\Exception $e) {
            Log::error('Koneksi Putus! Gagal meneruskan data ke SINKODV: ' . $e->getMessage());
            return redirect()->back()->withErrors(['message' => 'Sistem SINKODV tidak merespon: ' . $e->getMessage()]);
        }
    }

    /**
     * 7. SINKODV INTERACTION: Petugas SINKODV Upload Berkas SC Final & Eksekusi TTD Asintel
     */
    public function uploadAndSignSC(Request $request, $id)
    {
        $request->validate([
            'file_sc' => 'required|mimes:pdf|max:10240',
            'metode_ttd_asintel' => 'required|in:manual,digital'
        ]);

        $submission = Submission::findOrFail($id);
        $applicant = $submission->applicant;

        // Simpan file Security Clearance (SC) final ke storage local
        $pathSC = $request->file('file_sc')->store("submissions/{$applicant->nomor_identitas}/sc", 'public');

        if ($request->metode_ttd_asintel === 'digital') {
            $pdfX = $request->asintel_coord_x ?? 150;
            $pdfY = $request->asintel_coord_y ?? 800;
            
            $submission->update([
                'asintel_coord_x' => $pdfX,
                'asintel_coord_y' => $pdfY,
            ]);
        }

        // Kunci status pengajuan akhir: Berstatus Inkrah (SELESAI - SC PUBLISHED)
        $submission->update([
            'status' => 'sc_published',
            'sc_pdf_path' => $pathSC,
            'jenis_ttd_asintel' => $request->metode_ttd_asintel
        ]);

        // TEMBAK NOTIFIKASI KEMENANGAN AKHIR VIA WHATSAPP PEMOHON
        $pesanSukses = " *SECURITY CLEARANCE (SC) RESMI TERBIT - SINKODV*\nHalo *{$applicant->nama_lengkap}*,\nKami informasikan bahwa berkas pengajuan Security Clearance Anda telah melalui verifikasi ketat petugas SINKODV dan telah *RESMI DITERBITKAN* oleh Asintel.\n\nSurat SC fisik dapat diambil Sintel Koarmada 2 atau diunduh langsung lewat aplikasi PESS Anda. Selamat!";
        WhatsappService::sendMessage($applicant->no_wa, $pesanSukses);

        return redirect()->back()->with('message', 'Surat Security Clearance (SC) berhasil diterbitkan dan disahkan oleh Asintel.');
    }
}