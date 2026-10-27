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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'can_manage_koperasi')) {
                // Migrasi can_access_technical_cash sudah dihapus bersama modul kas; pada instalasi
                // baru kolom itu tidak ada sehingga ->after() ke kolom tersebut gagal di MySQL/MariaDB.
                $after = Schema::hasColumn('users', 'can_access_technical_cash') ? 'can_access_technical_cash' : 'can_access_mitra';
                $table->boolean('can_manage_koperasi')->default(false)->after($after);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'can_manage_koperasi')) {
                $table->dropColumn('can_manage_koperasi');
            }
        });
    }
};
