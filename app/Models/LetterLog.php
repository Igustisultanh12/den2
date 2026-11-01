<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LetterLog extends Model
{
    use HasFactory;

    /**
     * Daftarkan kolom yang boleh diisi (Mass Assignment).
     * Menambahkan 'sub_category_id' agar jenis surat (Nikah, PNS, dll) bisa tersimpan.
     */
    protected $fillable = [
        'full_number',
        'sequence',
        'priority',
        'category_id',
        'sub_category_id', // TAMBAHAN: Agar data sub-kategori tersimpan
        'subject',
        'recipient',
        'date',
        'is_archived',
        'number',
    ];

    /**
     * Cast attributes to native types.
     * Memastikan 'is_archived' terbaca sebagai boolean dan 'date' sebagai objek tanggal.
     */
    protected $casts = [
        'is_archived' => 'boolean',
        'date' => 'date',
        'sequence' => 'string', // Mempertegas bahwa sequence sekarang adalah String (Huruf)
    ];

    /**
     * Relasi ke tabel Category (Kategori Utama).
     * Menghubungkan log nomor dengan Kategori seperti SKHPP, Sprin, dll.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Relasi ke tabel LetterSubCategory (Sub-Kategori).
     * Menghubungkan log nomor dengan Jenis Surat dari Manajemen Kategori.
     */
    public function subCategory(): BelongsTo
    {
        // Pastikan nama model adalah LetterSubCategory sesuai file yang Bapak buat
        return $this->belongsTo(LetterSubCategory::class, 'sub_category_id');
    }

    /**
     * Scope untuk memfilter logs berdasarkan tahun berjalan (Opsional/Pendukung).
     */
    public function scopeThisYear($query)
    {
        return $query->whereYear('date', date('Y'));
    }

    /** Agenda surat tanpa nomor SKHPP (SKHPP punya buku nomor sendiri: skhpp_archives). */
    public function scopeWithoutSkhpp($query)
    {
        return $query->where(fn ($q) => $q->whereNull('category_id')
            ->orWhereNotIn('category_id', Category::skhpp()->select('id')));
    }

    /**
     * TAMBAHAN: Helper untuk mengecek apakah log sudah memiliki file fisik.
     * Mempermudah pengecekan di sistem arsip.
     */
    public function isUploaded()
    {
        return $this->is_archived === true;
    }
    
    protected function serializeDate(\DateTimeInterface $date)
	{
	    return $date->format('Y-m-d H:i:s');
	}
}