<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Buku nomor & arsip SKHPP tersendiri (dipisah dari Agenda Surat / Arsip Surat).
 *
 * - sumber "sinden": dibuat otomatis dari menu Penerbitan SKHPP (1 baris per SKHPP).
 * - sumber "manual": nomor diminta manual (SKHPP dibuat di luar menu SINDEN).
 * Nomor urut terpisah untuk SKHPP-D (Dinas Militer & PNS) dan SKHPP-P (Mitra/Perusahaan)
 * dan berlanjut per tahun.
 *
 * Data lama dipindahkan (disalin) dari skhpps, letter_logs & letters berkategori SKHPP.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('skhpp_archives')) {
            Schema::create('skhpp_archives', function (Blueprint $table) {
                $table->id();
                $table->foreignId('skhpp_id')->nullable()->unique()->constrained('skhpps')->nullOnDelete();
                $table->unsignedBigInteger('letter_log_id')->nullable(); // jejak migrasi dari Agenda Surat lama
                $table->string('sumber', 10)->default('manual');          // sinden | manual
                $table->string('jenis', 1);                               // D | P
                $table->string('priority', 5)->default('R');              // B | R | K | SR
                $table->unsignedInteger('nomor_urut')->nullable();
                $table->string('bulan_romawi', 6)->nullable();
                $table->unsignedSmallInteger('tahun')->nullable();
                $table->string('nomor_skhpp')->nullable()->index();
                $table->date('tanggal')->nullable();
                $table->string('nama')->nullable();
                $table->string('perihal');
                $table->text('peruntukan')->nullable();
                $table->text('keterangan')->nullable();
                $table->string('status', 20)->default('menunggu_pdf')->index(); // menunggu_pdf | tersimpan | dicabut
                $table->string('file_path')->nullable();
                $table->string('file_disk', 20)->default('local');
                $table->timestamp('uploaded_at')->nullable();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['jenis', 'tahun', 'nomor_urut']);
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('skhpp_archives');
    }

    private function backfill(): void
    {
        if (DB::table('skhpp_archives')->exists()) {
            return;
        }

        $now = now();
        $norm = fn ($v) => strtoupper(preg_replace('/\s+/', '', (string) $v));
        $priorityOf = function (?string $nomor, string $fallback = 'R') {
            if ($nomor && preg_match('/^\s*(SR|B|R|K)\s*\//i', $nomor, $m)) {
                return strtoupper($m[1]);
            }
            return $fallback;
        };
        $romanOf = function (?string $nomor) {
            if ($nomor && preg_match('/\/\s*(XII|XI|X|IX|VIII|VII|VI|V|IV|III|II|I)\s*\/\s*\d{4}\s*$/i', $nomor, $m)) {
                return strtoupper($m[1]);
            }
            return null;
        };
        $roman = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

        // Arsip PDF lama di tabel letters (diunggah lewat Arsip Surat), dikunci per nomor.
        $lettersByNumber = [];
        if (Schema::hasTable('letters')) {
            foreach (DB::table('letters')->whereNotNull('file_path')->orderBy('id')->get(['letter_number', 'file_path', 'created_at']) as $l) {
                $lettersByNumber[$norm($l->letter_number)] = $l;
            }
        }

        // 1) SKHPP yang dibuat dari menu SINDEN
        $sindenNumbers = [];
        if (Schema::hasTable('skhpps')) {
            foreach (DB::table('skhpps')->orderBy('id')->get() as $s) {
                $nomor = $s->nomor_skhpp ?: null;
                $file = $nomor ? ($lettersByNumber[$norm($nomor)] ?? null) : null;
                if ($nomor) {
                    $sindenNumbers[$norm($nomor)] = true;
                }

                DB::table('skhpp_archives')->insert([
                    'skhpp_id' => $s->id,
                    'sumber' => 'sinden',
                    'jenis' => ($s->kategori_personel ?? '') === 'perusahaan' ? 'P' : 'D',
                    'priority' => $priorityOf($nomor),
                    'nomor_urut' => $s->nomor_urut ?: null,
                    'bulan_romawi' => $s->bulan_romawi ?: $romanOf($nomor),
                    'tahun' => $s->tahun ?: null,
                    'nomor_skhpp' => $nomor,
                    'tanggal' => $s->tanggal_skhpp ?: null,
                    'nama' => $s->nama,
                    'perihal' => 'SKHPP - ' . $s->nama,
                    'peruntukan' => $s->peruntukan,
                    'keterangan' => null,
                    'status' => $file ? 'tersimpan' : 'menunggu_pdf',
                    'file_path' => $file?->file_path,
                    'file_disk' => $file ? 'public' : 'local',
                    'uploaded_at' => $file?->created_at,
                    'uploaded_by' => null,
                    'created_by' => $s->submitted_by ?? null,
                    'created_at' => $s->created_at ?? $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // 2) Nomor SKHPP yang dulu di-booking manual lewat Agenda Surat
        if (!Schema::hasTable('letter_logs') || !Schema::hasTable('categories')) {
            return;
        }
        $categories = DB::table('categories')->whereRaw('UPPER(code) LIKE ?', ['SKHPP%'])->get(['id', 'code']);
        if ($categories->isEmpty()) {
            return;
        }
        $jenisByCat = $categories->mapWithKeys(fn ($c) => [$c->id => str_contains(strtoupper($c->code), '-P') ? 'P' : 'D']);

        $logs = DB::table('letter_logs')->whereIn('category_id', $jenisByCat->keys())->orderBy('id')->get();
        foreach ($logs as $log) {
            if (isset($sindenNumbers[$norm($log->full_number)])) {
                continue; // sudah tercatat sebagai SKHPP SINDEN
            }
            $file = $lettersByNumber[$norm($log->full_number)] ?? null;
            $date = $log->date ? \Carbon\Carbon::parse($log->date) : null;

            DB::table('skhpp_archives')->insert([
                'skhpp_id' => null,
                'letter_log_id' => $log->id,
                'sumber' => 'manual',
                'jenis' => $jenisByCat[$log->category_id] ?? 'D',
                'priority' => $log->priority ?: $priorityOf($log->full_number),
                'nomor_urut' => is_numeric($log->sequence) ? (int) $log->sequence : null,
                'bulan_romawi' => $romanOf($log->full_number) ?: ($date ? $roman[(int) $date->format('n')] : null),
                'tahun' => $date ? (int) $date->format('Y') : null,
                'nomor_skhpp' => $log->full_number,
                'tanggal' => $log->date,
                'nama' => null,
                'perihal' => $log->subject ?: 'SKHPP',
                'peruntukan' => null,
                'keterangan' => $log->recipient ? 'Kepada: ' . $log->recipient : null,
                'status' => $file ? 'tersimpan' : 'menunggu_pdf',
                'file_path' => $file?->file_path,
                'file_disk' => $file ? 'public' : 'local',
                'uploaded_at' => $file?->created_at,
                'uploaded_by' => null,
                'created_by' => null,
                'created_at' => $log->created_at ?? $now,
                'updated_at' => $now,
            ]);
        }
    }
};
