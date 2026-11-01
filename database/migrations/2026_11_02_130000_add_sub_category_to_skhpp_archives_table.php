<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sub-kategori SKHPP (mis. SKHPP-D: Dik, Nikah, ...) yang dikelola di menu
 * Kategori Surat (tabel sub_categories). Hanya klasifikasi — tidak mengubah
 * format maupun urutan nomor SKHPP-D / SKHPP-P.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('skhpp_archives') && !Schema::hasColumn('skhpp_archives', 'sub_category_id')) {
            Schema::table('skhpp_archives', function (Blueprint $t) {
                $t->unsignedBigInteger('sub_category_id')->nullable()->index();
            });
        }

        if (!Schema::hasTable('skhpp_archives') || !Schema::hasTable('sub_categories')) {
            return;
        }

        // 1) Nomor SKHPP lama dari Agenda Surat: bawa sub-kategorinya.
        if (Schema::hasTable('letter_logs') && Schema::hasColumn('letter_logs', 'sub_category_id')) {
            $rows = DB::table('skhpp_archives')
                ->join('letter_logs', 'letter_logs.id', '=', 'skhpp_archives.letter_log_id')
                ->whereNull('skhpp_archives.sub_category_id')
                ->whereNotNull('letter_logs.sub_category_id')
                ->get(['skhpp_archives.id', 'letter_logs.sub_category_id']);
            foreach ($rows as $r) {
                if (DB::table('sub_categories')->where('id', $r->sub_category_id)->exists()) {
                    DB::table('skhpp_archives')->where('id', $r->id)->update(['sub_category_id' => $r->sub_category_id]);
                }
            }
        }

        // 2) SKHPP dari menu SINDEN: tebak dari jenis pernikahan / peruntukan.
        if (Schema::hasTable('skhpps')) {
            $archives = \App\Models\SkhppArchive::with('skhpp')
                ->whereNull('sub_category_id')->whereNotNull('skhpp_id')->get();
            foreach ($archives as $a) {
                if ($a->skhpp && ($subId = \App\Services\SkhppNumberService::guessSubCategoryId($a->skhpp))) {
                    $a->forceFill(['sub_category_id' => $subId])->saveQuietly();
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('skhpp_archives') && Schema::hasColumn('skhpp_archives', 'sub_category_id')) {
            Schema::table('skhpp_archives', function (Blueprint $t) {
                $t->dropIndex(['sub_category_id']);
                $t->dropColumn('sub_category_id');
            });
        }
    }
};
