<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catatan persetujuan personel atas ketentuan dinas (termasuk pelacakan lokasi)
     * yang dicentang saat aktivasi akun di aplikasi. Menjadi bukti kapan disetujui.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'tracking_consent_at')) {
                $table->timestamp('tracking_consent_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'tracking_consent_at')) {
                $table->dropColumn('tracking_consent_at');
            }
        });
    }
};
