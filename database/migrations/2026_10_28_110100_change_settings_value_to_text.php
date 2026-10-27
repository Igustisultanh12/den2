<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * settings.value semula VARCHAR(255): terlalu pendek untuk service account Firebase
     * terenkripsi, konfigurasi google-services, dan metadata APK pembaruan.
     */
    public function up(): void
    {
        if (Schema::hasTable('settings') && Schema::hasColumn('settings', 'value')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->text('value')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan ke VARCHAR(255) agar nilai panjang tidak terpotong.
    }
};
