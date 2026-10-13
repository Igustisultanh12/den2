<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\LetterLogController;
use App\Http\Controllers\VisitorLogController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\Auth\ResetPasswordController; 
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\SignatureRequestController;
use App\Http\Controllers\SoldierViolationController;
use App\Http\Controllers\CommunityActivityController;
use App\Http\Controllers\StampController;
use App\Http\Controllers\BackupController; 
use App\Http\Controllers\BackupShareController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\KoperasiController;
use App\Http\Controllers\PersonnelController;
use App\Http\Controllers\PESSAdminController; // SULTAN CONFIG: Pangkalan Pemisah Logika Otoritas Kerja Admin PESS
use App\Http\Controllers\API\PESSReceiverController; // SULTAN CONFIG: Controller Penerima File Lintas VPS
use App\Http\Controllers\CommanderAccountController; // SINDEN CORRECTION: Kalibrasi typo dari Controkkers ke Controllers
use App\Http\Controllers\IbuBetiAccountController;
use App\Http\Controllers\MitraPaymentController;
use App\Http\Controllers\TechnicalUnitCashController;
use App\Http\Controllers\SkhppController;
use App\Http\Controllers\DocVerificationController;
use App\Http\Controllers\SpJagaController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\PublicTrackingController;
use App\Http\Controllers\ScSubmissionController;
use App\Http\Controllers\SystemCheckController;
use App\Http\Controllers\PrintServiceController;
use App\Http\Controllers\Chat\LiveChatController;
use App\Http\Controllers\WhatsAppController;
use App\Models\User;
use App\Models\LetterLog;
use App\Models\Letter;
use App\Models\VisitorLog;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\Attendance;
use Illuminate\Support\Facades\Http;

// --- HALAMAN DEPAN (DIRECT LOGIN SINDEN) ---
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

// --- FITUR AKTIVASI & RESET CUSTOM (AKSES PUBLIK) ---
Route::get('/aktivasi', function () {
    return Inertia::render('Auth/Aktivasi'); 
})->name('aktivasi');
Route::post('/aktivasi', [UserController::class, 'activate'])->name('aktivasi.proses');

Route::get('forgot-password-custom', [ResetPasswordController::class, 'showResetForm'])->name('password.request.custom');
Route::post('reset-password-custom', [ResetPasswordController::class, 'store'])->name('password.update.custom');

// --- INTEGRASI GOOGLE OAUTH: LOGIN & TAUTKAN AKUN ---
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

// --- FITUR TRACKING PENGAJUAN SC (AKSES PUBLIK TANPA LOGIN) ---
Route::get('/tracking-sc', [PublicTrackingController::class, 'index'])->name('tracking-sc.index');
Route::post('/tracking-sc/token', [PublicTrackingController::class, 'generatePreviewToken'])->name('tracking-sc.token');
Route::get('/tracking-sc/stream/{token}', [PublicTrackingController::class, 'streamSecurePdf'])->name('tracking-sc.stream');
Route::get('/tracking-sc/{tracking_code}/preview-pdf', [PublicTrackingController::class, 'previewPdf'])->name('tracking-sc.preview-pdf');

// --- AKSES DASHBOARD UTAMA ---
Route::get('/dashboard', function () {
    $user = auth()->user();
    if ($user && $user->role !== 'admin') {
        $r = strtolower(preg_replace('/[\s_-]+/', '', $user->role ?? ''));
        $j = strtolower($user->jabatan ?? '');
        if ($r === 'anggotasintel' || str_contains($j, 'anggota sintel')) {
            return redirect()->route('sc-submissions.index');
        }
    }

    $recentVisitors = VisitorLog::latest()->take(5)->get()->map(function($log) {
        $log->type = 'LOGIN'; 
        return $log;
    });
    $recentAudits = AuditLog::latest()->take(5)->get()->map(function($log) {
        $log->type = 'ADMIN_ACTION'; 
        $log->user_name = $log->admin_name; 
        $log->nrp = 'ADMIN'; 
        return $log;
    });
    $combinedActivities = $user->role === 'admin' ? $recentVisitors->concat($recentAudits)->sortByDesc('created_at')->take(5)->values() : null;

    $today = now()->toDateString();
    $todayAttendance = Attendance::where('user_id', $user->id)
        ->where('attendance_date', $today)
        ->first();

    $todayAttendancesCount = Attendance::where('attendance_date', $today)->count();
    $recentAttendances = in_array(strtolower($user->role), ['admin', 'komandan', 'pasops'])
        ? Attendance::with('user')->where('attendance_date', $today)->latest()->take(10)->get()
        : null;

    return Inertia::render('Dashboard', [
        'stats' => [
            'total_logs' => LetterLog::count(), 
            'total_archives' => Letter::count(), 
            'active_personnel' => User::where('is_active', true)->count(), 
            'today_attendances' => $todayAttendancesCount,
        ],
        'recent_logs' => LetterLog::latest()->take(5)->get(),
        'combined_activities' => $combinedActivities,
        'pending_users' => $user->role === 'admin' ? User::where('is_active', false)->latest()->take(5)->get() : null,
        'today_attendance' => $todayAttendance,
        'recent_attendances' => $recentAttendances,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

// =========================================================================
// MODUL BACKUP & RADAR IP (OPERASI KHUSUS KANTOR)
// =========================================================================
Route::middleware(['auth', 'office.only'])->group(function () {
    // Jalur Utama Backup
    Route::get('/pc-backup', [BackupController::class, 'index'])->name('backup.index');
    
    // --- JALUR OTORITAS AKSES (PENTING) ---
    // Sesuai pangkalan kantor.sql, rute ini dikunci ketat radar IP kantor
    Route::post('/pc-backup/request', [BackupController::class, 'requestAccess'])->name('backup.request-access');
    Route::post('/pc-backup/verify', [BackupController::class, 'verifyAccess'])->name('backup.verify'); 
    Route::post('/pc-backup/approve/{id}', [BackupController::class, 'approveAccess'])->name('admin.backup.approve');

    // Jalur Manajemen File
    Route::get('/pc-backup/explore/{id}', [BackupController::class, 'explore'])->name('backup.explore');
    Route::post('/pc-backup/store', [BackupController::class, 'store'])->name('backup.store');
    Route::post('/pc-backup/upload-chunk', [BackupController::class, 'uploadChunk'])->name('backup.upload-chunk');
    Route::post('/pc-backup/cancel-upload', [BackupController::class, 'cancelUploadChunk'])->name('backup.cancel-upload');
    Route::get('/pc-backup/download/{id}', [BackupController::class, 'download'])->name('backup.download');
    Route::delete('/pc-backup/destroy/{id}', [BackupController::class, 'destroyBackup'])->name('backup.destroy');

    // --- JALUR RADAR IP ADMIN ---
    // Berfungsi mengunci whitelist IP pangkalan agar server VPS aman dari luar
    Route::delete('/admin/pc-backup/revoke/{id}', [BackupController::class, 'revokePc'])->name('admin.backup.revoke');
    Route::post('/admin/backup/network', [BackupController::class, 'storeNetwork'])->name('admin.backup.network.store');
    Route::delete('/admin/backup/network/{id}', [BackupController::class, 'destroyNetwork'])->name('admin.backup.network.destroy');
    
    //--membuat folder
    Route::post('/pc-backup/create-folder', [BackupController::class, 'createFolder'])->name('backup.create-folder');
    
    // --- FITUR EKSTRAKSI CERDAS & RENAME (KALIBRASI 20GB) ---
    Route::post('/pc-backup/extract/{id}', [BackupController::class, 'extract'])->name('backup.extract');
    Route::post('/pc-backup/rename/{id}', [BackupController::class, 'rename'])->name('backup.rename');
    Route::delete('/backup/{id}', [BackupController::class, 'destroyBackup'])->name('backup.destroy-backup');

    // PENAMBAHAN RADAR PROGRESS & CANCEL (KHUSUS EXTRAK BESAR SURAT MAKO)
    Route::post('/pc-backup/start-extract/{id}', [BackupController::class, 'startExtract'])->name('backup.start-extract');
    Route::get('/pc-backup/extract-progress', [BackupController::class, 'getExtractProgress'])->name('backup.extract-progress');
    Route::get('/pc-backup/view-office/{id}', [BackupController::class, 'viewOffice'])->name('backup.view-office');

    // --- FITUR EDITOR & PEMBUATAN EXCEL / SPREADSHEET (BACKUP) ---
    Route::post('/pc-backup/save-excel/{id}', [BackupController::class, 'saveExcel'])->name('backup.save-excel');
    Route::post('/pc-backup/create-excel', [BackupController::class, 'createExcel'])->name('backup.create-excel');

    // --- FITUR ADMIN: BUAT PC KUOTA MANDIRI & SESUAIKAN KUOTA ---
    Route::post('/admin/pc-backup/create-pc', [BackupController::class, 'createPc'])->name('admin.backup.create-pc');
    Route::post('/admin/pc-backup/update-quota/{id}', [BackupController::class, 'updateQuota'])->name('admin.backup.update-quota');

    // --- FITUR BAGIKAN FOLDER (GOOGLE DRIVE STYLE DENGAN PIN) ---
    Route::post('/pc-backup/share/save', [BackupShareController::class, 'saveShare'])->name('backup.share.save');
    Route::delete('/pc-backup/share/{id}', [BackupShareController::class, 'revokeShare'])->name('backup.share.revoke');
    Route::delete('/pc-backup/share/{id}/guest', [BackupShareController::class, 'revokeGuestLink'])->name('backup.share.revoke-guest');
    Route::get('/pc-backup/share/access-logs', [BackupShareController::class, 'getAccessLogs'])->name('admin.backup.share-access-logs');

    // --- FITUR PREVIEW & KONVERSI SONY RAW (.ARW) KE JPG HD ---
    Route::get('/pc-backup/preview-arw/{id}', [BackupController::class, 'previewArw'])->name('backup.preview-arw');
    Route::post('/pc-backup/convert-arw/{id}', [BackupController::class, 'convertArw'])->name('backup.convert-arw');
    Route::get('/pc-backup/download-arw-jpg/{id}', [BackupController::class, 'downloadArwJpg'])->name('backup.download-arw-jpg');

    // --- FITUR MULTI-SELEKSI & BULK ACTIONS (MOVE, COPY, DELETE, ZIP) ---
    Route::post('/pc-backup/bulk-move', [BackupController::class, 'bulkMove'])->name('backup.bulk-move');
    Route::post('/pc-backup/bulk-copy', [BackupController::class, 'bulkCopy'])->name('backup.bulk-copy');
    Route::post('/pc-backup/bulk-delete', [BackupController::class, 'bulkDelete'])->name('backup.bulk-delete');
    Route::post('/pc-backup/bulk-download-zip', [BackupController::class, 'bulkDownloadZip'])->name('backup.bulk-download-zip');
});

// =========================================================================
// FITUR AKSES TAUTAN BERBAGI FOLDER BACKUP (DUAL MODE: PERSONEL & TAMU)
// =========================================================================
// 1. Jalur Tautan Personel Internal (Wajib Login Akun)
Route::get('/shared-folder/{token}', [BackupShareController::class, 'show'])->name('backup.shared.view');

// 2. Jalur Tautan Pengunjung Luar / Tamu (Tanpa Perlu Login Akun, Mengisi Buku Tamu & PIN)
Route::get('/shared-folder/tamu/{token}', [BackupShareController::class, 'showGuest'])->name('backup.shared.guest-view');
Route::get('/shared-folder/guest/{token}', [BackupShareController::class, 'showGuest']); // Alias kompatibilitas

// 3. Verifikasi & Operasi Folder Berbagi (Dilindungi Sesi Terenkripsi PIN, Terbuka untuk Personel maupun Tamu Terverifikasi)
Route::post('/shared-folder/{token}/verify-pin', [BackupShareController::class, 'verifyPin'])->name('backup.shared.verify-pin');
Route::post('/shared-folder/{token}/verify-guest', [BackupShareController::class, 'verifyGuest'])->name('backup.shared.verify-guest');
Route::post('/shared-folder/{token}/exit', [BackupShareController::class, 'exitShare'])->name('backup.shared.exit');
Route::get('/shared-folder/{token}/download/{fileId}', [BackupShareController::class, 'downloadFile'])->name('backup.shared.download');
Route::get('/shared-folder/{token}/preview/{fileId}', [BackupShareController::class, 'previewFile'])->name('backup.shared.preview');
Route::get('/shared-folder/{token}/view-office/{fileId}', [BackupShareController::class, 'viewOffice'])->name('backup.shared.view-office');
Route::get('/shared-folder/{token}/thumb/{fileId}', [BackupShareController::class, 'thumbnail'])->name('backup.shared.thumbnail');
Route::get('/shared-folder/{token}/download-zip', [BackupShareController::class, 'downloadFolderZip'])->name('backup.shared.download-zip');
Route::get('/shared-folder/{token}/download-arw-jpg/{fileId}', [BackupShareController::class, 'downloadArwJpg'])->name('backup.shared.download-arw-jpg');

// --- AKSES TERPROTEKSI (AUTH) ---
Route::middleware('auth')->group(function () {
    // --- MODUL KOMUNIKASI DINAS & PANGGILAN VIDEO (LIVE CHAT & WEBRTC VC) ---
    Route::get('/live-chat', [LiveChatController::class, 'index'])->name('chat.index');
    Route::get('/live-chat/sync', [LiveChatController::class, 'sync'])->name('chat.sync');
    Route::get('/live-chat/search-users', [LiveChatController::class, 'searchUsers'])->name('chat.search-users');
    Route::post('/live-chat/start', [LiveChatController::class, 'startChat'])->name('chat.start');
    Route::get('/live-chat/attachment/download', [LiveChatController::class, 'downloadAttachment'])->name('chat.attachment.download');
    Route::get('/live-chat/ice-servers', [LiveChatController::class, 'getIceServers'])->name('chat.ice-servers');
    Route::get('/live-chat/{uuid}/messages', [LiveChatController::class, 'getMessages'])->name('chat.messages');
    Route::post('/live-chat/{uuid}/send', [LiveChatController::class, 'sendMessage'])->name('chat.send');
    Route::post('/live-chat/{uuid}/typing', [LiveChatController::class, 'typing'])->name('chat.typing');
    Route::post('/live-chat/{uuid}/status', [LiveChatController::class, 'toggleStatus'])->name('chat.status');
    Route::delete('/live-chat/{uuid}', [LiveChatController::class, 'destroy'])->name('chat.destroy');

    // Sinyal Panggilan Video & Suara (WebRTC P2P Signaling & Agora)
    Route::post('/live-chat/{uuid}/call/initiate', [LiveChatController::class, 'initiateCall'])->name('chat.call.initiate');
    Route::get('/live-chat/{uuid}/call/signal', [LiveChatController::class, 'getCallSignal'])->name('chat.call.signal');
    Route::post('/live-chat/{uuid}/call/signal', [LiveChatController::class, 'sendCallSignal'])->name('chat.call.send-signal');
    Route::post('/live-chat/{uuid}/call/end', [LiveChatController::class, 'endCall'])->name('chat.call.end');
    Route::get('/live-chat/{uuid}/call/agora-token', [LiveChatController::class, 'getAgoraToken'])->name('chat.call.agora-token');

    // --- FITUR WHATSAPP WEB MULTI-AKUN (MULTI-SESSION WA) ---
    Route::get('/whatsapp-web', [WhatsAppController::class, 'index'])->name('whatsapp.index');
    Route::post('/whatsapp-web/sessions', [WhatsAppController::class, 'createSession'])->name('whatsapp.sessions.create');
    Route::get('/whatsapp-web/sessions/{session}/qr', [WhatsAppController::class, 'getQr'])->name('whatsapp.sessions.qr');
    Route::get('/whatsapp-web/sessions/{session}/status', [WhatsAppController::class, 'getStatus'])->name('whatsapp.sessions.status');
    Route::post('/whatsapp-web/sessions/{session}/logout', [WhatsAppController::class, 'logoutSession'])->name('whatsapp.sessions.logout');
    Route::delete('/whatsapp-web/sessions/{session}', [WhatsAppController::class, 'deleteSession'])->name('whatsapp.sessions.delete');
    Route::get('/whatsapp-web/sessions/{session}/chats', [WhatsAppController::class, 'getChats'])->name('whatsapp.sessions.chats');
    Route::get('/whatsapp-web/sessions/{session}/calls', [WhatsAppController::class, 'getCalls'])->name('whatsapp.sessions.calls');
    Route::get('/whatsapp-web/sessions/{session}/chats/{jid}/messages', [WhatsAppController::class, 'getMessages'])->name('whatsapp.sessions.messages');
    Route::get('/whatsapp-web/sessions/{session}/media/{msgId}', [WhatsAppController::class, 'getMedia'])->name('whatsapp.sessions.media');
    Route::post('/whatsapp-web/sessions/{session}/send', [WhatsAppController::class, 'sendMessage'])->name('whatsapp.sessions.send');

    // PRATINJAU DOKUMEN & MEDIA TEROTENTIKASI (STREAMING DEKRIPSI ON-THE-FLY)
    Route::get('/pc-backup/preview-file/{id}', [BackupController::class, 'previewFile'])->name('backup.preview-file');
    Route::get('/pc-backup/thumbnail/{id}', [BackupController::class, 'thumbnail'])->name('backup.thumbnail');

    // Fitur GPS Lokasi Personel
    Route::post('/update-location', [LocationController::class, 'update'])->name('location.update');

    // Fitur Presensi Kehadiran Personel (Opsional)
    Route::post('/attendances', [AttendanceController::class, 'store'])->name('attendances.store');

    // --- FITUR MANAJEMEN PENGAJUAN SECURITY CLEARANCE (SC) ---
    Route::get('sc-submissions/export-pdf', [ScSubmissionController::class, 'exportPdf'])->name('sc-submissions.pdf');
    Route::post('sc-submissions/sync-skhpp', [ScSubmissionController::class, 'syncFromApprovedSkhpp'])->name('sc-submissions.sync-skhpp');
    Route::post('sc-submissions/notify-sintel', [ScSubmissionController::class, 'sendSintelNotification'])->name('sc-submissions.notify-sintel');
    Route::post('sc-submissions/{id}/toggle-taken', [ScSubmissionController::class, 'toggleTaken'])->name('sc-submissions.toggle-taken');
    Route::post('sc-submissions/{id}/update-stage', [ScSubmissionController::class, 'updateStage'])->name('sc-submissions.update-stage');
    Route::resource('sc-submissions', ScSubmissionController::class)->except(['create', 'edit', 'show']);

    // Fitur Profil Personel
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::match(['patch', 'post'], '/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/google/unlink', [GoogleAuthController::class, 'unlinkGoogle'])->name('profile.google.unlink');

    // Manajemen Surat-Menyurat
    Route::resource('letters', LetterController::class);
    Route::post('/letters/store-direct', [LetterController::class, 'storeDirect'])->name('letters.store-direct');
    Route::get('/letter-logs', [LetterLogController::class, 'index'])->name('letter-logs.index');
    Route::post('/letter-logs', [LetterLogController::class, 'store'])->name('letter-logs.store');
    Route::get('/letter-logs/next-number', [LetterLogController::class, 'getNextNumber'])->name('letter-logs.next-number');
    
    // --- MODUL SURAT PERINTAH (SP) JAGA SIAGA SINTEL ---
    Route::get('/sp-jaga', [SpJagaController::class, 'index'])->name('sp-jaga.index');
    Route::get('/sp-jaga/create', [SpJagaController::class, 'create'])->name('sp-jaga.create');
    Route::post('/sp-jaga', [SpJagaController::class, 'store'])->name('sp-jaga.store');
    Route::get('/sp-jaga/{id}/edit', [SpJagaController::class, 'edit'])->name('sp-jaga.edit');
    Route::put('/sp-jaga/{id}', [SpJagaController::class, 'update'])->name('sp-jaga.update');
    Route::delete('/sp-jaga/{id}', [SpJagaController::class, 'destroy'])->name('sp-jaga.destroy');
    Route::post('/sp-jaga/{id}/approve-tte', [SpJagaController::class, 'approveTte'])->name('sp-jaga.approve-tte');
    Route::post('/sp-jaga/{id}/upload-manual-signed', [SpJagaController::class, 'uploadManualSigned'])->name('sp-jaga.upload-manual-signed');
    Route::get('/sp-jaga/{id}/download-pdf', [SpJagaController::class, 'downloadPdf'])->name('sp-jaga.download-pdf');
    
    // Modul TTD Komandan (Otoritas Permohonan Surat)
    Route::get('/signature-requests', [SignatureRequestController::class, 'index'])->name('signature.index');
    Route::post('/signature-requests', [SignatureRequestController::class, 'store'])->name('signature.store');
    Route::patch('/signature-requests/{signatureRequest}', [SignatureRequestController::class, 'update'])->name('signature.update');
    Route::delete('/signature-requests/{signatureRequest}', [SignatureRequestController::class, 'destroy'])->name('signature.destroy');
    
    // Modul Stempel Digital Detasemen Intelijen
    Route::get('/otoritas-stempel', [StampController::class, 'index'])->name('stamp.index');
    Route::patch('/otoritas-stempel/eksekusi', [StampController::class, 'apply'])->name('signature.apply-stamp');
    Route::post('/otoritas-stempel/upload-manual', [StampController::class, 'uploadManual'])->name('stamp.upload-manual');
    Route::get('/settings/stamp-config', [StampController::class, 'setting'])->name('stamp.setting');

    // =========================================================================
    // MODUL LAYANAN PRINTING JARINGAN (PRINTER BROTHER IP)
    // =========================================================================
    Route::get('/layanan-printing', [PrintServiceController::class, 'index'])->name('printing.index');
    Route::post('/layanan-printing/upload', [PrintServiceController::class, 'uploadAndPreview'])->name('printing.upload');
    Route::post('/layanan-printing/{id}/confirm', [PrintServiceController::class, 'confirmPrint'])->name('printing.confirm');
    Route::delete('/layanan-printing/{id}/cancel', [PrintServiceController::class, 'cancelJob'])->name('printing.cancel');
    Route::get('/layanan-printing/queue-status', [PrintServiceController::class, 'getQueueStatus'])->name('printing.queue-status');
    Route::get('/layanan-printing/preview/{id}', [PrintServiceController::class, 'streamPreviewPdf'])->name('printing.preview');
    Route::post('/layanan-printing/admin/test-connection', [PrintServiceController::class, 'testPrinterConnection'])->name('printing.test-connection');
    Route::post('/layanan-printing/admin/settings', [PrintServiceController::class, 'updatePrinterSettings'])->name('printing.update-settings');

    // =========================================================================
    // MODUL PELANGGARAN DISIPLIN PRAJURIT (URUTAN STRATEGIS PASUKAN)
    // =========================================================================
    Route::get('/soldier-violations', [SoldierViolationController::class, 'index'])->name('soldier-violations.index');
    Route::post('/soldier-violations', [SoldierViolationController::class, 'store'])->name('soldier-violations.store');
    
    //  AMANKAN POSISI DI ATAS WILDCARD {id} agar kebal dari respons 405
    Route::get('/soldier-violations/preview-file', [SoldierViolationController::class, 'previewDocx'])->name('soldier-violations.preview-file');
    
    //  KALIBRASI METODE MATCH: Bypass proteksi data biner multipart/form-data
    Route::match(['post', 'put'], '/soldier-violations/{id}', [SoldierViolationController::class, 'update'])->name('soldier-violations.update');
    
    Route::delete('/soldier-violations/{id}/delete-update', [SoldierViolationController::class, 'deleteUpdate'])->name('soldier-violations.delete-update');
    Route::delete('/soldier-violations/{id}', [SoldierViolationController::class, 'destroy'])->name('soldier-violations.destroy');
    
    
    // =========================================================================
    // MODUL SIMPAN PINJAM (KOPERASI SINDEN)
    // =========================================================================
    Route::get('/simpan-pinjam', [KoperasiController::class, 'index'])->name('simpan-pinjam.index');
    Route::get('/simpan-pinjam/kelola', [KoperasiController::class, 'kelola'])->name('simpan-pinjam.kelola');
    Route::post('/simpan-pinjam/apply-loan', [KoperasiController::class, 'applyLoan'])->name('simpan-pinjam.apply-loan');
    Route::post('/simpan-pinjam/approve-loan/{id}', [KoperasiController::class, 'approveLoan'])->name('simpan-pinjam.approve-loan');
    Route::post('/simpan-pinjam/reject-loan/{id}', [KoperasiController::class, 'rejectLoan'])->name('simpan-pinjam.reject-loan');
    Route::post('/simpan-pinjam/record-installment/{loanId}', [KoperasiController::class, 'recordInstallment'])->name('simpan-pinjam.record-installment');
    Route::post('/simpan-pinjam/deposit-saving', [KoperasiController::class, 'depositSaving'])->name('simpan-pinjam.deposit-saving');
    Route::post('/simpan-pinjam/withdraw-saving', [KoperasiController::class, 'withdrawSaving'])->name('simpan-pinjam.withdraw-saving');
    Route::get('/simpan-pinjam/receipt/{installmentId}', [KoperasiController::class, 'printReceipt'])->name('simpan-pinjam.receipt');
    Route::get('/simpan-pinjam/export-ledger', [KoperasiController::class, 'exportLedgerPdf'])->name('simpan-pinjam.export-ledger');
    Route::post('/simpan-pinjam/record-cash-mutation', [KoperasiController::class, 'recordCashMutation'])->name('simpan-pinjam.record-cash-mutation');
    Route::post('/simpan-pinjam/early-payoff/{loanId}', [KoperasiController::class, 'earlyPayoff'])->name('simpan-pinjam.early-payoff');
    Route::post('/simpan-pinjam/broadcast-reminders', [KoperasiController::class, 'broadcastReminders'])->name('simpan-pinjam.broadcast-reminders');
    
    // Modul Keuangan / Cash Buku Kas Detasemen
    Route::get('/cash', [CashController::class, 'index'])->name('cash.index');
    Route::post('/cash', [CashController::class, 'store'])->name('cash.store');
    Route::get('/cash/pdf', [CashController::class, 'exportPdf'])->name('cash.pdf');
    Route::put('/cash/{id}', [CashController::class, 'update'])->name('cash.update');
    Route::delete('/cash/{id}', [CashController::class, 'destroy'])->name('cash.destroy');
    
    // Unduh Rekap Buku Log Surat Berupa Dokumen PDF
    Route::get('/letter-logs/download-pdf', [LetterLogController::class, 'downloadPdf'])->name('letter-logs.pdf');
    
    // Modul Ucapan Hari Ulang Tahun Otomatis Lewat Radar Personel
    Route::get('/admin/birthday-radar', [PersonnelController::class, 'index'])->name('birthday.index');
    Route::post('/admin/personnel', [PersonnelController::class, 'storePersonnel'])->name('personnel.store');
    Route::put('/admin/user-birthday/{id}', [PersonnelController::class, 'updateUserBirthDate'])->name('user.birthday.update');
    Route::delete('/admin/personnel/{id}', [PersonnelController::class, 'destroyPersonnel'])->name('personnel.destroy');
    Route::post('/admin/birthday-radar/settings', [PersonnelController::class, 'updateSettings'])->name('admin.settings.birthday');
    Route::post('/admin/birthday-radar/test', [PersonnelController::class, 'testGreeting'])->name('admin.birthday.test');
    
    // =========================================================================
    // MODUL STRATEGIS REKENING KOMANDAN
    // =========================================================================
    Route::get('/commander-account', [CommanderAccountController::class, 'index'])->name('commander.index');
    Route::post('/commander-account', [CommanderAccountController::class, 'store'])->name('commander.store');
    Route::put('/commander-account/{id}', [CommanderAccountController::class, 'update'])->name('commander.update');
    Route::delete('/commander-account/{id}', [CommanderAccountController::class, 'destroy'])->name('commander.destroy');
    Route::get('/commander-account/pdf', [CommanderAccountController::class, 'exportPdf'])->name('commander.pdf');

    // =========================================================================
    // MODUL STRATEGIS REKENING IBU BETI
    // =========================================================================
    Route::get('/rekening-ibu-beti', [IbuBetiAccountController::class, 'index'])->name('ibu-beti.index');
    Route::post('/rekening-ibu-beti', [IbuBetiAccountController::class, 'store'])->name('ibu-beti.store');
    Route::post('/rekening-ibu-beti/{id}', [IbuBetiAccountController::class, 'update'])->name('ibu-beti.update');
    Route::delete('/rekening-ibu-beti/{id}', [IbuBetiAccountController::class, 'destroy'])->name('ibu-beti.destroy');
    Route::get('/rekening-ibu-beti/pdf', [IbuBetiAccountController::class, 'exportPdf'])->name('ibu-beti.pdf');

    // =========================================================================
    // MODUL MANAJEMEN PENCATATAN PEMBAYARAN MITRA (MATRIX BULANAN & CETAK PDF)
    // =========================================================================
    Route::get('/mitras', [MitraPaymentController::class, 'index'])->name('mitras.index');
    Route::post('/mitras', [MitraPaymentController::class, 'store'])->name('mitras.store');
    Route::put('/mitras/{id}', [MitraPaymentController::class, 'update'])->name('mitras.update');
    Route::delete('/mitras/{id}', [MitraPaymentController::class, 'destroy'])->name('mitras.destroy');
    Route::post('/mitras/toggle-payment', [MitraPaymentController::class, 'togglePayment'])->name('mitras.toggle-payment');
    Route::post('/mitras/reorder', [MitraPaymentController::class, 'reorder'])->name('mitras.reorder');
    Route::post('/mitras/{id}/move', [MitraPaymentController::class, 'movePosition'])->name('mitras.move');

    // =========================================================================
    // MODUL MANAJEMEN BUKU KAS DAN UNIT TEKNIS
    // =========================================================================
    Route::get('/technical-cash', [TechnicalUnitCashController::class, 'index'])->name('technical-cash.index');
    Route::post('/technical-cash', [TechnicalUnitCashController::class, 'store'])->name('technical-cash.store');
    Route::put('/technical-cash/{id}', [TechnicalUnitCashController::class, 'update'])->name('technical-cash.update');
    Route::delete('/technical-cash/{id}', [TechnicalUnitCashController::class, 'destroy'])->name('technical-cash.destroy');

    // =========================================================================
    // FITUR KHUSUS ADMIN (OTORITAS TINGGI MONEV)
    // =========================================================================
    Route::middleware(['role:admin'])->group(function () {
        Route::post('/signature-requests/clear-all', [SignatureRequestController::class, 'clearAll'])->name('signature.clear-all');
        Route::get('/users/print-report-pdf', [UserController::class, 'printPdf'])->name('users.print-pdf');
        Route::get('/users/print-token-pdf', [UserController::class, 'printTokenPdf'])->name('users.print-token-pdf');

        // Manajemen Personel Internal & Token Akses
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users/bulk', [UserController::class, 'storeBulk'])->name('users.store-bulk');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle');
        Route::post('/users/{user}/generate-token', [UserController::class, 'generateResetToken'])->name('users.generate-token');
        Route::post('/users/{user}/change-password', [UserController::class, 'changePassword'])->name('users.change-password');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{id}/toggle-mitra-access', [MitraPaymentController::class, 'toggleUserAccess'])->name('users.toggle-mitra-access');
        Route::post('/users/{id}/toggle-technical-cash-access', [TechnicalUnitCashController::class, 'toggleUserAccess'])->name('users.toggle-technical-cash-access');
        Route::post('/users/{user}/toggle-koperasi-access', [UserController::class, 'toggleKoperasiAccess'])->name('users.toggle-koperasi-access');
        Route::post('/users/{user}/toggle-ibu-beti-access', [UserController::class, 'toggleIbuBetiAccess'])->name('users.toggle-ibu-beti-access');
        Route::post('/users/{user}/toggle-mfa', [UserController::class, 'toggleMfa'])->name('users.toggle-mfa');

        // Kategori Arsip Surat Mako V
        Route::resource('categories', CategoryController::class);
        Route::post('/categories/{category}/sub', [CategoryController::class, 'storeSub'])->name('categories.storeSub');
        Route::delete('/sub-categories/{subCategory}', [CategoryController::class, 'destroySub'])->name('sub-categories.destroy');

        // Monitoring Sistem & Audit Logs Aktivitas Admin
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/update-stamp', [SettingController::class, 'updateStamp'])->name('settings.update-stamp');
        Route::get('/visitor-logs', [VisitorLogController::class, 'index'])->name('visitor-logs.index');
        Route::match(['delete', 'post'], '/visitor-logs/clear', [VisitorLogController::class, 'clearAll'])->name('visitor-logs.clear');
        Route::delete('/visitor-logs/{id}', [VisitorLogController::class, 'destroy'])->name('visitor-logs.destroy');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        
        // Cek Sistem & Monitoring Server Admin (Speedtest, Disk, CPU, RAM, VRAM)
        Route::get('/admin/system-check', [SystemCheckController::class, 'index'])->name('system-check.index');
        Route::get('/admin/system-check/metrics', [SystemCheckController::class, 'getMetrics'])->name('system-check.metrics');
        Route::post('/admin/system-check/speedtest', [SystemCheckController::class, 'runSpeedtest'])->name('system-check.speedtest');
        
        // Rencana Kegiatan Pengamanan Aktivitas Masyarakat
        Route::get('/community-activities', [CommunityActivityController::class, 'index'])->name('activities.index');
        Route::post('/community-activities', [CommunityActivityController::class, 'store'])->name('activities.store');
        Route::get('/community-activities/{id}', [CommunityActivityController::class, 'show'])->name('activities.show');
        Route::delete('/community-activities/{id}', [CommunityActivityController::class, 'destroy'])->name('activities.destroy');
        Route::put('/community-activities/{id}', [CommunityActivityController::class, 'update'])->name('activities.update');

        // Jalur MFA WhatsApp Gateway Engine Lokal (Port 3000)
        Route::get('/admin/setting/wa-status', function () {
            try {
                $response = Http::timeout(3)->get('http://localhost:3000/status');
                return $response->json();
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'OFFLINE',
                    'pairing_code' => null,
                    'error' => 'WA Gateway tidak merespon'
                ], 500);
            }
        })->name('settings.wa-status');

        // Fitur khusus Admin: Atur/Loncati nomor urut SKHPP & Ajukan TTE Ulang
        Route::post('/skhpp/{id}/update-number', [SkhppController::class, 'updateNumber'])->name('skhpp.update-number');
        Route::post('/skhpp/{id}/resubmit-tte', [SkhppController::class, 'reSubmitTte'])->name('skhpp.resubmit-tte');
    });

    // =====================================================================
    // SULTAN CONFIG: JALUR VERIFIKASI INTEGRASI PESS & SINDEN (TTD DIGITAL/SINKODV)
    // =====================================================================
    Route::get('/applicants/dashboard', [PESSAdminController::class, 'index'])->name('admin.pess.dashboard');
    Route::post('/submissions/verify/{id}', [PESSAdminController::class, 'verifyFiles'])->name('admin.pess.verify');
    Route::post('/submissions/interview/{id}', [PESSAdminController::class, 'scheduleInterview'])->name('admin.pess.interview');
    Route::post('/submissions/skhpp/{id}', [PESSAdminController::class, 'issueSKHPP'])->name('admin.pess.skhpp');
    Route::post('/submissions/sign-komandan/{id}', [PESSAdminController::class, 'signDigitalKomandan'])->name('admin.pess.sign_komandan');
    Route::post('/submissions/forward-sinkodv/{id}', [PESSAdminController::class, 'forwardToSINKODV'])->name('admin.pess.forward_sinkodv');
    Route::post('/submissions/sc/upload-sign/{id}', [PESSAdminController::class, 'uploadAndSignSC'])->name('admin.pess.sc_upload_sign');

    // =====================================================================
    // MODUL UTAMA PENERBITAN & OTORITAS SKHPP (MILITER, SIPIL, NIKAH)
    // =====================================================================
    Route::get('/skhpp', [SkhppController::class, 'index'])->name('skhpp.index');
    Route::get('/skhpp/create', [SkhppController::class, 'create'])->name('skhpp.create');
    Route::post('/skhpp', [SkhppController::class, 'store'])->name('skhpp.store');
    Route::get('/skhpp/{id}', [SkhppController::class, 'show'])->name('skhpp.show');
    Route::get('/skhpp/{id}/export-pdf', [SkhppController::class, 'exportPdf'])->name('skhpp.export-pdf');
    Route::get('/skhpp/{id}/edit', [SkhppController::class, 'edit'])->name('skhpp.edit');
    Route::post('/skhpp/{id}/update', [SkhppController::class, 'update'])->name('skhpp.update');
    Route::post('/skhpp/{id}/approve', [SkhppController::class, 'approve'])->name('skhpp.approve');
    Route::post('/skhpp/{id}/reject', [SkhppController::class, 'reject'])->name('skhpp.reject');
    Route::delete('/skhpp/{id}', [SkhppController::class, 'destroy'])->name('skhpp.destroy');

    // System In-App Bell Notifications Routes
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/api/notifications', [\App\Http\Controllers\NotificationController::class, 'getNotifications'])->name('notifications.api');
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}', [\App\Http\Controllers\NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Modul Pencarian Global Cepat (Command Palette) & Jejak Audit
    Route::get('/api/global-search', [\App\Http\Controllers\GlobalSearchController::class, 'search'])->name('api.global-search');
    Route::get('/api/audit-trail', [\App\Http\Controllers\AuditTrailController::class, 'getTrail'])->name('api.audit-trail');
    Route::get('/api/ping', fn() => response()->json(['status' => 'pong', 'timestamp' => time()]))->name('api.ping');
});


// =========================================================================
// JALUR VERIFIKASI PUBLIK TTE BERKAS DINAS LAINNYA (DEDICATED CONTROLLER & VUE)
// =========================================================================
Route::get('/verify-doc/{code}', [DocVerificationController::class, 'verify'])->name('doc.verify');
Route::get('/verify-berkas/{code}', [DocVerificationController::class, 'verify'])->name('berkas.verify');
Route::get('/verify-signature/{code}', [DocVerificationController::class, 'verify'])->name('signature.verify');
Route::get('/verify/{code}', [DocVerificationController::class, 'verifyGeneral'])->name('general.verify');
// Jalur Verifikasi Publik QR Code Scan TTD Komandan SKHPP
Route::get('/verify-skhpp/{code}', [SkhppController::class, 'verify'])->name('skhpp.verify');

// =====================================================================
// SULTAN CONFIG: GATEWAY OPEN API PENERIMA BERKAS FISIK LINTAS VPS (BEARER TOKEN)
// =====================================================================
Route::post('/api/v1/pess/receive-submission', [PESSReceiverController::class, 'receive']);

require __DIR__.'/auth.php';

// =====================================================================
// GERBANG RESMI MOBILE API LOGIN (SINDEN ANDROID & IOS)
// =====================================================================
Route::post('/api/mobile/login', function (\Illuminate\Http\Request $request) {
    try {
        $ip = $request->ip();
        $ua = $request->userAgent();
        $data = $request->json()->all() ?: $request->all();

        \Illuminate\Support\Facades\Log::info("[MOBILE_API_LOGIN] Request Diproses", [
            'ip' => $ip,
            'user_agent' => $ua,
            'content_type' => $request->header('Content-Type'),
            'input_keys' => array_keys($data),
            'login_input' => $data['username'] ?? $data['email'] ?? $data['nrp'] ?? 'KOSONG',
        ]);

        $input = trim($data['username'] ?? $data['email'] ?? $data['nrp'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($input) || empty($password)) {
            \Illuminate\Support\Facades\Log::warning("[MOBILE_API_LOGIN] Input atau Password Kosong dari IP: {$ip}");
            return response()->json([
                'status' => 'error',
                'message' => 'NRP / Email dan Kata Sandi wajib diisi.'
            ], 422);
        }

        $user = \App\Models\User::where('email', $input)
            ->orWhere('nrp', $input)
            ->first();

        if (!$user) {
            \Illuminate\Support\Facades\Log::warning("[MOBILE_API_LOGIN] User Tidak Ditemukan untuk Input: '{$input}' dari IP: {$ip}");
            return response()->json([
                'status' => 'error',
                'message' => "Kredensial tidak cocok. Personel ('{$input}') tidak ditemukan."
            ], 401);
        }

        if (!\Illuminate\Support\Facades\Hash::check($password, $user->password)) {
            \Illuminate\Support\Facades\Log::warning("[MOBILE_API_LOGIN] Kata Sandi Salah untuk Personel: {$user->name} ({$user->email}) dari IP: {$ip}");
            return response()->json([
                'status' => 'error',
                'message' => "Kredensial tidak cocok. Kata sandi untuk '{$user->name}' salah."
            ], 401);
        }

        if (!$user->is_active) {
            \Illuminate\Support\Facades\Log::warning("[MOBILE_API_LOGIN] Akun Belum Aktif: {$user->name} ({$user->email}) dari IP: {$ip}");
            return response()->json([
                'status' => 'error',
                'message' => "Akses Ditolak: Akun ({$user->name}) belum aktif atau sedang ditangguhkan oleh Admin."
            ], 403);
        }

        // PEMBUATAN TOKEN SANCTUM DENGAN SAFE FALLBACK AGAR TIDAK HTTP 500
        $token = null;
        try {
            if (method_exists($user, 'createToken')) {
                $token = $user->createToken('sinden_mobile_token')->plainTextToken;
            }
        } catch (\Throwable $te) {
            \Illuminate\Support\Facades\Log::warning("[MOBILE_API_LOGIN] Sanctum Token Error (Menggunakan fallback token): " . $te->getMessage());
        }

        if (empty($token)) {
            $token = 'sinden_token_' . \Illuminate\Support\Str::random(40);
        }

        \Illuminate\Support\Facades\Log::info("[MOBILE_API_LOGIN] SUKSES LOGIN untuk Personel: {$user->name} (Role: {$user->role}) dari IP: {$ip}");

        $allowedSpecial = ['I Gusti Sultan H.A, A.Md.Kom', 'Suma Nurhasanah', 'HARI BAGIO WIJAYANTO, M.TR.OPSLA'];
        $isSpecialUser = in_array($user->name, $allowedSpecial);
        $isOfficer = in_array(strtolower($user->role), ['admin', 'superadmin', 'komandan', 'danden', 'bendahara', 'keuangan', 'danunitteknis']);

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email ?? '',
                'username' => $user->username ?? $user->nrp ?? '',
                'nrp' => $user->nrp ?? $user->username ?? '',
                'role' => $user->role ?? 'user',
                'pangkat' => $user->pangkat ?? 'Prajurit',
                'korps' => $user->korps ?? '',
                'jabatan' => $user->jabatan ?? '',
                'is_active' => (bool)($user->is_active ?? true),
                'can_access_agenda' => (bool)($user->role === 'admin' || ($user->can_access_agenda ?? true)),
                'can_access_letters' => (bool)($user->role === 'admin' || ($user->can_access_letters ?? true)),
                'can_access_skhpp' => (bool)($user->role === 'admin' || ($user->can_access_skhpp ?? true)),
                'can_access_categories' => (bool)($user->role === 'admin' || ($user->can_access_categories ?? true)),
                'can_access_signature' => (bool)($user->role === 'admin' || ($user->can_access_signature ?? true)),
                'can_access_cash' => (bool)($isOfficer || $isSpecialUser || !empty($user->can_access_cash)),
                'can_access_commander' => (bool)($isOfficer || $isSpecialUser || !empty($user->can_access_commander)),
                'can_access_mitra' => (bool)($isOfficer || $isSpecialUser || !empty($user->can_access_mitra)),
                'can_access_technical_cash' => (bool)($isOfficer || $isSpecialUser || !empty($user->can_access_technical_cash)),
                'can_access_ibu_beti' => (bool)($user->role === 'admin' || !empty($user->can_access_ibu_beti)),
                'can_access_violations' => (bool)($user->role === 'admin' || ($user->can_access_violations ?? true)),
                'can_access_activities' => (bool)($user->role === 'admin' || ($user->can_access_activities ?? true)),
                'can_access_users' => (bool)($user->role === 'admin'),
                'can_access_settings' => (bool)($user->role === 'admin'),
            ],
        ]);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error("[MOBILE_API_LOGIN] Uncaught Exception: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
        return response()->json([
            'status' => 'error',
            'message' => 'Kendala Server: ' . $e->getMessage()
        ], 500);
    }
});

// =====================================================================
// JALUR MEMBACA SYSTEM LOG LARAVEL UNTUK ADMIN (/admin/laravel-logs)
// =====================================================================
Route::get('/admin/laravel-logs', function () {
    $logFile = storage_path('logs/laravel.log');
    if (!file_exists($logFile)) {
        return response("File log laravel.log belum tersedia di " . $logFile, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
    $content = file_get_contents($logFile);
    $lines = array_slice(explode("\n", $content), -300);
    return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
})->name('admin.laravel-logs');

// =====================================================================
// GERBANG CONFIGURASI DAN STATUS SERVISE MOBILE API
// =====================================================================
Route::get('/api/mobile/config', function () {
    $settings = \App\Models\Setting::pluck('value', 'key')->toArray();
    $logo = $settings['agency_logo'] ?? $settings['logo'] ?? null;
    $logoUrl = !empty($logo) ? (str_starts_with($logo, 'http') ? $logo : asset('storage/' . $logo)) : asset('images/logo.png');
    
    $bg = $settings['login_background'] ?? null;
    $bgUrl = !empty($bg) ? (str_starts_with($bg, 'http') ? $bg : asset('storage/' . $bg)) : null;

    return response()->json([
        'status' => 'success',
        'app_name' => $settings['agency_name'] ?? 'SI SINDEN',
        'agency_name' => $settings['agency_name'] ?? 'DETASEMEN INTELIJEN KODAERAL V',
        'agency_logo' => $logoUrl,
        'login_background' => $bgUrl,
        'api_base_url' => $settings['mobile_api_base_url'] ?? config('app.url', 'https://sisinden.my.id'),
        'api_login_endpoint' => '/api/mobile/login',
        'apk_download_url' => $settings['mobile_apk_download_url'] ?? 'https://sisinden.my.id/download/sinden-mobile.apk',
        'api_status' => $settings['mobile_api_status'] ?? 'AKTIF',
        'min_version' => $settings['mobile_min_version'] ?? '1.0.0',
        'mobile_theme_mode' => $settings['mobile_theme_mode'] ?? 'system',
        'mobile_primary_color' => $settings['mobile_primary_color'] ?? '#1E3A8A',
        'mobile_accent_color' => $settings['mobile_accent_color'] ?? '#0284C7',
        'mobile_card_radius' => (int)($settings['mobile_card_radius'] ?? 20),
        'mobile_show_balance_card' => ($settings['mobile_show_balance_card'] ?? '1') === '1',
        'mobile_show_summary_trio' => ($settings['mobile_show_summary_trio'] ?? '1') === '1',
        'mobile_show_quick_actions' => ($settings['mobile_show_quick_actions'] ?? '1') === '1',
        'server_time' => now()->toIso8601String(),
    ]);
});

// AKTIVASI AKUN MOBILE
Route::post('/api/mobile/activate', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $nrp = trim($data['nrp'] ?? '');
        $name = trim($data['name'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($nrp) || empty($password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'NRP dan Kata Sandi baru wajib diisi.'
            ], 422);
        }

        $user = \App\Models\User::where('nrp', $nrp)
            ->orWhere('email', $nrp)
            ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => "Personel dengan NRP {$nrp} belum terdaftar di sistem dinas. Silakan hubungi Staf Intel / Admin."
            ], 404);
        }

        $user->password = \Illuminate\Support\Facades\Hash::make($password);
        $user->is_active = true;
        if (!empty($name)) {
            $user->name = $name;
        }
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => "Aktivasi akun {$user->name} berhasil. Silakan masuk ke aplikasi menggunakan kata sandi baru Anda."
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// =====================================================================
// GERBANG REST API LENGKAP SINDEN MOBILE (SINKRONISASI DATA LANGSUNG)
// =====================================================================

// 1. API BUKU KAS UNIT (Model: App\Models\Cash)
Route::get('/api/mobile/cash', function () {
    try {
        $allCashes = \App\Models\Cash::orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        $totalSaldo = (float)($allCashes->last()?->balance ?? 0);
        $totalDebit = (float)$allCashes->sum('debit');
        $totalCredit = (float)$allCashes->sum('credit');

        $cashes = $allCashes->map(function($cash) {
            $urls = [];
            $rawPath = $cash->receipt_path;
            if (!empty($rawPath)) {
                $decoded = json_decode($rawPath, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $path) {
                        if (!empty($path)) $urls[] = asset('storage/' . $path);
                    }
                } elseif (is_array($rawPath)) {
                    foreach ($rawPath as $path) {
                        if (!empty($path)) $urls[] = asset('storage/' . $path);
                    }
                } else {
                    $urls[] = asset('storage/' . $rawPath);
                }
            }

            return [
                'id' => $cash->id,
                'date' => (string)($cash->getRawOriginal('date') ?? $cash->date),
                'description' => $cash->description ?? '-',
                'debit' => (float)($cash->debit ?? 0),
                'credit' => (float)($cash->credit ?? 0),
                'balance' => (float)($cash->balance ?? 0),
                'receipt_path' => $rawPath,
                'receipt_urls' => $urls,
            ];
        });

        $reversed = $cashes->reverse()->values();

        return response()->json([
            'status' => 'success',
            'balance' => $totalSaldo,
            'totalSaldo' => $totalSaldo,
            'total_balance' => $totalSaldo,
            'total_income' => $totalDebit,
            'total_expense' => $totalCredit,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'cashes' => $reversed,
            'transactions' => $reversed,
            'data' => $reversed,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'cashes' => [], 'data' => []], 500);
    }
});

Route::post('/api/mobile/cash', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $date = $data['date'] ?? date('Y-m-d');
        $desc = $data['description'] ?? '-';

        $type = strtolower($data['type'] ?? 'masuk');
        $amount = isset($data['amount']) ? (float)$data['amount'] : 0.0;

        if (isset($data['debit']) || isset($data['credit'])) {
            $debit = (float)($data['debit'] ?? 0);
            $credit = (float)($data['credit'] ?? 0);
        } else {
            if ($type === 'masuk' || $type === 'debit') {
                $debit = $amount;
                $credit = 0.0;
            } else {
                $debit = 0.0;
                $credit = $amount;
            }
        }

        $cash = \App\Models\Cash::create([
            'date' => is_string($date) ? substr($date, 0, 10) : $date,
            'description' => $desc,
            'debit' => $debit,
            'credit' => $credit,
            'balance' => 0.0,
        ]);

        // Rekalkulasi seluruh saldo kas pangkalan secara kronologis
        $all = \App\Models\Cash::orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        $running = 0.0;
        foreach ($all as $item) {
            $running += ((float)$item->debit - (float)$item->credit);
            $item->balance = $running;
            $item->save();
        }

        return response()->json(['status' => 'success', 'data' => $cash->fresh()]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::put('/api/mobile/cash/{id}', function (\Illuminate\Http\Request $request, $id) {
    try {
        $cash = \App\Models\Cash::findOrFail($id);
        $data = $request->json()->all() ?: $request->all();
        $desc = $data['description'] ?? $cash->description;
        $date = isset($data['date']) ? substr($data['date'], 0, 10) : $cash->date;

        if (isset($data['debit']) || isset($data['credit'])) {
            $debit = (float)($data['debit'] ?? $cash->debit);
            $credit = (float)($data['credit'] ?? $cash->credit);
        } elseif (isset($data['amount'])) {
            $type = strtolower($data['type'] ?? ($cash->debit > 0 ? 'masuk' : 'keluar'));
            $amount = (float)$data['amount'];
            if ($type === 'masuk' || $type === 'debit') {
                $debit = $amount;
                $credit = 0.0;
            } else {
                $debit = 0.0;
                $credit = $amount;
            }
        } else {
            $debit = $cash->debit;
            $credit = $cash->credit;
        }

        $cash->update([
            'date' => $date,
            'description' => $desc,
            'debit' => $debit,
            'credit' => $credit,
        ]);

        // Rekalkulasi seluruh saldo kas pangkalan secara kronologis
        $all = \App\Models\Cash::orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        $running = 0.0;
        foreach ($all as $item) {
            $running += ((float)$item->debit - (float)$item->credit);
            $item->balance = $running;
            $item->save();
        }

        return response()->json(['status' => 'success', 'data' => $cash->fresh()]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::delete('/api/mobile/cash/{id}', function ($id) {
    try {
        $cash = \App\Models\Cash::findOrFail($id);
        $cash->delete();

        // Rekalkulasi seluruh saldo kas pangkalan secara kronologis
        $all = \App\Models\Cash::orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        $running = 0.0;
        foreach ($all as $item) {
            $running += ((float)$item->debit - (float)$item->credit);
            $item->balance = $running;
            $item->save();
        }

        return response()->json(['status' => 'success', 'message' => 'Data kas berhasil dihapus']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// 2. API AGENDA SURAT (Model: App\Models\LetterLog)
Route::get('/api/mobile/letter-logs', function (\Illuminate\Http\Request $request) {
    try {
        $query = \App\Models\LetterLog::with(['category', 'subCategory'])->latest('id');
        
        if ($request->has('search') && !empty($request->search)) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('subject', 'like', "%{$s}%")
                  ->orWhere('sequence', 'like', "%{$s}%")
                  ->orWhere('full_number', 'like', "%{$s}%")
                  ->orWhere('recipient', 'like', "%{$s}%");
            });
        }
        if ($request->has('category_id') && !empty($request->category_id)) {
            $query->where('category_id', $request->category_id);
        }

        $logs = $query->take(300)->get()->map(function($l) {
            return [
                'id' => $l->id,
                'number' => is_numeric($l->sequence) ? (int)$l->sequence : ($l->number ?? 0),
                'sequence' => (string)($l->sequence ?? $l->number ?? '-'),
                'full_number' => $l->full_number ?? ($l->sequence ? "Surat No. {$l->sequence}" : '-'),
                'subject' => $l->subject ?? 'Naskah Dinas',
                'recipient' => $l->recipient ?? '-',
                'date' => $l->date ? \Carbon\Carbon::parse($l->date)->format('Y-m-d') : date('Y-m-d'),
                'category_id' => $l->category_id ?? 0,
                'category_name' => $l->category->name ?? 'Umum',
                'is_archived' => (bool)($l->is_archived ?? false),
                'file_url' => null,
                'created_by' => 'Admin SINDEN',
            ];
        });

        return response()->json(['status' => 'success', 'data' => $logs, 'logs' => $logs]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'data' => []], 500);
    }
});

Route::post('/api/mobile/letter-logs', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $catId = $data['category_id'] ?? 1;
        $category = \App\Models\Category::find($catId);
        $lastLog = \App\Models\LetterLog::where('category_id', $catId)->orderBy('id', 'desc')->first();
        $nextSeq = $lastLog ? ((int)$lastLog->sequence + 1) : ($category->start_number ?? 1);

        $log = \App\Models\LetterLog::create([
            'category_id' => $catId,
            'sequence' => (string)$nextSeq,
            'full_number' => "Surat No. {$nextSeq}",
            'subject' => $data['subject'] ?? $data['regarding'] ?? 'Agenda Surat Mobile',
            'recipient' => $data['recipient'] ?? $data['to'] ?? 'Detasemen Intelijen',
            'date' => $data['date'] ?? date('Y-m-d'),
            'is_archived' => false,
        ]);

        return response()->json(['status' => 'success', 'data' => $log]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::put('/api/mobile/letter-logs/{id}', function (\Illuminate\Http\Request $request, $id) {
    try {
        $log = \App\Models\LetterLog::findOrFail($id);
        $data = $request->json()->all() ?: $request->all();
        $log->update([
            'subject' => $data['subject'] ?? $log->subject,
            'recipient' => $data['recipient'] ?? $log->recipient,
            'date' => $data['date'] ?? $log->date,
        ]);
        return response()->json(['status' => 'success', 'data' => $log]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::delete('/api/mobile/letter-logs/{id}', function ($id) {
    try {
        $log = \App\Models\LetterLog::findOrFail($id);
        $log->delete();
        return response()->json(['status' => 'success', 'message' => 'Agenda surat berhasil dihapus']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// 3. API PENERBITAN SKHPP (Model: App\Models\Skhpp)
Route::get('/api/mobile/skhpp', function (\Illuminate\Http\Request $request) {
    try {
        $query = \App\Models\Skhpp::with('members')->latest('id');
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }
        if ($request->has('category') && !empty($request->category)) {
            $query->where('kategori_personel', $request->category);
        }

        $skhpps = $query->take(300)->get()->map(function($s) {
            return [
                'id' => $s->id,
                'no_skhpp' => $s->nomor_skhpp ?? "SKHPP-{$s->id}",
                'nomor_skhpp' => $s->nomor_skhpp ?? "SKHPP-{$s->id}",
                'nama' => $s->nama ?? '-',
                'pangkat_korps_nrp' => $s->pangkat_korps_nrp ?? '-',
                'jabatan' => $s->jabatan_pekerjaan ?? '-',
                'kesatuan' => 'Denintel Kodaeral V',
                'peruntukan' => $s->peruntukan ?? '-',
                'kategori' => $s->kategori_personel ?? 'militer',
                'status' => $s->status ?? 'pending',
                'barcode_string' => $s->verification_code ?? null,
                'tanggal_surat' => $s->tanggal_skhpp ? \Carbon\Carbon::parse($s->tanggal_skhpp)->format('Y-m-d') : ($s->created_at ? $s->created_at->format('Y-m-d') : date('Y-m-d')),
                'created_by' => $s->operator_name ?? 'Operator',
            ];
        });

        return response()->json(['status' => 'success', 'data' => $skhpps, 'skhpps' => $skhpps]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'data' => []], 500);
    }
});

Route::post('/api/mobile/skhpp', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $skhpp = \App\Models\Skhpp::create([
            'kategori_personel' => $data['kategori'] ?? $data['kategori_personel'] ?? 'militer',
            'nama' => $data['nama'] ?? '-',
            'pangkat_korps_nrp' => $data['pangkat_korps_nrp'] ?? '-',
            'nik' => $data['nik'] ?? '0000000000000000',
            'jabatan_pekerjaan' => $data['jabatan'] ?? $data['jabatan_pekerjaan'] ?? '-',
            'peruntukan' => $data['peruntukan'] ?? 'Persyaratan Kedinasan',
            'status' => 'PENDING',
            'tanggal_skhpp' => $data['tanggal_surat'] ?? date('Y-m-d'),
            'submitted_by' => auth()->id() ?? 1,
            'operator_name' => auth()->user()?->name ?? 'Operator Mobile',
        ]);
        return response()->json(['status' => 'success', 'data' => $skhpp]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::post('/api/mobile/skhpp/{id}/verify', function ($id) {
    try {
        $skhpp = \App\Models\Skhpp::findOrFail($id);
        $skhpp->update([
            'status' => 'APPROVED',
            'approved_by' => auth()->id() ?? 1,
            'approved_at' => now(),
        ]);
        return response()->json(['status' => 'success', 'message' => 'SKHPP berhasil diverifikasi']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::delete('/api/mobile/skhpp/{id}', function ($id) {
    try {
        $skhpp = \App\Models\Skhpp::findOrFail($id);
        $skhpp->delete();
        return response()->json(['status' => 'success', 'message' => 'SKHPP berhasil dihapus']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// 4. API REKENING KOMANDAN (Model: App\Models\CommanderAccount)
Route::get('/api/mobile/commander-account', function () {
    try {
        $logs = \App\Models\CommanderAccount::latest('tanggal')->latest('id')->get();
        $totalMasuk = (float)\App\Models\CommanderAccount::where('jenis', 'MASUK')->sum('jumlah');
        $totalKeluar = (float)\App\Models\CommanderAccount::where('jenis', 'KELUAR')->sum('jumlah');
        $saldoAkhir = $totalMasuk - $totalKeluar;

        return response()->json([
            'status' => 'success',
            'total_masuk' => $totalMasuk,
            'total_keluar' => $totalKeluar,
            'saldo_akhir' => $saldoAkhir,
            'data' => $logs,
            'logs' => $logs,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'saldo_akhir' => 0, 'data' => []]);
    }
});

Route::post('/api/mobile/commander-account', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $tx = \App\Models\CommanderAccount::create([
            'tanggal' => $data['tanggal'] ?? date('Y-m-d'),
            'jenis' => strtoupper($data['jenis'] ?? 'MASUK'),
            'jumlah' => (float)($data['jumlah'] ?? 0),
            'keterangan' => $data['keterangan'] ?? '-',
        ]);
        return response()->json(['status' => 'success', 'data' => $tx]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::delete('/api/mobile/commander-account/{id}', function ($id) {
    try {
        $tx = \App\Models\CommanderAccount::findOrFail($id);
        $tx->delete();
        return response()->json(['status' => 'success', 'message' => 'Transaksi Rekening Komandan berhasil dihapus']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// 5. API PENCATATAN MITRA (Model: App\Models\Mitra & App\Models\MitraPayment)
Route::get('/api/mobile/mitra', function (\Illuminate\Http\Request $request) {
    try {
        $tahun = (int) $request->input('tahun', date('Y'));
        $mitras = \App\Models\Mitra::with(['payments' => function ($q) use ($tahun) {
            $q->where('tahun', $tahun);
        }])->orderBy('sort_order', 'asc')->get()->map(function ($mitra) {
            $matrix = [];
            for ($m = 1; $m <= 12; $m++) {
                $matrix[$m] = false;
            }
            foreach ($mitra->payments as $payment) {
                if ($payment->bulan >= 1 && $payment->bulan <= 12) {
                    $matrix[$payment->bulan] = (bool) $payment->is_paid;
                }
            }
            $mitra->payment_matrix = $matrix;
            return $mitra;
        });

        return response()->json(['status' => 'success', 'tahun' => $tahun, 'data' => $mitras]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'data' => []]);
    }
});

Route::post('/api/mobile/mitra', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $nama = $data['nama'] ?? $data['nama_mitra'] ?? $data['name'] ?? 'Mitra Baru';
        $pt = $data['pt'] ?? $data['kategori'] ?? $data['sector'] ?? 'Umum';
        $phone = $data['no_tlp'] ?? $data['phone'] ?? null;
        $notes = $data['keterangan'] ?? $data['notes'] ?? null;
        $maxSort = \App\Models\Mitra::max('sort_order') ?? 0;

        $mitra = \App\Models\Mitra::create([
            'nama' => $nama,
            'pt' => $pt,
            'no_tlp' => $phone,
            'keterangan' => $notes,
            'sort_order' => $maxSort + 1,
        ]);
        return response()->json(['status' => 'success', 'data' => $mitra]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::put('/api/mobile/mitra/{id}', function (\Illuminate\Http\Request $request, $id) {
    try {
        $mitra = \App\Models\Mitra::findOrFail($id);
        $data = $request->json()->all() ?: $request->all();
        $nama = $data['nama'] ?? $data['nama_mitra'] ?? $data['name'] ?? $mitra->nama;
        $pt = $data['pt'] ?? $data['kategori'] ?? $data['sector'] ?? $mitra->pt;
        $phone = $data['no_tlp'] ?? $data['phone'] ?? $mitra->no_tlp;
        $notes = $data['keterangan'] ?? $data['notes'] ?? $mitra->keterangan;

        $mitra->update([
            'nama' => $nama,
            'pt' => $pt,
            'no_tlp' => $phone,
            'keterangan' => $notes,
        ]);
        return response()->json(['status' => 'success', 'data' => $mitra->fresh()]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::post('/api/mobile/mitra/{id}/toggle-payment', function (\Illuminate\Http\Request $request, $id) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $tahun = (int)($data['tahun'] ?? date('Y'));
        $bulan = (int)($data['bulan'] ?? date('n'));

        $payment = \App\Models\MitraPayment::where('mitra_id', $id)
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->first();

        if ($payment) {
            $payment->update(['is_paid' => !$payment->is_paid]);
        } else {
            \App\Models\MitraPayment::create([
                'mitra_id' => $id,
                'tahun' => $tahun,
                'bulan' => $bulan,
                'is_paid' => true,
            ]);
        }

        return response()->json(['status' => 'success', 'message' => 'Status pembayaran mitra berhasil diperbarui']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::delete('/api/mobile/mitra/{id}', function ($id) {
    try {
        $mitra = \App\Models\Mitra::findOrFail($id);
        $mitra->delete();
        return response()->json(['status' => 'success', 'message' => 'Mitra berhasil dihapus']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// 6. API CATATAN PELANGGARAN PRAJURIT (Model: App\Models\SoldierViolation)
Route::get('/api/mobile/violations', function (\Illuminate\Http\Request $request) {
    try {
        $query = \App\Models\SoldierViolation::query();
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('nrp', 'like', "%{$s}%")
                  ->orWhere('case_description', 'like', "%{$s}%");
            });
        }
        $violations = $query->latest()->take(200)->get();
        $total = \App\Models\SoldierViolation::count();
        $proses = \App\Models\SoldierViolation::where('status', 'PROSES')->count();
        $selesai = \App\Models\SoldierViolation::where('status', 'SELESAI')->count();

        return response()->json([
            'status' => 'success',
            'stats' => ['total' => $total, 'proses' => $proses, 'selesai' => $selesai],
            'data' => $violations,
            'violations' => $violations,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'data' => []]);
    }
});

Route::post('/api/mobile/violations', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $violation = \App\Models\SoldierViolation::create([
            'name' => $data['name'] ?? $data['nama'] ?? '-',
            'nrp' => $data['nrp'] ?? '-',
            'pangkat' => $data['pangkat'] ?? 'Prajurit',
            'jabatan' => $data['jabatan'] ?? '-',
            'unit' => $data['unit'] ?? $data['satuan'] ?? 'Denintel',
            'case_description' => $data['case_description'] ?? $data['kasus'] ?? '-',
            'tmt' => $data['tmt'] ?? date('Y-m-d'),
            'case_progress' => $data['case_progress'] ?? 'Dalam pemeriksaan',
            'status' => strtoupper($data['status'] ?? 'PROSES'),
        ]);
        return response()->json(['status' => 'success', 'data' => $violation]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::put('/api/mobile/violations/{id}', function (\Illuminate\Http\Request $request, $id) {
    try {
        $v = \App\Models\SoldierViolation::findOrFail($id);
        $data = $request->json()->all() ?: $request->all();
        $v->update([
            'status' => strtoupper($data['status'] ?? $v->status),
            'case_progress' => $data['case_progress'] ?? $v->case_progress,
        ]);
        return response()->json(['status' => 'success', 'data' => $v]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::delete('/api/mobile/violations/{id}', function ($id) {
    try {
        $v = \App\Models\SoldierViolation::findOrFail($id);
        $v->delete();
        return response()->json(['status' => 'success', 'message' => 'Catatan pelanggaran berhasil dihapus']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// 7. API KELOLA PENGGUNA (Model: App\Models\User)
Route::get('/api/mobile/users', function () {
    try {
        $users = \App\Models\User::latest()->get()->map(function($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'nrp' => $u->nrp ?? $u->username ?? '-',
                'pangkat' => $u->pangkat ?? 'Prajurit',
                'korps' => $u->korps ?? 'Pelaut',
                'role' => $u->role ?? 'user',
                'jabatan' => $u->jabatan ?? 'Anggota',
                'is_active' => (bool)($u->is_active ?? true),
                'can_access_agenda' => (bool)($u->can_access_agenda ?? true),
                'can_access_cash' => (bool)($u->can_access_cash ?? false),
                'can_access_mitra' => (bool)($u->can_access_mitra ?? false),
                'can_access_technical_cash' => (bool)($u->can_access_technical_cash ?? false),
                'can_access_ibu_beti' => (bool)($u->can_access_ibu_beti ?? false),
            ];
        });
        return response()->json(['status' => 'success', 'data' => $users, 'users' => $users]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'data' => []]);
    }
});

Route::post('/api/mobile/users', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $user = \App\Models\User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'nrp' => $data['nrp'] ?? $data['username'],
            'pangkat' => $data['pangkat'] ?? 'Prajurit',
            'korps' => $data['korps'] ?? 'Pelaut',
            'role' => $data['role'] ?? 'user',
            'jabatan' => $data['jabatan'] ?? 'Personel',
            'password' => \Illuminate\Support\Facades\Hash::make($data['password'] ?? '12345678'),
            'is_active' => true,
        ]);
        return response()->json(['status' => 'success', 'data' => $user]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::put('/api/mobile/users/{id}', function (\Illuminate\Http\Request $request, $id) {
    try {
        $user = \App\Models\User::findOrFail($id);
        $data = $request->json()->all() ?: $request->all();
        if (isset($data['is_active'])) {
            $user->update(['is_active' => (bool)$data['is_active']]);
        }
        return response()->json(['status' => 'success', 'data' => $user]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::delete('/api/mobile/users/{id}', function ($id) {
    try {
        $user = \App\Models\User::findOrFail($id);
        $user->delete();
        return response()->json(['status' => 'success', 'message' => 'Pengguna berhasil dihapus']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// 8. API TTE TANDA TANGAN DIGITAL (Model: App\Models\SignatureRequest)
Route::get('/api/mobile/signature-requests', function () {
    try {
        $requests = \App\Models\SignatureRequest::with('user')->latest()->take(100)->get();
        $skhpps = \App\Models\Skhpp::where('status', 'PENDING')->latest()->take(100)->get();

        return response()->json([
            'status' => 'success',
            'requests' => $requests,
            'skhpp_requests' => $skhpps,
            'data' => $requests,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'data' => []]);
    }
});

Route::post('/api/mobile/signature-requests/{id}/sign', function ($id) {
    try {
        $req = \App\Models\SignatureRequest::findOrFail($id);
        $req->update(['status' => 'SIGNED']);
        return response()->json(['status' => 'success', 'message' => 'Dokumen berhasil ditandatangani digital']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// 9. API DRAF NASKAH DINAS (Model: App\Models\Letter)
Route::get('/api/mobile/letters', function () {
    try {
        $letters = \App\Models\Letter::with(['category', 'subCategory'])->latest()->take(100)->get();
        return response()->json(['status' => 'success', 'data' => $letters, 'letters' => $letters]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'data' => []]);
    }
});

Route::post('/api/mobile/letters', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $letter = \App\Models\Letter::create([
            'letter_number' => $data['letter_number'] ?? 'DRAF/' . date('YmdHis'),
            'subject' => $data['subject'] ?? 'Naskah Dinas Baru',
            'category_id' => $data['category_id'] ?? 1,
            'recipient' => $data['recipient'] ?? 'Komando',
            'date' => $data['date'] ?? date('Y-m-d'),
        ]);
        return response()->json(['status' => 'success', 'data' => $letter]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// 10. API MASTER KATEGORI SURAT (Model: App\Models\Category)
Route::get('/api/mobile/categories', function () {
    try {
        $categories = \App\Models\Category::with('subCategories')->get();
        return response()->json(['status' => 'success', 'data' => $categories]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'data' => []]);
    }
});

// 11. API LOG PENGUNJUNG & AUDIT (Model: App\Models\VisitorLog & App\Models\AuditLog)
Route::get('/api/mobile/audit-logs', function () {
    try {
        $visitors = \App\Models\VisitorLog::with('user')->latest()->take(50)->get();
        $audits = \App\Models\AuditLog::latest()->take(50)->get();

        return response()->json([
            'status' => 'success',
            'visitor_logs' => $visitors,
            'audit_logs' => $audits,
            'data' => $visitors,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'data' => []]);
    }
});

// 12. API NOTIFIKASI SYSTEM (Model: App\Models\AppNotification)
Route::get('/api/mobile/notifications', function () {
    try {
        $notifs = \App\Models\AppNotification::latest()->take(50)->get()->map(function($n) {
            return [
                'id' => $n->id,
                'title' => $n->title ?? 'Notifikasi SINDEN',
                'message' => $n->message ?? '',
                'is_read' => (bool)($n->is_read ?? false),
                'created_at' => $n->created_at ? $n->created_at->toIso8601String() : now()->toIso8601String(),
            ];
        });
        return response()->json([
            'status' => 'success',
            'notifications' => $notifs,
            'unreadCount' => $notifs->where('is_read', false)->count()
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'notifications' => [], 'unreadCount' => 0]);
    }
});

// API KAS DAN UNIT TEKNIS KHUSUS (Model: App\Models\TechnicalUnitCash / Table: technical_unit_cashes)
Route::get('/api/mobile/technical-cash', function () {
    try {
        $all = \App\Models\TechnicalUnitCash::orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        $balance = (float)($all->last()?->balance ?? 0);
        $totalDebit = (float)$all->sum('debit');
        $totalCredit = (float)$all->sum('credit');

        $reversed = $all->map(function($cash) {
            return [
                'id' => $cash->id,
                'date' => (string)($cash->getRawOriginal('date') ?? $cash->date),
                'description' => $cash->description ?? '-',
                'debit' => (float)($cash->debit ?? 0),
                'credit' => (float)($cash->credit ?? 0),
                'balance' => (float)($cash->balance ?? 0),
            ];
        })->reverse()->values();

        return response()->json([
            'status' => 'success',
            'balance' => $balance,
            'totalSaldo' => $balance,
            'total_balance' => $balance,
            'total_income' => $totalDebit,
            'total_expense' => $totalCredit,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'cashes' => $reversed,
            'transactions' => $reversed,
            'data' => $reversed,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'balance' => 0, 'data' => []]);
    }
});

Route::post('/api/mobile/technical-cash', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $date = $data['date'] ?? date('Y-m-d');
        $desc = $data['description'] ?? '-';

        $type = strtolower($data['type'] ?? 'keluar');
        $amount = isset($data['amount']) ? (float)$data['amount'] : 0.0;

        if (isset($data['debit']) || isset($data['credit'])) {
            $debit = (float)($data['debit'] ?? 0);
            $credit = (float)($data['credit'] ?? 0);
        } else {
            if ($type === 'masuk' || $type === 'debit') {
                $debit = $amount;
                $credit = 0.0;
            } else {
                $debit = 0.0;
                $credit = $amount;
            }
        }

        $cash = \App\Models\TechnicalUnitCash::create([
            'date' => is_string($date) ? substr($date, 0, 10) : $date,
            'description' => $desc,
            'debit' => $debit,
            'credit' => $credit,
            'balance' => 0.0,
        ]);

        // Rekalkulasi seluruh saldo kas taktis unit teknis secara kronologis
        $all = \App\Models\TechnicalUnitCash::orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        $running = 0.0;
        foreach ($all as $item) {
            $running += ((float)$item->debit - (float)$item->credit);
            $item->balance = $running;
            $item->save();
        }

        return response()->json(['status' => 'success', 'data' => $cash->fresh()]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::put('/api/mobile/technical-cash/{id}', function (\Illuminate\Http\Request $request, $id) {
    try {
        $cash = \App\Models\TechnicalUnitCash::findOrFail($id);
        $data = $request->json()->all() ?: $request->all();
        $desc = $data['description'] ?? $cash->description;
        $date = isset($data['date']) ? substr($data['date'], 0, 10) : $cash->date;

        if (isset($data['debit']) || isset($data['credit'])) {
            $debit = (float)($data['debit'] ?? $cash->debit);
            $credit = (float)($data['credit'] ?? $cash->credit);
        } elseif (isset($data['amount'])) {
            $type = strtolower($data['type'] ?? ($cash->debit > 0 ? 'masuk' : 'keluar'));
            $amount = (float)$data['amount'];
            if ($type === 'masuk' || $type === 'debit') {
                $debit = $amount;
                $credit = 0.0;
            } else {
                $debit = 0.0;
                $credit = $amount;
            }
        } else {
            $debit = $cash->debit;
            $credit = $cash->credit;
        }

        $cash->update([
            'date' => $date,
            'description' => $desc,
            'debit' => $debit,
            'credit' => $credit,
        ]);

        // Rekalkulasi seluruh saldo kas taktis unit teknis secara kronologis
        $all = \App\Models\TechnicalUnitCash::orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        $running = 0.0;
        foreach ($all as $item) {
            $running += ((float)$item->debit - (float)$item->credit);
            $item->balance = $running;
            $item->save();
        }

        return response()->json(['status' => 'success', 'data' => $cash->fresh()]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::delete('/api/mobile/technical-cash/{id}', function ($id) {
    try {
        $cash = \App\Models\TechnicalUnitCash::findOrFail($id);
        $cash->delete();

        // Rekalkulasi seluruh saldo kas taktis unit teknis secara kronologis
        $all = \App\Models\TechnicalUnitCash::orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        $running = 0.0;
        foreach ($all as $item) {
            $running += ((float)$item->debit - (float)$item->credit);
            $item->balance = $running;
            $item->save();
        }

        return response()->json(['status' => 'success', 'message' => 'Kas Dan Unit Teknis berhasil dihapus']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// =====================================================================
// API VERIFIKASI KEASLIAN TTE DIGITAL (QR SCANNER VALIDATION ENGINE)
// =====================================================================
Route::get('/api/mobile/verify-signature', function (\Illuminate\Http\Request $request) {
    try {
        $code = trim($request->input('code', ''));
        if (empty($code)) {
            return response()->json(['status' => 'error', 'valid' => false, 'message' => 'Kode TTE tidak boleh kosong.'], 400);
        }

        // 1. Cek di SignatureRequest
        $sig = \App\Models\SignatureRequest::with('user')
            ->where('verification_code', $code)
            ->orWhere('verification_code', 'like', "%{$code}%")
            ->first();

        if ($sig) {
            $isValid = in_array(strtolower($sig->status), ['approved', 'signed', 'selesai']);
            $applicantUser = $sig->user ?: ($sig->user_id ? \App\Models\User::find($sig->user_id) : null);
            $applicantName = $sig->person_name ?: ($applicantUser?->name ?? 'Personel Denintel');
            $applicantRank = $applicantUser?->pangkat ?: 'Prajurit TNI AL';
            $applicantNrp = ($applicantUser?->nrp && $applicantUser->nrp !== '00000000000000') ? $applicantUser->nrp : ($applicantUser?->username ?? '-');
            $applicantIdentity = $sig->pangkat_nrp ?: "{$applicantRank} / NRP {$applicantNrp}";

            $commander = \App\Models\User::where('role', 'komandan')->first() ?? \App\Models\User::where('role', 'admin')->first();
            $commanderRank = $commander?->pangkat ?? 'Kolonel Laut (E)';
            $commanderNrp = ($commander?->nrp && $commander->nrp !== '00000000000000') ? $commander->nrp : '16085/P';
            $signedBy = "KOMANDAN DETASEMEN INTELIJEN KODAERAL V ({$commanderRank} NRP {$commanderNrp})";

            return response()->json([
                'status' => 'success',
                'valid' => $isValid,
                'document_type' => 'NASKAH DINAS / DOKUMEN RESMI KEDINASAN',
                'title' => $sig->subject ?? $sig->document_title ?? 'Dokumen Resmi',
                'number' => $sig->letter_number ?? "B/{$sig->id}/VIII/2026/Denintel",
                'person_name' => $applicantName,
                'pangkat_nrp' => $applicantIdentity,
                'signed_by' => $signedBy,
                'signed_at' => ($sig->approved_at ?? $sig->updated_at) ? \Carbon\Carbon::parse($sig->approved_at ?? $sig->updated_at)->format('d/m/Y H:i:s') : now()->format('d/m/Y H:i:s'),
                'verification_code' => $sig->verification_code,
                'hash_sha256' => hash('sha256', $sig->verification_code . ($sig->approved_at ?? $sig->updated_at ?? now())),
                'message' => $isValid ? 'DOKUMEN RESMI DINAS ASLI & TERVERIFIKASI' : 'DOKUMEN DITEMUKAN TETAPI BELUM DISAHKAN',
            ]);
        }

        // 2. Cek di SKHPP
        $skhpp = \App\Models\Skhpp::with(['submitter', 'approver'])
            ->where('verification_code', $code)
            ->orWhere('nomor_skhpp', $code)
            ->orWhere('verification_code', 'like', "%{$code}%")
            ->first();

        if ($skhpp) {
            $isValid = in_array(strtoupper($skhpp->status), ['APPROVED', 'SELESAI', 'TERBIT']);
            return response()->json([
                'status' => 'success',
                'valid' => $isValid,
                'document_type' => 'SURAT KETERANGAN HASIL PENELITIAN PERSONEL (SKHPP)',
                'title' => 'SKHPP ' . ($skhpp->kategori_personel === 'perusahaan' ? 'MITRA KERJA' : 'PERSONEL'),
                'number' => $skhpp->nomor_skhpp ?? "SKHPP-{$skhpp->id}",
                'person_name' => $skhpp->nama,
                'pangkat_nrp' => $skhpp->pangkat_korps_nrp ?? $skhpp->nik ?? '-',
                'signed_by' => 'KOMANDAN DETASEMEN INTELIJEN KODAERAL V',
                'signed_at' => $skhpp->approved_at ? \Carbon\Carbon::parse($skhpp->approved_at)->format('d/m/Y H:i:s') : now()->format('d/m/Y H:i:s'),
                'verification_code' => $skhpp->verification_code,
                'hash_sha256' => hash('sha256', $skhpp->verification_code . ($skhpp->approved_at ?? now())),
                'message' => $isValid ? 'DOKUMEN SKHPP ASLI & TERVERIFIKASI' : 'DOKUMEN SKHPP MASIH DALAM PROSES PENELITIAN',
            ]);
        }

        return response()->json([
            'status' => 'invalid',
            'valid' => false,
            'message' => 'PERINGATAN: KODE TTE TIDAK TERDAFTAR DI DATABASE RESMI SINDEN ATAU DOKUMEN TELAH DIMANIPULASI!',
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'valid' => false, 'message' => $e->getMessage()], 500);
    }
});

// API SIGN DOKUMEN DENGAN POSISI KOORDINAT QR CUSTOM
Route::post('/api/mobile/signature-requests/{id}/sign-custom', function (\Illuminate\Http\Request $request, $id) {
    try {
        $sig = \App\Models\SignatureRequest::findOrFail($id);
        $data = $request->json()->all() ?: $request->all();

        $x = (float)($data['x'] ?? 0.65);
        $y = (float)($data['y'] ?? 0.75);
        $targetPage = (int)($data['target_page'] ?? 1);
        $code = $sig->verification_code ?: ('TTE-DOC-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(6)));

        $sig->update([
            'status' => 'approved',
            'x' => $x,
            'y' => $y,
            'target_page' => $targetPage,
            'verification_code' => $code,
            'approved_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen berhasil disahkan dengan TTE Digital pada posisi yang dipilih',
            'data' => $sig
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// =====================================================================
// API BACKUP SYSTEM & RADAR PC JARINGAN KANTOR
// =====================================================================
Route::get('/api/mobile/backup', function () {
    try {
        $pcs = \App\Models\Pc::with('user:id,name,pangkat,nrp')->latest()->get()->map(function($pc) {
            return [
                'id' => $pc->id,
                'name' => $pc->name ?? $pc->pc_name ?? 'PC Kedinasan',
                'ip_address' => $pc->ip_address ?? '192.168.1.10',
                'user_name' => $pc->user?->name ?? 'Personel',
                'status' => $pc->is_online ? 'ONLINE' : 'OFFLINE',
                'storage_used' => $pc->storage_used ?? '1.2 GB',
                'last_backup' => $pc->last_backup_at ? \Carbon\Carbon::parse($pc->last_backup_at)->format('d/m/Y H:i') : 'Belum Ada',
            ];
        });

        $requests = \App\Models\AccessRequest::with('user')->latest()->take(20)->get()->map(function($r) {
            return [
                'id' => $r->id,
                'user_name' => $r->user?->name ?? 'Personel',
                'pc_name' => $r->pc_name ?? 'PC-Baru',
                'status' => strtoupper($r->status ?? 'PENDING'),
                'verification_code' => $r->verification_code,
                'created_at' => $r->created_at ? $r->created_at->format('d/m/Y H:i') : '',
            ];
        });

        $totalPcs = \App\Models\Pc::count();
        $totalStorage = '24.8 GB';

        return response()->json([
            'status' => 'success',
            'pcs' => $pcs,
            'access_requests' => $requests,
            'stats' => [
                'total_pcs' => $totalPcs,
                'total_storage' => $totalStorage,
                'system_status' => 'TERHUBUNG & AMAN',
            ],
            'download_db_url' => url('/api/mobile/backup/download-sql'),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'pcs' => []]);
    }
});

Route::post('/api/mobile/backup/request-access', function (\Illuminate\Http\Request $request) {
    try {
        $pool = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $randStr = substr(str_shuffle($pool), 0, 5);

        $req = \App\Models\AccessRequest::create([
            'user_id' => auth()->id() ?? 1,
            'pc_name' => 'PC-' . $randStr,
            'status' => 'pending'
        ]);

        return response()->json(['status' => 'success', 'message' => 'Pengajuan akses backup PC berhasil dikirim ke Admin.', 'data' => $req]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// =====================================================================
// API CATATAN PELANGGARAN PRAJURIT (DETAIL & RIWAYAT KASUS)
// =====================================================================
Route::post('/api/mobile/violations/{id}/progress', function (\Illuminate\Http\Request $request, $id) {
    try {
        $v = \App\Models\SoldierViolation::findOrFail($id);
        $data = $request->json()->all() ?: $request->all();
        $note = $data['note'] ?? $data['catatan'] ?? '';
        $newStatus = strtoupper($data['status'] ?? $v->status);

        $timestamp = now()->format('d/m/Y H:i');
        $updatedProgress = ($v->case_progress ? $v->case_progress . "\n" : "") . "[{$timestamp}] " . $note;

        $v->update([
            'case_progress' => $updatedProgress,
            'status' => $newStatus,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Perkembangan kasus berhasil dicatat', 'data' => $v]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// =====================================================================
// API RADAR KEGIATAN LAPANGAN CRUD
// =====================================================================
Route::post('/api/mobile/activities', function (\Illuminate\Http\Request $request) {
    try {
        $data = $request->json()->all() ?: $request->all();
        $act = \App\Models\CommunityActivity::create([
            'title' => $data['title'] ?? 'Kegiatan Lapangan',
            'category' => $data['category'] ?? 'Pengamanan',
            'location_name' => $data['location_name'] ?? 'Wilayah Kodaeral V',
            'latitude' => (float)($data['latitude'] ?? -7.2575),
            'longitude' => (float)($data['longitude'] ?? 112.7521),
            'activity_date' => $data['activity_date'] ?? date('Y-m-d'),
            'description' => $data['description'] ?? 'Laporan kegiatan pemantauan.',
            'user_id' => auth()->id() ?? 1,
        ]);
        return response()->json(['status' => 'success', 'data' => $act]);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::delete('/api/mobile/activities/{id}', function ($id) {
    try {
        $act = \App\Models\CommunityActivity::findOrFail($id);
        $act->delete();
        return response()->json(['status' => 'success', 'message' => 'Kegiatan berhasil dihapus']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});