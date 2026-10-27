<?php

namespace App\Http\Controllers;

use App\Models\CommanderAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;// Pastikan package PDF terpasang aman seperti di kas buku kas

class CommanderAccountController extends Controller
{
    /**
     * Memeriksa hak akses logistik keuangan Komandan
     */
    private function checkAccess()
    {
        $user = Auth::user();
        // Komandan terdeteksi bypass nama khusus atau role khusus
        $isCommander = $user->name === 'I Gusti Sultan H.A, A.Md.Kom' || $user->role === 'komandan';
        $isAdminOrSuma = $user->isAdmin() || $user->role === 'user';

        if (!$isCommander && !$isAdminOrSuma) {
            abort(403, 'Anda tidak memiliki otoritas mengakses rekening Komandan.');
        }

        return [
            'canEdit' => $isAdminOrSuma,
            'user' => $user
        ];
    }

    public function index(Request $request)
    {
        $access = $this->checkAccess();
        $month = $request->query('month'); // format YYYY-MM
        
        // Ambil daftar bulan yang tersedia dari database transaksi
        $availableMonths = CommanderAccount::selectRaw("DISTINCT DATE_FORMAT(tanggal, '%Y-%m') as ym")
            ->orderBy('ym', 'desc')
            ->pluck('ym')
            ->toArray();

        $currentYm = date('Y-m');
        if (!in_array($currentYm, $availableMonths)) {
            array_unshift($availableMonths, $currentYm);
        }

        $saldoAwal = 0;
        $totalMasukKeseluruhan = (float) CommanderAccount::where('jenis', 'MASUK')->sum('jumlah');
        $totalKeluarKeseluruhan = (float) CommanderAccount::where('jenis', 'KELUAR')->sum('jumlah');
        $saldoKeseluruhan = $totalMasukKeseluruhan - $totalKeluarKeseluruhan;

        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $carbon = \Carbon\Carbon::parse($month . '-01');
            $startOfMonth = $carbon->copy()->startOfMonth()->toDateString();
            $endOfMonth = $carbon->copy()->endOfMonth()->toDateString();

            // 1. Hitung Saldo Awal (sebelum awal bulan yang dipilih)
            $prevMasuk = (float) CommanderAccount::where('tanggal', '<', $startOfMonth)->where('jenis', 'MASUK')->sum('jumlah');
            $prevKeluar = (float) CommanderAccount::where('tanggal', '<', $startOfMonth)->where('jenis', 'KELUAR')->sum('jumlah');
            $saldoAwal = $prevMasuk - $prevKeluar;

            // 2. Ambil transaksi bulan ini secara kronologis untuk kalkulasi saldo berjalan
            $periodLogs = CommanderAccount::whereBetween('tanggal', [$startOfMonth, $endOfMonth])
                ->orderBy('tanggal', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $totalMasukPeriode = (float) CommanderAccount::whereBetween('tanggal', [$startOfMonth, $endOfMonth])->where('jenis', 'MASUK')->sum('jumlah');
            $totalKeluarPeriode = (float) CommanderAccount::whereBetween('tanggal', [$startOfMonth, $endOfMonth])->where('jenis', 'KELUAR')->sum('jumlah');

            $runningBalance = $saldoAwal;
            $mappedLogs = [];
            foreach ($periodLogs as $item) {
                if ($item->jenis === 'MASUK') {
                    $runningBalance += (float) $item->jumlah;
                } else {
                    $runningBalance -= (float) $item->jumlah;
                }
                $item->saldo_berjalan = $runningBalance;
                $mappedLogs[] = $item;
            }

            // Tampilkan transaksi terbaru di atas (descending) untuk kenyamanan user di antarmuka web
            $logs = array_reverse($mappedLogs);
            $totalMasuk = $totalMasukPeriode;
            $totalKeluar = $totalKeluarPeriode;
            $saldoAkhir = $saldoAwal + $totalMasukPeriode - $totalKeluarPeriode;
        } else {
            // Semua Periode (All Time)
            $allLogs = CommanderAccount::orderBy('tanggal', 'asc')->orderBy('id', 'asc')->get();
            $runningBalance = 0;
            $mappedLogs = [];
            foreach ($allLogs as $item) {
                if ($item->jenis === 'MASUK') {
                    $runningBalance += (float) $item->jumlah;
                } else {
                    $runningBalance -= (float) $item->jumlah;
                }
                $item->saldo_berjalan = $runningBalance;
                $mappedLogs[] = $item;
            }

            $logs = array_reverse($mappedLogs);
            $totalMasuk = $totalMasukKeseluruhan;
            $totalKeluar = $totalKeluarKeseluruhan;
            $saldoAkhir = $saldoKeseluruhan;
        }

        return Inertia::render('CommanderAccount/Index', [
            'logs' => $logs,
            'canEdit' => $access['canEdit'],
            'available_months' => $availableMonths,
            'filters' => [
                'month' => $month ?? '',
                'search' => $request->query('search', ''),
            ],
            'stats' => [
                'saldo_awal' => $saldoAwal,
                'total_masuk' => $totalMasuk,
                'total_keluar' => $totalKeluar,
                'saldo_akhir' => $saldoAkhir,
                'saldo_keseluruhan' => $saldoKeseluruhan,
                'is_monthly' => !empty($month),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $access = $this->checkAccess();
        if (!$access['canEdit']) abort(403, 'Akses ditolak.');

        $request->validate([
            'tanggal' => 'required|date',
            'keterangan' => 'required|string|max:255',
            'jenis' => 'required|in:MASUK,KELUAR',
            'jumlah' => 'required|numeric|min:0',
        ]);

        CommanderAccount::create([
            'tanggal' => $request->tanggal,
            'keterangan' => strtoupper($request->keterangan),
            'jenis' => $request->jenis,
            'jumlah' => $request->jumlah,
            'petugas_input' => $access['user']->name,
        ]);

        return back()->with('success', 'Lapor! Mutasi rekening Komandan berhasil dicatat.');
    }

    public function update(Request $request, $id)
    {
        $access = $this->checkAccess();
        if (!$access['canEdit']) abort(403, 'Akses ditolak.');

        $request->validate([
            'tanggal' => 'required|date',
            'keterangan' => 'required|string|max:255',
            'jenis' => 'required|in:MASUK,KELUAR',
            'jumlah' => 'required|numeric|min:0',
        ]);

        $log = CommanderAccount::findOrFail($id);
        $log->update([
            'tanggal' => $request->tanggal,
            'keterangan' => strtoupper($request->keterangan),
            'jenis' => $request->jenis,
            'jumlah' => $request->jumlah,
            'petugas_input' => $access['user']->name . ' (EDIT)',
        ]);

        return back()->with('success', 'Lapor! Identitas transaksi berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $access = $this->checkAccess();
        if (!$access['canEdit']) abort(403, 'Akses ditolak.');

        CommanderAccount::destroy($id);
        return back()->with('success', 'Lapor! Transaksi telah dihapus dari log pangkalan.');
    }

    public function exportPdf(Request $request)
    {
        $this->checkAccess();
        $month = $request->query('month');
        
        $saldoAwal = 0;
        $periodLabel = 'Semua Periode Transaksi';
        $periodDates = null;
        
        $monthsIndo = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $carbon = \Carbon\Carbon::parse($month . '-01');
            $startOfMonth = $carbon->copy()->startOfMonth()->toDateString();
            $endOfMonth = $carbon->copy()->endOfMonth()->toDateString();
            
            $periodLabel = strtoupper($monthsIndo[(int)$carbon->format('n')] . ' ' . $carbon->format('Y'));
            $periodDates = '01 ' . $monthsIndo[(int)$carbon->format('n')] . ' ' . $carbon->format('Y') . ' s/d ' . $carbon->copy()->endOfMonth()->format('d') . ' ' . $monthsIndo[(int)$carbon->format('n')] . ' ' . $carbon->format('Y');

            // Saldo Awal sebelum bulan ini
            $prevMasuk = (float) CommanderAccount::where('tanggal', '<', $startOfMonth)->where('jenis', 'MASUK')->sum('jumlah');
            $prevKeluar = (float) CommanderAccount::where('tanggal', '<', $startOfMonth)->where('jenis', 'KELUAR')->sum('jumlah');
            $saldoAwal = $prevMasuk - $prevKeluar;

            $rawLogs = CommanderAccount::whereBetween('tanggal', [$startOfMonth, $endOfMonth])
                ->orderBy('tanggal', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $totalMasuk = (float) CommanderAccount::whereBetween('tanggal', [$startOfMonth, $endOfMonth])->where('jenis', 'MASUK')->sum('jumlah');
            $totalKeluar = (float) CommanderAccount::whereBetween('tanggal', [$startOfMonth, $endOfMonth])->where('jenis', 'KELUAR')->sum('jumlah');
        } else {
            $rawLogs = CommanderAccount::orderBy('tanggal', 'asc')->orderBy('id', 'asc')->get();
            $totalMasuk = (float) CommanderAccount::where('jenis', 'MASUK')->sum('jumlah');
            $totalKeluar = (float) CommanderAccount::where('jenis', 'KELUAR')->sum('jumlah');
            $periodDates = 'Keseluruhan Log Mutasi';
        }

        // Kalkulasi Saldo Berjalan (Running Balance) untuk tiap baris
        $runningBalance = $saldoAwal;
        $logs = [];
        $countMasuk = 0;
        $countKeluar = 0;

        foreach ($rawLogs as $item) {
            if ($item->jenis === 'MASUK') {
                $runningBalance += (float) $item->jumlah;
                $countMasuk++;
            } else {
                $runningBalance -= (float) $item->jumlah;
                $countKeluar++;
            }
            $item->saldo_berjalan = $runningBalance;
            $logs[] = $item;
        }

        $saldoAkhir = $saldoAwal + $totalMasuk - $totalKeluar;
        $accountNo = 'SINDEN-KMD-01';
        $accountHolder = 'KOMANDAN DETASEMEN INTELIJEN';
        
        $pdf = PDF::loadView('pdf.commander_cash', compact(
            'logs', 'totalMasuk', 'totalKeluar', 'saldoAwal', 'saldoAkhir', 
            'periodLabel', 'periodDates', 'month', 'accountNo', 'accountHolder',
            'countMasuk', 'countKeluar'
        ))->setPaper('a4', 'portrait');

        $filename = 'REKENING_KORAN_KOMANDAN_' . ($month ? str_replace('-', '', $month) : date('Ymd_His')) . '.pdf';
        return $pdf->stream($filename);
    }
}