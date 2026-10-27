<?php

namespace App\Http\Controllers;

use App\Models\Pc;
use App\Models\Backup;
use App\Models\AccessRequest; 
use App\Models\OfficeNetwork;
use App\Models\User;
use App\Models\BackupShare;
use App\Services\ArwService;
use App\Services\FileSecurityService;
use App\Services\StreamingZipService;
use App\Services\ThumbnailService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache; 
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ZipArchive;

class BackupController extends Controller
{
    /**
     * RADAR PUSAT & DASHBOARD BACKUP
     */
    public function index()
    {
        $user = Auth::user();
        // Kalibrasi Nama Komandan agar Bypass Terdeteksi
        $isAdmin = $user->role === 'admin' || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';

        return Inertia::render('Backup/Index', [
            'myPcs' => Pc::where('user_id', $user->id)->get()->map(fn($pc) => $this->formatPcData($pc)),
            'publicPcs' => Pc::where('user_id', '!=', $user->id)->with('user:id,name,pangkat,nrp')->get()->map(fn($pc) => $this->formatPcData($pc)),
            
            'authRequest' => AccessRequest::where('user_id', $user->id)->latest()->first(),
            
            // Mengambil antrean yang statusnya pending atau approved (belum completed)
            'pendingRequests' => $isAdmin ? AccessRequest::with('user')->where('status', '!=', 'completed')->latest()->get() : [],
            
            'networks' => $isAdmin ? OfficeNetwork::latest()->get() : [],
            'globalStats' => $isAdmin ? [
                'total_pcs' => Pc::count(),
                'total_storage' => $this->formatBytes(Backup::sum('file_size')),
            ] : null,
            'allUsers' => $isAdmin ? User::select('id', 'name', 'pangkat', 'nrp')->orderBy('name')->get() : [],
            'isAdmin' => $isAdmin
        ]);
    }

    /**
     * ALUR 1: PENGAJUAN AKSES OLEH PERSONEL
     * BYPASS TOTAL: Bebas dari dependensi Facade Str
     */
    public function requestAccess(Request $request)
    {
        $user = Auth::user();
        if (Pc::where('user_id', $user->id)->exists()) {
            return back()->with('error', 'Status aktif.');
        }
        $existing = AccessRequest::where('user_id', $user->id)->where('status', 'pending')->first();
        if ($existing) return back()->with('info', ' Pengajuan anda masih dalam antrian.');

        // Algoritma Acak Native PHP - Kebal Terhadap Kerusakan Autoloader Framework
        $pool = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $randomString = substr(str_shuffle($pool), 0, 5);

        AccessRequest::create([
            'user_id' => $user->id,
            'pc_name' => 'PC-' . $randomString,
            'status' => 'pending'
        ]);

        return back()->with('success', 'Pengajuan akses telah dikirim.');
    }

    /**
     * ALUR 2: PERSETUJUAN OLEH ADMIN (DENGAN KODE VISUAL VIA PORT 3000)
     */
    public function approveAccess($id)
    {
        if (Auth::user()->role !== 'admin' && Auth::user()->name !== 'I Gusti Sultan H.A, A.Md.Kom') {
            return abort(403, 'Anda tidak memiliki otoritas menyetujui.');
        }

        $access = AccessRequest::with('user')->findOrFail($id);
        $code = (string) rand(100000, 999999);
        
        $access->update([
            'status' => 'approved',
            'verification_code' => $code,
            'expires_at' => now()->addMinutes(30)
        ]);

        try {
            Http::timeout(5)->post('http://localhost:3000/send-message', [
                'number' => $access->user->whatsapp_number ?? $access->user->phone ?? $access->user->identity_number,
                'message' => "SINDEN RADAR:\nPengajuan akses disetujui.\n\nKODE AKSES: *{$code}*\nBerlaku: 30 Menit.\n\nSegera masukkan kode di Dashboard."
            ]);
        } catch (\Exception $e) { }

        return back()->with([
            'success' => 'Otoritas diberikan! Kode dikirim via WhatsApp.',
            'generatedCode' => $code 
        ]);
    }

    /**
     * ALUR 3: VERIFIKASI KODE & PEMBUATAN PC OTOMATIS
     */
    public function verifyAccess(Request $request)
    {
        $request->validate(['code' => 'required']);
        $user = Auth::user();
        $access = AccessRequest::where('user_id', $user->id)->where('verification_code', $request->code)->where('status', 'approved')->where('expires_at', '>', now())->first();

        if (!$access) return back()->with('error', 'Kode salah atau masa berlaku (30 Menit) telah habis!');

        $pool = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $randomHw = substr(str_shuffle($pool), 0, 8);

        Pc::create([
            'user_id' => $user->id,
            'pc_name' => 'PC ' . $user->name,
            'hardware_id' => 'HW-' . $randomHw,
            'current_usage' => 0,
            'max_quota' => 214748364800 // Kunci Jatah Kuota 200GB
        ]);
        
        $access->update(['status' => 'completed']);
        return back()->with('success', 'Jatah PC 200GB berhasil Diberikan');
    }

    /**
     * FEATURE: DIGITAL FILE EXPLORER (DENGAN RADAR PENCARIAN GLOBAL)
     */
    public function explore($id, Request $request)
    {
        $pc = Pc::with('user')->findOrFail($id);
        $parentId = $request->query('folder'); 
        $search = $request->query('search'); 

        $query = Backup::where('pc_id', $id);

        if ($search) {
            $contents = $query->where('file_name', 'like', '%' . $search . '%')
                              ->orderBy('is_folder', 'desc')
                              ->get();
        } else {
            $contents = $query->where('parent_id', $parentId)
                              ->orderBy('is_folder', 'desc')
                              ->orderBy('file_name', 'asc')
                              ->get();
        }

        BackupShare::ensureSchema();

        $folderShares = BackupShare::where('pc_id', $id)
            ->whereNotNull('backup_id')
            ->get()
            ->keyBy('backup_id');

        $contents = $contents->map(function($item) use ($folderShares) {
            if ($item->is_folder) {
                $totalSize = $this->getFolderSize($item->id);
                $item->size_human = $totalSize > 0 ? $this->formatBytes($totalSize) : '0 B';
                $item->file_size = $totalSize;

                $share = $folderShares->get($item->id);
                $item->share_info = $share ? [
                    'id' => $share->id,
                    'is_active' => (bool)$share->is_active,
                    'allow_guest' => isset($share->allow_guest) ? (bool)$share->allow_guest : true,
                    'guest_duration_hours' => (int)($share->guest_duration_hours ?: 24),
                    'guest_expires_at' => $share->guest_expires_at ? $share->guest_expires_at->toIso8601String() : null,
                    'guest_expires_at_human' => $share->guest_expires_at ? $share->guest_expires_at->format('d/m/Y H:i') : null,
                    'is_guest_expired' => $share->isGuestExpired(),
                    'share_token' => $share->share_token,
                    'pin' => $share->pin,
                    'share_url' => route('backup.shared.view', $share->share_token),
                    'guest_share_url' => route('backup.shared.guest-view', $share->share_token),
                    'access_count' => $share->access_count,
                    'last_accessed_at' => $share->last_accessed_at ? Carbon::parse($share->last_accessed_at)->format('d M Y H:i') : null,
                    'recent_guests' => $share->guestLogs()->take(10)->get()->map(fn($g) => [
                        'nrp' => $g->nrp,
                        'pangkat' => $g->pangkat,
                        'nama' => $g->nama,
                        'satuan' => $g->satuan,
                        'whatsapp' => $g->whatsapp,
                        'expires_at_human' => $g->expires_at ? $g->expires_at->format('d/m/Y H:i') : null,
                        'is_expired' => $g->expires_at ? now()->greaterThan($g->expires_at) : false,
                        'time_human' => $g->accessed_at ? $g->accessed_at->format('d/m/Y H:i') : $g->created_at->format('d/m/Y H:i'),
                        'ip' => $g->ip_address,
                    ]),
                ] : null;
            } else {
                $item->size_human = $this->formatBytes($item->file_size);
                $item->share_info = null;
            }
            
            $item->date_human = Carbon::parse($item->created_at)->format('d M Y H:i');
            $isArw = ArwService::isArw($item->file_type ?: $item->file_name);
            $ext = strtolower(pathinfo($item->file_name, PATHINFO_EXTENSION));
            $isImg = !$item->is_folder && in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg']);
            $item->is_arw = $isArw;
            if ($isArw) {
                $item->preview_url = route('backup.preview-arw', $item->id);
                $item->thumbnail_url = route('backup.thumbnail', $item->id);
                $item->download_jpg_url = route('backup.download-arw-jpg', $item->id);
            } else {
                $item->preview_url = !$item->is_folder ? route('backup.preview-file', $item->id) : null;
                $item->thumbnail_url = $isImg ? route('backup.thumbnail', $item->id) : null;
                $item->download_jpg_url = null;
            }
            $item->is_secured = true;
            return $item;
        });

        $breadcrumbs = [];
        $tempFolderId = $parentId;

        while ($tempFolderId) {
            $folder = Backup::find($tempFolderId);
            if ($folder) {
                array_unshift($breadcrumbs, [
                    'name' => $folder->file_name,
                    'id' => $folder->id
                ]);
                $tempFolderId = $folder->parent_id;
            } else {
                $tempFolderId = null;
            }
        }

        $currentShare = BackupShare::where('pc_id', $id)
            ->where(function ($q) use ($parentId) {
                if ($parentId) {
                    $q->where('backup_id', $parentId);
                } else {
                    $q->whereNull('backup_id');
                }
            })->first();

        $currentShareInfo = $currentShare ? [
            'id' => $currentShare->id,
            'is_active' => (bool)$currentShare->is_active,
            'allow_guest' => isset($currentShare->allow_guest) ? (bool)$currentShare->allow_guest : true,
            'guest_duration_hours' => (int)($currentShare->guest_duration_hours ?: 24),
            'guest_expires_at' => $currentShare->guest_expires_at ? $currentShare->guest_expires_at->toIso8601String() : null,
            'guest_expires_at_human' => $currentShare->guest_expires_at ? $currentShare->guest_expires_at->format('d/m/Y H:i') : null,
            'is_guest_expired' => $currentShare->isGuestExpired(),
            'share_token' => $currentShare->share_token,
            'pin' => $currentShare->pin,
            'share_url' => route('backup.shared.view', $currentShare->share_token),
            'guest_share_url' => route('backup.shared.guest-view', $currentShare->share_token),
            'access_count' => $currentShare->access_count,
            'last_accessed_at' => $currentShare->last_accessed_at ? Carbon::parse($currentShare->last_accessed_at)->format('d M Y H:i') : null,
            'recent_guests' => $currentShare->guestLogs()->take(10)->get()->map(fn($g) => [
                'nrp' => $g->nrp,
                'pangkat' => $g->pangkat,
                'nama' => $g->nama,
                'satuan' => $g->satuan,
                'whatsapp' => $g->whatsapp,
                'expires_at_human' => $g->expires_at ? $g->expires_at->format('d/m/Y H:i') : null,
                'is_expired' => $g->expires_at ? now()->greaterThan($g->expires_at) : false,
                'time_human' => $g->accessed_at ? $g->accessed_at->format('d/m/Y H:i') : $g->created_at->format('d/m/Y H:i'),
                'ip' => $g->ip_address,
            ]),
        ] : null;

        return Inertia::render('Backup/Explore', [
            'pc' => $this->formatPcData($pc),
            'contents' => $contents,
            'currentFolderId' => $parentId,
            'breadcrumbs' => $breadcrumbs,
            'searchQuery' => $search,
            'currentShare' => $currentShareInfo,
            'isAdmin' => Auth::user()->role === 'admin' || Auth::user()->name === 'I Gusti Sultan H.A, A.Md.Kom',
        ]);
    }

    /**
     * PROTOKOL REKURSIF: Hitung total file di dalam folder dan sub-foldernya
     */
    private function getFolderSize($folderId)
    {
        $total = 0;
        $items = Backup::where('parent_id', $folderId)->get();

        foreach ($items as $item) {
            if ($item->is_folder) {
                $total += $this->getFolderSize($item->id);
            } else {
                $total += $item->file_size;
            }
        }
        return $total;
    }

    /**
     * OPERASI KONVERSI OFFICE KE PDF DENGAN DEKRIPSI & CACHING INSTAN
     */
    public function viewOffice($id)
    {
        $backup = Backup::findOrFail($id);

        $user = Auth::user();
        $pc = Pc::findOrFail($backup->pc_id);
        if ($pc->user_id !== $user->id && $user->role !== 'admin' && $user->name !== 'I Gusti Sultan H.A, A.Md.Kom') {
            abort(403, 'Akses Ditolak: Anda tidak memiliki otoritas atas berkas PC ini.');
        }

        if ($backup->is_folder) {
            abort(400, 'Folder tidak dapat dipratinjau.');
        }

        $fullPath = FileSecurityService::verifySafeStoragePath($backup->file_path);
        if (!file_exists($fullPath)) {
            abort(404, 'Berkas fisik tidak ditemukan di server.');
        }

        $cacheDir = storage_path('app/public/office_cache');
        if (!file_exists($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $cacheKey = 'office_' . $backup->id . '_' . $backup->file_size . '.pdf';
        $cachedPdf = $cacheDir . '/' . $cacheKey;

        $ext = strtolower(pathinfo($backup->file_name, PATHINFO_EXTENSION) ?: $backup->file_type ?: 'docx');
        if ($ext === 'pdf') {
            return FileSecurityService::streamDecryptedInline($fullPath, $backup->file_name);
        }

        // Jika cache PDF sudah ada dan valid, langsung kirim dengan cepat
        if (file_exists($cachedPdf) && filesize($cachedPdf) > 100) {
            return response()->file($cachedPdf, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . pathinfo($backup->file_name, PATHINFO_FILENAME) . '.pdf"',
                'X-Frame-Options' => 'SAMEORIGIN',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        // Dekripsi berkas sementara dengan ekstensi aslinya agar LibreOffice mengenali formatnya
        $tempDecDir = storage_path('app/temp_dec');
        if (!file_exists($tempDecDir)) {
            @mkdir($tempDecDir, 0777, true);
        }
        $tempPlainFile = $tempDecDir . '/dec_' . uniqid() . '.' . $ext;

        if (FileSecurityService::isEncrypted($fullPath)) {
            $createdTmp = FileSecurityService::createDecryptedTempFile($fullPath);
            if ($createdTmp && file_exists($createdTmp)) {
                @rename($createdTmp, $tempPlainFile);
            } else {
                @copy($fullPath, $tempPlainFile);
            }
        } else {
            @copy($fullPath, $tempPlainFile);
        }

        try {
            $tempOutDir = storage_path('app/temp_out_' . uniqid());
            @mkdir($tempOutDir, 0777, true);

            $binary = 'libreoffice';
            $userProfile = '-env:UserInstallation=file:///tmp/libo_user_' . uniqid();
            $prefix = 'export HOME=/tmp && ';

            if (PHP_OS_FAMILY === 'Windows') {
                $prefix = '';
                $userProfile = '';
                $winPaths = [
                    'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
                    'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
                ];
                $binary = 'soffice';
                foreach ($winPaths as $wp) {
                    if (file_exists($wp)) {
                        $binary = '"' . $wp . '"';
                        break;
                    }
                }
            }

            $command = "{$prefix}{$binary} --headless --invisible --nologo --nodefault --nofirststartwizard {$userProfile} --convert-to pdf --outdir " . escapeshellarg($tempOutDir) . " " . escapeshellarg($tempPlainFile) . " 2>&1";
            @exec($command, $out, $ret);

            $generatedPdfs = glob($tempOutDir . '/*.pdf');
            if (empty($generatedPdfs) && PHP_OS_FAMILY !== 'Windows') {
                $commandSoffice = "export HOME=/tmp && soffice --headless --invisible --nologo --nodefault --nofirststartwizard {$userProfile} --convert-to pdf --outdir " . escapeshellarg($tempOutDir) . " " . escapeshellarg($tempPlainFile) . " 2>&1";
                @exec($commandSoffice, $outSoffice, $retSoffice);
                $generatedPdfs = glob($tempOutDir . '/*.pdf');
            }

            if (file_exists($tempPlainFile)) {
                @unlink($tempPlainFile);
            }

            if (!empty($generatedPdfs) && filesize($generatedPdfs[0]) > 100) {
                copy($generatedPdfs[0], $cachedPdf);
                @unlink($generatedPdfs[0]);
                @rmdir($tempOutDir);

                return response()->file($cachedPdf, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . pathinfo($backup->file_name, PATHINFO_FILENAME) . '.pdf"',
                    'X-Frame-Options' => 'SAMEORIGIN'
                ]);
            }
            @rmdir($tempOutDir);
        } catch (\Exception $e) {
            Log::warning("Gagal konversi office ID {$id} ke PDF: " . $e->getMessage());
        } finally {
            if (file_exists($tempPlainFile)) {
                @unlink($tempPlainFile);
            }
        }

        // Jangan redirect ke unduhan di dalam iframe! Kembalikan tampilan HTML informatif agar iframe selesai memuat
        return response('<div style="font-family:sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;background:#f8fafc;color:#1e293b;text-align:center;padding:24px;">' .
            '<svg style="width:48px;height:48px;color:#64748b;margin-bottom:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>' .
            '<h3 style="margin:0 0 8px 0;font-size:15px;font-weight:800;text-transform:uppercase;">Pratinjau Server Belum Tersedia</h3>' .
            '<p style="font-size:12px;color:#64748b;max-width:380px;margin:0 0 16px 0;line-height:1.5;">Dokumen ini belum dapat dikonversi ke PDF otomatis di server. Silakan klik tombol di bawah untuk mengunduh berkas langsung.</p>' .
            '<a href="' . route('backup.download', $backup->id) . '" style="padding:10px 20px;background:#059669;color:#fff;text-decoration:none;border-radius:10px;font-weight:700;font-size:12px;display:inline-flex;align-items:center;gap:8px;">Unduh Berkas Langsung</a>' .
            '</div>', 200, ['Content-Type' => 'text/html']);
    }

    /**
     * PRATINJAU DOKUMEN / MEDIA TEROTENTIKASI (STREAMING DEKRIPSI ON-THE-FLY)
     * PROTEKSI: Ditolak jika dibuka langsung di bilah peramban (New Tab / Hotlink)
     */
    public function previewFile(Request $request, $id)
    {
        if ($request->header('Sec-Fetch-Dest') === 'document') {
            abort(403, 'Akses Ditolak: Pratinjau berkas hanya diizinkan melalui antarmuka aplikasi internal.');
        }

        $backup = Backup::findOrFail($id);

        $user = Auth::user();
        $pc = Pc::findOrFail($backup->pc_id);
        if ($pc->user_id !== $user->id && $user->role !== 'admin' && $user->name !== 'I Gusti Sultan H.A, A.Md.Kom') {
            abort(403, 'Akses Ditolak: Anda tidak memiliki otoritas atas berkas PC ini.');
        }

        if ($backup->is_folder) {
            abort(400, 'Folder tidak dapat dipratinjau.');
        }

        $fullPath = FileSecurityService::verifySafeStoragePath($backup->file_path);
        if (!file_exists($fullPath)) {
            abort(404, 'Berkas fisik tidak ditemukan di server.');
        }

        if (ArwService::isArw($backup->file_type ?: $backup->file_name)) {
            return ArwService::previewResponse($fullPath, $backup->file_name);
        }

        return FileSecurityService::streamDecryptedInline($fullPath, $backup->file_name);
    }

    /**
     * PRATINJAU FORMAT GAMBAR SONY RAW (.ARW) SEBAGAI JPG HD
     * PROTEKSI: Ditolak jika dibuka langsung di bilah peramban (New Tab / Hotlink)
     */
    public function previewArw(Request $request, $id)
    {
        if ($request->header('Sec-Fetch-Dest') === 'document') {
            abort(403, 'Akses Ditolak: Pratinjau Sony RAW hanya diizinkan melalui antarmuka aplikasi internal.');
        }

        $backup = Backup::findOrFail($id);

        $user = Auth::user();
        $pc = Pc::findOrFail($backup->pc_id);
        if ($pc->user_id !== $user->id && $user->role !== 'admin' && $user->name !== 'I Gusti Sultan H.A, A.Md.Kom') {
            abort(403, 'Akses Ditolak: Anda tidak memiliki otoritas atas berkas PC ini.');
        }

        $fullPath = FileSecurityService::verifySafeStoragePath($backup->file_path);

        if (!file_exists($fullPath)) {
            abort(404, 'Berkas fisik Sony RAW tidak ditemukan di server penyimpanan.');
        }

        return ArwService::previewResponse($fullPath, $backup->file_name);
    }

    /**
     * THUMBNAIL TERKOMPRESI RINGAN & CEPAT (DISK CACHED)
     */
    public function thumbnail(Request $request, $id)
    {
        if ($request->header('Sec-Fetch-Dest') === 'document') {
            abort(403, 'Akses Ditolak.');
        }

        $backup = Backup::findOrFail($id);

        $user = Auth::user();
        $pc = Pc::findOrFail($backup->pc_id);
        if ($pc->user_id !== $user->id && $user->role !== 'admin' && $user->name !== 'I Gusti Sultan H.A, A.Md.Kom') {
            abort(403, 'Akses Ditolak.');
        }

        if ($backup->is_folder) {
            abort(400, 'Folder tidak memiliki thumbnail.');
        }

        $fullPath = FileSecurityService::verifySafeStoragePath($backup->file_path);
        if (!file_exists($fullPath)) {
            abort(404, 'Berkas fisik tidak ditemukan.');
        }

        $isArw = ArwService::isArw($backup->file_type ?: $backup->file_name);
        return ThumbnailService::getThumbnailResponse($fullPath, $backup->file_name, $isArw);
    }

    /**
     * KONVERSI SONY RAW (.ARW) MENJADI BERKAS .JPG KUALITAS TINGGI DAN SIMPAN DI FOLDER
     */
    public function convertArw(Request $request, $id)
    {
        try {
            $backup = Backup::findOrFail($id);

            if (!ArwService::isArw($backup->file_type ?: $backup->file_name)) {
                return response()->json(['status' => 'error', 'message' => 'Berkas bukan format Sony RAW (.ARW).'], 400);
            }

            $newBackup = ArwService::convertAndSaveToBackup($backup);

            if (!$newBackup) {
                return response()->json(['status' => 'error', 'message' => 'Gagal mengonversi berkas Sony RAW (.ARW) ke JPG.'], 500);
            }

            $newBackup->size_human = $this->formatBytes($newBackup->file_size);
            $newBackup->date_human = Carbon::parse($newBackup->created_at)->format('d M Y H:i');
            $newBackup->preview_url = asset('storage/' . $newBackup->file_path);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'success',
                    'message' => "Berkas {$backup->file_name} berhasil dikonversi menjadi {$newBackup->file_name}.",
                    'item' => $newBackup
                ]);
            }

            return back()->with('success', "Berkas berhasil dikonversi menjadi {$newBackup->file_name}.");
        } catch (\Exception $e) {
            Log::error("Gagal convert ARW ID {$id}: " . $e->getMessage());
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * UNDUH LANGSUNG BERKAS SONY RAW (.ARW) SEBAGAI FORMAT .JPG KUALITAS TINGGI
     */
    public function downloadArwJpg($id)
    {
        $backup = Backup::findOrFail($id);

        if (!ArwService::isArw($backup->file_type ?: $backup->file_name)) {
            abort(400, 'Berkas bukan format Sony RAW (.ARW).');
        }

        return ArwService::downloadConvertedJpg($backup);
    }

    /**
     * FEATURE: BUAT FOLDER BARU
     */
    public function createFolder(Request $request)
    {
        $request->validate([
            'pc_id' => 'required',
            'folder_name' => 'required|string|max:100',
            'parent_id' => 'nullable'
        ]);

        Backup::create([
            'pc_id' => $request->pc_id,
            'parent_id' => $request->parent_id,
            'file_name' => strtoupper($request->folder_name),
            'is_folder' => true,      
            'file_path' => 'folder', 
            'file_size' => 0,
            'file_type' => 'folder'
        ]);

        return back()->with('success', 'Folder berhasil dibuat.');
    }

    /**
     * FEATURE: RENAME BERKAS/FOLDER
     */
    public function rename(Request $request, $id)
    {
        $request->validate(['new_name' => 'required|string|max:255']);
        $item = Backup::findOrFail($id);
        $name = $item->is_folder ? strtoupper($request->new_name) : $request->new_name;
        $item->update(['file_name' => $name]);
        return back()->with('success', 'Identitas berkas berhasil diperbarui.');
    }

    /**
     * SINKRONISASI STRUKTUR FOLDER & BERKAS HASIL EKSTRAKSI KE BASIS DATA
     */
    private function syncExtractedTree($diskDir, $dbSubPath, $parentId, $pcId, &$totalExtracted, $cancelKey, $logKey, $cacheKey)
    {
        $items = @scandir($diskDir);
        if (!$items) return true;

        $fileCount = max(1, count($items) - 2);
        $idx = 0;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (Cache::has($cancelKey)) {
                return false;
            }

            $itemPath = $diskDir . DIRECTORY_SEPARATOR . $item;
            $itemSubPath = $dbSubPath . '/' . $item;

            if (is_dir($itemPath)) {
                $folderRecord = Backup::create([
                    'pc_id' => $pcId,
                    'parent_id' => $parentId,
                    'file_name' => strtoupper($item),
                    'is_folder' => true,
                    'file_path' => $itemSubPath,
                    'file_size' => 0,
                    'file_type' => 'folder'
                ]);
                $totalExtracted++;
                Cache::put($logKey, "Mendaftar folder: " . $item, 600);
                $res = $this->syncExtractedTree($itemPath, $itemSubPath, $folderRecord->id, $pcId, $totalExtracted, $cancelKey, $logKey, $cacheKey);
                if ($res === false) return false;
            } else {
                $fSize = file_exists($itemPath) ? filesize($itemPath) : 0;
                $fExt = strtolower(pathinfo($item, PATHINFO_EXTENSION) ?: 'file');
                Backup::create([
                    'pc_id' => $pcId,
                    'parent_id' => $parentId,
                    'file_name' => $item,
                    'is_folder' => false,
                    'file_path' => $itemSubPath,
                    'file_size' => $fSize,
                    'file_type' => $fExt,
                ]);
                $totalExtracted++;
                Cache::put($logKey, "Mendaftar berkas: " . $item, 600);
            }

            $idx++;
            $progress = min(98, 50 + (int)(($idx / $fileCount) * 48));
            Cache::put($cacheKey, $progress, 600);
        }
        return true;
    }

    /**
     * FEATURE: EKSTRAK ARSIP STANDARD (ZIP / RAR / 7Z / TAR)
     */
    public function extract($id)
    {
        $backup = Backup::findOrFail($id);
        $user = Auth::user();

        $pc = Pc::findOrFail($backup->pc_id);
        if ($pc->user_id !== $user->id && $user->role !== 'admin' && $user->name !== 'I Gusti Sultan H.A, A.Md.Kom') {
            abort(403, 'Akses Ditolak: Anda tidak memiliki otoritas atas berkas PC ini.');
        }

        $fullPath = FileSecurityService::verifySafeStoragePath($backup->file_path);
        if (!file_exists($fullPath)) {
            return back()->with('error', 'Berkas fisik arsip tidak ditemukan.');
        }

        $ext = strtolower(pathinfo($backup->file_name, PATHINFO_EXTENSION) ?: $backup->file_type ?: 'zip');
        $archiveExts = ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz'];
        if (!in_array($ext, $archiveExts)) {
            return back()->with('error', 'Hanya berkas arsip (ZIP / RAR / 7Z / TAR) yang dapat diekstrak.');
        }

        $folderName = 'EXTRACTED_' . strtoupper(pathinfo($backup->file_name, PATHINFO_FILENAME));
        $extractSubPath = 'backups/' . $backup->pc_id . '/' . time() . '_' . $folderName;
        $fullExtractPath = storage_path('app/public/' . $extractSubPath);
        if (!file_exists($fullExtractPath)) {
            @mkdir($fullExtractPath, 0777, true);
        }

        $tempArchive = null;
        if (FileSecurityService::isEncrypted($fullPath)) {
            $tempArchive = FileSecurityService::createDecryptedTempFile($fullPath);
            $archiveFile = $tempArchive ?: $fullPath;
        } else {
            $archiveFile = $fullPath;
        }

        $extracted = false;
        try {
            if ($ext === 'zip') {
                $zip = new \ZipArchive;
                if ($zip->open($archiveFile) === TRUE) {
                    $zip->extractTo($fullExtractPath);
                    $zip->close();
                    $extracted = true;
                }
            }

            if (!$extracted && $ext === 'rar' && class_exists('\RarArchive')) {
                try {
                    $rar = @\RarArchive::open($archiveFile);
                    if ($rar !== false) {
                        foreach ($rar->getEntries() as $entry) {
                            $entry->extract($fullExtractPath);
                        }
                        $rar->close();
                        $extracted = true;
                    }
                } catch (\Exception $e) {}
            }

            if (!$extracted) {
                $commands = [
                    "7z x -y -o" . escapeshellarg($fullExtractPath) . " " . escapeshellarg($archiveFile) . " 2>&1",
                    "7za x -y -o" . escapeshellarg($fullExtractPath) . " " . escapeshellarg($archiveFile) . " 2>&1",
                    "unrar x -y -o+ " . escapeshellarg($archiveFile) . " " . escapeshellarg($fullExtractPath . DIRECTORY_SEPARATOR) . " 2>&1",
                    "tar -xf " . escapeshellarg($archiveFile) . " -C " . escapeshellarg($fullExtractPath) . " 2>&1",
                ];
                foreach ($commands as $cmd) {
                    @exec($cmd);
                    $scanned = @scandir($fullExtractPath);
                    if ($scanned && count($scanned) > 2) {
                        $extracted = true;
                        break;
                    }
                }
            }

            if ($extracted) {
                $rootFolder = Backup::create([
                    'pc_id' => $backup->pc_id,
                    'parent_id' => $backup->parent_id,
                    'file_name' => $folderName,
                    'is_folder' => true,
                    'file_path' => $extractSubPath,
                    'file_size' => 0,
                    'file_type' => 'folder'
                ]);

                $total = 0;
                $dummyCancel = 'dummy_cancel_' . uniqid();
                $dummyLog = 'dummy_log_' . uniqid();
                $dummyCache = 'dummy_cache_' . uniqid();
                $this->syncExtractedTree($fullExtractPath, $extractSubPath, $rootFolder->id, $backup->pc_id, $total, $dummyCancel, $dummyLog, $dummyCache);

                return back()->with('success', "Berhasil mengekstrak {$total} item ke folder {$folderName}.");
            }

            return back()->with('error', 'Gagal mengekstrak berkas arsip. Pastikan arsip tidak rusak.');
        } finally {
            if ($tempArchive && file_exists($tempArchive)) {
                @unlink($tempArchive);
            }
        }
    }

    /**
     * FEATURE: EKSTRAKSI CERDAS DENGAN RADAR PROGRES (MENDUKUNG ZIP & RAR BESAR)
     */
    public function startExtract(Request $request, $id)
    {
        $backup = Backup::findOrFail($id);
        $user = Auth::user();

        $pc = Pc::findOrFail($backup->pc_id);
        if ($pc->user_id !== $user->id && $user->role !== 'admin' && $user->name !== 'I Gusti Sultan H.A, A.Md.Kom') {
            abort(403, 'Akses Ditolak: Anda tidak memiliki otoritas atas berkas PC ini.');
        }

        if ($backup->is_folder) {
            return response()->json(['status' => 'error', 'message' => 'Folder tidak dapat diekstrak.'], 400);
        }

        $fullPath = FileSecurityService::verifySafeStoragePath($backup->file_path);
        if (!file_exists($fullPath)) {
            return response()->json(['status' => 'error', 'message' => 'Berkas fisik arsip tidak ditemukan di server.'], 404);
        }

        $cacheKey = 'extract_progress_' . $user->id;
        $cancelKey = 'extract_cancel_' . $user->id;
        $logKey = 'extract_log_' . $user->id;

        Cache::put($cacheKey, 5, 600);
        Cache::put($logKey, 'Menyiapkan berkas arsip...', 600);
        Cache::forget($cancelKey);

        $destinationFolder = $request->destination ? preg_replace('/[^A-Za-z0-9_\-\.\s]/', '_', trim($request->destination)) : 'EXTRACTED_' . time();
        if (empty($destinationFolder)) {
            $destinationFolder = 'EXTRACTED_' . time();
        }

        $extractSubPath = 'backups/' . $backup->pc_id . '/' . time() . '_' . $destinationFolder;
        $fullExtractPath = storage_path('app/public/' . $extractSubPath);

        if (!file_exists($fullExtractPath)) {
            @mkdir($fullExtractPath, 0777, true);
        }

        // Dekripsi jika berkas di storage terenkripsi
        $tempArchive = null;
        if (FileSecurityService::isEncrypted($fullPath)) {
            Cache::put($logKey, 'Mendekripsi data arsip...', 600);
            $tempArchive = FileSecurityService::createDecryptedTempFile($fullPath);
            $archiveFile = $tempArchive ?: $fullPath;
        } else {
            $archiveFile = $fullPath;
        }

        $ext = strtolower(pathinfo($backup->file_name, PATHINFO_EXTENSION) ?: $backup->file_type ?: 'zip');
        $extracted = false;

        try {
            Cache::put($cacheKey, 20, 600);
            Cache::put($logKey, 'Membongkar isi arsip (' . strtoupper($ext) . ')...', 600);

            // 1. Jika ZIP, coba ZipArchive terlebih dahulu
            if ($ext === 'zip') {
                $zip = new \ZipArchive;
                if ($zip->open($archiveFile) === TRUE) {
                    $zip->extractTo($fullExtractPath);
                    $zip->close();
                    $extracted = true;
                }
            }

            // 2. Jika RAR dan ekstensi RarArchive ada
            if (!$extracted && $ext === 'rar' && class_exists('\RarArchive')) {
                try {
                    $rar = @\RarArchive::open($archiveFile);
                    if ($rar !== false) {
                        foreach ($rar->getEntries() as $entry) {
                            $entry->extract($fullExtractPath);
                        }
                        $rar->close();
                        $extracted = true;
                    }
                } catch (\Exception $e) {}
            }

            // 3. Fallback ke Command-Line Tools (7z, unrar, tar, unzip)
            if (!$extracted) {
                $commands = [];
                $commands[] = "7z x -y -o" . escapeshellarg($fullExtractPath) . " " . escapeshellarg($archiveFile) . " 2>&1";
                $commands[] = "7za x -y -o" . escapeshellarg($fullExtractPath) . " " . escapeshellarg($archiveFile) . " 2>&1";

                if ($ext === 'rar') {
                    $commands[] = "unrar x -y -o+ " . escapeshellarg($archiveFile) . " " . escapeshellarg($fullExtractPath . DIRECTORY_SEPARATOR) . " 2>&1";
                }

                $commands[] = "tar -xf " . escapeshellarg($archiveFile) . " -C " . escapeshellarg($fullExtractPath) . " 2>&1";

                if ($ext === 'zip') {
                    $commands[] = "unzip -o " . escapeshellarg($archiveFile) . " -d " . escapeshellarg($fullExtractPath) . " 2>&1";
                }

                foreach ($commands as $cmd) {
                    $out = [];
                    $ret = -1;
                    @exec($cmd, $out, $ret);
                    $scanned = @scandir($fullExtractPath);
                    if ($scanned && count($scanned) > 2) {
                        $extracted = true;
                        break;
                    }
                }
            }

            if (!$extracted) {
                $scanned = @scandir($fullExtractPath);
                if ($scanned && count($scanned) > 2) {
                    $extracted = true;
                }
            }

            if (!$extracted) {
                @rmdir($fullExtractPath);
                return response()->json([
                    'status' => 'error', 
                    'message' => 'Gagal membongkar berkas arsip. Pastikan format arsip ZIP/RAR valid dan tidak dilindungi kata sandi.'
                ], 500);
            }

            if (Cache::has($cancelKey)) {
                return response()->json(['status' => 'cancelled']);
            }

            Cache::put($cacheKey, 50, 600);
            Cache::put($logKey, 'Mendaftarkan struktur berkas ke pangkalan data...', 600);

            // Buat Folder Induk Hasil Ekstraksi
            $rootFolder = Backup::create([
                'pc_id' => $backup->pc_id,
                'parent_id' => $backup->parent_id,
                'file_name' => strtoupper($destinationFolder),
                'is_folder' => true,
                'file_path' => $extractSubPath,
                'file_size' => 0,
                'file_type' => 'folder'
            ]);

            $totalExtracted = 0;
            $res = $this->syncExtractedTree($fullExtractPath, $extractSubPath, $rootFolder->id, $backup->pc_id, $totalExtracted, $cancelKey, $logKey, $cacheKey);

            if ($res === false || Cache::has($cancelKey)) {
                return response()->json(['status' => 'cancelled']);
            }

            Cache::put($cacheKey, 100, 600);
            Cache::put($logKey, "Selesai: {$totalExtracted} item berhasil diekstrak.", 600);

            return response()->json([
                'status' => 'success', 
                'message' => "Arsip berhasil dibongkar! Sebanyak {$totalExtracted} item tersimpan di folder {$rootFolder->file_name}."
            ]);

        } catch (\Exception $e) {
            Log::error("Gagal ekstraksi arsip ID {$id}: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Terjadi kesalahan saat ekstraksi: ' . $e->getMessage()], 500);
        } finally {
            if ($tempArchive && file_exists($tempArchive)) {
                @unlink($tempArchive);
            }
        }
    }

    /**
     * RADAR PROGRESS & LOG EKSTRAKSI
     */
    public function getExtractProgress()
    {
        return response()->json([
            'progress' => Cache::get('extract_progress_' . Auth::id(), 0),
            'log' => Cache::get('extract_log_' . Auth::id(), 'Menunggu radar...')
        ]);
    }

    /**
     * OPERASI PEMBATALAN EKSTRAKSI
     */
    public function cancelExtract()
    {
        Cache::put('extract_cancel_' . Auth::id(), true, 60);
        return response()->json(['message' => 'Sinyal pembatalan dikirim...']);
    }

    /**
     * UPLOAD BERKAS KE STORAGE (MAX 20GB, RESPON DUKUNG JSON & INERTIA)
     */
    public function store(Request $request)
    {
        $request->validate([
            'pc_id' => 'required|exists:pcs,id', 
            'file' => 'required|file|max:20971520',
            'parent_id' => 'nullable'
        ]);

        $pc = Pc::findOrFail($request->pc_id);
        $uploadedFile = $request->file('file');
        $fileSize = $uploadedFile->getSize();
        $cleanFileName = $uploadedFile->getClientOriginalName();

        if (($pc->current_usage + $fileSize) > $pc->max_quota) {
            $msg = 'Penyimpanan Penuh! Kapasitas ' . $this->formatBytes($pc->max_quota) . ' terlampaui.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['status' => 'error', 'message' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        // VALIDASI KEAMANAN TINGKAT TINGGI: Anti-Malware, Magic Bytes, Double Extension, Anti-Polyglot
        try {
            $remainingQuota = $pc->max_quota - $pc->current_usage;
            FileSecurityService::validateFileSafety($uploadedFile->getRealPath(), $cleanFileName, $remainingQuota);
        } catch (\Exception $e) {
            Log::warning("Penyusupan berkas diblokir pada PC ID {$pc->id}: " . $e->getMessage());
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }

        $uniquePrefix = time() . '_' . substr(uniqid(), -6);
        $relativeSubPath = 'backups/' . $pc->id . '/' . $uniquePrefix . '_' . $cleanFileName;
        $destFullPath = FileSecurityService::verifySafeStoragePath($relativeSubPath);

        // Enkripsi berkas fisik pada level penyimpanan disk secara streaming aman
        FileSecurityService::encryptAndStoreFile($uploadedFile->getRealPath(), $destFullPath, $remainingQuota);

        $backup = Backup::create([
            'pc_id' => $pc->id,
            'parent_id' => $request->parent_id, 
            'file_name' => $cleanFileName,
            'file_path' => $relativeSubPath,
            'file_size' => $fileSize,
            'is_folder' => false,
            'file_type' => $uploadedFile->getClientOriginalExtension(),
        ]);

        $pc->increment('current_usage', $fileSize);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Berkas ' . $cleanFileName . ' berhasil diamankan.',
                'item' => [
                    'id' => $backup->id,
                    'file_name' => $backup->file_name,
                    'file_size' => $backup->file_size,
                    'size_human' => $this->formatBytes($backup->file_size),
                    'date_human' => Carbon::now()->format('d M Y H:i'),
                ],
                'pc' => [
                    'current_usage' => $pc->current_usage,
                    'usage_percentage' => round(($pc->current_usage / $pc->max_quota) * 100, 2),
                ]
            ]);
        }

        return back()->with('success', 'Berkas berhasil diamankan.');
    }

    /**
     * UNGGAH BERKAS BESAR DENGAN CHUNK / KEPINGAN (ANTI-LIMIT 100MB CLOUDFLARE)
     * Mendukung pemecahan berkas berukuran hingga 20 GB dengan enkripsi streaming dan validasi keamanan.
     */
    public function uploadChunk(Request $request)
    {
        // 1. Validasi Input Parameter
        $validated = $request->validate([
            'upload_id'    => 'required|string|regex:/^[a-zA-Z0-9_\-]+$/|max:100',
            'chunk_index'  => 'required|integer|min:0',
            'total_chunks' => 'required|integer|min:1',
            'file_name'    => 'required|string|max:255',
            'file_size'    => 'required|integer|min:1|max:21474836480', // Maksimal 20 GB
            'pc_id'        => 'required|exists:pcs,id',
            'parent_id'    => 'nullable',
            'chunk_file'   => 'required|file|max:20480', // Tiap kepingan maksimal 20 MB (normalnya 8-10 MB)
        ]);

        $user = Auth::user();
        $uploadId = $validated['upload_id'];
        $chunkIndex = (int)$validated['chunk_index'];
        $totalChunks = (int)$validated['total_chunks'];
        $totalFileSize = (int)$validated['file_size'];
        $cleanFileName = basename($validated['file_name']);

        // 2. Proteksi Path Traversal pada nama berkas
        if ($cleanFileName !== $validated['file_name'] || str_contains($validated['file_name'], '..') || str_contains($validated['file_name'], "\0")) {
            Log::critical("SECURITY ALERT [Path Traversal in Chunk Upload Filename]: {$validated['file_name']}");
            return response()->json(['status' => 'error', 'message' => 'Nama berkas memuat karakter terlarang.'], 422);
        }

        // 3. Cek awal ekstensi terlarang pada chunk pertama (hemat bandwidth)
        $ext = strtolower(pathinfo($cleanFileName, PATHINFO_EXTENSION));
        $blacklistedExtensions = [
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps', 'pht',
            'inc', 'exe', 'bat', 'cmd', 'sh', 'bash', 'zsh', 'vbs', 'vbe', 'js', 'jse', 
            'wsf', 'wsh', 'scr', 'ps1', 'ps2', 'psc1', 'cgi', 'pl', 'py', 'pyc', 'dll', 
            'jar', 'so', 'htaccess', 'htpasswd', 'ini', 'conf', 'asp', 'aspx', 'jsp', 'jspx'
        ];
        if (in_array($ext, $blacklistedExtensions, true)) {
            Log::critical("SECURITY ALERT [Blocked Chunk Upload Extension]: {$cleanFileName}");
            return response()->json(['status' => 'error', 'message' => "Ekstensi berkas .{$ext} dilarang keras demi alasan keamanan."], 422);
        }

        // 4. Verifikasi Kuota Penyimpanan PC
        $pc = Pc::findOrFail($validated['pc_id']);
        if (($pc->current_usage + $totalFileSize) > $pc->max_quota) {
            $msg = 'Penyimpanan Penuh! Kapasitas ' . $this->formatBytes($pc->max_quota) . ' terlampaui.';
            return response()->json(['status' => 'error', 'message' => $msg], 422);
        }

        // 5. Direktori Penyimpanan Sementara Kepingan Terisolasi (Non-Publik)
        $chunkDir = storage_path('app/chunks/' . $user->id . '/' . $uploadId);
        if (!file_exists($chunkDir)) {
            @mkdir($chunkDir, 0775, true);
        }

        // Jalankan pembersihan kepingan lama (>24 jam) secara berkala
        $this->cleanupStaleChunks();

        // 6. Simpan Kepingan Saat Ini
        $chunkPartPath = $chunkDir . '/chunk_' . sprintf('%05d', $chunkIndex) . '.part';
        $uploadedChunk = $request->file('chunk_file');
        
        if (!move_uploaded_file($uploadedChunk->getRealPath(), $chunkPartPath)) {
            copy($uploadedChunk->getRealPath(), $chunkPartPath);
        }

        // Jika belum kepingan terakhir, kembalikan status sukses penyimpanan kepingan
        if ($chunkIndex < ($totalChunks - 1)) {
            return response()->json([
                'status' => 'chunk_saved',
                'chunk_index' => $chunkIndex,
                'total_chunks' => $totalChunks,
                'progress' => round((($chunkIndex + 1) / $totalChunks) * 100, 1),
            ]);
        }

        // --- TAHAP FINAL: SELURUH KEPINGAN TELAH TIBA, GABUNGKAN SECARA STREAMING ---
        $lock = Cache::lock('chunk_assemble_' . $uploadId, 180);
        if (!$lock->get()) {
            return response()->json(['status' => 'processing', 'message' => 'Berkas sedang dalam proses penggabungan.'], 200);
        }

        try {
            // Verifikasi seluruh kepingan dari 0 sampai totalChunks - 1 lengkap
            for ($i = 0; $i < $totalChunks; $i++) {
                $expectedPart = $chunkDir . '/chunk_' . sprintf('%05d', $i) . '.part';
                if (!file_exists($expectedPart)) {
                    return response()->json([
                        'status' => 'error', 
                        'message' => "Kepingan berkas ke-{$i} hilang atau transmisi terputus. Silakan coba unggah ulang."
                    ], 422);
                }
            }

            // Gabungkan kepingan secara streaming (hemat RAM)
            $assembledTempPath = $chunkDir . '/assembled_' . uniqid() . '.tmp';
            $outHandle = fopen($assembledTempPath, 'wb');
            if (!$outHandle) {
                throw new \Exception('Gagal membuka buffer penyatuan berkas sementara di server.');
            }

            for ($i = 0; $i < $totalChunks; $i++) {
                $partPath = $chunkDir . '/chunk_' . sprintf('%05d', $i) . '.part';
                $inHandle = fopen($partPath, 'rb');
                if ($inHandle) {
                    while (!feof($inHandle)) {
                        $buffer = fread($inHandle, 1048576); // 1 MB buffer
                        if ($buffer !== false) {
                            fwrite($outHandle, $buffer);
                        }
                    }
                    fclose($inHandle);
                }
            }
            fclose($outHandle);

            // Verifikasi ukuran berkas gabungan terhadap file_size
            $assembledSize = filesize($assembledTempPath);
            if ($assembledSize !== $totalFileSize) {
                @unlink($assembledTempPath);
                throw new \Exception("Integritas berkas tidak cocok. Ukuran aktual {$assembledSize} byte tidak sesuai {$totalFileSize} byte.");
            }

            // Validasi Keamanan Berlapis (Anti-Malware, Magic Bytes, Double Extension, Anti-Polyglot)
            $remainingQuota = $pc->max_quota - $pc->current_usage;
            FileSecurityService::validateFileSafety($assembledTempPath, $cleanFileName, $remainingQuota);

            // Siapkan path penyimpanan aman terenkripsi
            $uniquePrefix = time() . '_' . substr(uniqid(), -6);
            $relativeSubPath = 'backups/' . $pc->id . '/' . $uniquePrefix . '_' . $cleanFileName;
            $destFullPath = FileSecurityService::verifySafeStoragePath($relativeSubPath);

            // Enkripsi berkas fisik pada level disk
            FileSecurityService::encryptAndStoreFile($assembledTempPath, $destFullPath, $remainingQuota);

            // Hapus berkas gabungan sementara
            if (file_exists($assembledTempPath)) {
                @unlink($assembledTempPath);
            }

            // Bersihkan seluruh kepingan sementara dari direktori
            $this->removeChunkDirectory($chunkDir);

            // Simpan ke Database
            $backup = Backup::create([
                'pc_id' => $pc->id,
                'parent_id' => $validated['parent_id'] ?? null,
                'file_name' => $cleanFileName,
                'file_path' => $relativeSubPath,
                'file_size' => $assembledSize,
                'is_folder' => false,
                'file_type' => $ext,
            ]);

            $pc->increment('current_usage', $assembledSize);

            return response()->json([
                'status' => 'success',
                'message' => 'Berkas ' . $cleanFileName . ' berhasil diunggah secara utuh dan diamankan.',
                'item' => [
                    'id' => $backup->id,
                    'file_name' => $backup->file_name,
                    'file_size' => $backup->file_size,
                    'size_human' => $this->formatBytes($backup->file_size),
                    'date_human' => Carbon::now()->format('d M Y H:i'),
                ],
                'pc' => [
                    'current_usage' => $pc->current_usage,
                    'usage_percentage' => round(($pc->current_usage / $pc->max_quota) * 100, 2),
                ]
            ]);

        } catch (\Throwable $e) {
            $this->removeChunkDirectory($chunkDir);
            Log::error("Gagal menggabungkan kepingan berkas pada PC ID {$pc->id}: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } finally {
            $lock->release();
        }
    }

    /**
     * Batalkan unggahan kepingan dan bersihkan direktori sementara
     */
    public function cancelUploadChunk(Request $request)
    {
        $validated = $request->validate([
            'upload_id' => 'required|string|regex:/^[a-zA-Z0-9_\-]+$/|max:100',
        ]);

        $chunkDir = storage_path('app/chunks/' . Auth::id() . '/' . $validated['upload_id']);
        $this->removeChunkDirectory($chunkDir);

        return response()->json(['status' => 'success', 'message' => 'Kepingan berkas berhasil dibatalkan dan dibersihkan.']);
    }

    /**
     * Hapus direktori kepingan sementara beserta isinya
     */
    protected function removeChunkDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = glob($dir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }

    /**
     * Pembersihan berkas kepingan yang berumur lebih dari 24 jam (Garbage Collector)
     */
    protected function cleanupStaleChunks(): void
    {
        try {
            $baseChunkDir = storage_path('app/chunks/' . Auth::id());
            if (!is_dir($baseChunkDir)) return;

            $dirs = glob($baseChunkDir . '/*', GLOB_ONLYDIR);
            if (!is_array($dirs)) return;

            $now = time();
            foreach ($dirs as $dir) {
                if (($now - filemtime($dir)) > 86400) { // Lebih dari 24 jam
                    $this->removeChunkDirectory($dir);
                }
            }
        } catch (\Throwable $e) {
            // Silent catch
        }
    }

    public function download($id)
    {
        try {
            $backup = Backup::findOrFail($id);

            if ($backup->is_folder) {
                return back()->with('error', 'Folder tidak bisa diunduh langsung.');
            }

            $fullPath = FileSecurityService::verifySafeStoragePath($backup->file_path);

            if (!file_exists($fullPath)) {
                Log::warning("File backup ID {$id} tidak ditemukan di path: {$fullPath}");
                return back()->with('error', 'Berkas fisik tidak ditemukan di server penyimpanan. Berkas mungkin belum diunggah sempurna atau telah dipindahkan.');
            }

            $mimeType = @mime_content_type($fullPath) ?: 'application/octet-stream';
            return FileSecurityService::streamDecryptedDownload($fullPath, $backup->file_name, $mimeType);
        } catch (\Exception $e) {
            Log::error("Gagal unduh berkas backup ID {$id}: " . $e->getMessage());
            return back()->with('error', 'Gagal mengunduh berkas: ' . $e->getMessage());
        }
    }

    public function destroyBackup($id)
    {
        $backup = Backup::findOrFail($id);
        $pc = Pc::findOrFail($backup->pc_id);

        if (!$backup->is_folder && Storage::disk('public')->exists($backup->file_path)) {
            Storage::disk('public')->delete($backup->file_path);
        }

        $pc->decrement('current_usage', $backup->file_size);
        $backup->delete();
        return back()->with('success', 'Data berhasil Dihapus.');
    }

    /**
     * MANAJEMEN RADAR IP
     */
    public function storeNetwork(Request $request) 
    {
        $request->validate(['location_name' => 'required', 'ip_address' => 'required']);
        OfficeNetwork::create($request->all());
        return back()->with('success', 'IP Baru telah diaktifkan!');
    }

    public function destroyNetwork($id) 
    {
        OfficeNetwork::destroy($id);
        return back()->with('success', 'IP telah dinonaktifkan.');
    }

        /**
     * OPERASI KHUSUS ADMIN: HAPUS AKSES & SELURUH BERKAS PC PERMANEN
     */
    public function revokePc($id)
    {
        $user = Auth::user();
        if ($user->role !== 'admin' && $user->name !== 'I Gusti Sultan H.A, A.Md.Kom') {
            return abort(403, 'Anda tidak memiliki otoritas menghapus akses PC.');
        }

        $pc = Pc::findOrFail($id);

        // 1. Hapus seluruh berkas fisik dari storage
        $backups = Backup::where('pc_id', $pc->id)->get();
        foreach ($backups as $backup) {
            if (!$backup->is_folder && !empty($backup->file_path) && Storage::disk('public')->exists($backup->file_path)) {
                Storage::disk('public')->delete($backup->file_path);
            }
        }

        // 2. Hapus seluruh direktori penyimpanan PC di storage/backups/{pc_id}
        if (Storage::disk('public')->exists('backups/' . $pc->id)) {
            Storage::disk('public')->deleteDirectory('backups/' . $pc->id);
        }

        // 3. Hapus seluruh record data Backup dari database
        Backup::where('pc_id', $pc->id)->delete();

        // 4. Hapus pengajuan akses personel (AccessRequest) agar personel bisa mengajukan ulang jika perlu
        AccessRequest::where('user_id', $pc->user_id)->delete();

        // 5. Hapus record PC
        $pc->delete();

        return back()->with('success', 'Otoritas akses dan seluruh berkas PC berhasil dihapus secara permanen.');
    }

    /** Spreadsheet .xlsx adalah paket ZIP: tolak konten lain (mis. HTML/skrip yang disamarkan). */
    private function isZipOfficeContent(string $bytes): bool
    {
        $magic = substr($bytes, 0, 4);
        return $magic === "PK\x03\x04" || $magic === "PK\x05\x06";
    }

    /** Tulis konten ke penyimpanan cadangan dalam bentuk terenkripsi AES-256 (lihat FileSecurityService). */
    private function storeEncryptedContent(string $relativePath, string $bytes): void
    {
        $dest = FileSecurityService::verifySafeStoragePath($relativePath);
        $tmp = tempnam(sys_get_temp_dir(), 'sinden_xlsx_');
        try {
            file_put_contents($tmp, $bytes);
            FileSecurityService::encryptAndStoreFile($tmp, $dest);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * FITUR: SIMPAN PERUBAHAN ISI BERKAS EXCEL (.XLSX / .XLS / .CSV)
     */
    public function saveExcel(Request $request, $id)
    {
        $request->validate([
            'base64_content' => 'required|string',
        ]);

        try {
            $backup = Backup::findOrFail($id);

            if ($backup->is_folder) {
                return response()->json(['status' => 'error', 'message' => 'Folder tidak dapat diedit sebagai spreadsheet.'], 400);
            }

            $decoded = base64_decode($request->base64_content, true);
            if ($decoded === false || !$this->isZipOfficeContent($decoded)) {
                return response()->json(['status' => 'error', 'message' => 'Format berkas biner tidak valid.'], 400);
            }

            $pc = Pc::findOrFail($backup->pc_id);
            $oldSize = (int)$backup->file_size;
            $newSize = strlen($decoded);
            $sizeDiff = $newSize - $oldSize;

            if ($sizeDiff > 0 && ($pc->current_usage + $sizeDiff) > $pc->max_quota) {
                return response()->json(['status' => 'error', 'message' => 'Penyimpanan penuh! Kuota pangkalan PC terlampaui.'], 400);
            }

            // Simpan terenkripsi (sama seperti unggahan biasa) agar tidak terbaca lewat URL /storage publik.
            $this->storeEncryptedContent($backup->file_path, $decoded);

            // Update metadata
            $backup->update([
                'file_size' => $newSize,
                'file_type' => pathinfo($backup->file_name, PATHINFO_EXTENSION) ?: 'xlsx',
                'updated_at' => now(),
            ]);

            if ($sizeDiff !== 0) {
                $pc->increment('current_usage', $sizeDiff);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Perubahan berkas Excel berhasil disimpan ke penyimpanan cadangan.',
                'file_size' => $newSize,
                'size_human' => $this->formatBytes($newSize),
                'date_human' => Carbon::parse($backup->updated_at)->format('d M Y H:i'),
                'pc_usage_human' => $this->formatBytes($pc->current_usage),
                'pc_usage_percentage' => $pc->max_quota > 0 ? round(($pc->current_usage / $pc->max_quota) * 100, 2) : 0,
            ]);
        } catch (\Exception $e) {
            Log::error("Gagal menyimpan berkas Excel backup ID {$id}: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal menyimpan perubahan berkas: ' . $e->getMessage()], 500);
        }
    }

    /**
     * FITUR: BUAT BERKAS SPREADSHEET EXCEL BARU LANGSUNG DI FOLDER
     */
    public function createExcel(Request $request)
    {
        $request->validate([
            'pc_id' => 'required|exists:pcs,id',
            'file_name' => 'required|string|max:100',
            'parent_id' => 'nullable',
            'base64_content' => 'required|string'
        ]);

        try {
            $pc = Pc::findOrFail($request->pc_id);
            $decoded = base64_decode($request->base64_content, true);
            if ($decoded === false || !$this->isZipOfficeContent($decoded)) {
                return back()->with('error', 'Format data spreadsheet tidak valid.');
            }

            $fileSize = strlen($decoded);
            if (($pc->current_usage + $fileSize) > $pc->max_quota) {
                return back()->with('error', 'Penyimpanan penuh! Kapasitas pangkalan PC terlampaui.');
            }

            // Nama dari pengguna disanitasi: tanpa path/karakter khusus, ekstensi selalu .xlsx.
            $baseName = preg_replace('/\.xlsx$/i', '', basename(str_replace('\\', '/', trim($request->file_name))));
            $baseName = trim(preg_replace('/[^A-Za-z0-9 _\-\.()]/', '_', $baseName), " .") ?: 'Spreadsheet';
            $fileName = $baseName . '.xlsx';

            $path = 'backups/' . $pc->id . '/' . time() . '_' . substr(uniqid(), -6) . '_' . $fileName;
            $this->storeEncryptedContent($path, $decoded);

            Backup::create([
                'pc_id' => $pc->id,
                'parent_id' => $request->parent_id,
                'file_name' => $fileName,
                'file_path' => $path,
                'file_size' => $fileSize,
                'is_folder' => false,
                'file_type' => 'xlsx',
            ]);

            $pc->increment('current_usage', $fileSize);
            return back()->with('success', 'Berkas Excel baru berhasil dibuat dan disimpan.');
        } catch (\Exception $e) {
            Log::error("Gagal membuat berkas Excel baru: " . $e->getMessage());
            return back()->with('error', 'Gagal membuat berkas Excel: ' . $e->getMessage());
        }
    }

    /**
     * OPERASI KHUSUS ADMIN: BUAT PANGKALAN PC BARU DENGAN KUOTA GB KUSTOM
     */
    public function createPc(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'admin' && $user->name !== 'I Gusti Sultan H.A, A.Md.Kom') {
            return abort(403, 'Anda tidak memiliki otoritas membuat pangkalan PC.');
        }

        $validated = $request->validate([
            'pc_name' => 'required|string|max:100',
            'user_id' => 'required|exists:users,id',
            'quota_gb' => 'required|numeric|min:1|max:100000',
        ]);

        $pool = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $randomHw = substr(str_shuffle($pool), 0, 8);
        $maxQuotaBytes = (int)($validated['quota_gb'] * 1073741824);

        $pc = Pc::create([
            'user_id' => $validated['user_id'],
            'pc_name' => $validated['pc_name'],
            'hardware_id' => 'HW-' . $randomHw,
            'current_usage' => 0,
            'max_quota' => $maxQuotaBytes,
        ]);

        return back()->with('success', "Pangkalan Backup '{$pc->pc_name}' dengan kuota {$validated['quota_gb']} GB berhasil dibangun.");
    }

    /**
     * OPERASI KHUSUS ADMIN: SESUAIKAN KUOTA GB PANGKALAN PC
     */
    public function updateQuota(Request $request, $id)
    {
        $user = Auth::user();
        if ($user->role !== 'admin' && $user->name !== 'I Gusti Sultan H.A, A.Md.Kom') {
            return abort(403, 'Anda tidak memiliki otoritas mengubah kuota pangkalan.');
        }

        $validated = $request->validate([
            'quota_gb' => 'required|numeric|min:1|max:100000',
        ]);

        $pc = Pc::findOrFail($id);
        $maxQuotaBytes = (int)($validated['quota_gb'] * 1073741824);

        $pc->update([
            'max_quota' => $maxQuotaBytes,
        ]);

        return back()->with('success', "Kapasitas kuota '{$pc->pc_name}' berhasil disesuaikan menjadi {$validated['quota_gb']} GB.");
    }

    private function formatPcData($pc) {
        $pc->usage_human = $this->formatBytes($pc->current_usage);
        $pc->quota_human = $this->formatBytes($pc->max_quota);
        $pc->usage_percentage = $pc->max_quota > 0 ? round(($pc->current_usage / $pc->max_quota) * 100, 2) : 0;
        return $pc;
    }

    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) $bytes /= 1024;
        return round($bytes, 2) . ' ' . $units[$i];
    }

    // =========================================================================
    // FITUR MANAJEMEN BERKAS BULK (MULTI-SELEKSI, CUT/MOVE, COPY, DELETE, ZIP)
    // =========================================================================

    /**
     * PINDAHKAN (CUT/MOVE / DRAG & DROP) BANYAK ITEM SEKALIGUS KE FOLDER TUJUAN
     */
    public function bulkMove(Request $request)
    {
        $request->validate([
            'pc_id' => 'required|exists:pcs,id',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:backups,id',
            'target_folder_id' => 'nullable'
        ]);

        $pc = Pc::findOrFail($request->pc_id);
        $user = Auth::user();
        if ($pc->user_id !== $user->id && $user->role !== 'admin' && $user->name !== 'I Gusti Sultan H.A, A.Md.Kom') {
            abort(403, 'Anda tidak memiliki otoritas atas pangkalan PC ini.');
        }

        $rawTarget = $request->input('target_folder_id');
        $targetFolderId = (!empty($rawTarget) && is_numeric($rawTarget)) ? (int) $rawTarget : null;
        if ($targetFolderId) {
            $targetFolder = Backup::where('id', $targetFolderId)->where('pc_id', $pc->id)->firstOrFail();
            if (!$targetFolder->is_folder) {
                return response()->json(['status' => 'error', 'message' => 'Tujuan pemindahan bukan berupa folder.'], 422);
            }
        }

        $ids = $request->ids;
        // Pencegahan circular loop (folder tidak boleh dipindah ke dirinya sendiri atau anak foldernya)
        foreach ($ids as $id) {
            if ($targetFolderId && $id == $targetFolderId) {
                return response()->json(['status' => 'error', 'message' => 'Tidak dapat memindahkan folder ke dalam dirinya sendiri.'], 422);
            }
            if ($targetFolderId && $this->isDescendantOf($targetFolderId, $id)) {
                return response()->json(['status' => 'error', 'message' => 'Tidak dapat memindahkan folder ke dalam sub-foldernya sendiri.'], 422);
            }
        }

        $count = Backup::whereIn('id', $ids)->where('pc_id', $pc->id)->update([
            'parent_id' => $targetFolderId
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "{$count} item berhasil dipindahkan ke folder tujuan."
        ]);
    }

    /**
     * SALIN (COPY & PASTE) BANYAK ITEM KE FOLDER TUJUAN DENGAN REKURSIVITAS & VALIDASI KUOTA
     */
    public function bulkCopy(Request $request)
    {
        $request->validate([
            'pc_id' => 'required|exists:pcs,id',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:backups,id',
            'target_folder_id' => 'nullable'
        ]);

        $pc = Pc::findOrFail($request->pc_id);
        $user = Auth::user();
        if ($pc->user_id !== $user->id && $user->role !== 'admin' && $user->name !== 'I Gusti Sultan H.A, A.Md.Kom') {
            abort(403, 'Anda tidak memiliki otoritas atas pangkalan PC ini.');
        }

        $rawTarget = $request->input('target_folder_id');
        $targetFolderId = (!empty($rawTarget) && is_numeric($rawTarget)) ? (int) $rawTarget : null;
        if ($targetFolderId) {
            $targetFolder = Backup::where('id', $targetFolderId)->where('pc_id', $pc->id)->firstOrFail();
            if (!$targetFolder->is_folder) {
                return response()->json(['status' => 'error', 'message' => 'Tujuan penyalinan bukan berupa folder.'], 422);
            }
        }

        $items = Backup::whereIn('id', $request->ids)->where('pc_id', $pc->id)->get();

        // Hitung total ukuran yang dibutuhkan
        $totalBytesNeeded = 0;
        foreach ($items as $item) {
            if ($item->is_folder) {
                $totalBytesNeeded += $this->getFolderSize($item->id);
            } else {
                $totalBytesNeeded += $item->file_size;
            }
        }

        $remainingQuota = $pc->max_quota - $pc->current_usage;
        if ($totalBytesNeeded > $remainingQuota) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sisa kuota pangkalan (' . $this->formatBytes($remainingQuota) . ') tidak mencukupi untuk menduplikasi ' . $this->formatBytes($totalBytesNeeded) . '.'
            ], 422);
        }

        $copiedCount = 0;
        foreach ($items as $item) {
            $this->duplicateBackupItem($item, $targetFolderId, $pc);
            $copiedCount++;
        }

        return response()->json([
            'status' => 'success',
            'message' => "{$copiedCount} item berhasil disalin ke folder tujuan."
        ]);
    }

    /**
     * HAPUS BANYAK ITEM SEKALIGUS (BULK DELETE)
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'pc_id' => 'required|exists:pcs,id',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:backups,id',
        ]);

        $pc = Pc::findOrFail($request->pc_id);
        $user = Auth::user();
        if ($pc->user_id !== $user->id && $user->role !== 'admin' && $user->name !== 'I Gusti Sultan H.A, A.Md.Kom') {
            abort(403, 'Anda tidak memiliki otoritas atas pangkalan PC ini.');
        }

        $items = Backup::whereIn('id', $request->ids)->where('pc_id', $pc->id)->get();
        $deletedCount = 0;

        foreach ($items as $item) {
            $this->deleteItemRecursively($item, $pc);
            $deletedCount++;
        }

        return response()->json([
            'status' => 'success',
            'message' => "{$deletedCount} item berhasil dihapus secara permanen."
        ]);
    }

    /**
     * UNDUH BANYAK ITEM TERPILIH SEBAGAI PAKET ZIP64 STREAMING
     *
     * Dialirkan langsung ke output HTTP peramban menggunakan metode STORE tanpa membuat
     * berkas temporer di disk server (zero disk overhead) dan meloloskan batas 100 detik Cloudflare.
     */
    public function bulkDownloadZip(Request $request)
    {
        $request->validate([
            'pc_id' => 'required|exists:pcs,id',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:backups,id',
        ]);

        $pc = Pc::findOrFail($request->pc_id);
        $user = Auth::user();
        if ($pc->user_id !== $user->id && $user->role !== 'admin' && $user->name !== 'I Gusti Sultan H.A, A.Md.Kom') {
            abort(403, 'Akses ditolak.');
        }

        $items = Backup::whereIn('id', $request->ids)->where('pc_id', $pc->id)->get();
        if ($items->isEmpty()) {
            return back()->with('error', 'Tidak ada berkas yang dipilih untuk dikompresi.');
        }

        // Tentukan nama paket ZIP berdasarkan folder yang dipilih atau folder aktif saat ini
        $folderTitle = null;

        // 1. Jika pengguna memilih 1 folder tunggal
        if ($items->count() === 1 && $items->first()->is_folder) {
            $folderTitle = $items->first()->file_name;
        }

        // 2. Jika ID folder aktif dikirimkan dari peramban
        if (!$folderTitle && $request->filled('folder_id')) {
            $currentFolder = Backup::where('id', $request->folder_id)->where('pc_id', $pc->id)->first();
            if ($currentFolder) {
                $folderTitle = $currentFolder->file_name;
            }
        }

        // 3. Jika nama folder aktif dikirimkan dari breadcrumb peramban
        if (!$folderTitle && $request->filled('folder_name')) {
            $folderTitle = $request->folder_name;
        }

        // 4. Jika seluruh item terpilih berada di dalam subfolder yang sama
        if (!$folderTitle) {
            $firstParentId = $items->first()->parent_id;
            if ($firstParentId) {
                $allSameParent = $items->every(fn($i) => $i->parent_id == $firstParentId);
                if ($allSameParent) {
                    $parentFolder = Backup::where('id', $firstParentId)->where('pc_id', $pc->id)->first();
                    if ($parentFolder) {
                        $folderTitle = $parentFolder->file_name;
                    }
                }
            }
        }

        // 5. Cadangan jika di root pangkalan
        if (!$folderTitle) {
            $folderTitle = $pc->pc_name;
        }

        $cleanTitle = trim(preg_replace('/[^A-Za-z0-9_\-]/', '_', $folderTitle), '_');
        $cleanTitle = preg_replace('/_+/', '_', $cleanTitle);
        if ($cleanTitle === '') {
            $cleanTitle = 'ARSIP_BERKAS';
        }

        $zipFileName = $cleanTitle . '_' . date('Ymd_His') . '.zip';

        return response()->stream(function () use ($items) {
            set_time_limit(0);
            @ini_set('max_execution_time', '0');
            @ini_set('memory_limit', '512M');

            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            $out = fopen('php://output', 'wb');
            if (!$out) return;

            $zipStream = new StreamingZipService($out);

            foreach ($items as $item) {
                $this->streamBackupItemToZip($item, $zipStream, '');
            }

            $zipStream->finish();
            fclose($out);
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $zipFileName . '"',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Cek apakah $childId merupakan turunan dari $ancestorId
     */
    private function isDescendantOf($childId, $ancestorId)
    {
        $currentId = $childId;
        while ($currentId) {
            $parent = Backup::where('id', $currentId)->value('parent_id');
            if (!$parent) return false;
            if ($parent == $ancestorId) return true;
            $currentId = $parent;
        }
        return false;
    }

    /**
     * Duplikasi berkas atau folder secara rekursif
     */
    private function duplicateBackupItem($item, $targetFolderId, $pc)
    {
        if ($item->is_folder) {
            $newName = ($item->parent_id == $targetFolderId) ? 'Salinan dari ' . $item->file_name : $item->file_name;
            $newFolder = Backup::create([
                'pc_id' => $pc->id,
                'parent_id' => $targetFolderId,
                'file_name' => $newName,
                'is_folder' => true,
                'file_path' => 'folder',
                'file_size' => 0,
                'file_type' => 'folder'
            ]);

            $children = Backup::where('parent_id', $item->id)->where('pc_id', $pc->id)->get();
            foreach ($children as $child) {
                $this->duplicateBackupItem($child, $newFolder->id, $pc);
            }
            return $newFolder;
        } else {
            $origPath = storage_path('app/public/' . $item->file_path);
            if (!file_exists($origPath)) {
                if (file_exists(storage_path('app/' . $item->file_path))) {
                    $origPath = storage_path('app/' . $item->file_path);
                } else if (file_exists($item->file_path)) {
                    $origPath = $item->file_path;
                }
            }
            if (!file_exists($origPath)) {
                \Log::warning("Gagal menyalin berkas: Berkas fisik tidak ditemukan untuk Backup ID {$item->id} ({$item->file_path})");
                return null;
            }

            $ext = pathinfo($item->file_name, PATHINFO_EXTENSION);
            $newName = ($item->parent_id == $targetFolderId) ? 'Salinan ' . $item->file_name : $item->file_name;

            $newSubPath = 'backups/' . $pc->id . '/' . time() . '_' . Str::random(8) . ($ext ? '.' . $ext : '');
            $destPath = storage_path('app/public/' . $newSubPath);

            $destDir = dirname($destPath);
            if (!file_exists($destDir)) {
                @mkdir($destDir, 0777, true);
            }

            if (@copy($origPath, $destPath)) {
                $newFile = Backup::create([
                    'pc_id' => $pc->id,
                    'parent_id' => $targetFolderId,
                    'file_name' => $newName,
                    'file_path' => $newSubPath,
                    'file_size' => $item->file_size,
                    'file_type' => $item->file_type,
                    'is_folder' => false
                ]);
                $pc->increment('current_usage', $item->file_size);
                return $newFile;
            }
            return null;
        }
    }

    /**
     * Hapus berkas atau folder beserta seluruh isinya secara rekursif
     */
    private function deleteItemRecursively($item, $pc)
    {
        if ($item->is_folder) {
            $children = Backup::where('parent_id', $item->id)->where('pc_id', $pc->id)->get();
            foreach ($children as $child) {
                $this->deleteItemRecursively($child, $pc);
            }
        } else {
            $fullPath = storage_path('app/public/' . $item->file_path);
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
            $pc->decrement('current_usage', $item->file_size);
        }
        $item->delete();
    }

    /**
     * Tambahkan item ke ZIP stream secara rekursif tanpa berkas temporer
     */
    private function streamBackupItemToZip($item, StreamingZipService $zipStream, string $zipSubDir): void
    {
        if (connection_aborted()) {
            return;
        }

        if ($item->is_folder) {
            $newDir = $zipSubDir ? $zipSubDir . '/' . $item->file_name : $item->file_name;
            $zipStream->addEmptyDir($newDir);
            $children = Backup::where('parent_id', $item->id)->where('pc_id', $item->pc_id)->get();
            foreach ($children as $child) {
                if (connection_aborted()) {
                    break;
                }
                $this->streamBackupItemToZip($child, $zipStream, $newDir);
            }
        } else {
            $fullPath = FileSecurityService::verifySafeStoragePath($item->file_path);
            if (file_exists($fullPath)) {
                $entryPath = $zipSubDir ? $zipSubDir . '/' . $item->file_name : $item->file_name;
                $timestamp = $item->updated_at ? $item->updated_at->timestamp : time();

                $zipStream->addFileStream($entryPath, function ($writeChunk) use ($fullPath) {
                    FileSecurityService::streamDecryptedChunks($fullPath, $writeChunk);
                }, $timestamp);
            }
        }
    }
}