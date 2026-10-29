<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditTrailController extends Controller
{
    /**
     * Endpoint API Mengambil Linimasa Jejak Audit Berkas
     */
    public function getTrail(Request $request)
    {
        $target = trim($request->input('target', ''));

        if (empty($target)) {
            return response()->json(['trails' => []]);
        }

        $logs = \App\Models\User::hideSuperAdminRows(AuditLog::with('user'), $request->user())
            ->where(function ($q) use ($target) {
                $q->where('target_personnel', 'like', "%{$target}%")
                  ->orWhere('description', 'like', "%{$target}%");
            })
            ->latest('id')
            ->take(30)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'admin_name' => $log->admin_name ?: ($log->user ? $log->user->name : 'Sistem'),
                    'description' => $log->description,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at ? $log->created_at->isoFormat('D MMMM Y, HH:mm') : '-',
                    'created_at_raw' => $log->created_at,
                ];
            });

        return response()->json([
            'target' => $target,
            'trails' => $logs,
        ]);
    }
}
