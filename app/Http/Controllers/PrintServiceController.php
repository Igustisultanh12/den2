<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use App\Models\Setting;
use App\Services\PrintService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PrintServiceController extends Controller
{
    protected PrintService $printService;

    public function __construct(PrintService $printService)
    {
        $this->printService = $printService;
    }

    /**
     * Halaman Utama Layanan Printing
     */
    public function index()
    {
        $user = Auth::user();

        $activeJob = PrintJob::with('user')
            ->where('status', 'printing')
            ->first();

        $queuedJobs = PrintJob::with('user')
            ->where('status', 'queued')
            ->orderBy('created_at', 'asc')
            ->get();

        $recentJobs = PrintJob::with('user')
            ->whereIn('status', ['completed', 'failed', 'cancelled'])
            ->latest()
            ->take(15)
            ->get();

        $printerSettings = [
            'brother_ip' => Setting::where('key', 'printer_brother_ip')->value('value') ?: '192.168.1.200',
            'brother_port' => (int)(Setting::where('key', 'printer_brother_port')->value('value') ?: 9100),
            'brother_name' => Setting::where('key', 'printer_brother_name')->value('value') ?: 'Brother Network Printer',

            'canon_ip' => Setting::where('key', 'printer_canon_ip')->value('value') ?: '192.168.1.201',
            'canon_port' => (int)(Setting::where('key', 'printer_canon_port')->value('value') ?: 9100),
            'canon_name' => Setting::where('key', 'printer_canon_name')->value('value') ?: 'Canon PIXMA G3010 Series',
            'canon_quality' => Setting::where('key', 'printer_canon_quality')->value('value') ?: 'very_high',

            // Legacy keys untuk kompatibilitas
            'printer_ip' => Setting::where('key', 'printer_brother_ip')->value('value') ?: '192.168.1.200',
            'printer_port' => (int)(Setting::where('key', 'printer_brother_port')->value('value') ?: 9100),
            'printer_name' => Setting::where('key', 'printer_brother_name')->value('value') ?: 'Brother Network Printer',
        ];

        return Inertia::render('PrintService/Index', [
            'activeJob' => $activeJob,
            'queuedJobs' => $queuedJobs,
            'recentJobs' => $recentJobs,
            'printerSettings' => $printerSettings,
            'stats' => [
                'total_queued' => $queuedJobs->count(),
                'total_today' => PrintJob::whereDate('created_at', today())->count(),
                'sheets_today' => (int)PrintJob::where('status', 'completed')->whereDate('completed_at', today())->sum('printed_sheets'),
            ]
        ]);
    }

    /**
     * Unggah berkas dokumen (PDF / DOCX), lakukan konversi, sisipkan lembar pemisah,
     * dan siapkan pratinjau sebelum dicetak
     */
    public function uploadAndPreview(Request $request)
    {
        $request->validate([
            'document_title' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,docx,doc|max:51200', // Maksimal 50MB
            'color_mode' => 'nullable|string|in:monochrome,color',
            'paper_size' => 'nullable|string|in:A4,F4,FOLIO,Folio,Letter,Legal',
            'print_density' => 'nullable|string|in:normal,light,dark,terang,pekat',
        ]);

        $file = $request->file('file');
        $originalFilename = $file->getClientOriginalName();
        $fileType = strtolower($file->getClientOriginalExtension());
        $colorMode = $request->input('color_mode', 'monochrome');
        $paperSize = strtoupper($request->input('paper_size', 'A4'));
        if ($paperSize === 'FOLIO') $paperSize = 'F4';
        $printDensity = $request->input('print_density', 'normal');
        $printerBrand = ($colorMode === 'color') ? 'canon' : 'brother';
        $printQuality = ($colorMode === 'color') ? 'very_high' : 'normal';

        // Simpan berkas asli
        $storedPath = $file->store('print_jobs/originals', 'local');
        $originalFullPath = Storage::disk('local')->path($storedPath);
        if (!file_exists($originalFullPath)) {
            $fallbackPath = storage_path('app/' . $storedPath);
            if (file_exists($fallbackPath)) {
                $originalFullPath = $fallbackPath;
            }
        }

        try {
            // 1. Dapatkan berkas PDF untuk pratinjau (konversi jika DOCX / DOC)
            $previewPdfPath = $this->printService->convertToPdf($originalFullPath, $fileType);

            // 2. Siapkan berkas siap cetak dengan menyisipkan 1 lembar kosong pemisah & ukuran kertas target
            $prepResult = $this->printService->preparePrintablePdf($previewPdfPath, $paperSize);
            if (!empty($prepResult['preview_pdf_path'])) {
                $previewPdfPath = $prepResult['preview_pdf_path'];
            }

            // 3. Tentukan IP Printer tujuan
            $printerIp = ($printerBrand === 'canon')
                ? (Setting::where('key', 'printer_canon_ip')->value('value') ?: '192.168.1.201')
                : (Setting::where('key', 'printer_brother_ip')->value('value') ?: '192.168.1.200');

            $job = PrintJob::create([
                'user_id' => Auth::id(),
                'document_title' => strtoupper($request->document_title),
                'original_filename' => $originalFilename,
                'file_type' => $fileType,
                'original_file_path' => $originalFullPath,
                'preview_pdf_path' => $previewPdfPath,
                'printable_pdf_path' => $prepResult['printable_pdf_path'],
                'total_pages' => $prepResult['total_pages'],
                'separator_pages' => $prepResult['separator_pages'],
                'total_sheets' => $prepResult['total_sheets'],
                'printed_sheets' => 0,
                'copies' => 1,
                'color_mode' => $colorMode,
                'paper_size' => $paperSize,
                'printer_brand' => $printerBrand,
                'print_quality' => $printQuality,
                'print_density' => $printDensity,
                'printer_ip' => $printerIp,
                'status' => 'draft',
            ]);

            return response()->json([
                'success' => true,
                'job' => $job,
                'preview_url' => route('printing.preview', $job->id),
                'message' => 'Berkas berhasil diproses. Silakan periksa pratinjau dokumen sebelum mencetak.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses berkas dokumen: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Mengalirkan streaming berkas PDF untuk penampil pratinjau di browser
     */
    public function streamPreviewPdf($id)
    {
        $job = PrintJob::findOrFail($id);

        // Hanya pemilik atau admin yang dapat melihat berkas
        if ($job->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $pdfPath = $job->preview_pdf_path;
        if (!$pdfPath || !file_exists($pdfPath)) {
            abort(404, 'Berkas pratinjau dokumen tidak ditemukan.');
        }

        return response()->file($pdfPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($pdfPath) . '"'
        ]);
    }

    /**
     * Konfirmasi cetak dokumen setelah personel memeriksa pratinjau
     */
    public function confirmPrint(Request $request, $id)
    {
        $job = PrintJob::findOrFail($id);

        if ($job->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            abort(403, 'Akses tidak diizinkan.');
        }

        if ($job->status !== 'draft') {
            return back()->with('error', 'Dokumen ini sudah pernah diproses.');
        }

        $colorMode = $request->input('color_mode', $job->color_mode ?: 'monochrome');
        $paperSize = strtoupper($request->input('paper_size', $job->paper_size ?: 'A4'));
        if ($paperSize === 'FOLIO') $paperSize = 'F4';
        $density = $request->input('print_density', $job->print_density ?: 'normal');
        $printerBrand = ($colorMode === 'color') ? 'canon' : 'brother';
        $printQuality = ($colorMode === 'color') ? 'very_high' : 'normal';

        $printerIp = ($printerBrand === 'canon')
            ? (Setting::where('key', 'printer_canon_ip')->value('value') ?: '192.168.1.201')
            : (Setting::where('key', 'printer_brother_ip')->value('value') ?: '192.168.1.200');

        // Jika ukuran kertas disesuaikan ulang saat konfirmasi pratinjau, perbarui printable pdf
        $printablePath = $job->printable_pdf_path;
        if ($paperSize !== $job->paper_size && $job->preview_pdf_path && file_exists($job->preview_pdf_path)) {
            $prepResult = $this->printService->preparePrintablePdf($job->preview_pdf_path, $paperSize);
            $printablePath = $prepResult['printable_pdf_path'];
        }

        $job->update([
            'color_mode' => $colorMode,
            'paper_size' => $paperSize,
            'printer_brand' => $printerBrand,
            'print_quality' => $printQuality,
            'print_density' => $density,
            'printer_ip' => $printerIp,
            'printable_pdf_path' => $printablePath,
            'status' => 'queued',
            'error_message' => null
        ]);

        // Picu pemrosesan antrean
        $this->printService->processQueue();

        return back()->with('success', 'Dokumen berhasil dimasukkan ke dalam antrean cetak.');
    }

    /**
     * Batalkan dokumen dari antrean atau proses cetak
     */
    public function cancelJob($id)
    {
        try {
            $job = PrintJob::findOrFail($id);

            if ($job->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
                abort(403, 'Akses tidak diizinkan.');
            }

            if ($job->status === 'queued' || $job->status === 'draft' || $job->status === 'printing') {
                $this->printService->cleanupPhysicalFiles($job);
                $job->update([
                    'status' => 'cancelled',
                    'completed_at' => now(),
                ]);

                // Jika yang dibatalkan adalah job yang sedang dicetak, lepaskan lock dan proses antrean berikutnya
                Cache::forget('print_service_queue_lock');
                $this->printService->processQueue();

                return back()->with('success', 'Pengajuan cetak dokumen berhasil dibatalkan dan berkas fisik dibersihkan.');
            }

            return back()->with('error', 'Dokumen yang telah selesai tidak dapat dibatalkan.');
        } catch (\Throwable $e) {
            Log::error('Gagal membatalkan dokumen cetak: ' . $e->getMessage(), [
                'job_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Gagal membatalkan dokumen: ' . $e->getMessage());
        }
    }

    /**
     * Endpoint data status antrean untuk pembaruan latar belakang secara berkala
     */
    public function getQueueStatus()
    {
        // Perbarui progres lembar cetak aktif secara bertahap
        $this->printService->tickActiveJobProgress();

        $activeJob = PrintJob::with('user')
            ->where('status', 'printing')
            ->first();

        $queuedJobs = PrintJob::with('user')
            ->where('status', 'queued')
            ->orderBy('created_at', 'asc')
            ->get();

        $recentJobs = PrintJob::with('user')
            ->whereIn('status', ['completed', 'failed', 'cancelled'])
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'active_job' => $activeJob,
            'queued_jobs' => $queuedJobs,
            'recent_jobs' => $recentJobs,
            'stats' => [
                'total_queued' => $queuedJobs->count(),
                'total_today' => PrintJob::whereDate('created_at', today())->count(),
                'sheets_today' => (int)PrintJob::where('status', 'completed')->whereDate('completed_at', today())->sum('printed_sheets'),
            ]
        ]);
    }

    /**
     * Tes koneksi soket IP Printer (Brother atau Canon G3010) (Khusus Admin)
     */
    public function testPrinterConnection(Request $request)
    {
        if (!Auth::user()->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Otoritas ditolak.'], 403);
        }

        $target = $request->input('target', 'brother');
        $ip = $request->input('ip');
        $port = (int)$request->input('port', 9100);

        $result = $this->printService->testPrinterConnection($ip, $port, $target);
        return response()->json($result);
    }

    /**
     * Pembaruan pengaturan IP Printer Jaringan Brother & Canon G3010 (Khusus Admin)
     */
    public function updatePrinterSettings(Request $request)
    {
        if (!Auth::user()->isAdmin()) {
            abort(403, 'Otoritas ditolak.');
        }

        $request->validate([
            'target' => 'nullable|string|in:brother,canon,both',
            'brother_ip' => 'nullable|string|max:100',
            'brother_port' => 'nullable|integer|min:1|max:65535',
            'brother_name' => 'nullable|string|max:100',
            'canon_ip' => 'nullable|string|max:100',
            'canon_port' => 'nullable|integer|min:1|max:65535',
            'canon_name' => 'nullable|string|max:100',
            'printer_ip' => 'nullable|string|max:100',
            'printer_port' => 'nullable|integer|min:1|max:65535',
            'printer_name' => 'nullable|string|max:100',
        ]);

        // Brother settings
        $brotherIp = $request->input('brother_ip', $request->input('printer_ip'));
        $brotherPort = $request->input('brother_port', $request->input('printer_port'));
        $brotherName = $request->input('brother_name', $request->input('printer_name'));

        if ($brotherIp) {
            Setting::updateOrCreate(['key' => 'printer_brother_ip'], ['value' => trim($brotherIp)]);
        }
        if ($brotherPort) {
            Setting::updateOrCreate(['key' => 'printer_brother_port'], ['value' => (string)$brotherPort]);
        }
        if ($brotherName) {
            Setting::updateOrCreate(['key' => 'printer_brother_name'], ['value' => trim($brotherName)]);
        }

        // Canon settings
        if ($request->filled('canon_ip')) {
            Setting::updateOrCreate(['key' => 'printer_canon_ip'], ['value' => trim($request->canon_ip)]);
        }
        if ($request->filled('canon_port')) {
            Setting::updateOrCreate(['key' => 'printer_canon_port'], ['value' => (string)$request->canon_port]);
        }
        if ($request->filled('canon_name')) {
            Setting::updateOrCreate(['key' => 'printer_canon_name'], ['value' => trim($request->canon_name)]);
        }

        return back()->with('success', 'Konfigurasi Printer Jaringan (Brother & Canon G3010) berhasil diperbarui.');
    }
}
