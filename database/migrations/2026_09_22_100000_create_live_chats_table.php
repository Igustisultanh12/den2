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
        if (!Schema::hasTable('live_chat_threads')) {
            Schema::create('live_chat_threads', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('subject')->default('Pusat Komunikasi Dinas');
                $table->string('status')->default('OPEN'); // OPEN, CLOSED
                $table->dateTime('last_message_at')->nullable();
                $table->unsignedInteger('unread_admin')->default(0);
                $table->unsignedInteger('unread_user')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('live_chat_messages')) {
            Schema::create('live_chat_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('thread_id')->constrained('live_chat_threads')->cascadeOnDelete();
                $table->string('sender_type'); // USER, ADMIN, OPERATOR
                $table->unsignedBigInteger('sender_id')->nullable();
                $table->string('sender_name')->default('Operator Dinas');
                $table->text('message')->nullable();
                $table->json('attachments')->nullable(); // Array of {original_name, file_path, mime_type, size, is_image}
                $table->boolean('is_read')->default(false);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_chat_messages');
        Schema::dropIfExists('live_chat_threads');
    }
};
