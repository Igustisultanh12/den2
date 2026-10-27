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
        // Schema builder agar portabel (MySQL/MariaDB/SQLite/PostgreSQL), bukan ALTER ... MODIFY mentah.
        if (Schema::hasTable('skhpps') && Schema::hasColumn('skhpps', 'kategori_personel')) {
            Schema::table('skhpps', function (Blueprint $table) {
                $table->string('kategori_personel', 50)->default('militer')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
