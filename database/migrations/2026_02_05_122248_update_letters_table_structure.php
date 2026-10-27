<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letters', function (Blueprint $col) {
            // Pastikan kolom ini ada atau tambahkan jika belum ada
            if (!Schema::hasColumn('letters', 'type')) {
                $col->enum('type', ['masuk', 'keluar', 'telegram'])->default('masuk')->after('id');
            }
            if (!Schema::hasColumn('letters', 'security_level')) {
                $col->string('security_level')->default('biasa')->after('type');
            }
            if (!Schema::hasColumn('letters', 'issuer')) {
                $col->string('issuer')->nullable()->after('subject');
            }
            if (!Schema::hasColumn('letters', 'date')) {
                $col->date('date')->after('issuer');
            }
            
            // Menambahkan foreign key untuk sub_category jika belum ada
            if (!Schema::hasColumn('letters', 'sub_category_id')) {
                $col->foreignId('sub_category_id')->nullable()->constrained('sub_categories')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        // type/security_level/issuer/date milik create_letters_table; di sini hanya
        // sub_category_id (beserta FK-nya) yang perlu dibalik.
        if (Schema::hasColumn('letters', 'sub_category_id')) {
            Schema::table('letters', function (Blueprint $col) {
                $col->dropForeign(['sub_category_id']);
                $col->dropColumn('sub_category_id');
            });
        }
    }
};