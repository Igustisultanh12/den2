<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SignatureRequest;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\AppNotification;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;

class SignatureRequestController extends Controller
{
    public function index(Request $request) {
        $user = auth()->user();
        
        $sigQuery = SignatureRequest::with('user');
        $skhppQuery = \App\Models\Skhpp::with(['submitter', 'approver', 'members']);

        if ($user->role !== 'admin' && $user->role !== 'komandan') {
            $sigQuery->where('user_id', $user->id);
            $skhppQuery->where('submitted_by', $user->id);
        }

        $requests = $sigQuery->latest()->paginate(15)->withQueryString();
        $skhppRequests = $skhppQuery->latest()->paginate(15)->withQueryString();

        $targetDoc = null;
        if ($request->filled('open_id') || $request->filled('id')) {
            $targetDoc = SignatureRequest::with('user')->find($request->open_id ?: $request->id);
        }

        $targetSkhpp = null;
        if ($request->filled('open_skhpp') || $request->filled('skhpp_id')) {
            $targetSkhpp = \App\Models\Skhpp::with(['submitter', 'approver', 'members'])->find($request->open_skhpp ?: $request->skhpp_id);
        }

        return Inertia::render('Signature/Index', [
            'requests' => $requests,
            'skhppRequests' => $skhppRequests,
            'targetDoc' => $targetDoc,
            'targetSkhpp' => $targetSkhpp,
        ]);
    }

    public function store(Request $request) {
        $request->validate([
            'subject' => 'required|string|max:255',
            'document_title' => 'nullable|string|max:255',
            'person_name' => 'nullable|string|max:255',
            'pangkat_nrp' => 'nullable|string|max:255',
            'jabatan' => 'nullable|string|max:255',
            'peruntukan' => 'nullable|string|max:1000',
            'letter_number' => 'nullable|string|max:255',
            'file' => 'required|mimes:pdf|max:153600',
            'x' => 'nullable|numeric',
            'y' => 'nullable|numeric',
            'width' => 'nullable|numeric',
            'target_page' => 'nullable|integer',
            'pages_data' => 'nullable',
        ]);

        try {
            if (!Storage::disk('public')->exists('signature_reqs')) {
                Storage::disk('public')->makeDirectory('signature_reqs');
            }

            $path = $request->file('file')->store('signature_reqs', 'public');
            
            $signatureRequest = SignatureRequest::create([
                'user_id' => auth()->id(),
                'subject' => $request->subject,
                'document_title' => $request->document_title ?: 'DOKUMEN RESMI DINAS',
                'person_name' => $request->person_name ?: auth()->user()->name,
                'pangkat_nrp' => $request->pangkat_nrp ?: ((auth()->user()->pangkat ?: 'TNI AL') . (auth()->user()->nrp ? (' / NRP ' . auth()->user()->nrp) : '')),
                'jabatan' => $request->jabatan ?: 'Personel Denintel Koarmada 2',
                'peruntukan' => $request->peruntukan ?: $request->subject,
                'letter_number' => $request->letter_number,
                'file_path' => $path,
                'status' => 'pending',
                'verification_code' => 'TTE-DOC-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(6)),
                'x' => $request->x ?? 0.58,
                'y' => $request->y ?? 0.72,
                'width' => $request->width ?? 0.15,
                'target_page' => $request->target_page ?? 1,
                'pages_data' => is_array($request->pages_data) ? json_encode($request->pages_data) : $request->pages_data,
            ]);

            $komandan = User::where('role', 'komandan')->whereNotNull('phone')->first() 
                     ?? User::where('role', 'admin')->whereNotNull('phone')->first();
            $directUrl = url('/signature-requests?open_id=' . $signatureRequest->id . '&tab=pdf');
            $pesan = " *SI SINDEN: PEMBERITAHUAN*\n\n" .
                     "Mohon izin Komandan, terdapat pengajuan berkas baru:\n\n" .
                     " *Perihal:* {$request->subject}\n" .
                     " *Pengaju:* " . auth()->user()->name . "\n\n" .
                     "Mohon izin untuk memeriksa & memvalidasi berkas langsung di: {$directUrl}";

            AppNotification::notify(
                $komandan?->id,
                'komandan',
                'Pengajuan TTD Digital PDF Baru',
                "Pengajuan berkas PDF perihal \"{$request->subject}\" diajukan oleh " . auth()->user()->name . ".",
                'primary',
                '/signature-requests?open_id=' . $signatureRequest->id . '&tab=pdf',
                $pesan,
                $komandan?->phone
            );

            return back()->with('message', 'Permintaan Berhasil Dikirim.');
        } catch (\Exception $e) {
            Log::error("Sinden Store Error: " . $e->getMessage());
            return back()->with('error', 'Gagal mengirim berkas.');
        }
    }

    public function update(Request $request, SignatureRequest $signatureRequest) {
        $user = auth()->user();

        // 1. LOGIKA REVISI (STAF UNGGAH ULANG)
        if ($user->id === $signatureRequest->user_id && $signatureRequest->status === 'rejected') {
            $request->validate([
                'file' => 'required|mimes:pdf|max:10240',
                'x' => 'nullable|numeric', 'y' => 'nullable|numeric', 'width' => 'nullable|numeric',
            ]);
            
            try {
                if (Storage::disk('public')->exists($signatureRequest->file_path)) {
                    Storage::disk('public')->delete($signatureRequest->file_path);
                }
                $newPath = $request->file('file')->store('signature_reqs', 'public');
                $signatureRequest->update([
                    'file_path' => $newPath, 
                    'status' => 'pending', 
                    'note' => null,
                    'x' => $request->x ?? $signatureRequest->x,
                    'y' => $request->y ?? $signatureRequest->y,
                    'width' => $request->width ?? $signatureRequest->width,
                ]);

                $komandan = User::where('role', 'komandan')->first();
                if ($komandan && $komandan->phone) {
                    $pesanRev = " *SI SINDEN: BERKAS TELAH DIREVISI*\n\n" .
                                "Mohon izin Komandan, berkas yang sebelumnya ditolak telah diperbaiki oleh staf:\n\n" .
                                " *Perihal:* {$signatureRequest->subject}\n" .
                                " *Oleh:* {$user->name}\n\n" .
                                "Mohon izin untuk memeriksa berkas di : https://sisinden.my.id/signature-request";
                    WhatsappService::sendMessage($komandan->phone, $pesanRev);
                }

                return back()->with('message', 'Berkas revisi berhasil diunggah.');
            } catch (\Exception $e) { 
                Log::error("Sinden Revision Error: " . $e->getMessage());
                return back()->with('error', 'Gagal revisi.'); 
            }
        }

        // 2. LOGIKA PENGESAHAN (KOMANDAN/ADMIN)
        if ($user->role !== 'admin' && $user->role !== 'komandan') {
            return back()->with('error', 'Otoritas Ditolak.');
        }

        $request->validate([
            'status' => 'required|in:approved,rejected',
            'note' => 'nullable|string|max:500',
            'x' => 'nullable|numeric',
            'y' => 'nullable|numeric',
            'width' => 'nullable|numeric',
            'target_page' => 'nullable|integer',
        ]);

        try {
            DB::beginTransaction();

            if ($request->status === 'approved') {
                Log::info("=== MEMULAI PROSES PENEMPELAN QR CODE TTD DIGITAL ===");
                Log::info("ID Request: " . $signatureRequest->id);

                if (!$signatureRequest->verification_code) {
                    $signatureRequest->verification_code = 'TTE-DOC-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(6));
                }

                $originalPath = storage_path('app/public/' . $signatureRequest->file_path);
                
                // Otomatisasi Normalisasi PDF Versi Tinggi (PDF 1.5+ Compressed Object Streams) ke PDF 1.4 Kompatibel
                $sourcePdfPath = $this->normalizePdfForFpdi($originalPath);

                // Generate QR Code TTD Image
                $verifyUrl = route('doc.verify', $signatureRequest->verification_code);
                $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($verifyUrl);
                $qrImageContent = @file_get_contents($qrApiUrl);

                $tempQrPath = storage_path('app/public/temp_qr_' . time() . '_' . $signatureRequest->id . '.png');
                if ($qrImageContent) {
                    file_put_contents($tempQrPath, $qrImageContent);
                    $signatureImg = $tempQrPath;
                } else {
                    $signatureKey = Setting::where('key', 'commander_signature')->first();
                    $signatureImg = storage_path('app/public/' . ($signatureKey->value ?? 'signatures/komandan_ttd.png'));
                }

                Log::info("Path PDF Asli: " . $originalPath);
                Log::info("Path PDF Source FPDI: " . $sourcePdfPath);
                Log::info("Path TTD QR: " . $signatureImg);

                if (!file_exists($signatureImg)) {
                    Log::error("CRITICAL: File TTD tidak ditemukan di storage!");
                    throw new \Exception('File TTD tidak ditemukan.');
                }

                $pdf = new Fpdi();
                try {
                    $pageCount = $pdf->setSourceFile($sourcePdfPath);
                } catch (\Exception $e) {
                    if (str_contains($e->getMessage(), 'compression technique')) {
                        throw new \Exception('Dokumen PDF dikompresi versi tinggi (PDF 1.5+). Jalankan perintah `sudo apt-get install -y ghostscript qpdf` di terminal server aaPanel agar dapat di-decompress otomatis.');
                    }
                    throw $e;
                }

                Log::info("Jumlah Halaman PDF: " . $pageCount);

                $xRatio = (float)($request->x ?? $signatureRequest->x);
                $yRatio = (float)($request->y ?? $signatureRequest->y);
                $wRatio = (float)($request->width ?? $signatureRequest->width);
                $targetPage = (int)($request->target_page ?? $pageCount);
                $applyToAll = !empty($request->apply_to_all);

                $pagesData = null;
                if (!empty($request->pages_data)) {
                    $pagesData = is_string($request->pages_data) ? json_decode($request->pages_data, true) : $request->pages_data;
                }

                Log::info("Data Diterima -> X: $xRatio, Y: $yRatio, Width: $wRatio, Target Hal: $targetPage, Apply All: " . ($applyToAll ? 'YES' : 'NO') . ", PagesData: " . json_encode($pagesData));

                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);

                    $shouldStamp = false;
                    $xR = $xRatio;
                    $yR = $yRatio;
                    $wR = $wRatio;

                    if ($pagesData && is_array($pagesData) && (isset($pagesData[$pageNo]) || isset($pagesData[(string)$pageNo]))) {
                        $pData = $pagesData[$pageNo] ?? $pagesData[(string)$pageNo];
                        $shouldStamp = true;
                        $xR = (float)($pData['x'] ?? $xRatio);
                        $yR = (float)($pData['y'] ?? $yRatio);
                        $wR = (float)($pData['width'] ?? $wRatio);
                    } elseif ($applyToAll || $targetPage === 0 || $pageNo === $targetPage) {
                        $shouldStamp = true;
                    }

                    if ($shouldStamp) {
                        $pdfW = $size['width'];
                        $pdfH = $size['height'];
                        $posX = $xR * $pdfW;
                        $posY = $yR * $pdfH;
                        $ttdW = $wR * $pdfW;

                        Log::info("Menempelkan TTD QR Code di Hal $pageNo (Posisi Custom: X=$posX, Y=$posY | Lebar=$ttdW)");
                        $pdf->Image($signatureImg, $posX, $posY, $ttdW, $ttdW, 'PNG');
                    }
                }

                $newFileName = 'signed_' . time() . '_' . basename($signatureRequest->file_path);
                $newPath = 'signature_reqs/' . $newFileName;
                $pdf->Output(storage_path('app/public/' . $newPath), 'F');
                Log::info("Output PDF Signed Berhasil: " . $newPath);

                if (file_exists($tempQrPath)) {
                    @unlink($tempQrPath);
                }

                if ($sourcePdfPath !== $originalPath && file_exists($sourcePdfPath)) {
                    @unlink($sourcePdfPath);
                }

                if (Storage::disk('public')->exists($signatureRequest->file_path)) {
                    Storage::disk('public')->delete($signatureRequest->file_path);
                }
                
                $signatureRequest->file_path = $newPath;
                $signatureRequest->x = $xRatio;
                $signatureRequest->y = $yRatio;
                $signatureRequest->width = $wRatio;
                $signatureRequest->target_page = $targetPage;
                $signatureRequest->pages_data = is_array($request->pages_data) ? json_encode($request->pages_data) : $request->pages_data;
            }

            $signatureRequest->status = $request->status;
            $signatureRequest->note = $request->note;
            $signatureRequest->save();

            // Notifikasi Sistem In-App Bell & WA
            $targetUser = $signatureRequest->user;
            $isApproved = ($request->status === 'approved');
            $statusMsg = $isApproved ? " *TELAH DISAHKAN*" : " *DITOLAK / PERLU REVISI*";
            $ket = $isApproved ? "Silakan unduh berkas Anda." : "Alasan: _" . ($request->note ?? '-') . "_";
            
            $directUrl = url('/signature-requests?open_id=' . $signatureRequest->id . '&tab=pdf');
            $pesanWA = " *SI SINDEN: STATUS BERKAS*\n\n" .
                       "Berkas: *{$signatureRequest->subject}*\n" .
                       "Status: {$statusMsg}\n\n" .
                       " {$ket}\n\n" .
                       "Tautan Berkas: {$directUrl}";

            AppNotification::notify(
                $signatureRequest->user_id,
                null,
                $isApproved ? 'Berkas PDF Berhasil Ditandatangani TTD QR' : 'Berkas PDF Ditolak / Perlu Revisi',
                $isApproved 
                    ? "Berkas \"{$signatureRequest->subject}\" telah disahkan & ditandatangani Komandan."
                    : "Berkas \"{$signatureRequest->subject}\" dikembalikan Komandan: \"{$request->note}\".",
                $isApproved ? 'success' : 'warning',
                '/signature-requests?open_id=' . $signatureRequest->id . '&tab=pdf',
                $pesanWA,
                $targetUser?->phone
            );

            AuditLog::create([
                'user_id' => $user->id, 'admin_name' => $user->name,
                'action' => 'TTD_DECISION', 'target_personnel' => $signatureRequest->subject,
                'description' => strtoupper($request->status) . " berkas pengajuan di halaman " . ($targetPage ?? '-'),
                'ip_address' => $request->ip(),
            ]);

            DB::commit();
            Log::info("=== OPERASI BERHASIL DISIMPAN ===");
            return back()->with('message', 'Proses Berhasil.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("SINDEN CRITICAL ERROR: " . $e->getMessage());
            return back()->with('error', 'Gagal memproses pengesahan: ' . $e->getMessage());
        }
    }

    public function destroy(SignatureRequest $signatureRequest) {
        if ($signatureRequest->file_path && Storage::disk('public')->exists($signatureRequest->file_path)) {
            Storage::disk('public')->delete($signatureRequest->file_path);
        }
        $signatureRequest->delete();
        return back()->with('message', 'Data Dihapus.');
    }
    
    public function clearAll() {
        if (auth()->user()->role !== 'admin') {
            return back()->with('error', 'Otoritas Ditolak.');
        }

        try {
            $requests = SignatureRequest::all();
            foreach ($requests as $req) {
                if ($req->file_path && Storage::disk('public')->exists($req->file_path)) {
                    Storage::disk('public')->delete($req->file_path);
                }
                $req->delete();
            }
            return back()->with('message', 'Seluruh data riwayat telah dibersihkan.');
        } catch (\Exception $e) {
            Log::error("Clear All Error: " . $e->getMessage());
            return back()->with('error', 'Gagal membersihkan data.');
        }
    }

    /**
     * Helper Otomatis: Mengonversi PDF versi tinggi (PDF 1.5+ / Compressed Object Streams)
     * menjadi PDF 1.4 kompatibel agar FPDI dapat membaca tanpa error parser.
     */
    private function normalizePdfForFpdi($inputPath)
    {
        if (!file_exists($inputPath)) {
            return $inputPath;
        }

        $outputPath = storage_path('app/public/temp_norm_' . time() . '_' . uniqid() . '.pdf');

        // 1. Coba konversi via Ghostscript (gs)
        $cmdGs = "gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=" . escapeshellarg($outputPath) . " " . escapeshellarg($inputPath);
        @exec($cmdGs, $outGs, $codeGs);

        if (file_exists($outputPath) && filesize($outputPath) > 0 && $codeGs === 0) {
            Log::info("Normalisasi PDF via Ghostscript (gs) Berhasil: " . $outputPath);
            return $outputPath;
        }

        // 2. Coba konversi via qpdf
        $cmdQpdf = "qpdf --qdf --object-streams=disable " . escapeshellarg($inputPath) . " " . escapeshellarg($outputPath);
        @exec($cmdQpdf, $outQpdf, $codeQpdf);

        if (file_exists($outputPath) && filesize($outputPath) > 0 && $codeQpdf === 0) {
            Log::info("Normalisasi PDF via qpdf Berhasil: " . $outputPath);
            return $outputPath;
        }

        // 3. Coba konversi via pdftk
        $cmdPdftk = "pdftk " . escapeshellarg($inputPath) . " output " . escapeshellarg($outputPath) . " uncompress";
        @exec($cmdPdftk, $outPdftk, $codePdftk);

        if (file_exists($outputPath) && filesize($outputPath) > 0 && $codePdftk === 0) {
            Log::info("Normalisasi PDF via pdftk Berhasil: " . $outputPath);
            return $outputPath;
        }

        return $inputPath;
    }
}