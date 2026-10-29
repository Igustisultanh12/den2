<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VisitorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class VisitorLogController extends Controller
{
    /**
     * Menampilkan daftar riwayat pengunjung dengan fitur pencarian yang lebih cerdas.
     */
    public function index(Request $request)
    {
        $logs = User::hideSuperAdminRows(VisitorLog::with('user'), $request->user())
            // PERBAIKAN: Pencarian yang mencakup tabel visitor_logs DAN tabel users
            ->when($request->search, function ($query, $search) {
                $query->where(function($q) use ($search) {
                    $q->where('user_name', 'like', "%{$search}%")
                      ->orWhere('nrp', 'like', "%{$search}%")
                      ->orWhere('ip_address', 'like', "%{$search}%")
                      // Cari juga berdasarkan nama di tabel users jika user_name di log kosong
                      ->orWhereHas('user', function($userQuery) use ($search) {
                          $userQuery->where('name', 'like', "%{$search}%")
                                    ->orWhere('nrp', 'like', "%{$search}%");
                      });
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(function ($log) {
                // SINKRONISASI LABEL GPS (Agar tombol di Vue Aktif)
                $hasCoords = !empty($log->latitude) && !empty($log->longitude);
                
                if ($hasCoords) {
                    // Gunakan format standar agar seragam
                    $log->location = "GPS: " . $log->latitude . ", " . $log->longitude;
                }

                // Prioritaskan nama dari tabel User (Data terbaru/Pangkat terbaru)
                $log->user_name = $log->user->name ?? $log->user_name ?? 'Tamu / Guest';
                
                return $log;
            });

        return Inertia::render('VisitorLogs/Index', [
            'logs' => $logs,
            // Mengirim balik input search agar kotak pencarian di Vue tidak kosong setelah di-enter
            'filters' => $request->only(['search']) 
        ]);
    }

    /**
     * Menghapus baris log tertentu.
     */
    public function destroy($id)
    {
        try {
            // Log Super Admin tidak terlihat (dan tidak dapat dihapus) oleh role di bawahnya.
            $log = User::hideSuperAdminRows(VisitorLog::query(), auth()->user())->findOrFail($id);
            $log->delete();
            return redirect()->back()->with('message', 'Data log berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Sistem gagal menghapus data');
        }
    }

    /**
     * Membersihkan seluruh riwayat log secara aman (TRUNCATE).
     */
    public function clearAll()
    {
        // Selain Super Admin, hanya log yang terlihat olehnya yang ikut dibersihkan.
        if (!auth()->user()?->isSuperAdmin()) {
            User::hideSuperAdminRows(VisitorLog::query(), auth()->user())->delete();
            return redirect()->back()->with('success', 'Lapor! Seluruh riwayat log telah berhasil dihapus.');
        }

        try {
            Schema::disableForeignKeyConstraints();
            VisitorLog::truncate();
            Schema::enableForeignKeyConstraints();

            return redirect()->back()->with('success', 'Lapor! Seluruh riwayat log telah berhasil dihapus.');
        } catch (\Exception $e) {
            // Fallback jika truncate dilarang oleh database
            VisitorLog::query()->delete();
            return redirect()->back()->with('success', 'Lapor! Seluruh riwayat log telah berhasil dihapus.');
        }
    }
    
    public function clearLogs()
    {
        return $this->clearAll();
    }
}