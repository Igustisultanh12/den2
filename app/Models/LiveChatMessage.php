<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveChatMessage extends Model
{
    protected $table = 'live_chat_messages';

    protected $fillable = [
        'thread_id',
        'sender_type',
        'sender_id',
        'sender_name',
        'message',
        'attachments',
        'is_read',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_read' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Pesan Chat Dinas baru → notifikasi push ke penerima (Firebase); diam bila belum diatur.
        static::created(fn (self $m) => \App\Support\PushNotifier::chatMessage($m));
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(LiveChatThread::class, 'thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
