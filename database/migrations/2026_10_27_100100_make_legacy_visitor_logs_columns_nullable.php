<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 2026_02_06_074849 membuat visitor_logs dengan device_type/browser/platform NOT NULL,
     * sedangkan pencatatan login kini memakai kolom device/user_agent dan tidak mengisinya.
     * Akibatnya login gagal (500) pada instalasi baru. Jadikan kolom lama nullable.
     */
    public function up(): void
    {
        if (! Schema::hasTable('visitor_logs')) {
            return;
        }

        $columns = array_values(array_filter(
            ['device_type', 'browser', 'platform'],
            fn ($column) => Schema::hasColumn('visitor_logs', $column)
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('visitor_logs', function (Blueprint $table) use ($columns) {
            foreach ($columns as $column) {
                $table->string($column)->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: mengembalikan NOT NULL akan gagal bila sudah ada baris tanpa nilai.
    }
};
