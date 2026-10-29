<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SignatureRequest;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /** Menampilkan daftar personel. */
    public function index()
    {
        return Inertia::render('Users/Index', [
            'users' => User::where('id', '!=', auth()->id())
                ->visibleTo(auth()->user())
                ->orderBy('created_at', 'desc')
                ->orderBy('is_active', 'asc')
                ->paginate(10),

            'allUsers' => User::where('id', '!=', auth()->id())
                ->visibleTo(auth()->user())
                ->orderBy('role', 'asc')
                ->get(),
        ]);
    }

    /**
     * FITUR: TAMBAH PERSONEL + GENERATE TOKEN AKTIVASI + NOTIFIKASI WA
     */
    public function store(Request $request)
    {
        $rawNrp = preg_replace('/[^A-Za-z0-9]/', '', $request->nrp ?? '');
        $autoEmail = !empty($rawNrp) ? (strtolower($rawNrp) . '@sinden.my.id') : null;

        if (!$request->filled('email') && $autoEmail) {
            $request->merge(['email' => $autoEmail]);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'pangkat' => 'required|string',
            'nrp' => 'required|string|unique:users,nrp',
            'phone' => 'required|string|unique:users,phone',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|string|in:super_admin,admin,personel,personel_sintel,komandan,wadan,pasops,danunit1,danunit2,danunitteknis,kaurmintel,paurset,staf,anggotasintel'
        ]);

        if ($denied = $this->roleChangeDenied(null, (string) $request->role)) {
            return back()->withErrors(['role' => $denied]);
        }

        try {
            DB::beginTransaction();
            
            // Generate Token Aktivasi (Contoh: SINDEN-A1B2C3)
            $activationToken = 'SINDEN-' . strtoupper(Str::random(6));
            
            // Sapaan Waktu
            $hour = now()->format('H');
            $sapaan = ($hour >= 5 && $hour < 11) ? "Selamat Pagi" : 
                     (($hour >= 11 && $hour < 15) ? "Selamat Siang" : 
                     (($hour >= 15 && $hour < 18) ? "Selamat Sore" : "Selamat Malam"));

            $roleMapping = [
                'super_admin'    => 'SUPER ADMIN',
                'admin'          => 'ADMINISTRATOR',
                'personel'       => 'PERSONEL SATUAN',
                'personel_sintel'=> 'PERSONEL SINTEL',
                'komandan'       => 'KOMANDAN (APPROVER)',
                'wadan'          => 'WAKIL KOMANDAN',
                'pasops'         => 'PASOPS',
                'danunit1'       => 'DAN UNIT I / LID',
                'danunit2'       => 'DAN UNIT II / PAMGAL',
                'danunitteknis'  => 'DAN UNIT TEKNIS',
                'kaurmintel'     => 'KAUR MINTEL',
                'paurset'        => 'PAUR SET',
                'staf'           => 'STAF ADMINISTRASI',
                'anggotasintel'  => 'PERSONEL SINTEL',
            ];
            $roleLabel = $roleMapping[$request->role] ?? 'PERSONEL';

            // PROSES SIMPAN
            $user = User::create([
                'name' => trim($request->name),
                'pangkat' => $request->pangkat,
                'nrp' => $request->nrp,
                'phone' => $request->phone,
                'email' => $request->email,
                'role' => $request->role,
                'password' => Hash::make(Str::random(32)),
                'activation_token' => $activationToken,
                'is_active' => false,
                'must_change_password' => true,
                'mfa_enabled' => $request->boolean('mfa_enabled', false),
            ]);

            AuditLog::create([
                'user_id'          => auth()->id(),
                'admin_name'       => auth()->user()->name,
                'action'           => 'TAMBAH PERSONEL',
                'target_personnel' => $user->name,
                'description'      => "Mendaftarkan {$user->name} sebagai {$roleLabel}. Token Aktivasi telah dikirim via WA.",
                'ip_address'       => $request->ip(),
            ]);

            DB::commit();

            // PROSES KIRIM WA
            try {
                $targetPhone = $user->phone;
                if (str_starts_with($targetPhone, '0')) {
                    $targetPhone = '62' . substr($targetPhone, 1);
                }

                $pesanWA = "*AKTIVASI AKSES SI SINDEN*\n\n" .
                           "{$sapaan}, *{$user->pangkat} {$user->name}*.\n" .
                           "Mohon izin, akun SINDEN Anda telah dibuat.\n\n" .
                           "*Detail Aktivasi:*\n" .
                           "- Jabatan: *{$roleLabel}*\n" .
                           "- NRP: *{$user->nrp}*\n" .
                           "- Email: *{$user->email}*\n" .
                           "- Token: *{$activationToken}*\n\n" .
                           "Silakan aktivasi akun dan buat password Anda di:\n" .
                           "https://sinden2.my.id/aktivasi\n\n" .
                           "_Harap segera lakukan aktivasi demi keamanan data._";

                WhatsappService::sendMessage($targetPhone, $pesanWA);
                
                return redirect()->back()->with('message', 'Personel berhasil ditambahkan dan Token WA terkirim.');
            } catch (\Exception $waError) {
                Log::error('Gagal kirim WA ke: ' . $user->phone . ' | Error: ' . $waError->getMessage());
                return redirect()->back()->with('message', 'Personel tersimpan, namun WA gagal terkirim (Gateway Offline). Token: ' . $activationToken);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal Simpan Personel: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal sistem: ' . $e->getMessage());
        }
    }

    /**
     * FITUR: PROSES AKTIVASI OLEH PERSONEL
     */
    public function activate(Request $request)
    {
        $request->validate([
            'nrp' => 'required|string',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $identifier = trim($request->nrp);
        $token = trim($request->token);

        $user = User::where('activation_token', $token)
                    ->where(function($q) use ($identifier) {
                        $q->where('nrp', $identifier)
                          ->orWhere('email', $identifier);
                    })
                    ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'token' => 'NRP / Email atau Token Aktivasi tidak valid.',
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'is_active' => true,
            'activation_token' => null,
            'must_change_password' => false,
        ]);

        // Kirim Notifikasi WA bahwa Aktivasi Telah Berhasil
        try {
            if (!empty($user->phone)) {
                $targetPhone = $user->phone;
                if (str_starts_with($targetPhone, '0')) {
                    $targetPhone = '62' . substr($targetPhone, 1);
                }

                $pesanWA = "*AKTIVASI AKUN SINDEN BERHASIL*\n\n" .
                           "Selamat, *{$user->pangkat} {$user->name}*!\n" .
                           "Akun SINDEN Anda telah diaktifkan.\n\n" .
                           "*Detail Otoritas:*\n" .
                           "- NRP: *{$user->nrp}*\n" .
                           "- Email: *{$user->email}*\n\n" .
                           "Silakan login ke sistem menggunakan Email (*{$user->email}*) dan Password yang telah Anda buat di:\n" .
                           "https://sinden2.my.id/login\n\n" .
                           "_Tetap jaga kerahasiaan kredensial akun Anda._";

                WhatsappService::sendMessage($targetPhone, $pesanWA);
            }
        } catch (\Exception $waError) {
            Log::error('Gagal kirim notif WA aktivasi ke: ' . $user->phone . ' | Error: ' . $waError->getMessage());
        }

        return redirect()->route('login')->with('message', 'Aktivasi Akun Berhasil! Akun Anda telah aktif. Silakan login menggunakan Email (' . $user->email . ') dan Password Anda.');
    }

    /**
     * FITUR: TAMBAH BANYAK PERSONEL (BULK) + CETAK TOKEN AKTIVASI PDF
     */
    public function storeBulk(Request $request)
    {
        $request->validate([
            'users' => 'required|array|min:1',
            'users.*.name' => 'required|string|max:255',
            'users.*.pangkat' => 'required|string',
            'users.*.nrp' => 'required|string|distinct|unique:users,nrp',
            'users.*.email' => 'nullable|email|distinct|unique:users,email',
            'users.*.phone' => 'nullable|string',
            'users.*.role' => 'nullable|string|in:super_admin,admin,personel,personel_sintel,komandan,wadan,pasops,danunit1,danunit2,danunitteknis,kaurmintel,paurset,staf,anggotasintel',
        ]);

        foreach ((array) $request->input('users', []) as $row) {
            if ($denied = $this->roleChangeDenied(null, (string) ($row['role'] ?? ''))) {
                return back()->withErrors(['users' => $denied]);
            }
        }

        $createdIds = [];

        try {
            DB::beginTransaction();

            $roleMapping = [
                'super_admin'    => 'SUPER ADMIN',
                'admin'          => 'ADMINISTRATOR',
                'personel'       => 'PERSONEL SATUAN',
                'personel_sintel'=> 'PERSONEL SINTEL',
                'komandan'       => 'KOMANDAN (APPROVER)',
                'wadan'          => 'WAKIL KOMANDAN',
                'pasops'         => 'PASOPS',
                'danunit1'       => 'DAN UNIT I / LID',
                'danunit2'       => 'DAN UNIT II / PAMGAL',
                'danunitteknis'  => 'DAN UNIT TEKNIS',
                'kaurmintel'     => 'KAUR MINTEL',
                'paurset'        => 'PAUR SET',
                'staf'           => 'STAF ADMINISTRASI',
                'anggotasintel'  => 'PERSONEL SINTEL',
            ];

            foreach ($request->users as $item) {
                $activationToken = 'SINDEN-' . strtoupper(Str::random(6));
                $rawNrp = preg_replace('/[^A-Za-z0-9]/', '', $item['nrp']);
                $email = !empty($item['email']) ? $item['email'] : (strtolower($rawNrp) . '@sinden.my.id');
                $role = !empty($item['role']) ? $item['role'] : 'personel';
                $phone = !empty($item['phone']) ? $item['phone'] : null;

                $user = User::create([
                    'name' => trim($item['name']),
                    'pangkat' => $item['pangkat'],
                    'nrp' => $item['nrp'],
                    'phone' => $phone,
                    'email' => $email,
                    'role' => $role,
                    'password' => Hash::make(Str::random(32)),
                    'activation_token' => $activationToken,
                    'is_active' => false,
                    'must_change_password' => true,
                ]);

                $createdIds[] = $user->id;

                AuditLog::create([
                    'user_id'          => auth()->id(),
                    'admin_name'       => auth()->user()->name,
                    'action'           => 'TAMBAH PERSONEL BULK',
                    'target_personnel' => $user->name,
                    'description'      => "Mendaftarkan masal {$user->name} ({$user->pangkat}/{$user->nrp}). Kode verifikasi via PDF.",
                    'ip_address'       => $request->ip(),
                ]);

                // Jika nomor telepon diisi, kirim notifikasi WA
                if (!empty($user->phone)) {
                    try {
                        $targetPhone = $user->phone;
                        if (str_starts_with($targetPhone, '0')) {
                            $targetPhone = '62' . substr($targetPhone, 1);
                        }
                        $pesanWA = "*AKTIVASI AKSES SI SINDEN*\n\n" .
                                   "Mohon izin, *{$user->pangkat} {$user->name}*.\n" .
                                   "Akun SINDEN Anda telah dibuat.\n\n" .
                                   "*Detail Aktivasi:*\n" .
                                   "- Jabatan: *" . ($roleMapping[$user->role] ?? 'ANGGOTA SINTEL') . "*\n" .
                                   "- NRP: *{$user->nrp}*\n" .
                                   "- Email: *{$user->email}*\n" .
                                   "- Token: *{$activationToken}*\n\n" .
                                   "Silakan aktivasi akun dan buat password Anda di:\n" .
                                   "https://sinden2.my.id/aktivasi\n\n" .
                                   "_Harap segera lakukan aktivasi demi kelancaran kedinasan._";

                        WhatsappService::sendMessage($targetPhone, $pesanWA);
                    } catch (\Exception $waErr) {
                        Log::warning('Gagal kirim WA bulk: ' . $waErr->getMessage());
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($createdIds) . ' Akun personel berhasil didaftarkan.',
                'ids' => $createdIds,
                'pdf_url' => route('users.print-token-pdf', ['ids' => implode(',', $createdIds)])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal Bulk Personel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * FITUR CETAK KODE VERIFIKASI (TOKEN AKTIVASI) PDF
     */
    public function printTokenPdf(Request $request)
    {
        $idsParam = $request->query('ids');
        $query = User::where('is_active', false)->whereNotNull('activation_token')->visibleTo(auth()->user());

        if ($idsParam) {
            $ids = explode(',', $idsParam);
            $query->whereIn('id', $ids);
        }

        $users = $query->orderBy('name', 'asc')->get();

        if ($users->isEmpty()) {
            return redirect()->back()->with('error', 'Tidak ada data token personel belum aktif yang dapat dicetak.');
        }

        $pdf = Pdf::loadView('pdf.kodeverifikasi', compact('users'));
        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream('Kode_Verifikasi_Personel_SINDEN.pdf');
    }

    /**
     * FITUR CETAK REKAP DATA PERSONEL PDF
     */
    public function printPdf(Request $request)
    {
        $users = User::where('id', '!=', auth()->id())
            ->visibleTo(auth()->user())
            ->orderBy('role', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        $pdf = Pdf::loadView('pdf.users_report', [
            'users' => $users,
            'title' => 'REKAPITULASI OTORITAS AKSES PERSONEL',
            'unit'  => 'DETASEMEN INTELIJEN KOARMADA II',
            'date'  => now()->format('d/m/Y H:i')
        ]);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream('Rekap_Personel_SINDEN.pdf');
    }

    /**
     * GENERATE TOKEN MANUAL DARI ADMIN
     */
    public function generateToken(Request $request, User $user)
    {
        $this->authorizeTarget($user);
        $token = strtoupper(Str::random(6));
        $expiresAt = now()->addMinutes(5);

        $user->update([
            'reset_token' => $token,
            'token_expires_at' => $expiresAt
        ]);

        AuditLog::create([
            'user_id'          => auth()->id(),
            'admin_name'       => auth()->user()->name,
            'action'           => 'REQUEST TOKEN',
            'target_personnel' => $user->name,
            'description'      => "Men-generate token reset password untuk {$user->name}",
            'ip_address'       => request()->ip(),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'token' => $token,
                'message' => "Token untuk {$user->name} berhasil dibuat: {$token}",
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'pangkat' => $user->pangkat,
                    'nrp' => $user->nrp,
                ]
            ]);
        }

        return back()
            ->with('token', $token)
            ->with('flash', [
                'token' => $token,
                'message' => "Token untuk {$user->name} berhasil dibuat: {$token}"
            ])
            ->with('message', "Token untuk {$user->name} berhasil dibuat: {$token}");
    }

    /**
     * ALIAS UNTUK GENERATE TOKEN
     */
    public function generateResetToken(Request $request, User $user)
    {
        return $this->generateToken($request, $user);
    }

    /**
     * TOGGLE STATUS AKTIF / NONAKTIF PERSONEL
     */
    public function toggle(Request $request, User $user)
    {
        $this->authorizeTarget($user);
        $request->validate(['password' => 'required']);

        if (!Hash::check($request->password, auth()->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Password Konfirmasi Salah!']);
        }

        $oldStatus = $user->is_active;
        $user->update(['is_active' => !$oldStatus]);

        AuditLog::create([
            'user_id'          => auth()->id(),
            'admin_name'       => auth()->user()->name,
            'action'           => $user->is_active ? 'AKTIVASI USER' : 'SUSPEND USER',
            'target_personnel' => $user->name,
            'description'      => "Merubah status akses {$user->name} menjadi " . ($user->is_active ? 'Aktif' : 'Nonaktif'),
            'ip_address'       => $request->ip(),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_active' => $user->is_active,
                'message' => 'Status akses berhasil diperbarui.'
            ]);
        }

        return back()->with('message', 'Status akses berhasil diperbarui.');
    }

    /**
     * ALIAS UNTUK TOGGLE STATUS
     */
    public function toggleStatus(Request $request, User $user)
    {
        return $this->toggle($request, $user);
    }

    /**
     * FITUR: ADMIN MENGUBAH PASSWORD PERSONEL SECARA LANGSUNG
     */
    public function changePassword(Request $request, User $user)
    {
        $this->authorizeTarget($user);
        $request->validate([
            'password' => 'required|string|min:6',
        ], [
            'password.required' => 'Password baru wajib diisi.',
            'password.min'      => 'Password minimal 6 karakter.',
        ]);

        $user->update([
            'password'             => Hash::make($request->password),
            'must_change_password' => false,
            'is_active'            => true,
            'reset_token'          => null,
            'token_expires_at'     => null,
        ]);

        AuditLog::create([
            'user_id'          => auth()->id(),
            'admin_name'       => auth()->user()->name,
            'action'           => 'UBAH PASSWORD PERSONEL',
            'target_personnel' => $user->name,
            'description'      => "Admin " . auth()->user()->name . " mengubah password akun {$user->name} ({$user->nrp}) secara langsung.",
            'ip_address'       => $request->ip(),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Password untuk {$user->name} berhasil diperbarui."
            ]);
        }

        return back()->with('message', "Password untuk {$user->name} berhasil diperbarui.");
    }

    /**
     * UPDATE DATA PERSONEL
     */
    public function update(Request $request, User $user)
    {
        $this->authorizeTarget($user);
        $request->validate([
            'name'     => 'required|string|max:255',
            'pangkat'  => 'required|string',
            'nrp'      => 'required|string|unique:users,nrp,' . $user->id,
            'phone'    => 'nullable|string',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'role'     => 'nullable|string|in:super_admin,admin,personel,personel_sintel,komandan,wadan,pasops,danunit1,danunit2,danunitteknis,kaurmintel,paurset,staf,anggotasintel',
            'jabatan'  => 'nullable|string|max:255',
            'password' => 'nullable|string|min:6',
        ]);

        $updateData = [
            'name'    => trim($request->name),
            'pangkat' => $request->pangkat,
            'nrp'     => $request->nrp,
            'phone'   => $request->phone,
            'email'   => $request->email,
        ];

        if ($request->has('jabatan') && \Illuminate\Support\Facades\Schema::hasColumn('users', 'jabatan')) {
            $updateData['jabatan'] = trim((string) $request->jabatan) ?: null;
        }

        $oldRole = (string) $user->role;
        if ($request->filled('role') && $request->role !== $oldRole) {
            if ($denied = $this->roleChangeDenied($user, (string) $request->role)) {
                return back()->withErrors(['role' => $denied]);
            }
            $updateData['role'] = $request->role;
        }

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
            $updateData['must_change_password'] = false;
        }

        if ($request->has('mfa_enabled')) {
            $updateData['mfa_enabled'] = (bool)$request->mfa_enabled;
        }

        $user->update($updateData);

        AuditLog::create([
            'user_id'          => auth()->id(),
            'admin_name'       => auth()->user()->name,
            'action'           => 'UPDATE PERSONEL',
            'target_personnel' => $user->name,
            'description'      => "Memperbarui data profil {$user->name}"
                . (isset($updateData['role']) ? " (peran: {$oldRole} → {$updateData['role']})" : '')
                . ($request->filled('password') ? " dan password akun" : ""),
            'ip_address'       => $request->ip(),
        ]);

        return back()->with('message', 'Data personel berhasil diperbarui.');
    }

    /**
     * Aturan perubahan peran: hanya Super Admin yang dapat memberi/mencabut peran Super
     * Admin, dan tidak ada yang dapat mengubah peran akunnya sendiri (cegah terkunci).
     * $target null = membuat personel baru.
     */
    private function roleChangeDenied(?User $target, string $newRole): ?string
    {
        $actor = auth()->user();
        $actorIsSuper = \App\Support\MobileAccess::isSuperAdmin($actor);
        $touchesSuper = $newRole === 'super_admin'
            || ($target && \App\Support\MobileAccess::isSuperAdmin($target));

        if ($target && (int) $target->id === (int) $actor->id) {
            return 'Anda tidak dapat mengubah peran akun Anda sendiri. Minta Super Admin lain untuk mengubahnya.';
        }
        if ($touchesSuper && !$actorIsSuper) {
            return 'Hanya Super Admin yang dapat memberi atau mencabut peran Super Admin.';
        }
        return null;
    }

    /** Akun Super Admin hanya dapat dikelola oleh Super Admin. */
    private function authorizeTarget(User $target): void
    {
        abort_unless($target->isManageableBy(auth()->user()), 403, 'Akun Super Admin hanya dapat dikelola oleh Super Admin.');
    }

    /**
     * TOGGLE HAK AKSES MITRA
     */
    public function toggleMitraAccess(User $user)
    {
        $this->authorizeTarget($user);
        $user->update(['can_access_mitra' => !$user->can_access_mitra]);

        AuditLog::create([
            'user_id'          => auth()->id(),
            'admin_name'       => auth()->user()->name,
            'action'           => 'UPDATE HAK AKSES MITRA',
            'target_personnel' => $user->name,
            'description'      => "Mengubah hak akses mitra untuk {$user->name} menjadi " . ($user->can_access_mitra ? 'Diizinkan' : 'Dilarang'),
            'ip_address'       => request()->ip(),
        ]);

        return back()->with('message', 'Hak akses mitra berhasil diperbarui.');
    }

    /**
     * HAPUS PERSONEL
     */
    public function destroy(User $user)
    {
        $this->authorizeTarget($user);
        $name = $user->name;
        $user->delete();

        AuditLog::create([
            'user_id'          => auth()->id(),
            'admin_name'       => auth()->user()->name,
            'action'           => 'HAPUS PERSONEL',
            'target_personnel' => $name,
            'description'      => "Menghapus akun {$name} dari sistem",
            'ip_address'       => request()->ip(),
        ]);

        return back()->with('message', 'Personel berhasil dihapus.');
    }

    /**
     * TOGGLE HAK AKSES PENGURUS SIMPAN PINJAM (KOPERASI)
     */
    public function toggleKoperasiAccess(User $user)
    {
        $this->authorizeTarget($user);
        $user->update(['can_manage_koperasi' => !$user->can_manage_koperasi]);

        AuditLog::create([
            'user_id'          => auth()->id(),
            'admin_name'       => auth()->user()->name,
            'action'           => 'UPDATE HAK AKSES PENGURUS KOPERASI',
            'target_personnel' => $user->name,
            'description'      => "Mengubah hak akses pengurus simpan pinjam untuk {$user->name} menjadi " . ($user->can_manage_koperasi ? 'Diizinkan' : 'Dilarang'),
            'ip_address'       => request()->ip(),
        ]);

        return back()->with('message', 'Hak akses Pengurus Simpan Pinjam berhasil diperbarui.');
    }



    /**
     * TOGGLE HAK AKSES M2F OTP WHATSAPP
     */
    public function toggleMfa(User $user)
    {
        $this->authorizeTarget($user);
        $newStatus = !$user->mfa_enabled;
        $user->update(['mfa_enabled' => $newStatus]);

        AuditLog::create([
            'user_id'          => auth()->id(),
            'admin_name'       => auth()->user()->name,
            'action'           => 'UPDATE M2F OTP WHATSAPP',
            'target_personnel' => $user->name,
            'description'      => "Mengubah status M2F OTP WhatsApp untuk {$user->name} ({$user->nrp}) menjadi " . ($newStatus ? 'Aktif' : 'Nonaktif'),
            'ip_address'       => request()->ip(),
        ]);

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'mfa_enabled' => $newStatus,
                'message' => 'Status M2F OTP WhatsApp berhasil diperbarui.'
            ]);
        }

        return back()->with('message', 'Status M2F OTP WhatsApp berhasil diperbarui.');
    }

}
