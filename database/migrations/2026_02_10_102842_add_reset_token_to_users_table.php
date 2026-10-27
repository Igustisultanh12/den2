<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up() {
    Schema::table('users', function (Blueprint $table) {
        if (!Schema::hasColumn('users', 'reset_token')) {
            $table->string('reset_token')->nullable(); // Menampung 6 digit token
        }
        if (!Schema::hasColumn('users', 'token_expires_at')) {
            $table->timestamp('token_expires_at')->nullable(); // Masa berlaku 5 menit
        }
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
