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
        // 1. TABEL UTAMA SURAT PERINTAH (SP) JAGA
        Schema::create('sp_jagas', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_sprin')->nullable(); // e.g. "Sprin/ 29 /VI/2026"
            $table->string('nomor_urut', 50)->nullable();
            $table->integer('bulan'); // 1 - 12
            $table->integer('tahun'); // e.g. 2026
            $table->string('bulan_romawi', 10)->nullable(); // e.g. "VI" / "VII"
            $table->date('tmt_mulai')->nullable();
            $table->date('tmt_selesai')->nullable();
            $table->date('tanggal_surat')->nullable();
            
            // Penerima Perintah (Perwira Tertua)
            $table->foreignId('perwira_tertua_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('perwira_tertua_nama')->nullable(); // e.g. "Kapten Laut (P) Indra Gunawan T.Z"
            $table->string('perwira_tertua_pangkat_nrp')->nullable(); // e.g. "Kapten Laut (P) NRP 19739/P"
            $table->string('perwira_tertua_jabatan')->nullable(); // e.g. "Dan Unit 1 Lid Den Intel Koarmada 2"
            $table->integer('total_personel_count')->default(26);
            $table->string('total_personel_terbilang')->default('Dua puluh enam');

            // Alur Tanda Tangan: 'tte' (QR digital) vs 'manual' (TTD basah fisik)
            $table->enum('ttd_type', ['tte', 'manual'])->default('tte');
            $table->enum('status', ['draft', 'pending_signature', 'signed', 'published'])->default('draft');
            $table->string('signed_file_path')->nullable(); // PDF hasil scan jika TTD manual

            // Verifikasi & TTE
            $table->string('verification_code')->nullable()->unique();
            $table->foreignId('penandatangan_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('penandatangan_nama')->nullable();
            $table->string('penandatangan_pangkat_nrp')->nullable();
            $table->string('penandatangan_jabatan')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // 2. TABEL DAFTAR PERWIRA JAGA (LAMPIRAN 1)
        Schema::create('sp_jaga_perwiras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sp_jaga_id')->constrained('sp_jagas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('no_urut')->default(1);
            $table->string('nama');
            $table->string('pangkat_korps')->nullable();
            $table->string('nrp')->nullable();
            $table->string('tgl_1')->nullable();
            $table->string('tgl_2')->nullable();
            $table->string('tgl_3')->nullable();
            $table->string('tgl_4')->nullable();
            $table->string('tgl_5')->nullable();
            $table->string('tgl_6')->nullable();
            $table->string('tgl_7')->nullable();
            $table->json('tgl_list')->nullable();
            $table->timestamps();
        });

        // 3. TABEL DAFTAR ANGGOTA JAGA DIVISI (LAMPIRAN 2)
        Schema::create('sp_jaga_anggotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sp_jaga_id')->constrained('sp_jagas')->cascadeOnDelete();
            $table->integer('divisi_no')->default(1); // Divisi 1 s.d. 5
            $table->string('tanggal_list_text')->nullable(); // e.g. "04, 09, 14, 19, 24, 29 JULI 2026"
            $table->json('tanggal_array')->nullable();
            $table->json('anggota_items')->nullable(); // Array objek: [{user_id, nama, pangkat_korps, nrp_nip, role_jaga}]
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sp_jaga_anggotas');
        Schema::dropIfExists('sp_jaga_perwiras');
        Schema::dropIfExists('sp_jagas');
    }
};