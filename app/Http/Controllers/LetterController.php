<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\Category;
use App\Models\LetterLog;
use App\Models\AuditLog; // Tambahkan ini untuk Audit Log
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class LetterController extends Controller
{
    /**
     * Tampilan daftar arsip digital (Letters)
     */
    public function index(Request $request)
    {
        // Arsip SKHPP dipisah ke menu Arsip & Nomor SKHPP.
        $categories = Category::withoutSkhpp()->get();

        // Tambahkan fitur Pencarian agar Bapak mudah mencari arsip
        $letters = Letter::withoutSkhpp()->with(['category', 'subCategory'])
            ->when($request->search, function ($query, $search) {
                $query->where(fn ($q) => $q->where('subject', 'like', "%{$search}%")
                      ->orWhere('letter_number', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(50)
            ->withQueryString(); 

        return Inertia::render('Letters/Index', [
            'letters' => $letters,
            'categories' => $categories,
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Form tambah arsip
     */
    public function create(Request $request)
    {
        $selectedLog = null;
        if ($request->has('log_id')) {
            $selectedLog = LetterLog::with('category')->find($request->log_id);
        }

        return Inertia::render('Letters/Create', [
            'selectedLog' => $selectedLog,
            'categories' => Category::withoutSkhpp()->with('subCategories')->get(),
            'letterCategories' => Category::withoutSkhpp()->where('is_telegram', false)->with('subCategories')->get(),
            'telegramCategories' => Category::withoutSkhpp()->where('is_telegram', true)->get()
        ]);
    }

    /**
     * Proses simpan dari Form
     */
    public function store(Request $request)
    {
        $request->validate([
            'type'            => 'required|in:masuk,keluar,telegram',
            'letter_number'   => 'required|string',
            'category_id'     => 'required|exists:categories,id',
            'subject'         => 'required|string|max:255',
            'date'            => 'required|date',
            'file'            => 'required|mimes:pdf|max:10240', 
            'letter_log_id'   => 'nullable|exists:letter_logs,id',
        ]);

        if (Category::find($request->category_id)?->isSkhpp()) {
            return back()->with('error', 'PDF SKHPP diarsipkan melalui menu Arsip & Nomor SKHPP, bukan Arsip Surat.');
        }

        try {
            DB::beginTransaction();

            $filePath = $request->file('file')->store('archives', 'public');

            $letter = Letter::create([
                'type'            => $request->type,
                'security_level'  => $request->security_level,
                'category_id'     => $request->category_id,
                'sub_category_id' => $request->sub_category_id,
                'letter_number'   => $request->letter_number,
                'subject'         => $request->subject,
                'date'            => $request->date,
                'issuer'          => $request->issuer,
                'file_path'       => $filePath,
            ]);

            if ($request->filled('letter_log_id')) {
                LetterLog::where('id', $request->letter_log_id)->update(['is_archived' => true]);
            }

            // AUDIT LOG: Catat aktivitas simpan arsip
            AuditLog::create([
                'user_id' => auth()->id(),
                'admin_name' => auth()->user()->name,
                'action' => 'ARSIP_SURAT',
                'target_personnel' => $request->letter_number,
                'description' => "Menyimpan arsip baru: " . $request->subject . " (No: " . $request->letter_number . ")",
                'ip_address' => $request->ip(),
            ]);

            DB::commit();
            return redirect()->route('letters.index')->with('success', 'Arsip berhasil disimpan.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /**
     * Simpan PDF Langsung dari baris tabel Buku Nomor
     */
    public function storeDirect(Request $request)
    {
        $request->validate([
            'letter_log_id' => 'required|exists:letter_logs,id',
            'file'          => 'required|mimes:pdf|max:20480', // Saya naikkan limit ke 20MB jika file scan tebal
        ]);

        try {
            DB::beginTransaction();

            $log = LetterLog::with('category')->findOrFail($request->letter_log_id);
            if ($log->category?->isSkhpp()) {
                DB::rollBack();
                return back()->with('error', 'PDF SKHPP diarsipkan melalui menu Arsip & Nomor SKHPP, bukan Arsip Surat.');
            }
            $filePath = $request->file('file')->store('archives', 'public');

            Letter::create([
                'type'            => 'keluar',
                'category_id'     => $log->category_id,
                'letter_number'   => $log->full_number,
                'subject'         => $log->subject,
                'date'            => $log->date,
                'file_path'       => $filePath,
                'security_level'  => $log->priority === 'B' ? 'biasa' : 'rahasia',
                'issuer'          => 'Internal Denintel',
            ]);

            $log->update(['is_archived' => true]);

            // AUDIT LOG: Direct Upload
            AuditLog::create([
                'user_id' => auth()->id(),
                'admin_name' => auth()->user()->name,
                'action' => 'DIRECT_UPLOAD',
                'target_personnel' => $log->full_number,
                'description' => "Unggah PDF langsung untuk No: " . $log->full_number,
                'ip_address' => $request->ip(),
            ]);

            DB::commit();
            return back()->with('success', 'File PDF berhasil diunggah.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memproses: ' . $e->getMessage());
        }
    }

    /**
     * Update data arsip
     */
    public function update(Request $request, Letter $letter)
    {
        $data = $request->validate([
            'letter_number'   => 'required|string',
            'category_id'     => 'required|exists:categories,id',
            'subject'         => 'required|string|max:255',
            'date'            => 'required|date',
            'issuer'          => 'nullable|string',
            'security_level'  => 'required|string',
        ]);

        try {
            $oldSubject = $letter->subject;
            $letter->update($data);

            // AUDIT LOG: Perubahan data
            AuditLog::create([
                'user_id' => auth()->id(),
                'admin_name' => auth()->user()->name,
                'action' => 'UPDATE_ARSIP',
                'target_personnel' => $letter->letter_number,
                'description' => "Mengubah data arsip " . $oldSubject . " menjadi " . $letter->subject,
                'ip_address' => $request->ip(),
            ]);

            return back()->with('success', 'Data diperbarui.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal update: ' . $e->getMessage());
        }
    }

    /**
     * Hapus arsip
     */
    public function destroy(Letter $letter)
    {
        try {
            $target = $letter->subject . " (" . $letter->letter_number . ")";
            
            if ($letter->file_path && Storage::disk('public')->exists($letter->file_path)) {
                Storage::disk('public')->delete($letter->file_path);
            }
            
            // Cek apakah arsip ini berasal dari Buku Nomor, jika ya, kembalikan statusnya
            LetterLog::where('full_number', $letter->letter_number)->update(['is_archived' => false]);

            $letter->delete();

            // AUDIT LOG: Penghapusan Arsip
            AuditLog::create([
                'user_id' => auth()->id(),
                'admin_name' => auth()->user()->name,
                'action' => 'HAPUS_ARSIP',
                'target_personnel' => $letter->letter_number,
                'description' => "Menghapus berkas fisik & data arsip: " . $target,
                'ip_address' => request()->ip(),
            ]);

            return redirect()->route('letters.index')->with('success', 'Arsip berhasil dihapus');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus: ' . $e->getMessage());
        }
    }
}