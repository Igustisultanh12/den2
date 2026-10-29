<?php

namespace App\Http\Controllers;

use App\Support\MobileAccess;
use App\Support\PersonnelTracking;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Halaman web "Pantau Personel": peta posisi realtime personel yang dikirim
 * aplikasi Android. Hanya Admin, Super Admin, dan Komandan.
 */
class PersonnelTrackingController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeViewer($request);

        return Inertia::render('Tracking/Index', [
            'personnel' => PersonnelTracking::snapshot($request->user()),
            'onlineThresholdSeconds' => PersonnelTracking::ONLINE_SECONDS,
        ]);
    }

    /** Data JSON yang dipoll halaman peta setiap beberapa detik. */
    public function data(Request $request)
    {
        $this->authorizeViewer($request);

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'personnel' => PersonnelTracking::snapshot($request->user()),
        ]);
    }

    public function history(Request $request, int $userId)
    {
        $this->authorizeViewer($request);
        abort_unless(PersonnelTracking::canViewHistory($request->user(), $userId), 403, 'Jejak lokasi Super Admin hanya dapat dilihat oleh Super Admin.');

        return response()->json([
            'user_id' => $userId,
            'points' => PersonnelTracking::history($userId, (int) $request->query('hours', 12)),
        ]);
    }

    private function authorizeViewer(Request $request): void
    {
        abort_unless(MobileAccess::canViewTracking($request->user()), 403, 'Hanya Admin, Super Admin, dan Komandan yang dapat memantau posisi personel.');
    }
}
