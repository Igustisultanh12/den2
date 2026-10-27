<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('personnels')) {
            Schema::table('personnels', function (Blueprint $table) {
                if (!Schema::hasColumn('personnels', 'pangkat')) {
                    $table->string('pangkat')->nullable()->after('id');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->dropColumn('pangkat');
        });
    }
};