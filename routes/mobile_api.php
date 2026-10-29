<?php

/*
|--------------------------------------------------------------------------
| SISINDEN Mobile API (/api/mobile/*)
|--------------------------------------------------------------------------
|
| Dipindahkan dari routes/web.php (v1.1.0). Perubahan utama:
|  - Semua endpoint data kini WAJIB token Sanctum (middleware `mobile.auth`).
|  - Setiap endpoint diberi permission yang sama dengan aturan web
|    (lihat App\Support\MobileAccess). Backend tetap menjadi otoritas akhir.
|  - Identitas tidak lagi diambil dari header X-User-* yang dikirim klien.
|  - Aktivasi akun mewajibkan token aktivasi (sama dengan web).
|  - Bentuk respons JSON lama dipertahankan agar aplikasi lama tetap kompatibel.
|
| Endpoint publik yang tersisa: login, activate, config, verify-signature.
|
*/

use App\Http\Controllers\Mobile\MobileApiController;
use App\Http\Controllers\Mobile\MobileCommsController;
use App\Models\User;
use App\Support\MobileAccess;
use App\Support\Turnstile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

if (!function_exists('mobile_payload')) {
    /** Baca body JSON atau form. */
    function mobile_payload(Request $request): array
    {
        return $request->json()->all() ?: $request->all();
    }
}

if (!function_exists('mobile_error')) {
    /** Respons error standar tanpa membocorkan detail internal server. */
    function mobile_error(\Throwable $e, string $message = 'Terjadi kendala pada server.', int $status = 500)
    {
        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan.'], 404);
        }
        Log::error('[MOBILE_API] ' . $e->getMessage(), ['trace' => substr($e->getTraceAsString(), 0, 2000)]);
        return response()->json(['status' => 'error', 'message' => $message], $status);
    }
}

if (!function_exists('mobile_validation_error')) {
    function mobile_validation_error(\Illuminate\Contracts\Validation\Validator $v)
    {
        return response()->json([
            'status' => 'error',
            'message' => $v->errors()->first(),
            'errors' => $v->errors(),
        ], 422);
    }
}

if (!function_exists('mobile_recalculate_balances')) {
    /** Rekalkulasi saldo berjalan kronologis (logika lama dipertahankan). */
    function mobile_recalculate_balances(string $modelClass): void
    {
        $running = 0.0;
        foreach ($modelClass::orderBy('date', 'asc')->orderBy('id', 'asc')->get() as $item) {
            $running += ((float) $item->debit - (float) $item->credit);
            if ((float) $item->balance !== $running) {
                $item->balance = $running;
                $item->save();
            }
        }
    }
}

if (!function_exists('mobile_debit_credit')) {
    /** @return array{0: float, 1: float} */
    function mobile_debit_credit(array $data, string $defaultType, float $oldDebit = 0.0, float $oldCredit = 0.0, bool $isUpdate = false): array
    {
        if (isset($data['debit']) || isset($data['credit'])) {
            return [max(0.0, (float) ($data['debit'] ?? 0)), max(0.0, (float) ($data['credit'] ?? 0))];
        }
        if (isset($data['amount'])) {
            $type = strtolower($data['type'] ?? $defaultType);
            $amount = max(0.0, (float) $data['amount']);
            return in_array($type, ['masuk', 'debit'], true) ? [$amount, 0.0] : [0.0, $amount];
        }
        return $isUpdate ? [$oldDebit, $oldCredit] : [0.0, 0.0];
    }
}

if (!function_exists('mobile_delegate')) {
    /**
     * Jalankan aksi controller web yang sudah ada (agar business logic, nomor
     * surat, TTE, notifikasi WA, dan audit log tidak diduplikasi), lalu ubah
     * redirect + flash message-nya menjadi respons JSON untuk aplikasi mobile.
     */
    function mobile_delegate(callable $action, string $defaultSuccess = 'Berhasil diproses.')
    {
        $keys = ['success', 'message', 'warning', 'error'];
        session()->forget($keys);
        try {
            $response = $action();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => collect($e->errors())->flatten()->first() ?? 'Data tidak valid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage() ?: 'Akses ditolak.'], $e->getStatusCode());
        } catch (\Throwable $e) {
            return mobile_error($e, 'Gagal memproses permintaan.');
        }

        $flash = collect($keys)->mapWithKeys(fn ($k) => [$k => session($k)])->all();
        session()->forget($keys);

        if (!empty($flash['error'])) {
            return response()->json(['status' => 'error', 'message' => (string) $flash['error']], 422);
        }
        if ($response instanceof \Illuminate\Http\JsonResponse) {
            return $response;
        }
        $message = $flash['success'] ?? $flash['message'] ?? $flash['warning'] ?? $defaultSuccess;
        return response()->json(['status' => 'success', 'message' => (string) $message]);
    }
}

// =====================================================================
// PUBLIK: LOGIN, AKTIVASI, KONFIGURASI, VERIFIKASI QR
// =====================================================================

Route::post('/api/mobile/login', function (Request $request) {
    try {
        $data = mobile_payload($request);
        $input = trim((string) ($data['username'] ?? $data['email'] ?? $data['nrp'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($input === '' || $password === '') {
            return response()->json(['status' => 'error', 'message' => 'NRP / Email dan Kata Sandi wajib diisi.'], 422);
        }

        // Cloudflare Turnstile diverifikasi SEBELUM menyentuh data akun.
        $ts = Turnstile::verify($data['cf_turnstile_token'] ?? null, $request->ip(), 'login');
        if (!$ts['ok']) {
            return response()->json(['status' => 'error', 'code' => 'turnstile_failed', 'message' => $ts['message']], 422);
        }

        $user = User::where('email', $input)->orWhere('nrp', $input)->first();

        // Pesan generik: jangan membocorkan apakah NRP terdaftar.
        if (!$user || !Hash::check($password, $user->password)) {
            Log::warning('[MOBILE_API_LOGIN] Kredensial salah', ['ip' => $request->ip()]);
            return response()->json(['status' => 'error', 'message' => 'NRP / Email atau kata sandi salah.'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['status' => 'error', 'message' => 'Akun belum aktif atau sedang ditangguhkan oleh Admin.'], 403);
        }

        if (!Schema::hasTable('personal_access_tokens')) {
            Log::critical('[MOBILE_API_LOGIN] Tabel personal_access_tokens belum dimigrasikan.');
            return response()->json([
                'status' => 'error',
                'message' => 'Layanan login mobile sedang dalam pemeliharaan. Hubungi Admin.',
            ], 503);
        }

        $deviceName = substr((string) ($data['device_name'] ?? 'sinden_mobile_token'), 0, 100);
        $token = $user->createToken($deviceName)->plainTextToken;

        Log::info('[MOBILE_API_LOGIN] Sukses', ['user_id' => $user->id, 'ip' => $request->ip()]);

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'user' => MobileAccess::sessionPayload($user),
        ]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Kendala server saat login. Coba beberapa saat lagi.');
    }
})->middleware('throttle:mobile-login');

Route::get('/api/mobile/config', function () {
    $settings = \App\Models\Setting::pluck('value', 'key')->toArray();
    $logo = $settings['agency_logo'] ?? $settings['logo'] ?? null;
    $logoUrl = !empty($logo) ? (str_starts_with($logo, 'http') ? $logo : asset('storage/' . $logo)) : asset('images/logo.png');

    $bg = $settings['login_background'] ?? null;
    $bgUrl = !empty($bg) ? (str_starts_with($bg, 'http') ? $bg : asset('storage/' . $bg)) : null;

    return response()->json([
        'status' => 'success',
        'app_name' => $settings['agency_name'] ?? 'SINDEN 2',
        'agency_name' => $settings['agency_name'] ?? 'DETASEMEN INTELIJEN KOARMADA 2',
        'agency_logo' => $logoUrl,
        'login_background' => $bgUrl,
        'api_base_url' => $settings['mobile_api_base_url'] ?? config('app.url', 'https://sinden2.my.id'),
        'api_login_endpoint' => '/api/mobile/login',
        'apk_download_url' => $settings['mobile_apk_download_url'] ?? 'https://sinden2.my.id/download/sinden-mobile.apk',
        'api_status' => $settings['mobile_api_status'] ?? 'AKTIF',
        'min_version' => $settings['mobile_min_version'] ?? '1.0.0',
        'mobile_theme_mode' => $settings['mobile_theme_mode'] ?? 'system',
        'mobile_primary_color' => $settings['mobile_primary_color'] ?? '#1E3A8A',
        'mobile_accent_color' => $settings['mobile_accent_color'] ?? '#0284C7',
        'mobile_card_radius' => (int) ($settings['mobile_card_radius'] ?? 20),
        'mobile_show_balance_card' => ($settings['mobile_show_balance_card'] ?? '1') === '1',
        'mobile_show_summary_trio' => ($settings['mobile_show_summary_trio'] ?? '1') === '1',
        'mobile_show_quick_actions' => ($settings['mobile_show_quick_actions'] ?? '1') === '1',
        'turnstile_site_key' => Turnstile::siteKey(),
        'turnstile_enabled' => Turnstile::enabled(),
        // Konfigurasi klien Firebase (publik, dari google-services.json) agar APK tidak perlu dibuild ulang.
        'push' => \App\Support\PushNotifier::clientConfig(),
        'push_enabled' => \App\Support\PushNotifier::enabled(),
        'server_time' => now()->toIso8601String(),
    ]);
})->middleware('throttle:60,1');

// Pembaruan aplikasi: APK yang diunggah Admin di Pengaturan web (publik; APK diverifikasi SHA-256 & tanda tangan di HP).
Route::get('/api/mobile/app-update', [\App\Http\Controllers\AppUpdateController::class, 'latest'])->middleware('throttle:60,1');
Route::get('/api/mobile/app-update/download', [\App\Http\Controllers\AppUpdateController::class, 'download'])->middleware('throttle:30,1');

// Halaman widget Cloudflare Turnstile yang dimuat WebView aplikasi Android
// (origin = domain Sinden 2, sehingga cocok dengan daftar hostname widget).
Route::get('/mobile/turnstile', function (Request $request) {
    $action = in_array($request->query('action'), ['login', 'activate'], true) ? $request->query('action') : 'login';
    $theme = $request->query('theme') === 'light' ? 'light' : 'dark';
    return response()
        ->view('mobile.turnstile', [
            'siteKey' => Turnstile::siteKey(),
            'action' => $action,
            'theme' => $theme,
        ])
        ->header('Cache-Control', 'no-store')
        ->header('X-Robots-Tag', 'noindex');
})->middleware('throttle:60,1');

// AKTIVASI AKUN MOBILE — wajib token aktivasi dari Admin (sama seperti web /aktivasi).
Route::post('/api/mobile/activate', function (Request $request) {
    try {
        $data = mobile_payload($request);
        $v = Validator::make($data, [
            'nrp' => 'required|string|max:100',
            'token' => 'required|string|max:255',
            'password' => 'required|string|min:8|max:255',
            'consent' => 'accepted',
        ], [
            'token.required' => 'Token aktivasi dari Admin wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            // Aplikasi versi lama (< 1.6.2) belum memiliki kotak persetujuan.
            'consent.accepted' => 'Persetujuan ketentuan dinas (termasuk pelacakan lokasi) wajib dicentang. Bila kotak persetujuan tidak terlihat, perbarui aplikasi Sinden 2 ke versi terbaru.',
        ]);
        if ($v->fails()) {
            return mobile_validation_error($v);
        }

        $ts = Turnstile::verify($data['cf_turnstile_token'] ?? null, $request->ip(), 'activate');
        if (!$ts['ok']) {
            return response()->json(['status' => 'error', 'code' => 'turnstile_failed', 'message' => $ts['message']], 422);
        }

        $identifier = trim($data['nrp']);
        $user = User::where('activation_token', trim($data['token']))
            ->where(function ($q) use ($identifier) {
                $q->where('nrp', $identifier)->orWhere('email', $identifier);
            })
            ->first();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'NRP / Email atau token aktivasi tidak valid.'], 422);
        }

        $user->update(array_merge([
            'password' => Hash::make($data['password']),
            'is_active' => true,
            'activation_token' => null,
            'must_change_password' => false,
        ], Schema::hasColumn('users', 'tracking_consent_at') ? ['tracking_consent_at' => now()] : []));

        return response()->json([
            'status' => 'success',
            'message' => 'Aktivasi akun berhasil. Silakan masuk menggunakan kata sandi baru Anda.',
        ]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memproses aktivasi akun.');
    }
})->middleware('throttle:activation');

// API VERIFIKASI KEASLIAN TTE DIGITAL (QR) — publik seperti /verify-* di web.
Route::get('/api/mobile/verify-signature', function (Request $request) {
    try {
        $code = trim((string) $request->input('code', ''));
        if ($code === '' || strlen($code) < 6) {
            return response()->json(['status' => 'error', 'valid' => false, 'message' => 'Kode TTE tidak valid.'], 400);
        }

        $sig = \App\Models\SignatureRequest::with('user')->where('verification_code', $code)->first();
        if ($sig) {
            $isValid = in_array(strtolower((string) $sig->status), ['approved', 'signed', 'selesai'], true);
            $applicant = $sig->user;
            return response()->json([
                'status' => 'success',
                'valid' => $isValid,
                'document_type' => 'NASKAH DINAS / DOKUMEN RESMI KEDINASAN',
                'title' => $sig->subject ?? $sig->document_title ?? 'Dokumen Resmi',
                'number' => $sig->letter_number ?? '-',
                'person_name' => $sig->person_name ?: ($applicant?->name ?? '-'),
                'pangkat_nrp' => $sig->pangkat_nrp ?: trim(($applicant?->pangkat ?? '') . ' ' . ($applicant?->nrp ? 'NRP ' . $applicant->nrp : '')),
                'signed_by' => 'KOMANDAN DETASEMEN INTELIJEN KOARMADA 2',
                'signed_at' => optional($sig->approved_at ? \Carbon\Carbon::parse($sig->approved_at) : null)->format('d/m/Y H:i:s'),
                'verification_code' => $sig->verification_code,
                'message' => $isValid ? 'DOKUMEN RESMI DINAS ASLI & TERVERIFIKASI' : 'DOKUMEN DITEMUKAN TETAPI BELUM DISAHKAN',
            ]);
        }

        $skhpp = \App\Models\Skhpp::where('verification_code', $code)->orWhere('nomor_skhpp', $code)->first();
        if ($skhpp) {
            $isValid = in_array(strtoupper((string) $skhpp->status), ['APPROVED', 'SELESAI', 'TERBIT'], true);
            return response()->json([
                'status' => 'success',
                'valid' => $isValid,
                'document_type' => 'SURAT KETERANGAN HASIL PENELITIAN PERSONEL (SKHPP)',
                'title' => 'SKHPP ' . ($skhpp->kategori_personel === 'perusahaan' ? 'MITRA KERJA' : 'PERSONEL'),
                'number' => $skhpp->nomor_skhpp ?? "SKHPP-{$skhpp->id}",
                'person_name' => $skhpp->nama,
                'pangkat_nrp' => $skhpp->pangkat_korps_nrp ?? '-',
                'signed_by' => 'KOMANDAN DETASEMEN INTELIJEN KOARMADA 2',
                'signed_at' => $skhpp->approved_at ? \Carbon\Carbon::parse($skhpp->approved_at)->format('d/m/Y H:i:s') : null,
                'verification_code' => $skhpp->verification_code,
                'message' => $isValid ? 'DOKUMEN SKHPP ASLI & TERVERIFIKASI' : 'DOKUMEN SKHPP MASIH DALAM PROSES PENELITIAN',
            ]);
        }

        return response()->json([
            'status' => 'invalid',
            'valid' => false,
            'message' => 'PERINGATAN: KODE TTE TIDAK TERDAFTAR DI DATABASE RESMI SINDEN.',
        ]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memverifikasi dokumen.');
    }
})->middleware('throttle:30,1');

// =====================================================================
// SESI, DASHBOARD, DAFTAR JAGA, PDF, NOTIFIKASI (v1.1.0)
// =====================================================================

Route::get('/api/mobile/me', [MobileApiController::class, 'me'])->middleware('mobile.auth');
Route::post('/api/mobile/logout', [MobileApiController::class, 'logout'])->middleware('mobile.auth');
Route::get('/api/mobile/dashboard', [MobileApiController::class, 'dashboard'])->middleware('mobile.auth:dashboard.view');

Route::get('/api/mobile/duty-roster', [MobileApiController::class, 'dutyRoster'])->middleware('mobile.auth:duty_roster.view');
Route::get('/api/mobile/pdf/sp-jaga/{id}', [MobileApiController::class, 'dutyRosterPdf'])->whereNumber('id')->middleware('mobile.auth:duty_roster.pdf');
Route::get('/api/mobile/pdf/letter-logs', [MobileApiController::class, 'letterLogsPdf'])->middleware('mobile.auth:letter_logs.pdf');
Route::get('/api/mobile/pdf/skhpp/{id}', [MobileApiController::class, 'skhppPdf'])->whereNumber('id')->middleware('mobile.auth:skhpp.pdf');
Route::get('/api/mobile/pdf/commander-account', [MobileApiController::class, 'commanderAccountPdf'])->middleware('mobile.auth:commander_account.pdf');

Route::get('/api/mobile/notifications', [MobileApiController::class, 'notifications'])->middleware('mobile.auth:notifications.view');
Route::post('/api/mobile/notifications/read-all', [MobileApiController::class, 'markAllNotificationsRead'])->middleware('mobile.auth:notifications.view');
Route::post('/api/mobile/notifications/{id}/read', [MobileApiController::class, 'markNotificationRead'])->whereNumber('id')->middleware('mobile.auth:notifications.view');

// =====================================================================
// 2. AGENDA SURAT (App\Models\LetterLog)
// =====================================================================
Route::get('/api/mobile/letter-logs', function (Request $request) {
    try {
        $query = \App\Models\LetterLog::with(['category', 'subCategory'])->latest('id');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('subject', 'like', "%{$s}%")
                  ->orWhere('sequence', 'like', "%{$s}%")
                  ->orWhere('full_number', 'like', "%{$s}%")
                  ->orWhere('recipient', 'like', "%{$s}%");
            });
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        $logs = $query->take(300)->get()->map(function ($l) {
            return [
                'id' => $l->id,
                'number' => is_numeric($l->sequence) ? (int) $l->sequence : 0,
                'sequence' => (string) ($l->sequence ?? '-'),
                'full_number' => $l->full_number ?? '-',
                'subject' => $l->subject ?? '-',
                'recipient' => $l->recipient ?? '-',
                'date' => $l->date ? \Carbon\Carbon::parse($l->date)->format('Y-m-d') : null,
                'category_id' => $l->category_id ?? 0,
                'category_name' => $l->category->name ?? 'Umum',
                'sub_category_name' => $l->subCategory->name ?? null,
                'is_archived' => (bool) ($l->is_archived ?? false),
            ];
        });

        return response()->json(['status' => 'success', 'data' => $logs, 'logs' => $logs]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memuat agenda surat.');
    }
})->middleware('mobile.auth:letter_logs.view');

Route::post('/api/mobile/letter-logs', function (Request $request) {
    try {
        $data = mobile_payload($request);
        $v = Validator::make($data, [
            'category_id' => 'required|integer|exists:categories,id',
            'subject' => 'required|string|max:500',
            'recipient' => 'required|string|max:255',
            'date' => 'required|date',
        ]);
        if ($v->fails()) return mobile_validation_error($v);

        $catId = (int) $data['category_id'];
        $category = \App\Models\Category::find($catId);
        $lastLog = \App\Models\LetterLog::where('category_id', $catId)->orderBy('id', 'desc')->first();
        $nextSeq = $lastLog ? ((int) $lastLog->sequence + 1) : ($category->start_number ?? 1);

        $log = \App\Models\LetterLog::create([
            'category_id' => $catId,
            'sequence' => (string) $nextSeq,
            'full_number' => "Surat No. {$nextSeq}",
            'subject' => $data['subject'],
            'recipient' => $data['recipient'],
            'date' => substr((string) $data['date'], 0, 10),
            'is_archived' => false,
        ]);

        return response()->json(['status' => 'success', 'message' => "Agenda surat nomor {$nextSeq} tercatat.", 'data' => $log]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal menyimpan agenda surat.');
    }
})->middleware('mobile.auth:letter_logs.create');

Route::put('/api/mobile/letter-logs/{id}', function (Request $request, $id) {
    try {
        $log = \App\Models\LetterLog::findOrFail($id);
        $data = mobile_payload($request);
        $v = Validator::make($data, [
            'subject' => 'nullable|string|max:500',
            'recipient' => 'nullable|string|max:255',
            'date' => 'nullable|date',
        ]);
        if ($v->fails()) return mobile_validation_error($v);
        $log->update([
            'subject' => $data['subject'] ?? $log->subject,
            'recipient' => $data['recipient'] ?? $log->recipient,
            'date' => isset($data['date']) ? substr((string) $data['date'], 0, 10) : $log->date,
        ]);
        return response()->json(['status' => 'success', 'message' => 'Agenda surat diperbarui.', 'data' => $log]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memperbarui agenda surat.');
    }
})->middleware('mobile.auth:letter_logs.update');

Route::delete('/api/mobile/letter-logs/{id}', function ($id) {
    try {
        \App\Models\LetterLog::findOrFail($id)->delete();
        return response()->json(['status' => 'success', 'message' => 'Agenda surat berhasil dihapus']);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal menghapus agenda surat.');
    }
})->middleware('mobile.auth:letter_logs.delete');

// =====================================================================
// 3. SKHPP (App\Models\Skhpp)
// =====================================================================
Route::get('/api/mobile/skhpp', function (Request $request) {
    try {
        $query = \App\Models\Skhpp::query()->latest('id');
        if ($request->filled('status')) {
            $query->where('status', strtolower($request->status));
        }
        if ($request->filled('category')) {
            $query->where('kategori_personel', $request->category);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('nama', 'like', "%{$s}%")
                  ->orWhere('nomor_skhpp', 'like', "%{$s}%")
                  ->orWhere('pangkat_korps_nrp', 'like', "%{$s}%");
            });
        }

        $skhpps = $query->take(300)->get()->map(function ($s) {
            return [
                'id' => $s->id,
                'no_skhpp' => $s->nomor_skhpp,
                'nomor_skhpp' => $s->nomor_skhpp,
                'nama' => $s->nama ?? '-',
                'pangkat_korps_nrp' => $s->pangkat_korps_nrp ?? '-',
                'jabatan' => $s->jabatan_pekerjaan ?? '-',
                'kesatuan' => 'Denintel Koarmada 2',
                'peruntukan' => $s->peruntukan ?? '-',
                'kategori' => $s->kategori_personel ?? 'militer',
                'status' => strtoupper($s->status ?? 'PENDING'),
                'barcode_string' => $s->verification_code,
                'tanggal_surat' => $s->tanggal_skhpp
                    ? \Carbon\Carbon::parse($s->tanggal_skhpp)->format('Y-m-d')
                    : optional($s->created_at)->format('Y-m-d'),
                'created_by' => $s->operator_name ?? '-',
            ];
        });

        return response()->json(['status' => 'success', 'data' => $skhpps, 'skhpps' => $skhpps]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memuat daftar SKHPP.');
    }
})->middleware('mobile.auth:skhpp.view');

// Pengajuan SKHPP: memakai SkhppController@store milik web (multipart: foto_1 wajib)
// sehingga nomor, kode verifikasi, antrean TTD Komandan, dan notifikasi tetap sama.
Route::post('/api/mobile/skhpp', function (Request $request) {
    return mobile_delegate(
        fn () => app(\App\Http\Controllers\SkhppController::class)->store($request),
        'Pengajuan SKHPP terkirim ke antrean TTD Komandan.'
    );
})->middleware(['mobile.auth:skhpp.create', 'throttle:10,1']);

// Persetujuan Komandan: SkhppController@approve (penomoran resmi + TTE).
Route::post('/api/mobile/skhpp/{id}/verify', function (Request $request, $id) {
    \App\Models\Skhpp::findOrFail($id);
    return mobile_delegate(
        fn () => app(\App\Http\Controllers\SkhppController::class)->approve($request, $id),
        'SKHPP disetujui.'
    );
})->whereNumber('id')->middleware('mobile.auth:skhpp.approve');

Route::post('/api/mobile/skhpp/{id}/reject', function (Request $request, $id) {
    \App\Models\Skhpp::findOrFail($id);
    return mobile_delegate(
        fn () => app(\App\Http\Controllers\SkhppController::class)->reject($request, $id),
        'SKHPP dikembalikan untuk revisi.'
    );
})->whereNumber('id')->middleware('mobile.auth:skhpp.approve');

Route::delete('/api/mobile/skhpp/{id}', function ($id) {
    \App\Models\Skhpp::findOrFail($id);
    return mobile_delegate(
        fn () => app(\App\Http\Controllers\SkhppController::class)->destroy($id),
        'SKHPP berhasil dihapus.'
    );
})->whereNumber('id')->middleware('mobile.auth:skhpp.delete');

// =====================================================================
// 4. REKENING KOMANDAN (App\Models\CommanderAccount)
// =====================================================================
Route::get('/api/mobile/commander-account', function (Request $request) {
    try {
        $query = \App\Models\CommanderAccount::query();
        if ($request->filled('month') && preg_match('/^(\d{4})-(\d{2})$/', $request->month, $m)) {
            $query->whereYear('tanggal', (int) $m[1])->whereMonth('tanggal', (int) $m[2]);
        }
        $logs = $query->latest('tanggal')->latest('id')->get();
        $totalMasuk = (float) \App\Models\CommanderAccount::where('jenis', 'MASUK')->sum('jumlah');
        $totalKeluar = (float) \App\Models\CommanderAccount::where('jenis', 'KELUAR')->sum('jumlah');

        return response()->json([
            'status' => 'success',
            'total_masuk' => $totalMasuk,
            'total_keluar' => $totalKeluar,
            'saldo_akhir' => $totalMasuk - $totalKeluar,
            'can_manage' => MobileAccess::can($request->user(), 'commander_account.manage'),
            'data' => $logs,
            'logs' => $logs,
        ]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memuat rekening komandan.');
    }
})->middleware('mobile.auth:commander_account.view');

Route::post('/api/mobile/commander-account', function (Request $request) {
    try {
        $data = mobile_payload($request);
        $v = Validator::make($data, [
            'tanggal' => 'required|date',
            'jenis' => 'required|in:MASUK,KELUAR,masuk,keluar',
            'jumlah' => 'required|numeric|min:1',
            'keterangan' => 'required|string|max:500',
        ]);
        if ($v->fails()) return mobile_validation_error($v);

        $tx = \App\Models\CommanderAccount::create([
            'tanggal' => substr((string) $data['tanggal'], 0, 10),
            'jenis' => strtoupper($data['jenis']),
            'jumlah' => (float) $data['jumlah'],
            'keterangan' => $data['keterangan'],
            'petugas_input' => $request->user()->name,
        ]);
        return response()->json(['status' => 'success', 'message' => 'Transaksi tersimpan.', 'data' => $tx]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal menyimpan transaksi rekening komandan.');
    }
})->middleware('mobile.auth:commander_account.manage');

// Ubah mutasi: memakai CommanderAccountController@update milik web (format & penanda "(EDIT)" sama).
Route::put('/api/mobile/commander-account/{id}', function (Request $request, $id) {
    \App\Models\CommanderAccount::findOrFail($id);
    $data = mobile_payload($request);
    if (isset($data['jenis'])) {
        $data['jenis'] = strtoupper((string) $data['jenis']);
    }
    if (isset($data['tanggal'])) {
        $data['tanggal'] = substr((string) $data['tanggal'], 0, 10);
    }
    $request->merge($data);
    return mobile_delegate(
        fn () => app(\App\Http\Controllers\CommanderAccountController::class)->update($request, $id),
        'Transaksi Rekening Komandan berhasil diperbarui.'
    );
})->whereNumber('id')->middleware('mobile.auth:commander_account.manage');

Route::delete('/api/mobile/commander-account/{id}', function ($id) {
    try {
        \App\Models\CommanderAccount::findOrFail($id)->delete();
        return response()->json(['status' => 'success', 'message' => 'Transaksi Rekening Komandan berhasil dihapus']);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal menghapus transaksi.');
    }
})->middleware('mobile.auth:commander_account.manage');

// =====================================================================
// 5. MITRA (App\Models\Mitra & App\Models\MitraPayment)
// =====================================================================
Route::get('/api/mobile/mitra', function (Request $request) {
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
            unset($mitra->payments);
            return $mitra;
        });

        return response()->json(['status' => 'success', 'tahun' => $tahun, 'data' => $mitras]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memuat data mitra.');
    }
})->middleware('mobile.auth:mitra.view');

$mitraRules = [
    'nama' => 'required|string|max:255',
    'pt' => 'nullable|string|max:255',
    'no_tlp' => 'nullable|string|max:50',
    'keterangan' => 'nullable|string|max:1000',
];

Route::post('/api/mobile/mitra', function (Request $request) use ($mitraRules) {
    try {
        $data = mobile_payload($request);
        $v = Validator::make($data, $mitraRules);
        if ($v->fails()) return mobile_validation_error($v);
        $maxSort = \App\Models\Mitra::max('sort_order') ?? 0;

        $mitra = \App\Models\Mitra::create([
            'nama' => $data['nama'],
            'pt' => $data['pt'] ?? null,
            'no_tlp' => $data['no_tlp'] ?? null,
            'keterangan' => $data['keterangan'] ?? null,
            'sort_order' => $maxSort + 1,
        ]);
        return response()->json(['status' => 'success', 'message' => 'Mitra tersimpan.', 'data' => $mitra]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal menyimpan mitra.');
    }
})->middleware('mobile.auth:mitra.manage');

Route::put('/api/mobile/mitra/{id}', function (Request $request, $id) use ($mitraRules) {
    try {
        $mitra = \App\Models\Mitra::findOrFail($id);
        $data = mobile_payload($request);
        $v = Validator::make($data, $mitraRules);
        if ($v->fails()) return mobile_validation_error($v);
        $mitra->update([
            'nama' => $data['nama'],
            'pt' => $data['pt'] ?? $mitra->pt,
            'no_tlp' => $data['no_tlp'] ?? $mitra->no_tlp,
            'keterangan' => $data['keterangan'] ?? $mitra->keterangan,
        ]);
        return response()->json(['status' => 'success', 'message' => 'Mitra diperbarui.', 'data' => $mitra->fresh()]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memperbarui mitra.');
    }
})->middleware('mobile.auth:mitra.manage');

Route::post('/api/mobile/mitra/{id}/toggle-payment', function (Request $request, $id) {
    try {
        \App\Models\Mitra::findOrFail($id);
        $data = mobile_payload($request);
        $v = Validator::make($data, [
            'tahun' => 'required|integer|min:2000|max:2100',
            'bulan' => 'required|integer|min:1|max:12',
        ]);
        if ($v->fails()) return mobile_validation_error($v);

        $payment = \App\Models\MitraPayment::firstOrNew([
            'mitra_id' => $id,
            'tahun' => (int) $data['tahun'],
            'bulan' => (int) $data['bulan'],
        ]);
        $payment->is_paid = $payment->exists ? !$payment->is_paid : true;
        $payment->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Status pembayaran mitra berhasil diperbarui',
            'is_paid' => (bool) $payment->is_paid,
        ]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memperbarui status pembayaran.');
    }
})->middleware('mobile.auth:mitra.manage');

Route::delete('/api/mobile/mitra/{id}', function ($id) {
    try {
        \App\Models\Mitra::findOrFail($id)->delete();
        return response()->json(['status' => 'success', 'message' => 'Mitra berhasil dihapus']);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal menghapus mitra.');
    }
})->middleware('mobile.auth:mitra.manage');

// =====================================================================
// 6. CATATAN PELANGGARAN PRAJURIT (App\Models\SoldierViolation)
//    Kolom mengikuti tabel asli: rank, position, incident_date, case_development.
// =====================================================================
$violationStatuses = ['PROSES', 'SIDANG', 'SELESAI', 'BANDING', 'DINAS_KEMBALI', 'PDTH'];

Route::get('/api/mobile/violations', function (Request $request) {
    try {
        $query = \App\Models\SoldierViolation::query();
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('nrp', 'like', "%{$s}%")
                  ->orWhere('case_description', 'like', "%{$s}%");
            });
        }
        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->status));
        }
        $violations = $query->latest()->take(200)->get()->map(fn ($v) => [
            'id' => $v->id,
            'name' => $v->name,
            'nrp' => $v->nrp,
            'rank' => $v->rank,
            'position' => $v->position,
            'unit' => $v->unit,
            'case_description' => $v->case_description,
            'incident_date' => $v->incident_date ? substr((string) $v->incident_date, 0, 10) : null,
            'case_development' => $v->case_development,
            'status' => $v->status,
            'created_at' => optional($v->created_at)->toIso8601String(),
        ]);

        return response()->json([
            'status' => 'success',
            'stats' => [
                'total' => \App\Models\SoldierViolation::count(),
                'proses' => \App\Models\SoldierViolation::where('status', 'PROSES')->count(),
                'selesai' => \App\Models\SoldierViolation::where('status', 'SELESAI')->count(),
            ],
            'data' => $violations,
            'violations' => $violations,
        ]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memuat data pelanggaran.');
    }
})->middleware('mobile.auth:violations.view');

Route::post('/api/mobile/violations', function (Request $request) use ($violationStatuses) {
    try {
        $data = mobile_payload($request);
        $v = Validator::make($data, [
            'nama' => 'required|string|max:255',
            'nrp' => 'required|string|max:50|unique:soldier_violations,nrp',
            'pangkat' => 'required|string|max:100',
            'jabatan' => 'required|string|max:255',
            'satuan' => 'required|string|max:255',
            'kasus' => 'required|string',
            'tmt' => 'required|date',
            'perkembangan_kasus' => 'required|string',
            'status' => ['required', Rule::in($violationStatuses)],
        ]);
        if ($v->fails()) return mobile_validation_error($v);

        $timestamp = now()->format('d/m/Y H:i');
        $violation = \App\Models\SoldierViolation::create([
            'name' => strtoupper($data['nama']),
            'nrp' => $data['nrp'],
            'rank' => $data['pangkat'],
            'position' => $data['jabatan'],
            'unit' => $data['satuan'],
            'case_description' => $data['kasus'],
            'incident_date' => $data['tmt'],
            'case_development' => "[{$timestamp}] AWAL: " . $data['perkembangan_kasus'],
            'status' => $data['status'],
            'lampiran_berkas' => json_encode([[
                'id' => uniqid(),
                'tanggal' => $timestamp,
                'catatan' => 'REGISTRASI AWAL: ' . $data['perkembangan_kasus'],
                'file_paths' => [],
            ]]),
        ]);
        return response()->json(['status' => 'success', 'message' => 'Catatan pelanggaran tersimpan.', 'data' => $violation]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal mencatat pelanggaran.');
    }
})->middleware('mobile.auth:violations.manage');

Route::post('/api/mobile/violations/{id}/progress', function (Request $request, $id) use ($violationStatuses) {
    try {
        $violation = \App\Models\SoldierViolation::findOrFail($id);
        $data = mobile_payload($request);
        $v = Validator::make($data, [
            'note' => 'required|string|max:2000',
            'status' => ['nullable', Rule::in($violationStatuses)],
        ]);
        if ($v->fails()) return mobile_validation_error($v);

        $timestamp = now()->format('d/m/Y H:i');
        $violation->update([
            'case_development' => trim(($violation->case_development ? $violation->case_development . "\n" : '') . "[{$timestamp}] " . $data['note']),
            'status' => $data['status'] ?? $violation->status,
        ]);
        return response()->json(['status' => 'success', 'message' => 'Perkembangan kasus berhasil dicatat', 'data' => $violation]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal mencatat perkembangan kasus.');
    }
})->middleware('mobile.auth:violations.manage');

Route::put('/api/mobile/violations/{id}', function (Request $request, $id) use ($violationStatuses) {
    try {
        $violation = \App\Models\SoldierViolation::findOrFail($id);
        $data = mobile_payload($request);
        $v = Validator::make($data, ['status' => ['required', Rule::in($violationStatuses)]]);
        if ($v->fails()) return mobile_validation_error($v);
        $violation->update(['status' => $data['status']]);
        return response()->json(['status' => 'success', 'data' => $violation]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memperbarui status pelanggaran.');
    }
})->middleware('mobile.auth:violations.manage');

Route::delete('/api/mobile/violations/{id}', function ($id) {
    try {
        \App\Models\SoldierViolation::findOrFail($id)->delete();
        return response()->json(['status' => 'success', 'message' => 'Catatan pelanggaran berhasil dihapus']);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal menghapus catatan pelanggaran.');
    }
})->middleware('mobile.auth:violations.manage');

// =====================================================================
// 7. KELOLA PENGGUNA (khusus Admin, sama seperti /users di web)
// =====================================================================
Route::get('/api/mobile/users', function (Request $request) {
    try {
        $users = User::visibleTo($request->user())->latest()->get()->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'nrp' => $u->nrp ?? '-',
            'pangkat' => $u->pangkat ?? '',
            'korps' => $u->korps ?? '',
            'role' => $u->role ?? 'user',
            'jabatan' => $u->jabatan ?? '',
            'phone' => $u->phone,
            'is_active' => (bool) ($u->is_active ?? false),
            'can_access_mitra' => (bool) ($u->can_access_mitra ?? false),
        ]);
        return response()->json(['status' => 'success', 'data' => $users, 'users' => $users]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memuat daftar pengguna.');
    }
})->middleware('mobile.auth:users.view');

Route::post('/api/mobile/users', function (Request $request) {
    try {
        $data = mobile_payload($request);
        $v = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'nrp' => 'required|string|max:50|unique:users,nrp',
            'pangkat' => 'nullable|string|max:100',
            'role' => ['required', Rule::in(MobileAccess::KNOWN_ROLES)],
            'password' => 'required|string|min:8|max:255',
        ]);
        if ($v->fails()) return mobile_validation_error($v);
        if (in_array(strtolower(str_replace([' ', '-'], '_', $data['role'])), ['super_admin', 'superadmin'], true) && !$request->user()->isSuperAdmin()) {
            return response()->json(['status' => 'error', 'message' => 'Hanya Super Admin yang dapat membuat akun Super Admin.'], 403);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'nrp' => $data['nrp'],
            'pangkat' => $data['pangkat'] ?? null,
            'role' => $data['role'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);
        return response()->json(['status' => 'success', 'message' => 'Pengguna dibuat.', 'data' => MobileAccess::profile($user)]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal membuat pengguna.');
    }
})->middleware('mobile.auth:users.manage');

Route::put('/api/mobile/users/{id}', function (Request $request, $id) {
    try {
        $target = User::findOrFail($id);
        if (!$target->isManageableBy($request->user())) {
            return response()->json(['status' => 'error', 'message' => 'Akun Super Admin hanya dapat dikelola oleh Super Admin.'], 403);
        }
        $data = mobile_payload($request);
        $v = Validator::make($data, ['is_active' => 'required|boolean']);
        if ($v->fails()) return mobile_validation_error($v);
        if ((int) $target->id === (int) $request->user()->id && !$data['is_active']) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak dapat menonaktifkan akun sendiri.'], 422);
        }
        $target->update(['is_active' => (bool) $data['is_active']]);
        if (!$target->is_active) {
            $target->tokens()->delete(); // putuskan sesi mobile yang aktif
        }
        return response()->json(['status' => 'success', 'message' => 'Status pengguna diperbarui.', 'data' => MobileAccess::profile($target)]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memperbarui pengguna.');
    }
})->middleware('mobile.auth:users.manage');

Route::delete('/api/mobile/users/{id}', function (Request $request, $id) {
    try {
        $target = User::findOrFail($id);
        if ((int) $target->id === (int) $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak dapat menghapus akun sendiri.'], 422);
        }
        if (!$target->isManageableBy($request->user())) {
            return response()->json(['status' => 'error', 'message' => 'Akun Super Admin hanya dapat dikelola oleh Super Admin.'], 403);
        }
        $target->tokens()->delete();
        $target->delete();
        return response()->json(['status' => 'success', 'message' => 'Pengguna berhasil dihapus']);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal menghapus pengguna.');
    }
})->middleware('mobile.auth:users.manage');

// =====================================================================
// 8. TTE TANDA TANGAN DIGITAL (App\Models\SignatureRequest)
// =====================================================================
Route::get('/api/mobile/signature-requests', function (Request $request) {
    try {
        $user = $request->user();
        $sigQuery = \App\Models\SignatureRequest::with('user:id,name,pangkat,nrp')->latest();
        $skhppQuery = \App\Models\Skhpp::where('status', 'pending')->latest();
        // Sama dengan SignatureRequestController@index: non-pejabat hanya melihat miliknya.
        if (!MobileAccess::can($user, 'signature.approve')) {
            $sigQuery->where('user_id', $user->id);
            $skhppQuery->where('submitted_by', $user->id);
        }
        $requests = $sigQuery->take(100)->get()->map(fn ($s) => [
            'id' => $s->id,
            'subject' => $s->subject ?? $s->document_title ?? 'Dokumen',
            'document_title' => $s->document_title ?? $s->subject,
            'letter_number' => $s->letter_number,
            'person_name' => $s->person_name ?? $s->user?->name,
            'requester_name' => $s->user?->name ?? $s->person_name,
            'peruntukan' => $s->peruntukan,
            'status' => $s->status,
            'note' => $s->note,
            'verification_code' => $s->verification_code,
            'created_at' => optional($s->created_at)->toIso8601String(),
        ]);
        $skhpps = $skhppQuery->take(100)->get(['id', 'nama', 'nomor_skhpp', 'peruntukan', 'status', 'created_at']);

        return response()->json([
            'status' => 'success',
            'can_approve' => MobileAccess::can($user, 'signature.approve'),
            'requests' => $requests,
            'skhpp_requests' => $skhpps,
            'data' => $requests,
        ]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memuat permohonan tanda tangan.');
    }
})->middleware('mobile.auth:signature.view');

// Pengesahan / penolakan: SignatureRequestController@update milik web
// (stempel QR TTE dibubuhkan ke PDF persis seperti di web).
$signatureDecision = function (Request $request, $id, string $status) {
    $sig = \App\Models\SignatureRequest::findOrFail($id);
    $request->merge(['status' => $status]);
    return mobile_delegate(
        fn () => app(\App\Http\Controllers\SignatureRequestController::class)->update($request, $sig),
        $status === 'approved' ? 'Dokumen berhasil disahkan.' : 'Dokumen dikembalikan.'
    );
};

Route::post('/api/mobile/signature-requests/{id}/sign', fn (Request $request, $id) => $signatureDecision($request, $id, 'approved'))
    ->whereNumber('id')->middleware('mobile.auth:signature.approve');
Route::post('/api/mobile/signature-requests/{id}/sign-custom', fn (Request $request, $id) => $signatureDecision($request, $id, 'approved'))
    ->whereNumber('id')->middleware('mobile.auth:signature.approve');
Route::post('/api/mobile/signature-requests/{id}/reject', fn (Request $request, $id) => $signatureDecision($request, $id, 'rejected'))
    ->whereNumber('id')->middleware('mobile.auth:signature.approve');

// =====================================================================
// 9. DRAF NASKAH DINAS & 10. KATEGORI
// =====================================================================
Route::get('/api/mobile/letters', function () {
    try {
        $letters = \App\Models\Letter::with(['category', 'subCategory'])->latest()->take(100)->get();
        return response()->json(['status' => 'success', 'data' => $letters, 'letters' => $letters]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memuat naskah dinas.');
    }
})->middleware('mobile.auth:letters.view');

Route::post('/api/mobile/letters', function (Request $request) {
    try {
        $data = mobile_payload($request);
        $v = Validator::make($data, [
            'subject' => 'required|string|max:500',
            'category_id' => 'required|integer|exists:categories,id',
            'recipient' => 'required|string|max:255',
            'date' => 'required|date',
            'letter_number' => 'nullable|string|max:255',
        ]);
        if ($v->fails()) return mobile_validation_error($v);
        $letter = \App\Models\Letter::create([
            'letter_number' => $data['letter_number'] ?? ('DRAF/' . date('YmdHis')),
            'subject' => $data['subject'],
            'category_id' => $data['category_id'],
            'recipient' => $data['recipient'],
            'date' => $data['date'],
        ]);
        return response()->json(['status' => 'success', 'data' => $letter]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal menyimpan naskah dinas.');
    }
})->middleware('mobile.auth:letters.create');

Route::get('/api/mobile/categories', function () {
    try {
        $categories = \App\Models\Category::with('subCategories')->orderBy('name')->get();
        return response()->json(['status' => 'success', 'data' => $categories]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memuat kategori surat.');
    }
})->middleware('mobile.auth:categories.view');

// =====================================================================
// 11. LOG PENGUNJUNG & AUDIT (Admin)
// =====================================================================
Route::get('/api/mobile/audit-logs', function (Request $request) {
    try {
        $visitors = User::hideSuperAdminRows(\App\Models\VisitorLog::query(), $request->user())->latest()->take(50)->get();
        $audits = User::hideSuperAdminRows(\App\Models\AuditLog::query(), $request->user())->latest()->take(50)->get();
        return response()->json(['status' => 'success', 'visitor_logs' => $visitors, 'audit_logs' => $audits, 'data' => $visitors]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memuat log audit.');
    }
})->middleware('mobile.auth:audit_logs.view');

// =====================================================================
// 12. BACKUP (ringkasan Admin) & PENGAJUAN AKSES
// =====================================================================
Route::get('/api/mobile/backup', function () {
    try {
        $pcs = \App\Models\Pc::with('user:id,name,pangkat,nrp')->latest()->get()->map(fn ($pc) => [
            'id' => $pc->id,
            'name' => $pc->name ?? $pc->pc_name ?? 'PC Kedinasan',
            'user_name' => $pc->user?->name ?? '-',
            'status' => $pc->is_online ? 'ONLINE' : 'OFFLINE',
            'last_backup' => $pc->last_backup_at ? \Carbon\Carbon::parse($pc->last_backup_at)->format('d/m/Y H:i') : null,
        ]);
        $requests = \App\Models\AccessRequest::with('user:id,name')->latest()->take(20)->get()->map(fn ($r) => [
            'id' => $r->id,
            'user_name' => $r->user?->name ?? '-',
            'pc_name' => $r->pc_name,
            'status' => strtoupper($r->status ?? 'PENDING'),
            'created_at' => optional($r->created_at)->format('d/m/Y H:i'),
        ]);
        return response()->json([
            'status' => 'success',
            'pcs' => $pcs,
            'access_requests' => $requests,
            'stats' => ['total_pcs' => \App\Models\Pc::count()],
        ]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memuat data backup.');
    }
})->middleware('mobile.auth:backup.view');

Route::post('/api/mobile/backup/request-access', function (Request $request) {
    try {
        $req = \App\Models\AccessRequest::create([
            'user_id' => $request->user()->id,
            'pc_name' => 'PC-' . strtoupper(\Illuminate\Support\Str::random(5)),
            'status' => 'pending',
        ]);
        return response()->json(['status' => 'success', 'message' => 'Pengajuan akses backup PC berhasil dikirim ke Admin.', 'data' => $req]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal mengirim pengajuan akses.');
    }
})->middleware(['mobile.auth', 'throttle:5,1']);

// =====================================================================
// 14. RADAR KEGIATAN LAPANGAN
// =====================================================================
Route::post('/api/mobile/activities', function (Request $request) {
    try {
        $data = mobile_payload($request);
        $v = Validator::make($data, [
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'location_name' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'activity_date' => 'required|date',
            'description' => 'required|string',
        ]);
        if ($v->fails()) return mobile_validation_error($v);
        $act = \App\Models\CommunityActivity::create(array_merge(
            $v->validated(),
            ['user_id' => $request->user()->id]
        ));
        return response()->json(['status' => 'success', 'data' => $act]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal menyimpan kegiatan.');
    }
})->middleware('mobile.auth:activities.manage');

Route::delete('/api/mobile/activities/{id}', function ($id) {
    try {
        \App\Models\CommunityActivity::findOrFail($id)->delete();
        return response()->json(['status' => 'success', 'message' => 'Kegiatan berhasil dihapus']);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal menghapus kegiatan.');
    }
})->middleware('mobile.auth:activities.manage');

// =====================================================================
// 15. PRESENSI KEHADIRAN (identitas HANYA dari token)
// =====================================================================
Route::get('/api/mobile/attendances', function (Request $request) {
    try {
        $user = $request->user();
        $today = now()->toDateString();

        return response()->json([
            'status' => 'success',
            'today_attendance' => \App\Models\Attendance::where('user_id', $user->id)->whereDate('attendance_date', $today)->first(),
            'attendances' => \App\Models\Attendance::where('user_id', $user->id)->orderBy('attendance_date', 'desc')->take(30)->get(),
            'total_attended_today' => \App\Models\Attendance::whereDate('attendance_date', $today)->count(),
            'total_active_personnel' => User::where('is_active', true)->count(),
        ]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memuat data presensi.');
    }
})->middleware('mobile.auth:attendance.view');

Route::post('/api/mobile/attendances', function (Request $request) {
    try {
        $user = $request->user();
        $data = mobile_payload($request);
        $v = Validator::make($data, [
            'status' => 'required|in:hadir,dinas_luar,piket,izin',
            // Lokasi GPS & foto senyum wajib, sama seperti presensi di web.
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'location_name' => 'nullable|string|max:255',
            'device_model' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:1000',
            'photo' => 'required|string',
            'smile_score' => 'nullable|integer|between:0,100',
        ], [
            'latitude.required' => 'Lokasi GPS wajib. Aktifkan GPS dan izinkan akses lokasi.',
            'longitude.required' => 'Lokasi GPS wajib. Aktifkan GPS dan izinkan akses lokasi.',
            'photo.required' => 'Foto verifikasi senyum wajib diambil sebelum presensi.',
        ]);
        if ($v->fails()) return mobile_validation_error($v);

        $today = now()->toDateString();
        $timeNow = now()->format('H:i:s');
        $lat = (float) $data['latitude'];
        $lng = (float) $data['longitude'];
        $locName = $data['location_name'] ?? null;
        if (empty($locName)) {
            $locName = "GPS: {$lat}, {$lng}";
        }

        // Gambar di-encode ulang (anti polyglot / payload tersisip) sebelum disimpan.
        $photoPath = null;
        $cleanPhoto = \App\Support\SafeImage::fromDataUrl($data['photo']);
        if ($cleanPhoto !== null) {
            $photoPath = 'attendances/presensi_' . $user->id . '_' . date('Ymd_His') . '_' . \Illuminate\Support\Str::random(12) . '.jpg';
            \Illuminate\Support\Facades\Storage::disk('public')->put($photoPath, $cleanPhoto);
        }
        if ($photoPath === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Foto verifikasi senyum tidak valid. Silakan ambil ulang foto.',
                'errors' => ['photo' => ['Foto verifikasi senyum tidak valid.']],
            ], 422);
        }

        $saveData = [
            'time_in' => $timeNow,
            'status' => $data['status'],
            'ip_address' => $request->ip(),
            'device' => $data['device_model'] ?? 'Android Mobile',
            'user_agent' => $request->userAgent() ?: 'Sinden 2 Android App',
            'latitude' => $lat,
            'longitude' => $lng,
            'location_name' => $locName,
            'notes' => $data['notes'] ?? null,
            'photo' => $photoPath,
            'smile_score' => isset($data['smile_score']) ? (int) $data['smile_score'] : null,
        ];

        $attendance = \App\Models\Attendance::updateOrCreate(
            ['user_id' => $user->id, 'attendance_date' => $today],
            $saveData
        );

        // Titik presensi sekaligus memperbarui posisi personel di peta pemantauan.
        \App\Support\PersonnelTracking::record($user, [[
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => $data['accuracy'] ?? null,
            'provider' => 'attendance',
        ]], $data['device_model'] ?? null);

        return response()->json([
            'status' => 'success',
            'message' => "Presensi ({$data['status']}) tercatat pukul {$timeNow} WIB.",
            'attendance' => $attendance,
        ]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal mencatat presensi.');
    }
})->middleware(['mobile.auth:attendance.create', 'throttle:20,1']);

// =====================================================================
// CHAT DINAS, PANGGILAN VIDEO (AGORA), PRATINJAU TTE, TTE SP JAGA
// =====================================================================
Route::middleware('mobile.auth:chat.view')->group(function () {
    Route::get('/api/mobile/chat', [MobileCommsController::class, 'index']);
    Route::get('/api/mobile/chat/sync', [MobileCommsController::class, 'sync']);
    Route::get('/api/mobile/chat/unread', [MobileCommsController::class, 'unread']);
    Route::get('/api/mobile/chat/incoming-call', [MobileCommsController::class, 'incomingCall']);
    Route::get('/api/mobile/chat/{uuid}/messages', [MobileCommsController::class, 'messages']);
    Route::post('/api/mobile/chat/{uuid}/send', [MobileCommsController::class, 'send'])->middleware('throttle:60,1');
    Route::post('/api/mobile/chat/{uuid}/typing', [MobileCommsController::class, 'typing'])->middleware('throttle:60,1');
    Route::post('/api/mobile/chat/{uuid}/call/initiate', [MobileCommsController::class, 'initiateCall'])->middleware('throttle:20,1');
    Route::get('/api/mobile/chat/{uuid}/call/signal', [MobileCommsController::class, 'callSignal']);
    Route::post('/api/mobile/chat/{uuid}/call/signal', [MobileCommsController::class, 'sendCallSignal']);
    Route::post('/api/mobile/chat/{uuid}/call/end', [MobileCommsController::class, 'endCall']);
    Route::get('/api/mobile/chat/{uuid}/call/agora-token', [MobileCommsController::class, 'agoraToken']);

    // Chat antar-personel: siapa pun dapat mencari kontak & memulai chat pribadi.
    Route::get('/api/mobile/chat/users', [MobileCommsController::class, 'searchUsers']);
    Route::post('/api/mobile/chat/start', [MobileCommsController::class, 'start'])->middleware('throttle:30,1');
    // Hapus: peserta chat pribadi, atau operator untuk utas layanan (dicek di controller).
    Route::delete('/api/mobile/chat/{uuid}', [MobileCommsController::class, 'destroy']);
});

Route::middleware('mobile.auth:chat.manage')->group(function () {
    Route::post('/api/mobile/chat/{uuid}/status', [MobileCommsController::class, 'toggleStatus']);
});

// Halaman media vicon untuk WebView (tanpa data; token Agora diberikan oleh aplikasi).
Route::get('/mobile/vicon', [MobileCommsController::class, 'viconPage'])->middleware('throttle:60,1');

// SDK Agora Web (npm agora-rtc-sdk-ng, MIT) disajikan dari server sendiri agar vicon tidak
// bergantung pada CDN Agora yang kadang lambat/terblokir di jaringan tertentu.
Route::get('/mobile/agora-sdk', function () {
    $path = base_path('node_modules/agora-rtc-sdk-ng/AgoraRTC_N-production.js');
    abort_unless(is_file($path), 404);
    return response()->file($path, [
        'Content-Type' => 'application/javascript; charset=utf-8',
        'Cache-Control' => 'public, max-age=604800',
    ]);
})->middleware('throttle:120,1');

Route::get('/api/mobile/pdf/signature/{id}', [MobileCommsController::class, 'signaturePdf'])
    ->whereNumber('id')->middleware('mobile.auth:signature.view');

Route::get('/api/mobile/duty-roster/pending', [MobileCommsController::class, 'pendingSpJaga'])->middleware('mobile.auth:duty_roster.sign');
Route::post('/api/mobile/duty-roster/{id}/approve', [MobileCommsController::class, 'approveSpJaga'])
    ->whereNumber('id')->middleware('mobile.auth:duty_roster.sign');

// =====================================================================
// 16. PELACAKAN LOKASI REALTIME PERSONEL
// =====================================================================
// Aplikasi mengirim titik lokasi berkala (atau sekumpulan titik yang tertunda
// saat offline). Identitas personel HANYA dari token.
Route::post('/api/mobile/location', function (Request $request) {
    try {
        $data = mobile_payload($request);
        $points = isset($data['points']) && is_array($data['points']) ? $data['points'] : [$data];
        $v = Validator::make(['points' => $points, 'device_model' => $data['device_model'] ?? null], [
            'points' => 'required|array|min:1|max:200',
            'points.*.latitude' => 'required|numeric|between:-90,90',
            'points.*.longitude' => 'required|numeric|between:-180,180',
            'points.*.accuracy' => 'nullable|numeric|min:0',
            'points.*.speed' => 'nullable|numeric|min:0',
            'points.*.heading' => 'nullable|numeric|between:0,360',
            'points.*.battery' => 'nullable|integer|between:0,100',
            'points.*.is_charging' => 'nullable|boolean',
            'points.*.provider' => 'nullable|string|max:30',
            'points.*.recorded_at' => 'nullable|date',
            'device_model' => 'nullable|string|max:150',
        ]);
        if ($v->fails()) return mobile_validation_error($v);

        $location = \App\Support\PersonnelTracking::record($request->user(), $points, $data['device_model'] ?? null);

        return response()->json([
            'status' => 'success',
            'accepted' => count($points),
            'recorded_at' => $location?->recorded_at?->toIso8601String(),
        ]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal menyimpan lokasi.');
    }
})->middleware(['mobile.auth:location.report', 'throttle:120,1']);

Route::get('/api/mobile/tracking', function (Request $request) {
    try {
        $personnel = \App\Support\PersonnelTracking::snapshot($request->user());
        return response()->json([
            'status' => 'success',
            'server_time' => now()->toIso8601String(),
            'online_threshold_seconds' => \App\Support\PersonnelTracking::ONLINE_SECONDS,
            'total_online' => count(array_filter($personnel, fn ($p) => $p['is_online'])),
            'personnel' => $personnel,
        ]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memuat posisi personel.');
    }
})->middleware('mobile.auth:tracking.view');

Route::get('/api/mobile/tracking/{userId}/history', function (Request $request, int $userId) {
    try {
        if (!\App\Support\PersonnelTracking::canViewHistory($request->user(), $userId)) {
            return response()->json(['status' => 'error', 'message' => 'Jejak lokasi Super Admin hanya dapat dilihat oleh Super Admin.'], 403);
        }
        return response()->json([
            'status' => 'success',
            'user_id' => $userId,
            'points' => \App\Support\PersonnelTracking::history($userId, (int) $request->query('hours', 12)),
        ]);
    } catch (\Throwable $e) {
        return mobile_error($e, 'Gagal memuat jejak lokasi.');
    }
})->whereNumber('userId')->middleware('mobile.auth:tracking.view');

// =====================================================================
// NOTIFIKASI PUSH (FIREBASE CLOUD MESSAGING) â€” v1.5
// =====================================================================
Route::post('/api/mobile/push/register', function (Request $request) {
    $data = $request->validate([
        'token' => 'required|string|min:20|max:4096',
        'app_version' => 'nullable|string|max:20',
        'device_name' => 'nullable|string|max:100',
    ]);
    if (!\Illuminate\Support\Facades\Schema::hasTable('device_tokens')) {
        return response()->json(['status' => 'error', 'message' => 'Server belum diperbarui (php artisan migrate).'], 503);
    }
    \App\Models\DeviceToken::register($request->user()->id, $data['token'], $data['app_version'] ?? null, $data['device_name'] ?? null);
    return response()->json(['status' => 'success', 'push_enabled' => \App\Support\PushNotifier::enabled()]);
})->middleware(['mobile.auth', 'throttle:30,1']);

Route::post('/api/mobile/push/unregister', function (Request $request) {
    $data = $request->validate(['token' => 'required|string|max:4096']);
    if (\Illuminate\Support\Facades\Schema::hasTable('device_tokens')) {
        \App\Models\DeviceToken::where('user_id', $request->user()->id)
            ->where('token_hash', \App\Models\DeviceToken::hashOf($data['token']))->delete();
    }
    return response()->json(['status' => 'success']);
})->middleware(['mobile.auth', 'throttle:30,1']);

// Uji notifikasi push ke perangkat milik pengguna sendiri (menu Pengaturan aplikasi).
Route::post('/api/mobile/push/test', function (Request $request) {
    $user = $request->user();
    $push = \App\Support\PushNotifier::class;
    if (!$push::clientConfig() || !\App\Support\PushNotifier::serviceAccountProject()) {
        return response()->json(['status' => 'error', 'code' => 'push_not_configured',
            'message' => 'Notifikasi push belum diatur Admin (Pengaturan web â†’ Notifikasi Push Aplikasi).']);
    }
    if (!\App\Support\PushNotifier::enabled()) {
        return response()->json(['status' => 'error', 'code' => 'push_disabled', 'message' => 'Notifikasi push dinonaktifkan Admin.']);
    }
    $devices = \Illuminate\Support\Facades\Schema::hasTable('device_tokens')
        ? \App\Models\DeviceToken::where('user_id', $user->id)->count() : 0;
    if ($devices === 0) {
        return response()->json(['status' => 'error', 'code' => 'no_device',
            'message' => 'HP ini belum terdaftar untuk notifikasi push. Buka ulang aplikasi lalu coba lagi.']);
    }
    $sent = \App\Support\PushNotifier::sendNow([$user->id], [
        'type' => 'notification',
        'id' => (string) (900000000 + random_int(1, 99999999)),
        'title' => 'Uji notifikasi Sinden 2',
        'body' => 'Notifikasi push berhasil diterima di HP ini.',
        'link' => '/notifications',
    ], 300);
    return response()->json($sent > 0
        ? ['status' => 'success', 'message' => "Terkirim ke {$sent} perangkat. Notifikasi akan muncul dalam beberapa detik."]
        : ['status' => 'error', 'code' => 'send_failed', 'message' => 'Server gagal mengirim ke Firebase. Periksa service account & google-services.json di Pengaturan web (lihat log server [PUSH]).']);
})->middleware(['mobile.auth', 'throttle:10,1']);
