<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pelacakan lokasi personel dari aplikasi Android:
     * - personnel_locations: satu baris posisi terakhir per personel (untuk peta realtime).
     * - personnel_location_histories: jejak titik lokasi (dibersihkan otomatis > 30 hari).
     */
    public function up(): void
    {
        if (!Schema::hasTable('personnel_locations')) {
            Schema::create('personnel_locations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->float('accuracy')->nullable();
                $table->float('speed')->nullable();
                $table->float('heading')->nullable();
                $table->unsignedTinyInteger('battery')->nullable();
                $table->boolean('is_charging')->nullable();
                $table->string('provider', 30)->nullable();
                $table->string('device', 150)->nullable();
                $table->timestamp('recorded_at')->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('personnel_location_histories')) {
            Schema::create('personnel_location_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->float('accuracy')->nullable();
                $table->float('speed')->nullable();
                $table->unsignedTinyInteger('battery')->nullable();
                $table->timestamp('recorded_at');
                $table->timestamp('created_at')->nullable();
                $table->index(['user_id', 'recorded_at']);
            });
        }

        if (Schema::hasTable('attendances') && !Schema::hasColumn('attendances', 'smile_score')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->unsignedTinyInteger('smile_score')->nullable()->after('photo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attendances', 'smile_score')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropColumn('smile_score');
            });
        }
        Schema::dropIfExists('personnel_location_histories');
        Schema::dropIfExists('personnel_locations');
    }
};
