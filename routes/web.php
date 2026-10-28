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
use App\Http\Controllers\KoperasiController;
use App\Http\Controllers\PersonnelController;
use App\Http\Controllers\PESSAdminController; // SULTAN CONFIG: Pangkalan Pemisah Logika Otoritas Kerja Admin PESS
use App\Http\Controllers\API\PESSReceiverController; // SULTAN CONFIG: Controller Penerima File Lintas VPS
use App\Http\Controllers\CommanderAccountController;
use App\Http\Controllers\MitraPaymentController;
use App\Http\Controllers\SkhppController;
use App\Http\Controllers\DocVerificationController;
use App\Http\Controllers\SpJagaController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\PersonnelTrackingController;
use App\Http\Controllers\PublicTrackingController;
use App\Http\Controllers\ScSubmissionController;
use App\Http\Controllers\SystemCheckController;
use App\Http\Controllers\PrintServiceController;
use App\Http\Controllers\Chat\LiveChatController;
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
// Token aktivasi/reset hanya 6 karakter: batasi percobaan agar tidak dapat di-brute-force.
Route::post('/aktivasi', [UserController::class, 'activate'])->middleware('throttle:activation')->name('aktivasi.proses');

Route::get('forgot-password-custom', [ResetPasswordController::class, 'showResetForm'])->name('password.request.custom');
Route::post('reset-password-custom', [ResetPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.update.custom');

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
    if ($user && !$user->isAdmin()) {
        $r = strtolower(preg_replace('/[\s_-]+/', '', $user->role ?? ''));
        $j = strtolower($user->jabatan ?? '');
        if ($r === 'anggotasintel' || $r === 'personelsintel' || str_contains($j, 'anggota sintel') || str_contains($j, 'sintel')) {
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
    $combinedActivities = ($user && $user->isAdmin()) ? $recentVisitors->concat($recentAudits)->sortByDesc('created_at')->take(5)->values() : null;

    $today = now()->toDateString();
    $todayAttendance = Attendance::where('user_id', $user->id)
        ->where('attendance_date', $today)
        ->first();

    $todayAttendancesCount = Attendance::where('attendance_date', $today)->count();
    $recentAttendances = in_array(strtolower($user->role), ['super_admin', 'superadmin', 'admin', 'komandan', 'pasops'])
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
        'pending_users' => ($user && $user->isAdmin()) ? User::where('is_active', false)->latest()->take(5)->get() : null,
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

    // PRATINJAU DOKUMEN & MEDIA TEROTENTIKASI (STREAMING DEKRIPSI ON-THE-FLY)
    Route::get('/pc-backup/preview-file/{id}', [BackupController::class, 'previewFile'])->name('backup.preview-file');
    Route::get('/pc-backup/thumbnail/{id}', [BackupController::class, 'thumbnail'])->name('backup.thumbnail');

    // Fitur GPS Lokasi Personel
    Route::post('/update-location', [LocationController::class, 'update'])->name('location.update');

    // Fitur Presensi Kehadiran Personel (wajib GPS & foto senyum)
    Route::post('/attendances', [AttendanceController::class, 'store'])->name('attendances.store');

    // Pantau Personel: posisi realtime dari aplikasi Android (Admin, Super Admin, Komandan)
    Route::get('/pantau-personel', [PersonnelTrackingController::class, 'index'])->name('tracking.index');
    Route::get('/pantau-personel/data', [PersonnelTrackingController::class, 'data'])->name('tracking.data');
    Route::get('/pantau-personel/{userId}/jejak', [PersonnelTrackingController::class, 'history'])->whereNumber('userId')->name('tracking.history');

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
    // FITUR KHUSUS ADMIN (OTORITAS TINGGI MONEV & MANAJEMEN PENGGUNA)
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
        Route::post('/users/{user}/toggle-koperasi-access', [UserController::class, 'toggleKoperasiAccess'])->name('users.toggle-koperasi-access');
        Route::post('/users/{user}/toggle-mfa', [UserController::class, 'toggleMfa'])->name('users.toggle-mfa');

        // Kategori Arsip Surat Mako Koarmada 2
        Route::resource('categories', CategoryController::class);
        Route::post('/categories/{category}/sub', [CategoryController::class, 'storeSub'])->name('categories.storeSub');
        Route::delete('/sub-categories/{subCategory}', [CategoryController::class, 'destroySub'])->name('sub-categories.destroy');

        // Monitoring Log Pengunjung & Audit Aktivitas
        Route::get('/visitor-logs', [VisitorLogController::class, 'index'])->name('visitor-logs.index');
        Route::match(['delete', 'post'], '/visitor-logs/clear', [VisitorLogController::class, 'clearAll'])->name('visitor-logs.clear');
        Route::delete('/visitor-logs/{id}', [VisitorLogController::class, 'destroy'])->name('visitor-logs.destroy');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

        // Rencana Kegiatan Pengamanan Aktivitas Masyarakat
        Route::get('/community-activities', [CommunityActivityController::class, 'index'])->name('activities.index');
        Route::post('/community-activities', [CommunityActivityController::class, 'store'])->name('activities.store');
        Route::get('/community-activities/{id}', [CommunityActivityController::class, 'show'])->name('activities.show');
        Route::delete('/community-activities/{id}', [CommunityActivityController::class, 'destroy'])->name('activities.destroy');
        Route::put('/community-activities/{id}', [CommunityActivityController::class, 'update'])->name('activities.update');

        // Fitur khusus Admin: Atur/Loncati nomor urut SKHPP & Ajukan TTE Ulang
        Route::post('/skhpp/{id}/update-number', [SkhppController::class, 'updateNumber'])->name('skhpp.update-number');
        Route::post('/skhpp/{id}/resubmit-tte', [SkhppController::class, 'reSubmitTte'])->name('skhpp.resubmit-tte');
    });

    // =========================================================================
    // FITUR KHUSUS SUPER ADMIN (SISTEM, SETTING WA GATEWAY, API & PENGATURAN)
    // =========================================================================
    Route::middleware(['role:super_admin'])->group(function () {
        // Pengaturan Sistem & Konfigurasi Aplikasi / API
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        // Pembaruan aplikasi Android: unggah/hapus APK sumber pembaruan di HP personel
        Route::post('/settings/app-update', [\App\Http\Controllers\AppUpdateController::class, 'store'])->name('settings.app-update.store');
        Route::post('/settings/app-update/github', [\App\Http\Controllers\AppUpdateController::class, 'fetchFromGithub'])->middleware('throttle:6,1')->name('settings.app-update.github');
        Route::delete('/settings/app-update', [\App\Http\Controllers\AppUpdateController::class, 'destroy'])->name('settings.app-update.destroy');
        Route::post('/settings/update-stamp', [SettingController::class, 'updateStamp'])->name('settings.update-stamp');

        // Cek Sistem & Monitoring Server Admin (Speedtest, Disk, CPU, RAM, VRAM)
        Route::get('/admin/system-check', [SystemCheckController::class, 'index'])->name('system-check.index');
        Route::get('/admin/system-check/metrics', [SystemCheckController::class, 'getMetrics'])->name('system-check.metrics');
        Route::post('/admin/system-check/speedtest', [SystemCheckController::class, 'runSpeedtest'])->name('system-check.speedtest');

        // Jalur MFA WhatsApp Gateway Engine Lokal (Port 3000)
        Route::get('/admin/setting/wa-status', function () {
            try {
                $data = Http::timeout(3)->get('http://127.0.0.1:3000/status')->json() ?? [];
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'OFFLINE',
                    'gateway' => 'down',
                    'qr' => null,
                    'error' => 'WA Gateway tidak berjalan. Jalankan: pm2 start sinden-wa-gateway',
                ]);
            }

            // Gateway hidup tapi belum ada sesi aktif (instalasi baru / setelah logout):
            // minta gateway membuat sesi "dinas" agar QR muncul pada pengecekan berikutnya.
            $active = collect($data['sessions'] ?? [])
                ->contains(fn ($s) => in_array($s['status'] ?? '', ['CONNECTED', 'QR_READY', 'CONNECTING'], true));
            if (!$active) {
                try {
                    Http::timeout(5)->post('http://127.0.0.1:3000/sessions', ['sessionId' => 'dinas', 'label' => 'WA Dinas / Radar Utama']);
                } catch (\Exception $e) {
                    // Diabaikan: status dicek ulang tiap 5 detik oleh halaman.
                }
            }

            $connected = collect($data['sessions'] ?? [])->firstWhere('status', 'CONNECTED');

            return response()->json([
                'status' => $data['status'] ?? 'OFFLINE',
                'gateway' => 'up',
                'qr' => $data['qr'] ?? null,
                'phone' => $connected['phone'] ?? null,
                'name' => $connected['name'] ?? null,
                'preparing' => !$active || ($data['status'] ?? '') === 'OFFLINE',
            ]);
        })->name('settings.wa-status');

        // Putuskan WhatsApp yang tertaut lalu siapkan QR baru untuk nomor lain.
        Route::post('/admin/setting/wa-logout', function () {
            try {
                $res = Http::timeout(15)->post('http://127.0.0.1:3000/sessions/dinas/logout');
            } catch (\Exception $e) {
                return response()->json(['ok' => false, 'message' => 'WA Gateway tidak berjalan.'], 503);
            }
            if ($res->status() === 404) {
                return response()->json(['ok' => true, 'message' => 'Tidak ada WhatsApp yang tertaut.']);
            }
            \Illuminate\Support\Facades\Log::info('[WA_GATEWAY] Logout sesi dinas oleh Super Admin', ['user_id' => auth()->id()]);
            return response()->json([
                'ok' => $res->successful(),
                'message' => $res->successful() ? 'WhatsApp berhasil di-logout. QR baru akan muncul sebentar lagi.' : ($res->json('error') ?? 'Gagal logout WhatsApp.'),
            ], $res->successful() ? 200 : 500);
        })->name('settings.wa-logout');
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
// SISINDEN MOBILE API (/api/mobile/*) — lihat routes/mobile_api.php
// Seluruh endpoint data mewajibkan token Sanctum + permission (v1.1.0).
// =====================================================================
require __DIR__.'/mobile_api.php';

// =====================================================================
// JALUR MEMBACA SYSTEM LOG LARAVEL (/admin/laravel-logs) — KHUSUS ADMIN
// Sebelumnya rute ini publik dan dapat dibaca siapa pun.
// =====================================================================
Route::get('/admin/laravel-logs', function () {
    $logFile = storage_path('logs/laravel.log');
    if (!file_exists($logFile)) {
        return response("File log laravel.log belum tersedia.", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
    $lines = array_slice(explode("\n", (string) file_get_contents($logFile)), -300);
    return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
})->middleware(['auth', 'role:super_admin'])->name('admin.laravel-logs');
