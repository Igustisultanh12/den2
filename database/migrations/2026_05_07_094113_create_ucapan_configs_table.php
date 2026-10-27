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
        if (!Schema::hasTable('ucapan_configs')) {
            Schema::create('ucapan_configs', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique(); // Pengganti 'key' agar lebih unik
                $table->text('narasi');           // Pengganti 'value'
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ucapan_configs');
    }
};
