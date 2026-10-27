<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\CommanderAccountController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\LetterLogController;
use App\Http\Controllers\SkhppController;
use App\Http\Controllers\SpJagaController;
use App\Models\AppNotification;
use App\Models\Attendance;
use App\Models\LetterLog;
use App\Models\SignatureRequest;
use App\Models\Skhpp;
use App\Models\SpJaga;
use App\Models\User;
use App\Support\DutyRoster;
use App\Support\MobileAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoint mobile baru (v1.1.0). Semua route di sini dilindungi middleware
 * `mobile.auth` sehingga $request->user() selalu berisi pengguna token.
 */
class MobileApiController extends Controller
{
    /** Identitas, role, dan permission resmi pengguna yang sedang login. */
    public function me(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'user' => MobileAccess::sessionPayload($request->user()),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /** Cabut token yang sedang dipakai (logout aman di sisi server). */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['status' => 'success', 'message' => 'Anda telah keluar.']);
    }

    /**
     * Ringkasan dashboard sesuai hak akses. Modul yang tidak diizinkan tidak
     * dikirim sama sekali (bukan dikirim lalu disembunyikan di klien).
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $perm = MobileAccess::permissions($user);
        $today = now()->toDateString();

        $todayAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('attendance_date', $today)->first();

        $summary = [
            'attendance' => [
                'today' => $todayAttendance,
                'total_attended_today' => Attendance::whereDate('attendance_date', $today)->count(),
                'total_active_personnel' => User::where('is_active', true)->count(),
            ],
            'notifications_unread' => $this->notificationQuery($user)->where('is_read', false)->count(),
        ];

        if ($perm['letter_logs.view']) {
            $summary['letter_logs_total'] = LetterLog::count();
            $summary['letter_logs_this_month'] = LetterLog::whereYear('date', now()->year)
                ->whereMonth('date', now()->month)->count();
        }
        if ($perm['skhpp.view']) {
            $q = Skhpp::query()->where('status', 'pending');
            if (!$perm['skhpp.approve']) {
                $q->where('submitted_by', $user->id);
            }
            $summary['skhpp_pending'] = $q->count();
        }
        if ($perm['signature.approve']) {
            $summary['signature_pending'] = SignatureRequest::whereNotIn(
                \Illuminate\Support\Facades\DB::raw('LOWER(status)'),
                ['approved', 'signed', 'selesai', 'rejected', 'ditolak']
            )->count();
        }

        // Daftar jaga: SP periode berjalan + periode berikutnya (untuk jadwal mendatang).
        $duty = $this->dutyForDashboard($user);

        return response()->json([
            'status' => 'success',
            'server_time' => now()->toIso8601String(),
            'user' => MobileAccess::sessionPayload($user),
            'summary' => $summary,
            'duty' => $duty,
        ]);
    }

    private function dutyForDashboard(User $user): array
    {
        $today = now()->toDateString();
        $current = DutyRoster::resolve($user, null, (int) now()->format('n'), (int) now()->format('Y'));
        $next = DutyRoster::resolve($user, null, (int) now()->addMonthNoOverflow()->format('n'), (int) now()->addMonthNoOverflow()->format('Y'));

        $days = [];
        $myDates = [];
        $sources = [];
        foreach (array_filter([$current, $next]) as $sp) {
            $built = DutyRoster::build($sp, $user);
            $sources[] = DutyRoster::summary($sp);
            foreach ($built['days'] as $d) {
                $d['sp_id'] = $sp->id;
                $days[] = $d;
            }
            $myDates = array_merge($myDates, $built['my_dates']);
        }

        $todayEntry = collect($days)->firstWhere('date', $today);
        $upcoming = collect($days)->filter(fn ($d) => $d['date'] > $today)->take(5)->values()->all();
        $myNext = collect($myDates)->sort()->first(fn ($d) => $d >= $today);

        return [
            'sources' => $sources,
            'today' => $todayEntry,
            'upcoming' => $upcoming,
            'my_next_date' => $myNext,
            'my_dates' => array_values(array_filter($myDates, fn ($d) => $d >= $today)),
        ];
    }

    /** Daftar jaga lengkap untuk satu SP (by id atau bulan/tahun). */
    public function dutyRoster(Request $request)
    {
        $data = $request->validate([
            'sp_id' => 'nullable|integer',
            'bulan' => 'nullable|integer|min:1|max:12',
            'tahun' => 'nullable|integer|min:2000|max:2100',
        ]);
        $user = $request->user();

        $sp = DutyRoster::resolve($user, $data['sp_id'] ?? null, $data['bulan'] ?? null, $data['tahun'] ?? null);

        $available = DutyRoster::visibleQuery($user)
            ->orderBy('tahun', 'desc')->orderBy('bulan', 'desc')->orderBy('id', 'desc')
            ->limit(24)->get()
            ->map(fn (SpJaga $s) => DutyRoster::summary($s))->values();

        if (!$sp) {
            return response()->json([
                'status' => 'success',
                'sp' => null,
                'days' => [],
                'my_dates' => [],
                'my_roles' => [],
                'available' => $available,
            ]);
        }

        $built = DutyRoster::build($sp, $user);

        return response()->json(array_merge([
            'status' => 'success',
            'sp' => DutyRoster::summary($sp),
            'available' => $available,
        ], $built));
    }

    /** PDF resmi SP Jaga — memakai template yang sama dengan web. */
    public function dutyRosterPdf(Request $request, int $id)
    {
        $sp = SpJaga::findOrFail($id);
        if (!DutyRoster::canSee($request->user(), $sp)) {
            return response()->json(['status' => 'error', 'code' => 'forbidden', 'message' => 'SP Jaga belum diterbitkan.'], 403);
        }
        return $this->pdfResponse(fn () => app(SpJagaController::class)->downloadPdf($id));
    }

    public function letterLogsPdf(Request $request)
    {
        $request->validate([
            'category_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);
        return $this->pdfResponse(fn () => app(LetterLogController::class)->downloadPdf($request));
    }

    public function skhppPdf(int $id)
    {
        Skhpp::findOrFail($id);
        // Permission `skhpp.pdf` ditegakkan oleh middleware route (paritas dengan web).
        return $this->pdfResponse(fn () => app(SkhppController::class)->exportPdf($id));
    }

    public function commanderAccountPdf(Request $request)
    {
        $request->validate(['month' => ['nullable', 'regex:/^\d{4}-\d{2}$/']]);
        return $this->pdfResponse(fn () => app(CommanderAccountController::class)->exportPdf($request));
    }

    /**
     * Pastikan respons benar-benar PDF; kegagalan dikembalikan sebagai JSON
     * yang dapat dibaca aplikasi (bukan halaman HTML).
     */
    private function pdfResponse(callable $make)
    {
        try {
            $response = $make();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage() ?: 'Akses ditolak.'], $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('[MOBILE_PDF] ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal membuat dokumen PDF.'], 500);
        }

        if ($response instanceof Response) {
            $type = (string) $response->headers->get('Content-Type');
            if ($response->isSuccessful() && !str_contains($type, 'pdf')) {
                return response()->json(['status' => 'error', 'message' => 'Server tidak mengembalikan berkas PDF.'], 502);
            }
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }

    // ------------------------------------------------------------------
    // Notifikasi (dibatasi untuk pengguna/role terkait, sama seperti web)
    // ------------------------------------------------------------------

    private function notificationQuery(User $user)
    {
        return AppNotification::query()->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere(function ($q2) use ($user) {
                  $q2->whereNull('user_id')->where(function ($q3) use ($user) {
                      $q3->where('role', $user->role)->orWhereNull('role');
                  });
              });
        });
    }

    public function notifications(Request $request)
    {
        $user = $request->user();
        $notifs = $this->notificationQuery($user)->latest()->take(50)->get()->map(fn ($n) => [
            'id' => $n->id,
            'title' => $n->title ?? 'Notifikasi SINDEN',
            'message' => $n->message ?? '',
            'type' => $n->type ?? 'info',
            'link' => $n->link,
            'is_read' => (bool) $n->is_read,
            'created_at' => optional($n->created_at)->toIso8601String(),
        ]);

        return response()->json([
            'status' => 'success',
            'notifications' => $notifs,
            'unreadCount' => $this->notificationQuery($user)->where('is_read', false)->count(),
        ]);
    }

    public function markNotificationRead(Request $request, int $id)
    {
        $notif = $this->notificationQuery($request->user())->where('id', $id)->firstOrFail();
        $notif->update(['is_read' => true]);
        return response()->json(['status' => 'success']);
    }

    public function markAllNotificationsRead(Request $request)
    {
        $this->notificationQuery($request->user())->where('is_read', false)->update(['is_read' => true]);
        return response()->json(['status' => 'success']);
    }
}
