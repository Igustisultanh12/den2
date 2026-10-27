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
        if (! Schema::hasTable('print_jobs')) {
            return;
        }

        $columns = array_values(array_filter(
            ['original_file_path', 'preview_pdf_path', 'printable_pdf_path'],
            fn ($column) => Schema::hasColumn('print_jobs', $column)
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('print_jobs', function (Blueprint $table) use ($columns) {
            foreach ($columns as $column) {
                $table->string($column, 255)->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
