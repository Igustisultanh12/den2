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
        if (Schema::hasTable('sp_jagas') && Schema::hasColumn('sp_jagas', 'nomor_urut')) {
            Schema::table('sp_jagas', function (Blueprint $table) {
                $table->string('nomor_urut', 50)->nullable()->default(null)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sp_jagas') && Schema::hasColumn('sp_jagas', 'nomor_urut')) {
            Schema::table('sp_jagas', function (Blueprint $table) {
                $table->integer('nomor_urut')->nullable()->default(null)->change();
            });
        }
    }
};
