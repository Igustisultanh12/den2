<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ScSubmission extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'current_stage' => 'integer',
        'is_taken' => 'boolean',
        'taken_at' => 'datetime',
        'tanggal_sc' => 'date',
        'sc_preview_uploaded_at' => 'datetime',
        'sc_preview_expired_at' => 'datetime',
    ];

    protected $appends = [
        'stage_title',
        'stage_description',
        'progress_percentage',
        'all_stages',
        'is_sc_preview_available',
        'sc_preview_remaining_hours',
        'file_skhpp_url',
        'file_sc_preview_url',
        'tanggal_sc_formatted',
        'taken_at_formatted',
        'kategori_sc',
        'kategori_sc_label',
    ];

    /**
     * 10 Tahapan Resmi Pengajuan Security Clearance (SC)
     */
    public const STAGES = [
        1 => [
            'id' => 1,
            'title' => 'Pengisian RH',
            'desc' => 'Pengisian formulir Daftar Riwayat Hidup (RH) oleh personel pemohon.',
            'category' => 'Denintel',
        ],
        2 => [
            'id' => 2,
            'title' => 'Pengecekan Kelengkapan Dokumen',
            'desc' => 'Pemeriksaan berkas administrasi dan kelengkapan lampiran oleh petugas Denintel.',
            'category' => 'Denintel',
        ],
        3 => [
            'id' => 3,
            'title' => 'Proses Cetak SKHPP',
            'desc' => 'Pencetakan lembar naskah resmi SKHPP untuk penandatanganan kedinasan.',
            'category' => 'Denintel',
        ],
        4 => [
            'id' => 4,
            'title' => 'Menunggu TTD Komandan Denintel',
            'desc' => 'Pengajuan berkas untuk diteliti dan ditandatangani oleh Komandan Detasemen Intelijen.',
            'category' => 'Denintel',
        ],
        5 => [
            'id' => 5,
            'title' => 'SKHPP Terbit',
            'desc' => 'Surat Keterangan Hasil Penelitian Personel (SKHPP) telah resmi diterbitkan oleh Denintel.',
            'category' => 'Denintel',
        ],
        6 => [
            'id' => 6,
            'title' => 'Proses Staf Intelijen',
            'desc' => 'Pemberkasan dan penyerahan ke Staf Intelijen (Sintel) Komando Daerah Angkatan Laut.',
            'category' => 'Sintel',
        ],
        7 => [
            'id' => 7,
            'title' => 'Verifikasi Dokumen Sintel',
            'desc' => 'Penelitian dan validasi kepatutan data intelijen di tingkat Staf Intelijen Koarmada 2.',
            'category' => 'Sintel',
        ],
        8 => [
            'id' => 8,
            'title' => 'Proses Pencetakan SC',
            'desc' => 'Pencetakan blangko resmi naskah Security Clearance (SC).',
            'category' => 'Sintel',
        ],
        9 => [
            'id' => 9,
            'title' => 'Menunggu TTD Asintel',
            'desc' => 'Naskah Security Clearance diajukan untuk ditandatangani oleh Asisten Intelijen (Asintel).',
            'category' => 'Sintel',
        ],
        10 => [
            'id' => 10,
            'title' => 'SC Terbit dan Bisa Diambil di Sintel Mako Koarmada 2',
            'desc' => 'Naskah Security Clearance telah selesai terbit dan siap diambil oleh personel pemohon di Sintel Mako Koarmada 2.',
            'category' => 'Selesai',
        ],
    ];

    public function logs()
    {
        return $this->hasMany(ScSubmissionLog::class, 'sc_submission_id')->orderBy('id', 'asc');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function skhpp()
    {
        return $this->belongsTo(Skhpp::class, 'skhpp_id');
    }


    public function getStatusAttribute($value)
    {
        if (is_numeric($value)) {
            return match ((int)$value) {
                2 => 'selesai',
                3 => 'perbaikan',
                4 => 'ditolak',
                default => 'proses',
            };
        }
        return $value ?: 'proses';
    }

    public function setStatusAttribute($value)
    {
        $this->attributes['status'] = $value;
    }

    public function getStageTitleAttribute()
    {
        return self::STAGES[$this->current_stage]['title'] ?? 'Dalam Proses';
    }

    public function getStageDescriptionAttribute()
    {
        return self::STAGES[$this->current_stage]['desc'] ?? '-';
    }

    public function getProgressPercentageAttribute()
    {
        return min(100, max(10, $this->current_stage * 10));
    }

    public function getAllStagesAttribute()
    {
        return array_values(self::STAGES);
    }

    /**
     * Memeriksa dan membersihkan berkas petinjau SC jika telah melampaui 2x24 jam (48 jam).
     */
    public function checkAndPurgeExpiredPreview(): bool
    {
        if ($this->file_sc_preview && $this->sc_preview_uploaded_at) {
            try {
                $uploadedAt = \Illuminate\Support\Carbon::parse($this->sc_preview_uploaded_at);
                $expiresTimestamp = $uploadedAt->timestamp + (48 * 3600);
                if (now()->timestamp >= $expiresTimestamp) {
                    if (Storage::disk('public')->exists($this->file_sc_preview)) {
                        Storage::disk('public')->delete($this->file_sc_preview);
                    }
                    $this->update([
                        'file_sc_preview' => null,
                        'sc_preview_expired_at' => now(),
                    ]);
                    return true;
                }
            } catch (\Throwable $e) {
                return false;
            }
        }
        return false;
    }

    public function getIsScPreviewAvailableAttribute(): bool
    {
        if (empty($this->file_sc_preview) || empty($this->sc_preview_uploaded_at)) {
            return false;
        }
        try {
            $uploadedAt = \Illuminate\Support\Carbon::parse($this->sc_preview_uploaded_at);
            return now()->timestamp < ($uploadedAt->timestamp + (48 * 3600));
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getScPreviewRemainingHoursAttribute(): ?int
    {
        if (empty($this->file_sc_preview) || empty($this->sc_preview_uploaded_at)) {
            return null;
        }
        try {
            $uploadedAt = \Illuminate\Support\Carbon::parse($this->sc_preview_uploaded_at);
            $expiresTimestamp = $uploadedAt->timestamp + (48 * 3600);
            $nowTimestamp = now()->timestamp;
            if ($nowTimestamp >= $expiresTimestamp) {
                return 0;
            }
            $diffSeconds = $expiresTimestamp - $nowTimestamp;
            return max(1, (int) ceil($diffSeconds / 3600));
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getFileSkhppUrlAttribute(): ?string
    {
        if (!$this->file_skhpp) {
            return null;
        }
        return asset('storage/' . $this->file_skhpp);
    }

    public function getFileScPreviewUrlAttribute(): ?string
    {
        if (!$this->file_sc_preview || !$this->is_sc_preview_available) {
            return null;
        }
        return asset('storage/' . $this->file_sc_preview);
    }
    public function getTanggalScFormattedAttribute(): string
    {
        if ($this->tanggal_sc) {
            return \Illuminate\Support\Carbon::parse($this->tanggal_sc)->format('d/m/Y');
        }
        return $this->created_at ? $this->created_at->format('d/m/Y') : '-';
    }

    public function getTakenAtFormattedAttribute(): string
    {
        if ($this->is_taken && $this->taken_at) {
            return \Illuminate\Support\Carbon::parse($this->taken_at)->format('d/m/Y');
        }
        return '-';
    }

    public function getKategoriScAttribute($value): string
    {
        if (!empty($value)) {
            return strtolower($value) === 'perusahaan' ? 'perusahaan' : 'dinas';
        }

        // Deteksi cerdas dari relasi SKHPP atau nomor SKHPP
        if ($this->skhpp && $this->skhpp->kategori_personel === 'perusahaan') {
            return 'perusahaan';
        }
        if (!empty($this->nomor_skhpp) && str_contains($this->nomor_skhpp, 'SKHPP-P')) {
            return 'perusahaan';
        }

        return 'dinas';
    }

    public function getKategoriScLabelAttribute(): string
    {
        return ($this->kategori_sc === 'perusahaan') ? 'SC Perusahaan (SKHPP-P)' : 'SC Dinas (SKHPP-D)';
    }

    /**
     * Memastikan skema basis data dan seluruh kolom tabel SC selalu siap (Self-Healing Schema)
     */
    public static function ensureSchema(): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('sc_submissions')) {
                // Pastikan kolom kategori_sc selalu ada
                if (!\Illuminate\Support\Facades\Schema::hasColumn('sc_submissions', 'kategori_sc')) {
                    \Illuminate\Support\Facades\Schema::table('sc_submissions', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->string('kategori_sc', 20)->default('dinas')->index();
                    });
                }
                // Pastikan kolom baru untuk status pengambilan dan tanggal SC selalu ada
                if (!\Illuminate\Support\Facades\Schema::hasColumn('sc_submissions', 'is_taken')) {
                    \Illuminate\Support\Facades\Schema::table('sc_submissions', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->boolean('is_taken')->default(false)->index();
                    });
                }
                if (!\Illuminate\Support\Facades\Schema::hasColumn('sc_submissions', 'taken_at')) {
                    \Illuminate\Support\Facades\Schema::table('sc_submissions', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->timestamp('taken_at')->nullable()->index();
                    });
                }
                if (!\Illuminate\Support\Facades\Schema::hasColumn('sc_submissions', 'tanggal_sc')) {
                    \Illuminate\Support\Facades\Schema::table('sc_submissions', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->date('tanggal_sc')->nullable()->index();
                    });
                }
                if (!\Illuminate\Support\Facades\Schema::hasColumn('sc_submissions', 'taken_by')) {
                    \Illuminate\Support\Facades\Schema::table('sc_submissions', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->string('taken_by')->nullable();
                    });
                }

                // Bila tabel masih kosong (0 berkas), reset dan bangun ulang secara bersih sempurna
                $count = 0;
                try {
                    $count = \Illuminate\Support\Facades\DB::table('sc_submissions')->count();
                } catch (\Throwable $e) {}

                if ($count === 0) {
                    try {
                        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
                        \Illuminate\Support\Facades\Schema::dropIfExists('sc_submission_logs');
                        \Illuminate\Support\Facades\Schema::dropIfExists('sc_submissions');
                        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
                    } catch (\Throwable $e) {}
                }
            }

            if (!\Illuminate\Support\Facades\Schema::hasTable('sc_submissions')) {
                \Illuminate\Support\Facades\Schema::create('sc_submissions', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->string('kategori_sc', 20)->default('dinas')->index();
                    $table->string('tracking_code')->nullable()->unique();
                    $table->string('nomor_resi')->nullable()->index();
                    $table->string('tipe_permohonan')->nullable()->default('baru');
                    $table->string('nama')->nullable();
                    $table->string('pangkat_korps')->nullable();
                    $table->string('identifier_type')->default('nrp');
                    $table->string('identifier_number')->nullable()->index();
                    $table->string('kesatuan')->nullable();
                    $table->string('jabatan')->nullable();
                    $table->string('phone')->nullable();
                    $table->string('keperluan')->nullable();
                    $table->unsignedTinyInteger('current_stage')->default(1)->index();
                    $table->string('status', 50)->default('proses')->index();
                    $table->boolean('is_taken')->default(false)->index();
                    $table->timestamp('taken_at')->nullable()->index();
                    $table->date('tanggal_sc')->nullable()->index();
                    $table->string('taken_by')->nullable();
                    $table->unsignedBigInteger('skhpp_id')->nullable();
                    $table->string('nomor_surat_rh')->nullable();
                    $table->string('nomor_skhpp')->nullable();
                    $table->string('file_skhpp')->nullable();
                    $table->string('nomor_sc')->nullable();
                    $table->string('file_sc_preview')->nullable();
                    $table->timestamp('sc_preview_uploaded_at')->nullable();
                    $table->timestamp('sc_preview_expired_at')->nullable();
                    $table->text('catatan_petugas')->nullable();
                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->timestamps();
                });
            } else {
                // Jika tabel memiliki isi data, pastikan status adalah VARCHAR(50)
                try {
                    \Illuminate\Support\Facades\DB::statement("ALTER TABLE `sc_submissions` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'proses'");
                } catch (\Throwable $e) {}

                try {
                    $columns = \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM sc_submissions");
                    foreach ($columns as $col) {
                        $fieldName = $col->Field ?? $col->field ?? null;
                        $isNull = ($col->Null ?? $col->null ?? 'YES') === 'NO';
                        $colType = $col->Type ?? $col->type ?? null;

                        if ($fieldName && $isNull && !in_array($fieldName, ['id', 'status', 'created_at', 'updated_at'])) {
                            try {
                                \Illuminate\Support\Facades\DB::statement("ALTER TABLE `sc_submissions` MODIFY COLUMN `{$fieldName}` {$colType} NULL DEFAULT NULL");
                            } catch (\Throwable $e) {}
                        }
                    }
                } catch (\Throwable $e) {}
            }

            if (!\Illuminate\Support\Facades\Schema::hasTable('sc_submission_logs')) {
                \Illuminate\Support\Facades\Schema::create('sc_submission_logs', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('sc_submission_id')->index();
                    $table->unsignedTinyInteger('stage');
                    $table->string('stage_title');
                    $table->text('notes')->nullable();
                    $table->unsignedBigInteger('user_id')->nullable();
                    $table->string('user_name')->nullable();
                    $table->timestamps();
                });
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ensureSchema warning: ' . $e->getMessage());
        }
    }

}
