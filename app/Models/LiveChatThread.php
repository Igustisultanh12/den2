<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class LiveChatThread extends Model
{
    protected $table = 'live_chat_threads';

    protected $fillable = [
        'uuid',
        'user_id',
        'peer_id',
        'operator_id',
        'subject',
        'status',
        'last_message_at',
        'unread_admin',
        'unread_user',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'unread_admin' => 'integer',
        'unread_user' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /** Lawan bicara pada chat langsung (null = utas layanan Operator Dinas). */
    public function peer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'peer_id');
    }

    public function isDirect(): bool
    {
        return $this->peer_id !== null;
    }

    public function messages(): HasMany
    {
        return $this->hasMany(LiveChatMessage::class, 'thread_id')->orderBy('id', 'asc');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(LiveChatMessage::class, 'thread_id')->latestOfMany();
    }

    protected static function booted(): void
    {
        static::deleting(function (LiveChatThread $thread) {
            $thread->purgeFiles();
        });
    }

    /**
     * Menghapus seluruh berkas lampiran obrolan dari server storage
     */
    public function purgeFiles(): void
    {
        $chatDir = "chat_attachments/{$this->uuid}";

        // 1. Hapus jika ada di disk public
        if (Storage::disk('public')->exists($chatDir)) {
            Storage::disk('public')->deleteDirectory($chatDir);
        }

        // 2. Hapus jika ada di disk local / private
        if (Storage::disk('local')->exists($chatDir)) {
            Storage::disk('local')->deleteDirectory($chatDir);
        }

        // 3. Hapus path lokal absolut jika masih tertinggal
        $localPath = storage_path("app/public/{$chatDir}");
        if (is_dir($localPath)) {
            File::deleteDirectory($localPath);
        }

        $privatePath = storage_path("app/private/{$chatDir}");
        if (is_dir($privatePath)) {
            File::deleteDirectory($privatePath);
        }
    }
}
