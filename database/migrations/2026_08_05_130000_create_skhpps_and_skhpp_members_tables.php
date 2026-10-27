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
        if (!Schema::hasTable('skhpps')) {
            Schema::create('skhpps', function (Blueprint $table) {
                $table->id();
                $table->string('kategori_personel', 50)->default('militer');
                $table->boolean('is_pernikahan')->default(false);
                $table->string('nomor_skhpp')->nullable(); // Contoh: R/171/SKHPP/VIII/2026
                $table->integer('nomor_urut')->nullable();
                $table->string('bulan_romawi')->nullable();
                $table->integer('tahun')->nullable();
                $table->date('tanggal_skhpp')->nullable();

                // Point 1c: Surat Pengantar / Security Clearance
                $table->text('surat_pengantar');

                // Point 2: Data Personel Utama
                $table->string('nama');
                $table->string('pangkat_korps_nrp')->nullable(); // Untuk Militer
                $table->string('nik')->nullable(); // Untuk Sipil
                $table->string('jabatan_pekerjaan');
                $table->string('tempat_lahir');
                $table->date('tanggal_lahir');
                $table->enum('jenis_kelamin', ['Laki-laki', 'Perempuan'])->default('Laki-laki');
                $table->string('agama');
                $table->text('alamat');

                // Point 2h: Pengikut / Anggota Banyak
                $table->boolean('has_pengikut')->default(false);

                // Point 4: Peruntukan SKHPP
                $table->text('peruntukan');

                // Unggahan Foto
                $table->string('foto_1')->nullable(); // Foto Utama / Calon Suami
                $table->string('foto_2')->nullable(); // Foto Calon Istri (Pernikahan)
                $table->string('label_foto_1')->nullable();
                $table->string('label_foto_2')->nullable();

                // Status Approval & QR Code TTD
                $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])->default('pending');
                $table->string('verification_code')->unique(); // Unique Code untuk QR Scan
                $table->foreignId('submitted_by')->nullable()->constrained('users')->onDelete('set null');
                $table->string('operator_name')->nullable();
                $table->string('operator_nrp_pangkat')->nullable();
                $table->timestamp('submitted_at')->nullable();

                $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamp('approved_at')->nullable();
                $table->text('catatan_revisi')->nullable();

                $table->timestamps();
            });
        }

        if (!Schema::hasTable('skhpp_members')) {
            Schema::create('skhpp_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('skhpp_id')->constrained('skhpps')->onDelete('cascade');
                $table->integer('no_urut')->default(1);
                $table->string('nama');
                $table->string('pangkat_nrp_nik');
                $table->string('jabatan');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skhpp_members');
        Schema::dropIfExists('skhpps');
    }
};
