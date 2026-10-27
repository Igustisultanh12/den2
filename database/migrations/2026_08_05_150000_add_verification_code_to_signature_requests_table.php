<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('signature_requests', 'verification_code')) {
            Schema::table('signature_requests', function (Blueprint $table) {
                $table->string('verification_code')->nullable()->unique()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('signature_requests', 'verification_code')) {
            Schema::table('signature_requests', function (Blueprint $table) {
                $table->dropUnique(['verification_code']);
                $table->dropColumn('verification_code');
            });
        }
    }
};
