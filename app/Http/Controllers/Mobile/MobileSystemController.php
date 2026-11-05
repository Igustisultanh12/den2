<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Services\SystemCheckService;
use Illuminate\Http\Request;

/**
 * Cek Sistem & Server untuk aplikasi Android — data yang sama dengan halaman web
 * /admin/system-check (khusus Super Admin, dijaga middleware mobile.auth:system.view).
 */
class MobileSystemController extends Controller
{
    public function metrics(Request $request)
    {
        return response()->json(SystemCheckService::getAllMetrics());
    }

    public function speedtest(Request $request)
    {
        @set_time_limit(90);
        $result = SystemCheckService::runOoklaSpeedtest();

        return response()->json($result, ($result['status'] ?? 'success') === 'error' ? 422 : 200);
    }
}
