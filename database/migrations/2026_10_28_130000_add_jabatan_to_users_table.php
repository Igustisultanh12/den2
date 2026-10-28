<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom jabatan personel. Sudah dirujuk di banyak tempat (MobileAccess,
     * PersonnelTracking, dokumen TTE) namun belum pernah dibuat di tabel users
     * produksi, sehingga query yang menyebut kolom ini gagal di MySQL.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'jabatan')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            // after() hanya berpengaruh di MySQL/MariaDB; diabaikan dengan aman di SQLite.
            if (Schema::hasColumn('users', 'pangkat')) {
                $table->string('jabatan')->nullable()->after('pangkat');
            } else {
                $table->string('jabatan')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'jabatan')) {
                $table->dropColumn('jabatan');
            }
        });
    }
};
