<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Database yang sudah menjalankan versi lama 2026_04_22_113046 tidak pernah
     * mengubah letter_logs.sequence ke string. Migrasi ini menyusulkannya.
     */
    public function up(): void
    {
        if (! Schema::hasTable('letter_logs') || ! Schema::hasColumn('letter_logs', 'sequence')) {
            return;
        }

        if (in_array(Schema::getColumnType('letter_logs', 'sequence'), ['varchar', 'string', 'char', 'text'], true)) {
            return;
        }

        Schema::table('letter_logs', function (Blueprint $table) {
            $table->string('sequence', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: rollback tipe ditangani oleh 2026_04_22_113046.
    }
};
