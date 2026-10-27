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
        if (!Schema::hasTable('submissions')) {
            Schema::create('submissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('applicant_id')->nullable();
                $table->unsignedBigInteger('submission_category_id')->nullable();
                $table->json('answers_json')->nullable();
                $table->string('generated_pdf_path')->nullable();
                $table->json('attachment_paths')->nullable();
                $table->string('status')->default('verifying')->comment('Pangkalan kontrol status PESS -> Si Sinden -> SINKODV');
                $table->json('interview_details')->nullable();
                $table->string('skhpp_path')->nullable();
                $table->string('jenis_ttd_komandan')->nullable();
                $table->integer('komandan_coord_x')->nullable();
                $table->integer('komandan_coord_y')->nullable();
                $table->string('sc_pdf_path')->nullable();
                $table->string('jenis_ttd_asintel')->nullable();
                $table->integer('asintel_coord_x')->nullable();
                $table->integer('asintel_coord_y')->nullable();
                $table->timestamps();
            });
            return;
        }

        Schema::table('submissions', function (Blueprint $table) {
            // SULTAN PROTEKSI: Cek jika kolom 'status' belum ada, baru buat baru
            if (!Schema::hasColumn('submissions', 'status')) {
                $table->string('status')
                      ->default('verifying')
                      ->comment('Pangkalan kontrol status PESS -> Si Sinden -> SINKODV')
                      ->after('attachment_paths');
            }

            // Kolom integrasi baru lainnya langsung ditambahkan
            if (!Schema::hasColumn('submissions', 'interview_details')) {
                $table->json('interview_details')
                      ->nullable()
                      ->comment('Detail jadwal wawancara terintegrasi WA & Notifikasi')
                      ->after('status');
            }

            if (!Schema::hasColumn('submissions', 'skhpp_path')) {
                $table->string('skhpp_path')
                      ->nullable()
                      ->comment('Path penyimpanan berkas SKHPP fisik/digital')
                      ->after('interview_details');
            }

            if (!Schema::hasColumn('submissions', 'jenis_ttd_komandan')) {
                $table->string('jenis_ttd_komandan')
                      ->nullable()
                      ->comment('Klasifikasi metode penandatanganan: manual / digital')
                      ->after('skhpp_path');
            }

            if (!Schema::hasColumn('submissions', 'komandan_coord_x')) {
                $table->integer('komandan_coord_x')
                      ->nullable()
                      ->comment('Koordinat X untuk penempatan stempel/TTD Komandan')
                      ->after('jenis_ttd_komandan');
            }

            if (!Schema::hasColumn('submissions', 'komandan_coord_y')) {
                $table->integer('komandan_coord_y')
                      ->nullable()
                      ->comment('Koordinat Y untuk penempatan stempel/TTD Komandan')
                      ->after('komandan_coord_x');
            }

            if (!Schema::hasColumn('submissions', 'sc_pdf_path')) {
                $table->string('sc_pdf_path')
                      ->nullable()
                      ->comment('Berkas final Security Clearance yang diterbitkan SINKODV')
                      ->after('komandan_coord_y');
            }

            if (!Schema::hasColumn('submissions', 'jenis_ttd_asintel')) {
                $table->string('jenis_ttd_asintel')
                      ->nullable()
                      ->comment('Klasifikasi metode penandatanganan Asintel: manual / digital')
                      ->after('sc_pdf_path');
            }

            if (!Schema::hasColumn('submissions', 'asintel_coord_x')) {
                $table->integer('asintel_coord_x')
                      ->nullable()
                      ->comment('Koordinat X untuk stempel/TTD Asintel di SINKODV')
                      ->after('jenis_ttd_asintel');
            }

            if (!Schema::hasColumn('submissions', 'asintel_coord_y')) {
                $table->integer('asintel_coord_y')
                      ->nullable()
                      ->comment('Koordinat Y untuk stempel/TTD Asintel di SINKODV')
                      ->after('asintel_coord_x');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            // SULTAN PROTEKSI: Hanya drop jika kolom memang ada
            $columnsToDrop = [
                'interview_details', 'skhpp_path', 'jenis_ttd_komandan',
                'komandan_coord_x', 'komandan_coord_y', 'sc_pdf_path',
                'jenis_ttd_asintel', 'asintel_coord_x', 'asintel_coord_y'
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('submissions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};