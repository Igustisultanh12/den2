<?php

namespace App\Http\Controllers;

use App\Models\Pc;
use App\Models\Backup;
use App\Models\BackupShare;
use App\Models\BackupShareGuest;
use App\Models\BackupShareAccessLog;
use App\Services\ArwService;
use App\Services\FileSecurityService;
use App\Services\StreamingZipService;
use App\Services\ThumbnailService;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Carbon\Carbon;
use ZipArchive;

class BackupShareController extends Controller
{
    /**
     * Menyimpan atau memperbarui konfigurasi tautan berbagi (PIN, Status Aktif)
     */
    public function saveShare(Request $request)
    {
        BackupShare::ensureSchema();

        $validated = $request->validate([
            'pc_id' => 'required|exists:pcs,id',
            'backup_id' => 'nullable|exists:backups,id',
            'pin' => 'required|string|min:4|max:30',
            'is_active' => 'required|boolean',
            'allow_guest' => 'nullable|boolean',
            'guest_duration_hours' => 'nullable|integer|min:1|max:720',
            'share_name' => 'nullable|string|max:150',
        ]);

        $user = Auth::user();
        $pc = Pc::findOrFail($validated['pc_id']);

        // Verifikasi kepemilikan atau otoritas admin
        $isAdmin = $user->isAdmin() || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';
        if (!$isAdmin && $pc->user_id !== $user->id) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki hak otoritas membagikan folder ini.'], 403);
        }

        $share = BackupShare::where('pc_id', $pc->id)
            ->where(function ($q) use ($validated) {
                if (!empty($validated['backup_id'])) {
                    $q->where('backup_id', $validated['backup_id']);
                } else {
                    $q->whereNull('backup_id');
                }
            })
            ->first();

        $defaultName = $validated['share_name'];
        if (empty($defaultName)) {
            if (!empty($validated['backup_id'])) {
                $folder = Backup::find($validated['backup_id']);
                $defaultName = $folder?->file_name ?: 'Folder Berkas';
            } else {
                $defaultName = $pc->pc_name ?: 'Pangkalan Backup';
            }
        }

        if (!$share) {
            $share = new BackupShare([
                'pc_id' => $pc->id,
                'backup_id' => $validated['backup_id'] ?? null,
                'share_token' => Str::random(32),
                'created_by' => $user->id,
            ]);
        }

        $share->pin = trim($validated['pin']);
        $share->is_active = (bool)$validated['is_active'];
        $allowGuest = $request->has('allow_guest') ? (bool)$validated['allow_guest'] : true;
        $share->allow_guest = $allowGuest;
        $durationHours = $request->filled('guest_duration_hours') ? max(1, (int)$request->input('guest_duration_hours')) : 24;
        $share->guest_duration_hours = $durationHours;

        // Atur masa berlaku tautan pengunjung luar (otomatis kedaluwarsa setelah batas waktu)
        if ($allowGuest) {
            $share->guest_expires_at = now()->addHours($durationHours);
        } else {
            $share->guest_expires_at = null;
        }

        $share->share_name = $defaultName;
        $share->save();

        $recentGuests = $share->guestLogs()->take(10)->get()->map(function ($g) {
            $isExpired = $g->expires_at ? now()->greaterThan($g->expires_at) : false;
            return [
                'id' => $g->id,
                'nrp' => $g->nrp,
                'pangkat' => $g->pangkat,
                'nama' => $g->nama,
                'satuan' => $g->satuan,
                'whatsapp' => $g->whatsapp,
                'ip' => $g->ip_address,
                'expires_at_human' => $g->expires_at ? $g->expires_at->format('d/m/Y H:i') : null,
                'is_expired' => $isExpired,
                'time_human' => $g->accessed_at ? $g->accessed_at->format('d/m/Y H:i') : $g->created_at->format('d/m/Y H:i'),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Tautan berbagi dan PIN keamanan berhasil diperbarui.',
            'share' => $share,
            'share_url' => route('backup.shared.view', $share->share_token),
            'guest_share_url' => route('backup.shared.guest-view', $share->share_token),
            'guest_duration_hours' => (int)($share->guest_duration_hours ?: 24),
            'guest_expires_at' => $share->guest_expires_at ? $share->guest_expires_at->toIso8601String() : null,
            'guest_expires_at_human' => $share->guest_expires_at ? $share->guest_expires_at->format('d/m/Y H:i') : null,
            'is_guest_expired' => $share->isGuestExpired(),
            'recent_guests' => $recentGuests,
        ]);
    }

    /**
     * Menonaktifkan atau menghapus tautan berbagi (seluruh akses)
     */
    public function revokeShare(Request $request, $id)
    {
        $share = BackupShare::findOrFail($id);
        $user = Auth::user();
        $isAdmin = $user->isAdmin() || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';

        if (!$isAdmin && $share->created_by !== $user->id && $share->pc->user_id !== $user->id) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki otoritas.'], 403);
        }

        $share->update(['is_active' => false]);

        return response()->json([
            'status' => 'success',
            'message' => 'Akses tautan berbagi berhasil dinonaktifkan seketika.',
            'share' => $share,
        ]);
    }

    /**
     * Menghapus / Menutup Tautan Pengunjung Tamu Seketika
     */
    public function revokeGuestLink(Request $request, $id)
    {
        $share = BackupShare::findOrFail($id);
        $user = Auth::user();
        $isAdmin = $user->isAdmin() || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';

        if (!$isAdmin && $share->created_by !== $user->id && $share->pc->user_id !== $user->id) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki otoritas.'], 403);
        }

        $share->update([
            'allow_guest' => false,
            'guest_expires_at' => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Tautan pengunjung berhasil dihapus & dinonaktifkan.',
            'share' => $share,
        ]);
    }

    /**
     * Halaman Akses Berbagi Folder Khusus Personel Internal (Wajib Login Akun SINDEN)
     */
    public function show($token, Request $request)
    {
        // 1. Otoritas Personel: Wajib login akun terlebih dahulu sebelum akses PIN & berkas
        if (!Auth::check()) {
            $targetUrl = $request->fullUrl();
            session()->put('url.intended', $targetUrl);
            return redirect()->route('login', ['redirect' => $targetUrl]);
        }

        BackupShare::ensureSchema();

        $share = BackupShare::with(['pc', 'folder'])
            ->where('share_token', $token)
            ->first();

        if (!$share) {
            return Inertia::render('Backup/SharedFolder', [
                'isNotFound' => true,
                'shareName' => 'Tautan Tidak Ditemukan',
            ]);
        }

        // Jika tautan telah dinonaktifkan oleh Admin
        if (!$share->is_active) {
            return Inertia::render('Backup/SharedFolder', [
                'isDeactivated' => true,
                'shareName' => $share->share_name,
            ]);
        }

        $sessionKey = 'verified_backup_share_' . $share->id;
        $isVerified = session()->get($sessionKey) === true;

        // Jika belum memasukkan PIN yang benar
        if (!$isVerified) {
            return $this->renderPinView($share, $token, false);
        }

        return $this->renderFolderView($share, $token, $request, false);
    }

    /**
     * Halaman Akses Berbagi Folder untuk Pengunjung Luar / Tamu (Tanpa Akun, Buku Tamu & PIN)
     */
    public function showGuest($token, Request $request)
    {
        BackupShare::ensureSchema();

        $share = BackupShare::with(['pc', 'folder'])
            ->where('share_token', $token)
            ->first();

        if (!$share) {
            return Inertia::render('Backup/SharedFolder', [
                'isNotFound' => true,
                'shareName' => 'Tautan Tidak Ditemukan',
            ]);
        }

        // Jika tautan telah dinonaktifkan oleh Admin
        if (!$share->is_active) {
            return Inertia::render('Backup/SharedFolder', [
                'isDeactivated' => true,
                'shareName' => $share->share_name,
            ]);
        }

        // Periksa apakah tautan pengunjung telah kadaluarsa melewati batas waktu durasi (otomatis dihapus)
        if ($share->guest_expires_at && now()->greaterThan($share->guest_expires_at)) {
            // Otomatis hapus / nonaktifkan tautan pengunjung
            $share->update([
                'allow_guest' => false,
                'guest_expires_at' => null,
            ]);
            session()->forget('verified_backup_share_' . $share->id);
            session()->forget('guest_backup_share_' . $share->id);
            session()->forget('guest_backup_share_expires_at_' . $share->id);

            return Inertia::render('Backup/SharedFolder', [
                'isGuestExpired' => true,
                'shareName' => $share->share_name,
                'folderName' => $share->folder?->file_name ?: $share->pc->pc_name,
                'guestDurationHours' => (int)($share->guest_duration_hours ?: 24),
                'shareToken' => $token,
                'personnelUrl' => route('backup.shared.view', $token),
            ]);
        }

        // Jika pemilik folder menonaktifkan akses tamu
        if (!$share->allow_guest) {
            return Inertia::render('Backup/SharedFolder', [
                'isGuestDisabled' => true,
                'shareName' => $share->share_name,
                'folderName' => $share->folder?->file_name ?: $share->pc->pc_name,
                'shareToken' => $token,
                'personnelUrl' => route('backup.shared.view', $token),
            ]);
        }

        $sessionKey = 'verified_backup_share_' . $share->id;
        $isVerified = session()->get($sessionKey) === true;

        // Periksa apakah masa berlaku sesi akses tamu telah kedaluwarsa
        if ($isVerified) {
            $guestExpiresAt = session()->get('guest_backup_share_expires_at_' . $share->id);
            if ($guestExpiresAt && now()->timestamp > $guestExpiresAt) {
                session()->forget('verified_backup_share_' . $share->id);
                session()->forget('guest_backup_share_' . $share->id);
                session()->forget('guest_backup_share_expires_at_' . $share->id);
                $isVerified = false;
                $duration = $share->guest_duration_hours ?: 24;
                return $this->renderPinView(
                    $share, 
                    $token, 
                    true, 
                    "Masa berlaku akses sesi tamu Anda ({$duration} Jam) telah berakhir. Silakan isi kembali formulir Buku Tamu untuk memperbarui akses."
                );
            }
        }

        // Jika belum memasukkan PIN dan identitas tamu
        if (!$isVerified) {
            return $this->renderPinView($share, $token, true);
        }

        return $this->renderFolderView($share, $token, $request, true);
    }

    /**
     * Memverifikasi PIN Keamanan yang dimasukkan personel internal (dengan Rate-Limiting)
     */
    public function verifyPin($token, Request $request)
    {
        $request->validate([
            'pin' => 'required|string',
        ]);

        $share = BackupShare::where('share_token', $token)->first();

        if (!$share || !$share->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tautan tidak valid atau telah dinonaktifkan oleh Administrator.',
            ], 403);
        }

        $throttleKey = 'backup_share_pin_' . $share->id . '_' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'status' => 'error',
                'message' => "Terlalu banyak percobaan salah! Silakan tunggu {$seconds} detik lagi.",
            ], 429);
        }

        if (!$share->verifyPin($request->pin)) {
            RateLimiter::hit($throttleKey, 300); // Kunci 5 menit jika gagal 5x
            $remaining = RateLimiter::remaining($throttleKey, 5);

            return response()->json([
                'status' => 'error',
                'message' => "PIN keamanan tidak sesuai! Sisa percobaan: {$remaining} kali.",
            ], 401);
        }

        RateLimiter::clear($throttleKey);

        // Catat keberhasilan verifikasi ke dalam session
        session()->put('verified_backup_share_' . $share->id, true);

        // Catat log audit personel yang mengakses
        $user = Auth::user();
        if ($user) {
            Log::info("Personel {$user->name} (" . ($user->nrp ?? $user->id) . ") berhasil memverifikasi PIN folder share ID {$share->id}");

            try {
                BackupShareAccessLog::recordAccess(
                    shareId: $share->id,
                    accessType: 'personel',
                    nama: $user->name,
                    nrp: $user->nrp ?? $user->nip ?? '-',
                    pangkat: $user->pangkat ?? 'Personel',
                    satuan: 'Internal SINDEN',
                    whatsapp: null,
                    userId: $user->id,
                    ip: $request->ip(),
                    userAgent: substr((string)$request->userAgent(), 0, 500)
                );
            } catch (\Throwable $e) {
                Log::warning("Gagal mencatat audit log akses personel: " . $e->getMessage());
            }
        }

        // Perbarui statistik akses
        $share->increment('access_count');
        $share->update(['last_accessed_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'PIN terverifikasi. Membuka folder...',
        ]);
    }

    /**
     * Memverifikasi PIN Keamanan & Catat Identitas Pengunjung Tamu (NRP, Nama, Satuan)
     */
    public function verifyGuest($token, Request $request)
    {
        $request->validate([
            'pin' => 'required|string',
            'nrp' => 'nullable|string|max:50',
            'pangkat' => 'nullable|string|max:100',
            'nama' => 'required|string|max:150',
            'satuan' => 'required|string|max:150',
            'whatsapp' => ['required', 'string', 'min:9', 'max:25'],
        ], [
            'whatsapp.required' => 'Nomor WhatsApp wajib diisi untuk menerima notifikasi masa berlaku akses.',
        ]);

        $share = BackupShare::where('share_token', $token)->first();

        if (!$share || !$share->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tautan tidak valid atau telah dinonaktifkan oleh Administrator.',
            ], 403);
        }

        if (!$share->allow_guest) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses pengunjung / tamu untuk folder ini telah dinonaktifkan oleh pemilik folder.',
            ], 403);
        }

        $throttleKey = 'backup_share_guest_pin_' . $share->id . '_' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'status' => 'error',
                'message' => "Terlalu banyak percobaan salah! Silakan tunggu {$seconds} detik lagi.",
            ], 429);
        }

        if (!$share->verifyPin($request->pin)) {
            RateLimiter::hit($throttleKey, 300);
            $remaining = RateLimiter::remaining($throttleKey, 5);

            return response()->json([
                'status' => 'error',
                'message' => "PIN keamanan tidak sesuai! Sisa percobaan: {$remaining} kali.",
            ], 401);
        }

        RateLimiter::clear($throttleKey);

        // Bersihkan dan normalisasi nomor WhatsApp (08xxx -> 628xxx)
        $waRaw = preg_replace('/[^0-9]/', '', (string)$request->whatsapp);
        if (str_starts_with($waRaw, '0')) {
            $waNormalized = '62' . substr($waRaw, 1);
        } else {
            $waNormalized = $waRaw;
        }

        $durationHours = (int)($share->guest_duration_hours ?: 24);
        if ($durationHours <= 0) {
            $durationHours = 24;
        }
        $expiresAt = now()->addHours($durationHours);

        $pangkat = trim($request->pangkat ?: '');
        $nrpInput = trim($request->nrp ?: '');

        // Jika pangkat tidak diisi namun mengisi NRP, coba cari data personel di sistem
        if (empty($pangkat) && !empty($nrpInput) && $nrpInput !== '-') {
            $matchedUser = \App\Models\User::where('nrp', $nrpInput)
                ->orWhere('nip', $nrpInput)
                ->first();
            if ($matchedUser && !empty($matchedUser->pangkat)) {
                $pangkat = trim($matchedUser->pangkat);
            }
        }

        // Catat ke tabel riwayat tamu (BackupShareGuest)
        BackupShareGuest::ensureSchema();
        $guestRecord = BackupShareGuest::create([
            'backup_share_id' => $share->id,
            'nrp' => trim($request->nrp ?: '-'),
            'pangkat' => $pangkat ?: null,
            'nama' => trim($request->nama),
            'satuan' => trim($request->satuan),
            'whatsapp' => $waNormalized,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string)$request->userAgent(), 0, 500),
            'accessed_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        // Catat ke tabel audit log akses terpadu (BackupShareAccessLog)
        try {
            BackupShareAccessLog::recordAccess(
                shareId: $share->id,
                accessType: 'tamu',
                nama: $guestRecord->nama,
                nrp: $guestRecord->nrp,
                pangkat: $guestRecord->pangkat,
                satuan: $guestRecord->satuan,
                whatsapp: $guestRecord->whatsapp,
                userId: null,
                ip: $request->ip(),
                userAgent: substr((string)$request->userAgent(), 0, 500),
                expiresAt: $expiresAt
            );
        } catch (\Throwable $logErr) {
            Log::warning("Gagal mencatat log akses tamu ke BackupShareAccessLog: " . $logErr->getMessage());
        }

        // Simpan sesi terverifikasi dan identitas tamu
        session()->put('verified_backup_share_' . $share->id, true);
        session()->put('guest_backup_share_expires_at_' . $share->id, $expiresAt->timestamp);
        session()->put('guest_backup_share_' . $share->id, [
            'id' => $guestRecord->id,
            'nrp' => $guestRecord->nrp,
            'pangkat' => $guestRecord->pangkat,
            'nama' => $guestRecord->nama,
            'satuan' => $guestRecord->satuan,
            'whatsapp' => $guestRecord->whatsapp,
            'duration_hours' => $durationHours,
            'expires_at_timestamp' => $expiresAt->timestamp,
            'expires_at_human' => $expiresAt->format('d/m/Y H:i') . ' WIB',
            'login_at' => now()->format('d M Y H:i'),
        ]);

        // Kirim Notifikasi WhatsApp Otomatis ke Nomor Tamu
        try {
            $formattedExpires = $expiresAt->format('d/m/Y H:i') . ' WIB';
            $folderTitle = $share->share_name ?: ($share->folder?->file_name ?: $share->pc->pc_name);
            $guestLink = route('backup.shared.guest-view', $share->share_token);

            $pangkatStr = trim($guestRecord->pangkat ?: '');
            $namaStr = trim($guestRecord->nama);
            $nrpStr = trim($guestRecord->nrp ?: '');
            $nrpPart = '';
            if (!empty($nrpStr) && $nrpStr !== '-') {
                $cleanNrp = trim(preg_replace('/^NRP\s*[:.-]?\s*/i', '', $nrpStr));
                $nrpPart = "[NRP {$cleanNrp}]";
            }

            $salutationParts = array_filter([$pangkatStr, $namaStr, $nrpPart]);
            $recipientHeader = "Yth. " . implode(' ', $salutationParts);

            $waMessage = "*SINDEN - PEMBERITAHUAN SHARING FOLDER*\n"
                . "━━━━━━━━━━━━━━━━━━━━\n"
                . "*{$recipientHeader}*\n"
                . "Satuan/Instansi : *{$guestRecord->satuan}*\n\n"
                . "Akses Anda ke folder berbagi telah berhasil diverifikasi:\n"
                . "Folder : *{$folderTitle}*\n"
                . "Masa Berlaku Akses : *{$durationHours} Jam*\n"
                . "Berlaku Hingga : *{$formattedExpires}*\n\n"
                . "Tautan Akses :\n"
                . "{$guestLink}\n\n"
                . "Pemberitahuan Keamanan Kedinasan:\n"
                . "1. Seluruh aktivitas penjelajahan dan pengunduhan berkas tercatat dalam log pangkalan berkas SINDEN.\n"
                . "2. Tautan folder ini aktif selama {$durationHours} jam dan otomatis terkunci/dihapus setelah batas waktu berakhir.\n"
                . "━━━━━━━━━━━━━━━━━━━━\n"
                . "_Sistem Informasi & Dokumen Elektronik (SINDEN)_";

            WhatsappService::sendMessage($waNormalized, $waMessage);
        } catch (\Throwable $waErr) {
            Log::warning("Gagal mengirim notifikasi WA akses tamu: " . $waErr->getMessage());
        }

        Log::info("Tamu Luar [" . ($guestRecord->pangkat ? "{$guestRecord->pangkat} " : "") . "{$guestRecord->nama} - NRP: {$guestRecord->nrp} ({$guestRecord->satuan}) WA: {$waNormalized}] berhasil membuka folder share ID {$share->id}. Berlaku {$durationHours} jam.");

        $share->increment('access_count');
        $share->update(['last_accessed_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => "Identitas tamu dan PIN terverifikasi. Masa berlaku akses {$durationHours} jam. Notifikasi telah dikirim ke WhatsApp Anda.",
        ]);
    }

    /**
     * Keluar dari sesi verifikasi PIN (Kunci Kembali Folder)
     */
    public function exitShare($token, Request $request)
    {
        $share = BackupShare::where('share_token', $token)->first();
        if ($share) {
            session()->forget('verified_backup_share_' . $share->id);
            session()->forget('guest_backup_share_' . $share->id);
            session()->forget('guest_backup_share_expires_at_' . $share->id);
        }

        $isGuest = $request->query('mode') === 'guest' 
            || str_contains(url()->previous(), '/tamu/') 
            || str_contains(url()->previous(), '/guest/');

        if ($isGuest) {
            return redirect()->route('backup.shared.guest-view', $token);
        }

        return redirect()->route('backup.shared.view', $token);
    }

    /**
     * Menampilkan Layar Input PIN / Formulir Buku Tamu
     */
    private function renderPinView($share, $token, bool $isGuestMode, ?string $expiredMessage = null)
    {
        return Inertia::render('Backup/SharedFolder', [
            'needsPin' => true,
            'isGuestMode' => $isGuestMode,
            'isDeactivated' => false,
            'isNotFound' => false,
            'isGuestDisabled' => false,
            'expiredMessage' => $expiredMessage,
            'guestDurationHours' => (int)($share->guest_duration_hours ?: 24),
            'guestExpiresAt' => $share->guest_expires_at ? $share->guest_expires_at->toIso8601String() : null,
            'guestExpiresAtHuman' => $share->guest_expires_at ? $share->guest_expires_at->format('d/m/Y H:i') : null,
            'isGuestExpired' => $share->isGuestExpired(),
            'shareToken' => $token,
            'shareName' => $share->share_name,
            'folderName' => $share->folder?->file_name ?: $share->pc->pc_name,
            'pcName' => $share->pc->pc_name,
            'allowGuest' => (bool)$share->allow_guest,
            'personnelUrl' => route('backup.shared.view', $token),
            'guestUrl' => route('backup.shared.guest-view', $token),
            'currentUser' => Auth::user() ? [
                'id' => Auth::id(),
                'name' => Auth::user()->name,
                'pangkat' => Auth::user()->pangkat ?? 'Personel',
                'nrp' => Auth::user()->nrp ?? Auth::user()->nip ?? null,
            ] : null,
        ]);
    }

    /**
     * Menampilkan Daftar Berkas Shared Folder (Mode Terverifikasi)
     */
    private function renderFolderView($share, $token, Request $request, bool $isGuestMode)
    {
        // Menentukan folder aktif dalam cakupan berbagi
        $requestedFolderId = $request->query('folder');
        $currentFolderId = null;

        if ($requestedFolderId) {
            $candidateFolder = Backup::find($requestedFolderId);
            if ($candidateFolder && $candidateFolder->is_folder && $share->isWithinScope($candidateFolder)) {
                $currentFolderId = $candidateFolder->id;
            } else {
                $currentFolderId = $share->backup_id;
            }
        } else {
            $currentFolderId = $share->backup_id;
        }

        // Mengambil daftar isi folder
        $search = trim($request->query('search', ''));
        $query = Backup::where('pc_id', $share->pc_id);

        if ($search) {
            $contents = $query->where('file_name', 'like', '%' . $search . '%')
                ->orderBy('is_folder', 'desc')
                ->get();
        } else {
            $contents = $query->where('parent_id', $currentFolderId)
                ->orderBy('is_folder', 'desc')
                ->orderBy('file_name', 'asc')
                ->get();
        }

        // Saring secara ketat agar hanya item di dalam lingkup share yang dimunculkan
        $filteredContents = $contents->filter(fn($item) => $share->isWithinScope($item))->values();

        $mappedContents = $filteredContents->map(function ($item) use ($token) {
            $itemArray = $item->toArray();
            $itemArray['size_human'] = !$item->is_folder ? $this->formatBytes($item->file_size) : '--';
            $itemArray['date_human'] = Carbon::parse($item->updated_at ?: $item->created_at)->format('d M Y H:i');
            $itemArray['download_url'] = !$item->is_folder ? route('backup.shared.download', ['token' => $token, 'fileId' => $item->id]) : null;
            $isArw = ArwService::isArw($item->file_type ?: $item->file_name);
            $ext = strtolower(pathinfo($item->file_name, PATHINFO_EXTENSION));
            $isImg = !$item->is_folder && in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg']);
            $itemArray['is_arw'] = $isArw;
            $itemArray['preview_url'] = !$item->is_folder ? route('backup.shared.preview', ['token' => $token, 'fileId' => $item->id]) : null;
            $itemArray['thumbnail_url'] = ($isArw || $isImg) ? route('backup.shared.thumbnail', ['token' => $token, 'fileId' => $item->id]) : null;
            $itemArray['download_jpg_url'] = $isArw ? route('backup.shared.download-arw-jpg', ['token' => $token, 'fileId' => $item->id]) : null;
            $itemArray['is_secured'] = true;
            return $itemArray;
        });

        // Susun Breadcrumb Terbatas (tidak melewati batas atas folder yang dibagikan)
        $breadcrumbs = [];
        $tempFolderId = $currentFolderId;
        $maxDepth = 20;
        $depth = 0;

        while ($tempFolderId && $depth < $maxDepth) {
            if ($share->backup_id && (int)$tempFolderId === (int)$share->backup_id) {
                $folder = Backup::find($tempFolderId);
                array_unshift($breadcrumbs, [
                    'id' => $folder->id,
                    'name' => $folder->file_name,
                ]);
                break;
            }

            $folder = Backup::find($tempFolderId);
            if ($folder) {
                array_unshift($breadcrumbs, [
                    'id' => $folder->id,
                    'name' => $folder->file_name,
                ]);
                $tempFolderId = $folder->parent_id;
            } else {
                break;
            }
            $depth++;
        }

        $guestUser = session()->get('guest_backup_share_' . $share->id);
        $guestExpiresAt = session()->get('guest_backup_share_expires_at_' . $share->id);

        return Inertia::render('Backup/SharedFolder', [
            'needsPin' => false,
            'isDeactivated' => false,
            'isNotFound' => false,
            'isGuestDisabled' => false,
            'isGuestMode' => $isGuestMode,
            'guestDurationHours' => (int)($share->guest_duration_hours ?: 24),
            'guestExpiresAt' => $guestExpiresAt,
            'shareToken' => $token,
            'shareName' => $share->share_name,
            'pcName' => $share->pc->pc_name,
            'folderName' => $share->folder?->file_name ?: $share->pc->pc_name,
            'contents' => $mappedContents,
            'currentFolderId' => $currentFolderId,
            'shareRootFolderId' => $share->backup_id,
            'breadcrumbs' => $breadcrumbs,
            'searchQuery' => $search,
            'personnelUrl' => route('backup.shared.view', $token),
            'guestUrl' => route('backup.shared.guest-view', $token),
            'guestUser' => $guestUser,
            'currentUser' => Auth::user() ? [
                'id' => Auth::id(),
                'name' => Auth::user()->name,
                'pangkat' => Auth::user()->pangkat ?? 'Personel',
                'nrp' => Auth::user()->nrp ?? Auth::user()->nip ?? null,
            ] : null,
        ]);
    }

    /**
     * Memvalidasi sesi akses folder berbagi dan memeriksa masa berlaku akses tamu
     */
    private function validateShareSession(BackupShare $share): void
    {
        $sessionKey = 'verified_backup_share_' . $share->id;
        if (session()->get($sessionKey) !== true) {
            abort(403, 'Otoritas PIN keamanan belum terverifikasi.');
        }

        $isGuestSession = session()->has('guest_backup_share_' . $share->id);
        if ($isGuestSession) {
            if ($share->isGuestExpired()) {
                session()->forget('verified_backup_share_' . $share->id);
                session()->forget('guest_backup_share_' . $share->id);
                session()->forget('guest_backup_share_expires_at_' . $share->id);
                abort(403, 'Masa berlaku tautan pengunjung telah berakhir dan telah dihapus secara otomatis.');
            }

            $guestExpiresAt = session()->get('guest_backup_share_expires_at_' . $share->id);
            if ($guestExpiresAt && now()->timestamp > $guestExpiresAt) {
                session()->forget('verified_backup_share_' . $share->id);
                session()->forget('guest_backup_share_' . $share->id);
                session()->forget('guest_backup_share_expires_at_' . $share->id);
                abort(403, 'Masa berlaku sesi akses tamu telah habis. Silakan isi kembali formulir.');
            }
        }
    }

    /**
     * Unduh Berkas Tunggal secara Aman (Terbatas dalam Lingkup Folder)
     */
    public function downloadFile($token, $fileId)
    {
        $share = BackupShare::where('share_token', $token)->where('is_active', true)->firstOrFail();
        $this->validateShareSession($share);

        $file = Backup::where('id', $fileId)->where('pc_id', $share->pc_id)->firstOrFail();

        if ($file->is_folder) {
            abort(400, 'Folder tidak dapat diunduh langsung.');
        }

        if (!$share->isWithinScope($file)) {
            abort(403, 'Akses ditolak: Berkas berada di luar cakupan folder yang dibagikan.');
        }

        $fullPath = FileSecurityService::verifySafeStoragePath($file->file_path);
        if (!file_exists($fullPath)) {
            Log::warning("Shared file download not found: {$fullPath}");
            abort(404, 'Berkas fisik tidak ditemukan di server penyimpanan.');
        }

        $mimeType = @mime_content_type($fullPath) ?: 'application/octet-stream';
        return FileSecurityService::streamDecryptedDownload($fullPath, $file->file_name, $mimeType);
    }

    /**
     * Pratinjau Berkas (Gambar, PDF, dsb.) secara Aman
     */
    public function previewFile($token, $fileId)
    {
        if (request()->header('Sec-Fetch-Dest') === 'document') {
            abort(403, 'Akses Ditolak: Pratinjau berkas hanya diizinkan melalui antarmuka aplikasi internal.');
        }

        $share = BackupShare::where('share_token', $token)->where('is_active', true)->firstOrFail();
        $this->validateShareSession($share);

        $file = Backup::where('id', $fileId)->where('pc_id', $share->pc_id)->firstOrFail();

        if ($file->is_folder || !$share->isWithinScope($file)) {
            abort(403, 'Akses ditolak.');
        }

        $fullPath = FileSecurityService::verifySafeStoragePath($file->file_path);
        if (!file_exists($fullPath)) {
            abort(404, 'Berkas fisik tidak ditemukan.');
        }

        // Dukungan khusus format Sony RAW (.ARW) -> Pratinjau sebagai JPG kualitas tinggi
        if (ArwService::isArw($file->file_type ?: $file->file_name)) {
            return ArwService::previewResponse($fullPath, $file->file_name);
        }

        return FileSecurityService::streamDecryptedInline($fullPath, $file->file_name);
    }

    /**
     * Konversi Dokumen Office (Word, Excel, dsb.) ke PDF untuk Pratinjau Shared Folder
     */
    public function viewOffice($token, $fileId)
    {
        $share = BackupShare::where('share_token', $token)->where('is_active', true)->firstOrFail();
        $this->validateShareSession($share);

        $file = Backup::where('id', $fileId)->where('pc_id', $share->pc_id)->firstOrFail();

        if ($file->is_folder || !$share->isWithinScope($file)) {
            abort(403, 'Akses ditolak.');
        }

        $fullPath = FileSecurityService::verifySafeStoragePath($file->file_path);
        if (!file_exists($fullPath)) {
            abort(404, 'Berkas fisik tidak ditemukan.');
        }

        $ext = strtolower(pathinfo($file->file_name, PATHINFO_EXTENSION) ?: $file->file_type ?: 'docx');
        if ($ext === 'pdf') {
            return FileSecurityService::streamDecryptedInline($fullPath, $file->file_name);
        }

        $cacheDir = storage_path('app/public/office_cache');
        if (!file_exists($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $cacheKey = 'office_' . $file->id . '_' . $file->file_size . '.pdf';
        $cachedPdf = $cacheDir . '/' . $cacheKey;

        if (file_exists($cachedPdf) && filesize($cachedPdf) > 100) {
            return response()->file($cachedPdf, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . pathinfo($file->file_name, PATHINFO_FILENAME) . '.pdf"',
                'X-Frame-Options' => 'SAMEORIGIN',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

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
                    'Content-Disposition' => 'inline; filename="' . pathinfo($file->file_name, PATHINFO_FILENAME) . '.pdf"',
                    'X-Frame-Options' => 'SAMEORIGIN',
                    'Cache-Control' => 'public, max-age=86400',
                ]);
            }
            @rmdir($tempOutDir);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal konversi shared office ID {$fileId} ke PDF: " . $e->getMessage());
        } finally {
            if (file_exists($tempPlainFile)) {
                @unlink($tempPlainFile);
            }
        }

        return response('<div style="font-family:sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;background:#f8fafc;color:#1e293b;text-align:center;padding:24px;">' .
            '<svg style="width:48px;height:48px;color:#64748b;margin-bottom:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>' .
            '<h3 style="margin:0 0 8px 0;font-size:15px;font-weight:800;text-transform:uppercase;">Pratinjau Server Belum Tersedia</h3>' .
            '<p style="font-size:12px;color:#64748b;max-width:380px;margin:0 0 16px 0;line-height:1.5;">Dokumen ini belum dapat dikonversi ke PDF otomatis di server. Silakan klik tombol di bawah untuk mengunduh berkas langsung.</p>' .
            '<a href="' . route('backup.shared.download', ['token' => $token, 'fileId' => $file->id]) . '" style="padding:10px 20px;background:#059669;color:#fff;text-decoration:none;border-radius:10px;font-weight:700;font-size:12px;display:inline-flex;align-items:center;gap:8px;">Unduh Berkas Langsung</a>' .
            '</div>', 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Sajikan thumbnail gambar terkompresi cepat untuk shared folder (Disk Cached)
     */
    public function thumbnail($token, $fileId)
    {
        if (request()->header('Sec-Fetch-Dest') === 'document') {
            abort(403, 'Akses Ditolak.');
        }

        $share = BackupShare::where('share_token', $token)->where('is_active', true)->firstOrFail();
        $this->validateShareSession($share);

        $file = Backup::where('id', $fileId)->where('pc_id', $share->pc_id)->firstOrFail();

        if ($file->is_folder || !$share->isWithinScope($file)) {
            abort(403, 'Akses ditolak.');
        }

        $fullPath = FileSecurityService::verifySafeStoragePath($file->file_path);
        if (!file_exists($fullPath)) {
            abort(404, 'Berkas fisik tidak ditemukan.');
        }

        $isArw = ArwService::isArw($file->file_type ?: $file->file_name);
        return ThumbnailService::getThumbnailResponse($fullPath, $file->file_name, $isArw);
    }

    /**
     * Unduh Berkas Sony RAW (.ARW) Terkonversi sebagai Format JPG HD dalam Shared Folder
     */
    public function downloadArwJpg($token, $fileId)
    {
        $share = BackupShare::where('share_token', $token)->where('is_active', true)->firstOrFail();
        $this->validateShareSession($share);

        $file = Backup::where('id', $fileId)->where('pc_id', $share->pc_id)->firstOrFail();

        if ($file->is_folder || !$share->isWithinScope($file)) {
            abort(403, 'Akses ditolak: Berkas berada di luar cakupan.');
        }

        if (!ArwService::isArw($file->file_type ?: $file->file_name)) {
            abort(400, 'Berkas bukan format Sony RAW (.ARW).');
        }

        FileSecurityService::verifySafeStoragePath($file->file_path);

        return ArwService::downloadConvertedJpg($file);
    }

    /**
     * Unduh Seluruh Isi Folder Menjadi Paket ZIP64 Streaming
     * Dialirkan langsung ke respons peramban menggunakan metode STORE tanpa membuat berkas fisik temporer di disk.
     */
    public function downloadFolderZip($token, Request $request)
    {
        $share = BackupShare::where('share_token', $token)->where('is_active', true)->firstOrFail();
        $this->validateShareSession($share);

        $targetFolderId = $request->query('folder_id') ?: $share->backup_id;
        $targetFolder = null;

        if ($targetFolderId) {
            $targetFolder = Backup::findOrFail($targetFolderId);
            if (!$share->isWithinScope($targetFolder)) {
                abort(403, 'Folder berada di luar cakupan.');
            }
        }

        // Ambil seluruh item (berkas dan subfolder) di dalam target folder
        $items = Backup::where('pc_id', $share->pc_id)
            ->where('parent_id', $targetFolderId)
            ->get();

        if ($items->isEmpty()) {
            return back()->with('error', 'Tidak ada berkas yang dapat dikompresi di folder ini.');
        }

        $folderTitle = $targetFolder ? $targetFolder->file_name : ($share->share_name ?: 'BERKAS_BAGIKAN');
        $zipFileName = 'UNDUHAN_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $folderTitle) . '_' . date('Ymd_His') . '.zip';

        return response()->stream(function () use ($items, $share) {
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
                $this->streamSharedItemToZip($item, $zipStream, '', $share);
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
     * Alirkan item berbagi ke ZIP stream secara rekursif
     */
    private function streamSharedItemToZip($item, StreamingZipService $zipStream, string $zipSubDir, BackupShare $share): void
    {
        if (connection_aborted()) {
            return;
        }

        if ($item->is_folder) {
            $newDir = $zipSubDir ? $zipSubDir . '/' . $item->file_name : $item->file_name;
            $zipStream->addEmptyDir($newDir);
            $children = Backup::where('parent_id', $item->id)->where('pc_id', $share->pc_id)->get();
            foreach ($children as $child) {
                if (connection_aborted()) {
                    break;
                }
                $this->streamSharedItemToZip($child, $zipStream, $newDir, $share);
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

    /**
     * Endpoint API Khusus Admin: Menampilkan Log Siapa Saja yang Mengakses Share Folder Secara Langsung
     */
    public function getAccessLogs(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }

        $isAdmin = $user->isAdmin() || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';
        if (!$isAdmin) {
            return response()->json(['status' => 'error', 'message' => 'Otoritas ditolak: Khusus Administrator.'], 403);
        }

        BackupShareAccessLog::ensureSchema();

        $query = BackupShareAccessLog::with(['share.pc', 'share.folder', 'user'])
            ->latest('last_accessed_at');

        // Filter tipe akses ('all', 'personel', 'tamu')
        if ($request->filled('type') && in_array($request->type, ['personel', 'tamu'])) {
            $query->where('access_type', $request->type);
        }

        // Filter folder tertentu
        if ($request->filled('share_id')) {
            $query->where('backup_share_id', $request->share_id);
        }

        // Filter pencarian (nama, pangkat, nrp, satuan, ip, nama folder)
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('pangkat', 'like', "%{$search}%")
                    ->orWhere('nrp', 'like', "%{$search}%")
                    ->orWhere('satuan', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('share', function ($sq) use ($search) {
                        $sq->where('share_name', 'like', "%{$search}%");
                    });
            });
        }

        $allLogs = $query->take(150)->get();

        // Statistik agregat
        $totalAccessors = BackupShareAccessLog::count();
        $totalPersonel = BackupShareAccessLog::where('access_type', 'personel')->count();
        $totalTamu = BackupShareAccessLog::where('access_type', 'tamu')->count();
        $totalHits = (int)BackupShareAccessLog::sum('access_count');

        $formattedLogs = $allLogs->map(function ($log) {
            $share = $log->share;
            $shareTitle = $share?->share_name ?: ($share?->folder?->file_name ?: $share?->pc?->pc_name ?: 'Folder Berbagi');
            $isExpired = $log->expires_at ? now()->greaterThan($log->expires_at) : false;

            return [
                'id' => $log->id,
                'access_type' => $log->access_type,
                'pangkat' => $log->pangkat ?: ($log->access_type === 'personel' ? 'Personel' : '-'),
                'nama' => $log->nama,
                'nrp' => $log->nrp ?: '-',
                'satuan' => $log->satuan ?: ($log->access_type === 'personel' ? 'Internal SINDEN' : '-'),
                'whatsapp' => $log->whatsapp ?: null,
                'ip_address' => $log->ip_address ?: '-',
                'user_agent' => $log->user_agent,
                'access_count' => (int)$log->access_count,
                'last_accessed_at' => $log->last_accessed_at ? $log->last_accessed_at->format('d/m/Y H:i:s') . ' WIB' : '-',
                'last_accessed_time' => $log->last_accessed_at ? $log->last_accessed_at->format('H:i:s') . ' WIB' : '-',
                'last_accessed_date' => $log->last_accessed_at ? $log->last_accessed_at->format('d M Y') : '-',
                'time_ago' => $log->last_accessed_at ? $log->last_accessed_at->diffForHumans() : '-',
                'first_accessed_at' => $log->first_accessed_at ? $log->first_accessed_at->format('d/m/Y H:i') . ' WIB' : '-',
                'expires_at' => $log->expires_at ? $log->expires_at->format('d/m/Y H:i') . ' WIB' : null,
                'is_expired' => $isExpired,
                'share_id' => $log->backup_share_id,
                'share_name' => $shareTitle,
                'pc_name' => $share?->pc?->pc_name ?: '-',
                'share_url' => $share ? route('backup.shared.view', $share->share_token) : null,
                'guest_share_url' => $share ? route('backup.shared.guest-view', $share->share_token) : null,
                'is_share_active' => (bool)($share?->is_active ?? false),
            ];
        });

        return response()->json([
            'status' => 'success',
            'server_time' => now()->format('H:i:s') . ' WIB',
            'server_timestamp' => now()->timestamp,
            'stats' => [
                'total_accessors' => $totalAccessors,
                'total_personel' => $totalPersonel,
                'total_tamu' => $totalTamu,
                'total_hits' => $totalHits,
            ],
            'logs' => $formattedLogs,
        ]);
    }

    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) $bytes /= 1024;
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
