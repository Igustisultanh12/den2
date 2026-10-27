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
        if (!Schema::hasTable('visitor_logs')) {
            Schema::create('visitor_logs', function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
                $blueprint->string('user_name')->nullable(); 
                $blueprint->string('nrp')->nullable(); 
                $blueprint->string('ip_address')->nullable();
                $blueprint->string('user_agent')->nullable();
                $blueprint->timestamp('login_at')->useCurrent();
                $blueprint->timestamps();
            });
        } else {
            Schema::table('visitor_logs', function (Blueprint $blueprint) {
                if (!Schema::hasColumn('visitor_logs', 'user_name')) {
                    $blueprint->string('user_name')->nullable()->after('user_id');
                }
                if (!Schema::hasColumn('visitor_logs', 'nrp')) {
                    $blueprint->string('nrp')->nullable()->after('user_name');
                }
                if (!Schema::hasColumn('visitor_logs', 'user_agent')) {
                    $blueprint->string('user_agent')->nullable()->after('nrp');
                }
                if (!Schema::hasColumn('visitor_logs', 'login_at')) {
                    $blueprint->timestamp('login_at')->useCurrent()->after('user_agent');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_logs');
    }
};
