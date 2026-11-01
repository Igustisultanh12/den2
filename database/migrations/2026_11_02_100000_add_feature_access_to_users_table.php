<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fitur menu yang diaktifkan Admin per personel (lihat App\Support\FeatureAccess).
     * NULL = fitur bawaan sesuai peran; array JSON = hanya fitur yang tercantum.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'feature_access')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('feature_access')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'feature_access')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('feature_access');
            });
        }
    }
};
