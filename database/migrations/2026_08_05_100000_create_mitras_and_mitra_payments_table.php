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
        // 1. Tabel Master Data Mitra
        if (!Schema::hasTable('mitras')) {
            Schema::create('mitras', function (Blueprint $table) {
                $table->id();
                $table->string('nama');
                $table->string('pt')->nullable();
                $table->string('no_tlp')->nullable();
                $table->integer('sort_order')->default(0);
                $table->text('keterangan')->nullable();
                $table->timestamps();
            });
        }

        // 2. Tabel Catatan Pembayaran Bulanan Mitra
        if (!Schema::hasTable('mitra_payments')) {
            Schema::create('mitra_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mitra_id')->constrained('mitras')->onDelete('cascade');
                $table->integer('tahun');
                $table->integer('bulan'); // 1 sampai 12
                $table->boolean('is_paid')->default(false);
                $table->string('catatan')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['mitra_id', 'tahun', 'bulan']);
            });
        }

        // 3. Tambah Kolom Otoritas Akses Mitra ke Tabel Users (Jika belum ada)
        if (!Schema::hasColumn('users', 'can_access_mitra')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('can_access_mitra')->default(false)->after('role');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mitra_payments');
        Schema::dropIfExists('mitras');

        if (Schema::hasColumn('users', 'can_access_mitra')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('can_access_mitra');
            });
        }
    }
};
