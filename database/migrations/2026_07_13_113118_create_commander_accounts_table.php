<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('commander_accounts')) {
            Schema::create('commander_accounts', function (Blueprint $table) {
                $table->id();
                $table->date('tanggal');
                $table->string('keterangan');
                $table->enum('jenis', ['MASUK', 'KELUAR']);
                $table->decimal('jumlah', 15, 2);
                $table->string('petugas_input'); // Mencatat siapa yang menginput data (Admin/Suma)
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commander_accounts');
    }
};