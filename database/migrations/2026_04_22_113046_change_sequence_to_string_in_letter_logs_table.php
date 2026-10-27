<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Versi lama file ini keliru membuat tabel `personnels` (sudah ditangani oleh
        // 2026_05_07_090911_create_personnels_table). Tujuan sebenarnya: sequence → string.
        if (Schema::hasTable('letter_logs') && Schema::hasColumn('letter_logs', 'sequence')) {
            Schema::table('letter_logs', function (Blueprint $table) {
                $table->string('sequence', 50)->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('letter_logs') && Schema::hasColumn('letter_logs', 'sequence')) {
            Schema::table('letter_logs', function (Blueprint $table) {
                // Mengembalikan ke Integer jika diperlukan (rollback)
                $table->integer('sequence')->change();
            });
        }
    }
};
