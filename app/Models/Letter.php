<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Letter extends Model
{
    protected $fillable = [
        'type',             
        'category_id',      
        'sub_category_id',  
        'security_level',   
        'letter_number',    
        'subject',          
        'date',             
        'issuer',           
        'file_path'         
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Arsip surat masuk/keluar tanpa SKHPP (SKHPP diarsipkan di skhpp_archives). */
    public function scopeWithoutSkhpp($query)
    {
        return $query->where(fn ($q) => $q->whereNull('category_id')
            ->orWhereNotIn('category_id', Category::skhpp()->select('id')));
    }

    /**
     * PERBAIKAN: Pastikan nama Model target adalah 'SubCategory' 
     * bukan 'LetterSubCategory' (sesuaikan dengan nama file di folder Models Anda).
     */
    public function subCategory(): BelongsTo
    {
        // Jika nama file model Anda adalah SubCategory.php, gunakan ini:
        return $this->belongsTo(SubCategory::class, 'sub_category_id');
    }
}