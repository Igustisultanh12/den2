<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SHOW COLUMNS / MODIFY COLUMN hanya ada di MySQL/MariaDB; driver lain sudah nullable lewat 163000.
        if (Schema::hasTable('sc_submissions') && in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            try {
                $columns = DB::select("SHOW COLUMNS FROM sc_submissions");
                foreach ($columns as $col) {
                    $fieldName = $col->Field ?? $col->field ?? null;
                    $isNull = ($col->Null ?? $col->null ?? 'YES') === 'NO';
                    $colType = $col->Type ?? $col->type ?? null;

                    if ($fieldName && $isNull && !in_array($fieldName, ['id', 'created_at', 'updated_at'])) {
                        try {
                            DB::statement("ALTER TABLE `sc_submissions` MODIFY COLUMN `{$fieldName}` {$colType} NULL DEFAULT NULL");
                        } catch (\Throwable $e) {
                            // Abaikan jika tipe khusus tidak mendukung
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Abaikan
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
