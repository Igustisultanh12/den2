<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'avatar',
        'password',
        'pangkat',
        'nrp',
        'phone',
        'role',
        'is_active',
        'can_access_mitra',
        'can_manage_koperasi',
        'activation_token',
        'must_change_password', // Agar sistem tahu user perlu ganti password
        'tracking_consent_at', // Waktu personel menyetujui ketentuan dinas (termasuk pelacakan) saat aktivasi
        'reset_token',
        'token_expires_at',
        'birth_date',
        'google_id',
        'google_email',
        'google_avatar',
        'mfa_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'activation_token', // Sembunyikan token dari respon JSON demi keamanan
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'can_access_mitra' => 'boolean',
            'can_manage_koperasi' => 'boolean',
            'mfa_enabled' => 'boolean',
            'must_change_password' => 'boolean',
            'token_expires_at' => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        $r = strtolower(str_replace([' ', '-'], '_', (string) ($this->role ?? '')));
        return in_array($r, ['super_admin', 'superadmin'], true);
    }

    public function isAdmin(): bool
    {
        $r = strtolower(str_replace([' ', '-'], '_', (string) ($this->role ?? '')));
        return in_array($r, ['super_admin', 'superadmin', 'admin'], true);
    }

    public function isPersonel(): bool
    {
        $r = strtolower(str_replace([' ', '-'], '_', (string) ($this->role ?? '')));
        return in_array($r, ['personel', 'user'], true);
    }

    public function isPersonelSintel(): bool
    {
        $r = strtolower(str_replace([' ', '-'], '_', (string) ($this->role ?? '')));
        $jabatan = strtolower((string) ($this->jabatan ?? ''));
        return in_array($r, ['personel_sintel', 'anggotasintel'], true) || str_contains($jabatan, 'sintel');
    }

    /**
     * Relasi Rekening Koperasi Anggota
     */
    public function koperasiAccount()
    {
        return $this->hasOne(KoperasiAccount::class, 'user_id');
    }

    /**
     * Relasi Pinjaman Koperasi
     */
    public function koperasiLoans()
    {
        return $this->hasMany(KoperasiLoan::class, 'user_id')->latest();
    }

    /**
     * Relasi Presensi Personel
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'user_id')->latest('attendance_date');
    }

    /**
     * Cek apakah user adalah pengurus koperasi atau admin
     */
    public function isPengurusKoperasi(): bool
    {
        return $this->isAdmin() || (bool)$this->can_manage_koperasi || $this->name === 'I Gusti Sultan H.A, A.Md.Kom';
    }
}
