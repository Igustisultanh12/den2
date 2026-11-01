<?php

namespace App\Console\Commands;

use App\Models\SkhppArchive;
use App\Services\SkhppPdfService;
use Illuminate\Console\Command;

/**
 * Simpan PDF resmi untuk SKHPP SINDEN yang sudah disahkan TTE Komandan
 * tetapi belum memiliki PDF di Arsip SKHPP (mis. data sebelum fitur arsip ada).
 *
 *   php artisan skhpp:archive-pdfs
 */
class ArchiveSkhppPdfs extends Command
{
    protected $signature = 'skhpp:archive-pdfs {--limit=0 : Batasi jumlah SKHPP yang diproses}';

    protected $description = 'Simpan PDF SKHPP SINDEN yang sudah disahkan ke Arsip SKHPP';

    public function handle(): int
    {
        $query = SkhppArchive::with('skhpp')
            ->where('sumber', 'sinden')
            ->whereNull('file_path')
            ->where('status', '!=', SkhppArchive::STATUS_DICABUT)
            ->whereHas('skhpp', fn ($q) => $q->where('status', 'approved'))
            ->orderBy('id');

        if ((int) $this->option('limit') > 0) {
            $query->limit((int) $this->option('limit'));
        }

        $ok = 0;
        $fail = 0;
        foreach ($query->get() as $archive) {
            if (SkhppPdfService::storeToArchive($archive->skhpp)) {
                $ok++;
                $this->line("✓ {$archive->nomor_skhpp}");
            } else {
                $fail++;
                $this->warn("✗ Gagal: " . ($archive->nomor_skhpp ?: "#{$archive->id}"));
            }
        }

        $this->info("Selesai. Tersimpan: {$ok}, gagal: {$fail}.");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
