<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Skhpp extends Model
{
    use HasFactory;

    protected $table = 'skhpps';

    protected $fillable = [
        'kategori_personel',
        'is_pernikahan',
        'nomor_skhpp',
        'nomor_urut',
        'nomor_sisipan', // huruf nomor sisipan, mis. "a" untuk nomor 12a
        'bulan_romawi',
        'tahun',
        'tanggal_skhpp',
        'surat_pengantar',
        'nama',
        'pangkat_korps_nrp',
        'nik',
        'jabatan_pekerjaan',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'agama',
        'alamat',
        'has_pengikut',
        'peruntukan',
        'foto_1',
        'foto_2',
        'label_foto_1',
        'label_foto_2',
        'status',
        'verification_code',
        'submitted_by',
        'operator_name',
        'operator_nrp_pangkat',
        'submitted_at',
        'approved_by',
        'approved_at',
        'catatan_revisi',
    ];

    protected $casts = [
        'is_pernikahan' => 'boolean',
        'has_pengikut' => 'boolean',
        'tanggal_lahir' => 'date',
        'tanggal_skhpp' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function members()
    {
        return $this->hasMany(SkhppMember::class, 'skhpp_id')->orderBy('no_urut', 'asc');
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
