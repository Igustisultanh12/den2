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
                if (!Schema::hasColumn('sc_submissions', 'is_taken')) {
                    $table->boolean('is_taken')->default(false)->index();
                }
                if (!Schema::hasColumn('sc_submissions', 'taken_at')) {
                    $table->timestamp('taken_at')->nullable()->index();
                }
                if (!Schema::hasColumn('sc_submissions', 'tanggal_sc')) {
                    $table->date('tanggal_sc')->nullable()->index();
                }
                if (!Schema::hasColumn('sc_submissions', 'taken_by')) {
                    $table->string('taken_by')->nullable();
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
                $columns = [];
                if (Schema::hasColumn('sc_submissions', 'is_taken')) $columns[] = 'is_taken';
                if (Schema::hasColumn('sc_submissions', 'taken_at')) $columns[] = 'taken_at';
                if (Schema::hasColumn('sc_submissions', 'tanggal_sc')) $columns[] = 'tanggal_sc';
                if (Schema::hasColumn('sc_submissions', 'taken_by')) $columns[] = 'taken_by';
                // Index harus di-drop dulu; SQLite menolak drop kolom yang masih ber-index.
                foreach (['is_taken', 'taken_at', 'tanggal_sc'] as $indexed) {
                    $index = "sc_submissions_{$indexed}_index";
                    if (in_array($indexed, $columns, true) && Schema::hasIndex('sc_submissions', $index)) {
                        $table->dropIndex($index);
                    }
                }
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
