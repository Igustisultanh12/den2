<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Penanda terakhir kali aplikasi lokasi palsu (mock location) terdeteksi pada personel. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('personnel_locations') && !Schema::hasColumn('personnel_locations', 'mock_detected_at')) {
            Schema::table('personnel_locations', function (Blueprint $t) {
                $t->timestamp('mock_detected_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('personnel_locations') && Schema::hasColumn('personnel_locations', 'mock_detected_at')) {
            Schema::table('personnel_locations', function (Blueprint $t) {
                $t->dropColumn('mock_detected_at');
            });
        }
    }
};
