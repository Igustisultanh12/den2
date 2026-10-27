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
        if (Schema::hasTable('sc_submissions')) {
            Schema::table('sc_submissions', function (Blueprint $table) {
                if (!Schema::hasColumn('sc_submissions', 'skhpp_id')) {
                    $table->unsignedBigInteger('skhpp_id')->nullable();
                }
                if (!Schema::hasColumn('sc_submissions', 'file_skhpp')) {
                    $table->string('file_skhpp')->nullable();
                }
                if (!Schema::hasColumn('sc_submissions', 'file_sc_preview')) {
                    $table->string('file_sc_preview')->nullable();
                }
                if (!Schema::hasColumn('sc_submissions', 'sc_preview_uploaded_at')) {
                    $table->timestamp('sc_preview_uploaded_at')->nullable();
                }
                if (!Schema::hasColumn('sc_submissions', 'sc_preview_expired_at')) {
                    $table->timestamp('sc_preview_expired_at')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sc_submissions')) {
            Schema::table('sc_submissions', function (Blueprint $table) {
                // up() tidak pernah membuat foreign key untuk skhpp_id, jadi cukup drop kolomnya.
                $columns = array_values(array_filter([
                    'skhpp_id',
                    'file_skhpp',
                    'file_sc_preview',
                    'sc_preview_uploaded_at',
                    'sc_preview_expired_at',
                ], fn ($column) => Schema::hasColumn('sc_submissions', $column)));
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
