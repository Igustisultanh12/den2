<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sc_submissions')) {
            Schema::table('sc_submissions', function (Blueprint $table) {
                if (!Schema::hasColumn('sc_submissions', 'kategori_sc')) {
                    $table->string('kategori_sc', 20)->default('dinas')->index()->after('nomor_resi');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sc_submissions')) {
            Schema::table('sc_submissions', function (Blueprint $table) {
                if (Schema::hasColumn('sc_submissions', 'kategori_sc')) {
                    // Index harus di-drop dulu; SQLite menolak drop kolom yang masih ber-index.
                    if (Schema::hasIndex('sc_submissions', 'sc_submissions_kategori_sc_index')) {
                        $table->dropIndex('sc_submissions_kategori_sc_index');
                    }
                    $table->dropColumn('kategori_sc');
                }
            });
        }
    }
};
