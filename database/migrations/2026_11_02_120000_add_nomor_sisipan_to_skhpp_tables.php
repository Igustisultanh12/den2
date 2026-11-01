<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor SKHPP sisipan yang memuat huruf (mis. "1a", "12b").
 * nomor_urut tetap menyimpan angka dasarnya (1, 12) agar urutan otomatis
 * berlanjut dari angka terbesar; nomor_sisipan menyimpan hurufnya ("a").
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['skhpp_archives', 'skhpps'] as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'nomor_sisipan')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('nomor_sisipan', 5)->nullable();
                });
            }
        }

        // Nomor sisipan lama di Agenda Surat (mis. sequence "7a") yang belum terbaca saat dipindahkan.
        if (Schema::hasTable('skhpp_archives') && Schema::hasTable('letter_logs')) {
            $rows = DB::table('skhpp_archives')
                ->join('letter_logs', 'letter_logs.id', '=', 'skhpp_archives.letter_log_id')
                ->whereNull('skhpp_archives.nomor_urut')
                ->get(['skhpp_archives.id', 'letter_logs.sequence']);

            foreach ($rows as $r) {
                if (preg_match('/^\s*(\d+)\s*([a-zA-Z]{1,3})?\s*$/', (string) $r->sequence, $m)) {
                    DB::table('skhpp_archives')->where('id', $r->id)->update([
                        'nomor_urut' => (int) $m[1],
                        'nomor_sisipan' => isset($m[2]) && $m[2] !== '' ? strtolower($m[2]) : null,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['skhpp_archives', 'skhpps'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'nomor_sisipan')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('nomor_sisipan');
                });
            }
        }
    }
};
