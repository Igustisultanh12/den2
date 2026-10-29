<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\MitraPayment;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MitraPaymentController extends Controller
{
    /**
     * Tampilkan Halaman Utama Matriks Pencatatan Pembayaran Mitra
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Otoritas Akses: Admin, Developer, atau can_access_mitra == true
        $allowedNames = [
            'I Gusti Sultan H.A, A.Md.Kom'
        ];

        $canAccess = $user->isAdmin() 
            || in_array($user->name, $allowedNames) 
            || (bool)$user->can_access_mitra;

        if (!$canAccess) {
            return redirect()->route('dashboard')->with('error', 'Akses ditolak! Anda tidak memiliki otoritas melihat Modul Pembayaran Mitra.');
        }

        $tahun = (int) $request->input('tahun', date('Y'));

        // Query seluruh mitra diurutkan berdasarkan sort_order lalu id
        $mitras = Mitra::with(['payments' => function ($q) use ($tahun) {
            $q->where('tahun', $tahun);
        }])
        ->orderBy('sort_order', 'asc')
        ->orderBy('id', 'asc')
        ->get()
        ->map(function ($mitra) {
            // Petakan matriks bulan 1..12
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

        // Daftar tahun yang tersedia (misal dari 2024 s/d 2030)
        $availableYears = range(2024, (int)date('Y') + 2);

        return Inertia::render('Mitra/Index', [
            'mitras' => $mitras,
            'currentYear' => $tahun,
            'availableYears' => array_values($availableYears),
            'canEdit' => $canAccess,
        ]);
    }

    /**
     * Tambah Data Mitra Baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'pt' => 'nullable|string|max:255',
            'no_tlp' => 'nullable|string|max:100',
            'keterangan' => 'nullable|string',
        ]);

        $maxSort = Mitra::max('sort_order') ?? 0;

        Mitra::create([
            'nama' => $request->nama,
            'pt' => $request->pt,
            'no_tlp' => $request->no_tlp,
            'keterangan' => $request->keterangan,
            'sort_order' => $maxSort + 1,
        ]);

        return redirect()->back()->with('success', 'Data Mitra berhasil ditambahkan!');
    }

    /**
     * Update Data Mitra
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'pt' => 'nullable|string|max:255',
            'no_tlp' => 'nullable|string|max:100',
            'keterangan' => 'nullable|string',
        ]);

        $mitra = Mitra::findOrFail($id);
        $mitra->update([
            'nama' => $request->nama,
            'pt' => $request->pt,
            'no_tlp' => $request->no_tlp,
            'keterangan' => $request->keterangan,
        ]);

        return redirect()->back()->with('success', 'Data Mitra berhasil diperbarui!');
    }

    /**
     * Hapus Data Mitra
     */
    public function destroy($id)
    {
        $mitra = Mitra::findOrFail($id);
        $mitra->delete();

        return redirect()->back()->with('success', 'Data Mitra berhasil dihapus!');
    }

    /**
     * Toggle Checkbox Pembayaran Mitra per Bulan
     */
    public function togglePayment(Request $request)
    {
        $request->validate([
            'mitra_id' => 'required|exists:mitras,id',
            'tahun' => 'required|integer',
            'bulan' => 'required|integer|between:1,12',
            'is_paid' => 'required|boolean',
        ]);

        MitraPayment::updateOrCreate(
            [
                'mitra_id' => $request->mitra_id,
                'tahun' => $request->tahun,
                'bulan' => $request->bulan,
            ],
            [
                'is_paid' => $request->is_paid,
                'updated_by' => auth()->user()->name,
            ]
        );

        return redirect()->back()->with('success', 'Status pembayaran berhasil disinkronkan.');
    }

    /**
     * Geser / Reorder Urutan Mitra (Naik / Turun atau Masif)
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|exists:mitras,id',
            'orders.*.sort_order' => 'required|integer',
        ]);

        foreach ($request->orders as $item) {
            Mitra::where('id', $item['id'])->update([
                'sort_order' => $item['sort_order']
            ]);
        }

        return redirect()->back()->with('success', 'Urutan baris mitra berhasil diperbarui!');
    }

    /**
     * Pindah Posisi Mitra Ke Atas / Ke Bawah (1 Langkah)
     */
    public function movePosition(Request $request, $id)
    {
        $request->validate([
            'direction' => 'required|in:up,down',
        ]);

        $current = Mitra::findOrFail($id);
        $direction = $request->direction;

        if ($direction === 'up') {
            $neighbor = Mitra::where('sort_order', '<', $current->sort_order)
                ->orderBy('sort_order', 'desc')
                ->first();
        } else {
            $neighbor = Mitra::where('sort_order', '>', $current->sort_order)
                ->orderBy('sort_order', 'asc')
                ->first();
        }

        if ($neighbor) {
            $temp = $current->sort_order;
            $current->sort_order = $neighbor->sort_order;
            $neighbor->sort_order = $temp;

            $current->save();
            $neighbor->save();
        }

        return redirect()->back();
    }

    /**
     * Toggle Akses Khusus Modul Mitra untuk User Tertentu (Admin Only)
     */
    public function toggleUserAccess(Request $request, $userId)
    {
        if (!auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Hanya Admin yang dapat mengubah hak akses pengguna.');
        }

        $targetUser = User::findOrFail($userId);
        abort_unless($targetUser->isManageableBy(auth()->user()), 403, 'Akun Super Admin hanya dapat dikelola oleh Super Admin.');
        $targetUser->can_access_mitra = !$targetUser->can_access_mitra;
        $targetUser->save();

        return redirect()->back()->with('success', 'Hak akses Modul Mitra untuk ' . $targetUser->name . ' berhasil diperbarui.');
    }
}
