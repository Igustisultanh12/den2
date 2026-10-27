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
        Schema::table('visitor_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('visitor_logs', 'location')) {
                $table->string('location')->nullable()->after('ip_address');
            }
            
            if (!Schema::hasColumn('visitor_logs', 'device')) {
                $table->string('device')->nullable()->after('user_agent');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visitor_logs', function (Blueprint $table) {
            $table->dropColumn(['location', 'device']);
        });
    }
};