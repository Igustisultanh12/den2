<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kanal "Layanan Aplikasi": utas layanan yang hanya dibalas Super Admin, tanpa pernah
     * menampilkan identitasnya, dan harus dikonfirmasi lebih dulu sebelum chat berjalan.
     *
     * channel       → 'operator' (utas Operator Dinas, perilaku lama) | 'app' (Layanan Aplikasi).
     *                  Hanya berlaku untuk utas layanan (peer_id NULL).
     * requested_at  → kapan permintaan chat Layanan Aplikasi terakhir diajukan (status PENDING).
     */
    public function up(): void
    {
        if (!Schema::hasTable('live_chat_threads')) {
            return;
        }
        Schema::table('live_chat_threads', function (Blueprint $table) {
            if (!Schema::hasColumn('live_chat_threads', 'channel')) {
                $table->string('channel', 20)->default('operator')->index();
            }
            if (!Schema::hasColumn('live_chat_threads', 'requested_at')) {
                $table->dateTime('requested_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('live_chat_threads')) {
            return;
        }
        Schema::table('live_chat_threads', function (Blueprint $table) {
            if (Schema::hasColumn('live_chat_threads', 'channel')) {
                $table->dropIndex(['channel']);
                $table->dropColumn('channel');
            }
            if (Schema::hasColumn('live_chat_threads', 'requested_at')) {
                $table->dropColumn('requested_at');
            }
        });
    }
};
