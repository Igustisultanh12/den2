<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Arsip & buku nomor SKHPP (terpisah dari Agenda Surat / Arsip Surat).
 *
 * sumber = sinden : dibuat otomatis dari menu Penerbitan SKHPP (skhpp_id terisi)
 * sumber = manual : nomor diminta manual untuk SKHPP yang dibuat di luar menu SINDEN
 */
class SkhppArchive extends Model
{
    public const STATUS_MENUNGGU_PDF = 'menunggu_pdf';
    public const STATUS_TERSIMPAN = 'tersimpan';
    public const STATUS_DICABUT = 'dicabut';

    public const STATUS_LABELS = [
        self::STATUS_MENUNGGU_PDF => 'Menunggu Upload PDF',
        self::STATUS_TERSIMPAN => 'PDF Tersimpan',
        self::STATUS_DICABUT => 'Dicabut',
    ];

    public const JENIS_LABELS = [
        'D' => 'SKHPP-D (Dinas Militer & PNS)',
        'P' => 'SKHPP-P (Mitra Kerja / Perusahaan)',
    ];

    protected $fillable = [
        'skhpp_id', 'letter_log_id', 'sumber', 'jenis', 'priority',
        'nomor_urut', 'nomor_sisipan', 'bulan_romawi', 'tahun', 'nomor_skhpp', 'tanggal',
        'nama', 'perihal', 'peruntukan', 'keterangan', 'status',
        'file_path', 'file_disk', 'uploaded_at', 'uploaded_by', 'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'uploaded_at' => 'datetime',
        'nomor_urut' => 'integer',
        'tahun' => 'integer',
    ];

    protected $hidden = ['file_path', 'file_disk'];

    protected $appends = ['status_label', 'jenis_label', 'has_file', 'nomor_urut_label'];

    /** "12" atau "12a" (nomor sisipan). */
    public function getNomorUrutLabelAttribute(): ?string
    {
        return $this->nomor_urut ? $this->nomor_urut . ($this->nomor_sisipan ?? '') : null;
    }

    public function skhpp()
    {
        return $this->belongsTo(Skhpp::class, 'skhpp_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? strtoupper((string) $this->status);
    }

    public function getJenisLabelAttribute(): string
    {
        return self::JENIS_LABELS[$this->jenis] ?? ('SKHPP-' . $this->jenis);
    }

    public function getHasFileAttribute(): bool
    {
        return !empty($this->file_path);
    }

    public function fileExists(): bool
    {
        return $this->file_path && Storage::disk($this->file_disk ?: 'local')->exists($this->file_path);
    }

    public function deleteFile(): void
    {
        if ($this->file_path && ($this->file_disk ?: 'local') === 'local') {
            Storage::disk('local')->delete($this->file_path);
        }
        // Berkas lama di disk "public" milik tabel letters tidak dihapus dari sini.
    }

    /**
     * Buat / perbarui baris arsip untuk SKHPP yang diterbitkan dari menu SINDEN.
     * Status menjadi "PDF Tersimpan" hanya bila SKHPP sudah disahkan & PDF-nya ada.
     */
    public static function syncFromSkhpp(Skhpp $skhpp): self
    {
        $archive = self::firstOrNew(['skhpp_id' => $skhpp->id]);

        $archive->fill([
            'sumber' => 'sinden',
            'jenis' => \App\Services\SkhppNumberService::jenisFor($skhpp->kategori_personel),
            'nomor_urut' => $skhpp->nomor_urut ?: null,
            'nomor_sisipan' => $skhpp->nomor_sisipan ?: null,
            'bulan_romawi' => $skhpp->bulan_romawi ?: null,
            'tahun' => $skhpp->tahun ?: null,
            'nomor_skhpp' => $skhpp->nomor_skhpp ?: null,
            'tanggal' => $skhpp->tanggal_skhpp,
            'nama' => $skhpp->nama,
            'perihal' => 'SKHPP - ' . $skhpp->nama,
            'peruntukan' => $skhpp->peruntukan,
        ]);

        if (!$archive->exists) {
            $archive->priority = $archive->priority ?: 'R';
            $archive->created_by = $skhpp->submitted_by;
        }

        if ($archive->status !== self::STATUS_DICABUT) {
            $archive->status = ($skhpp->status === 'approved' && $archive->file_path)
                ? self::STATUS_TERSIMPAN
                : self::STATUS_MENUNGGU_PDF;
        }

        $archive->save();

        return $archive;
    }
}
