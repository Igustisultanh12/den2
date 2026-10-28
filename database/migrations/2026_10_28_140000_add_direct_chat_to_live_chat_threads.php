<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chat langsung antar-personel (gaya WhatsApp).
     *
     * peer_id NULL  → utas layanan "Operator Dinas" (perilaku lama: personel ↔ operator).
     * peer_id terisi → percakapan pribadi antara user_id dan peer_id; hanya kedua
     *                  peserta yang dapat membaca (operator tidak).
     * unread_user  = belum dibaca oleh user_id; unread_admin = belum dibaca oleh pihak lain.
     */
    public function up(): void
    {
        if (!Schema::hasTable('live_chat_threads') || Schema::hasColumn('live_chat_threads', 'peer_id')) {
            return;
        }
        Schema::table('live_chat_threads', function (Blueprint $table) {
            $table->unsignedBigInteger('peer_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('live_chat_threads') || !Schema::hasColumn('live_chat_threads', 'peer_id')) {
            return;
        }
        Schema::table('live_chat_threads', function (Blueprint $table) {
            $table->dropIndex(['peer_id']);
            $table->dropColumn('peer_id');
        });
    }
};
