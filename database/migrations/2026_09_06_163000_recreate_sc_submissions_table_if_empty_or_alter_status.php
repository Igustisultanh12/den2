<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sc_submissions')) {
            $count = 0;
            try {
                $count = DB::table('sc_submissions')->count();
            } catch (\Throwable $e) {}

            if ($count === 0) {
                Schema::disableForeignKeyConstraints();
                Schema::dropIfExists('sc_submission_logs');
                Schema::dropIfExists('sc_submissions');
                Schema::enableForeignKeyConstraints();
            }
        }

        if (!Schema::hasTable('sc_submissions')) {
            Schema::create('sc_submissions', function (Blueprint $table) {
                $table->id();
                $table->string('tracking_code')->nullable()->unique();
                $table->string('nomor_resi')->nullable()->index();
                $table->string('tipe_permohonan')->nullable()->default('baru');
                $table->string('nama')->nullable();
                $table->string('pangkat_korps')->nullable();
                $table->string('identifier_type')->default('nrp');
                $table->string('identifier_number')->nullable()->index();
                $table->string('kesatuan')->nullable();
                $table->string('jabatan')->nullable();
                $table->string('phone')->nullable();
                $table->string('keperluan')->nullable();
                $table->unsignedTinyInteger('current_stage')->default(1)->index();
                $table->string('status', 50)->default('proses')->index();
                $table->unsignedBigInteger('skhpp_id')->nullable();
                $table->string('nomor_surat_rh')->nullable();
                $table->string('nomor_skhpp')->nullable();
                $table->string('file_skhpp')->nullable();
                $table->string('nomor_sc')->nullable();
                $table->string('file_sc_preview')->nullable();
                $table->timestamp('sc_preview_uploaded_at')->nullable();
                $table->timestamp('sc_preview_expired_at')->nullable();
                $table->text('catatan_petugas')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            try {
                DB::statement("ALTER TABLE `sc_submissions` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'proses'");
            } catch (\Throwable $e) {}

            try {
                $columns = DB::select("SHOW COLUMNS FROM sc_submissions");
                foreach ($columns as $col) {
                    $fieldName = $col->Field ?? $col->field ?? null;
                    $isNull = ($col->Null ?? $col->null ?? 'YES') === 'NO';
                    $colType = $col->Type ?? $col->type ?? null;

                    if ($fieldName && $isNull && !in_array($fieldName, ['id', 'status', 'created_at', 'updated_at'])) {
                        try {
                            DB::statement("ALTER TABLE `sc_submissions` MODIFY COLUMN `{$fieldName}` {$colType} NULL DEFAULT NULL");
                        } catch (\Throwable $e) {}
                    }
                }
            } catch (\Throwable $e) {}
        }

        if (!Schema::hasTable('sc_submission_logs')) {
            Schema::create('sc_submission_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sc_submission_id')->index();
                $table->unsignedTinyInteger('stage');
                $table->string('stage_title');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_name')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
