<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Carbon\Carbon;

class ResetPasswordController extends Controller
{
    /**
     * Menampilkan halaman input token reset password custom.
     */
    public function showResetForm()
    {
        return Inertia::render('Auth/ResetPasswordCustom');
    }

    /**
     * Proses Verifikasi Token & Reset Password dengan validasi 5 menit.
     */
    public function store(Request $request)
    {
        // 1. Validasi Input Awal
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required', 
            'password' => 'required|confirmed|min:8',
        ], [
            'email.exists' => 'Email dinas tidak terdaftar di sistem.',
            'token.required' => 'Token 6-digit wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal harus 8 karakter.',
        ]);

        $user = User::where('email', $request->email)->first();

        // 2. CEK VALIDITAS TOKEN
        // Menggunakan trim() untuk membuang spasi tak sengaja (sering terjadi di HP)
        // Menggunakan casting (string) untuk memastikan perbandingan karakter murni
        $inputToken = trim((string)$request->token);
        $dbToken = $user->reset_token ? trim((string)$user->reset_token) : null;

        if (is_null($dbToken) || !hash_equals($dbToken, $inputToken)) {
            return back()->withErrors([
                'token' => 'Otoritas Gagal: Token salah atau sudah tidak berlaku.'
            ]);
        }

        // 3. CEK MASA BERLAKU TOKEN (5 Menit)
        // Pastikan APP_TIMEZONE di .env sudah Asia/Jakarta
        if ($user->token_expires_at) {
            $expiry = Carbon::parse($user->token_expires_at);
            
            if (now()->greaterThan($expiry)) {
                return back()->withErrors([
                    'token' => 'Otoritas Gagal: Token sudah hangus (melewati batas 5 menit). Silahkan hubungi Admin.'
                ]);
            }
        } else {
            return back()->withErrors(['token' => 'Otoritas Gagal: Token tidak valid atau sesi telah berakhir.']);
        }

        // 4. PROSES UPDATE PASSWORD
        // Gunakan Hash::make untuk enkripsi standar Laravel
        $user->update([
            'password' => Hash::make($request->password),
            'reset_token' => null, // Segera hapus token agar tidak bisa dipakai ulang
            'token_expires_at' => null,
        ]);

        // Opsional: Catat aksi ini ke Audit Log jika Bapak ingin memantau siapa yang ganti password
        /*
        AuditLog::create([
            'user_id' => $user->id,
            'admin_name' => 'SYSTEM',
            'action' => 'RESET PASSWORD',
            'target_personnel' => $user->name,
            'description' => "Personel berhasil memperbarui password melalui verifikasi token admin.",
            'ip_address' => $request->ip(),
        ]);
        */

        return redirect()->route('login')->with('message', 'Password berhasil diperbarui. Silahkan login kembali.');
    }
}