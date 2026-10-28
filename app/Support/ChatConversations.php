<?php

namespace App\Support;

use App\Models\LiveChatMessage;
use App\Models\LiveChatThread;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Logika Chat Dinas bersama untuk web dan aplikasi Android.
 *
 * Dua jenis utas:
 *  - Layanan (peer_id NULL): personel ↔ seluruh operator (Admin/Super Admin/Developer).
 *  - Langsung (peer_id terisi): percakapan pribadi dua personel; operator tidak ikut membaca.
 *
 * sender_type tetap 'USER' untuk pesan dari user_id dan 'ADMIN' untuk pihak lain agar
 * klien lama tetap terbaca; klien baru menentukan "pesan saya" dari sender_id.
 */
class ChatConversations
{
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    public const AUDIO_EXTENSIONS = ['m4a', 'mp4a', 'mp4', 'aac', 'webm', 'weba', 'ogg', 'oga', 'opus', 'mp3', 'mpga', 'wav'];
    public const FILE_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'];
    public const MAX_TOTAL_BYTES = 20 * 1024 * 1024;

    public static function isOperator(User $user): bool
    {
        return MobileAccess::isAdmin($user) || MobileAccess::isDeveloper($user);
    }

    /** 'user' | 'peer' | 'operator' | null (tidak berhak). */
    public static function roleIn(LiveChatThread $thread, User $user): ?string
    {
        if ((int) $thread->user_id === (int) $user->id) {
            return 'user';
        }
        if ($thread->isDirect()) {
            return (int) $thread->peer_id === (int) $user->id ? 'peer' : null;
        }
        return self::isOperator($user) ? 'operator' : null;
    }

    public static function authorize(LiveChatThread $thread, User $user): string
    {
        $role = self::roleIn($thread, $user);
        abort_if($role === null, 403, 'Anda tidak memiliki akses ke percakapan ini.');
        return $role;
    }

    public static function find(string $uuid, User $user): LiveChatThread
    {
        $thread = LiveChatThread::with(['user', 'peer'])->where('uuid', $uuid)->firstOrFail();
        self::authorize($thread, $user);
        return $thread;
    }

    /** Pihak lain dalam percakapan (null bila lawannya "Operator Dinas"). */
    public static function otherParty(LiveChatThread $thread, User $viewer): ?User
    {
        $role = self::roleIn($thread, $viewer);
        if ($thread->isDirect()) {
            return $role === 'user' ? $thread->peer : $thread->user;
        }
        return $role === 'user' ? null : $thread->user;
    }

    public static function unreadFor(LiveChatThread $thread, string $role): int
    {
        return (int) ($role === 'user' ? $thread->unread_user : $thread->unread_admin);
    }

    /** Daftar percakapan milik $user, terbaru di atas. */
    public static function listFor(User $user, string $search = '', int $limit = 60): array
    {
        $operator = self::isOperator($user);
        $search = trim($search);

        $threads = LiveChatThread::with(['user', 'peer', 'latestMessage'])
            ->where(function ($q) use ($user, $operator) {
                $q->where('user_id', $user->id)->orWhere('peer_id', $user->id);
                if ($operator) {
                    $q->orWhereNull('peer_id');
                }
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($s) use ($search) {
                    $match = fn ($u) => $u->where('name', 'like', "%{$search}%")
                        ->orWhere('nrp', 'like', "%{$search}%")
                        ->orWhere('pangkat', 'like', "%{$search}%");
                    $s->whereHas('user', $match)->orWhereHas('peer', $match)->orWhere('subject', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $items = $threads->map(fn ($t) => self::summary($t, $user))->filter()->values()->all();

        // Personel selalu melihat entri "Operator Dinas" walau belum pernah mengirim pesan.
        if (!$operator && !collect($items)->contains(fn ($i) => $i['kind'] === 'support' && $i['role'] === 'user')) {
            $items[] = [
                'uuid' => null,
                'kind' => 'support',
                'role' => 'user',
                'title' => 'Operator Dinas',
                'subtitle' => 'Layanan & laporan ke Admin',
                'avatar' => null,
                'partner' => null,
                'status' => 'OPEN',
                'unread' => 0,
                'is_online' => false,
                'is_typing' => false,
                'last_message_at' => null,
                'last_message_human' => null,
                'latest_message' => null,
            ];
        }

        return $items;
    }

    public static function summary(LiveChatThread $t, User $viewer): ?array
    {
        $role = self::roleIn($t, $viewer);
        if ($role === null) {
            return null;
        }
        $other = self::otherParty($t, $viewer);
        $latest = $t->latestMessage;
        $title = $other
            ? trim(($other->pangkat ? $other->pangkat . ' ' : '') . $other->name)
            : 'Operator Dinas';

        return [
            'uuid' => $t->uuid,
            'kind' => $t->isDirect() ? 'direct' : 'support',
            'role' => $role,
            'title' => $title,
            'subtitle' => $other ? ($other->jabatan ?: ($other->nrp ? 'NRP ' . $other->nrp : null)) : 'Layanan & laporan ke Admin',
            'avatar' => $other?->avatar,
            'partner' => $other ? self::userCard($other) : null,
            'status' => $t->status,
            'unread' => self::unreadFor($t, $role),
            'is_online' => $other ? self::isOnline($other->id) : false,
            'is_typing' => self::isTyping($t, $viewer),
            'last_message_at' => $t->last_message_at?->toIso8601String(),
            'last_message_human' => $t->last_message_at?->diffForHumans(),
            'latest_message' => $latest ? [
                'sender_id' => $latest->sender_id,
                'sender_type' => $latest->sender_type,
                'sender_name' => $latest->sender_name,
                'message' => $latest->message,
                'preview' => self::preview($latest),
                'created_at' => $latest->created_at?->toIso8601String(),
            ] : null,
        ];
    }

    public static function userCard(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'pangkat' => $u->pangkat,
            'nrp' => $u->nrp,
            'jabatan' => $u->jabatan,
            'role' => $u->role,
            'avatar' => $u->avatar,
        ];
    }

    /** Teks singkat pesan terakhir untuk daftar percakapan & notifikasi. */
    public static function preview(LiveChatMessage $m): string
    {
        $text = trim((string) $m->message);
        if ($text !== '') {
            return mb_substr($text, 0, 120);
        }
        $att = collect($m->attachments ?? []);
        if ($att->contains(fn ($a) => !empty($a['is_audio']))) {
            return '🎤 Pesan suara';
        }
        if ($att->contains(fn ($a) => !empty($a['is_image']))) {
            return '📷 Foto';
        }
        return $att->isNotEmpty() ? '📎 Lampiran' : 'Pesan';
    }

    /** Utas layanan Operator Dinas milik personel (dibuat bila belum ada). */
    public static function supportThreadFor(User $user): LiveChatThread
    {
        $thread = LiveChatThread::where('user_id', $user->id)->whereNull('peer_id')->where('status', 'OPEN')->latest('last_message_at')->first()
            ?? LiveChatThread::where('user_id', $user->id)->whereNull('peer_id')->latest('last_message_at')->first();

        return $thread ?? LiveChatThread::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => 'Pusat Komunikasi Dinas',
            'status' => 'OPEN',
            'last_message_at' => now(),
            'unread_admin' => 0,
            'unread_user' => 0,
        ]);
    }

    /** Percakapan langsung dua personel (satu utas untuk setiap pasangan). */
    public static function directThread(User $me, User $other): LiveChatThread
    {
        abort_if((int) $me->id === (int) $other->id, 422, 'Tidak dapat mengirim pesan ke diri sendiri.');

        $thread = LiveChatThread::where(function ($q) use ($me, $other) {
            $q->where(fn ($a) => $a->where('user_id', $me->id)->where('peer_id', $other->id))
              ->orWhere(fn ($b) => $b->where('user_id', $other->id)->where('peer_id', $me->id));
        })->first();

        if ($thread) {
            if ($thread->status !== 'OPEN') {
                $thread->update(['status' => 'OPEN']);
            }
            return $thread;
        }

        return LiveChatThread::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $me->id,
            'peer_id' => $other->id,
            'subject' => 'Chat Pribadi',
            'status' => 'OPEN',
            'last_message_at' => now(),
            'unread_admin' => 0,
            'unread_user' => 0,
        ]);
    }

    /** Kontak yang dapat dihubungi: seluruh personel aktif selain diri sendiri. */
    public static function contacts(User $me, string $q = '', int $limit = 30): array
    {
        $q = trim($q);
        return User::query()
            ->where('id', '!=', $me->id)
            ->where('is_active', true)
            ->when($q !== '', function ($s) use ($q) {
                $s->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                    ->orWhere('nrp', 'like', "%{$q}%")
                    ->orWhere('pangkat', 'like', "%{$q}%"));
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn ($u) => self::userCard($u) + ['is_online' => self::isOnline($u->id)])
            ->all();
    }

    public static function markRead(LiveChatThread $thread, User $viewer): void
    {
        $role = self::roleIn($thread, $viewer);
        if ($role === null) {
            return;
        }
        $column = $role === 'user' ? 'unread_user' : 'unread_admin';
        if ((int) $thread->{$column} > 0) {
            $thread->update([$column => 0]);
        }
        $thread->messages()
            ->where('is_read', false)
            ->where(fn ($q) => $q->whereNull('sender_id')->orWhere('sender_id', '!=', $viewer->id))
            ->update(['is_read' => true]);
    }

    /**
     * Kirim pesan teks/lampiran/pesan suara.
     *
     * @param  array<int, UploadedFile>  $files
     * @return LiveChatMessage|string pesan baru, atau teks galat untuk ditampilkan
     */
    public static function send(LiveChatThread $thread, User $sender, ?string $text, array $files = [], ?int $voiceDurationMs = null): LiveChatMessage|string
    {
        $role = self::roleIn($thread, $sender);
        if ($role === null) {
            return 'Akses ditolak.';
        }
        if ($thread->status === 'CLOSED') {
            return 'Sesi obrolan ini telah ditutup.';
        }

        $total = array_sum(array_map(fn ($f) => (int) $f->getSize(), $files));
        if ($total > self::MAX_TOTAL_BYTES) {
            return 'Total ukuran lampiran melebihi batas 20MB.';
        }

        $isVoice = $voiceDurationMs !== null;
        $allowed = $isVoice ? self::AUDIO_EXTENSIONS : array_merge(self::IMAGE_EXTENSIONS, self::FILE_EXTENSIONS, self::AUDIO_EXTENSIONS);
        $uploaded = [];
        foreach ($files as $file) {
            $clean = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $file->getClientOriginalName()) ?: 'berkas';
            // Ekstensi & jenis ditentukan dari ISI berkas, bukan nama dari klien.
            $stored = SafeImage::storeUpload($file, "chat_attachments/{$thread->uuid}", $allowed);
            if ($stored === null) {
                return "Isi berkas [{$clean}] tidak sesuai format yang diizinkan.";
            }
            $ext = strtolower(pathinfo($stored, PATHINFO_EXTENSION));
            $isAudio = in_array($ext, self::AUDIO_EXTENSIONS, true);
            $isImage = in_array($ext, self::IMAGE_EXTENSIONS, true);
            $uploaded[] = array_filter([
                'original_name' => $clean,
                'file_path' => $stored,
                'url' => Storage::disk('public')->url($stored),
                'mime_type' => Storage::disk('public')->mimeType($stored) ?: $file->getClientMimeType(),
                'size' => $file->getSize(),
                'is_image' => $isImage,
                'is_audio' => $isAudio,
                'is_voice' => $isVoice && $isAudio,
                'duration_ms' => $isAudio && $voiceDurationMs ? max(0, min($voiceDurationMs, 30 * 60 * 1000)) : null,
            ], fn ($v) => $v !== null);
        }

        $text = strip_tags(trim((string) $text));
        if ($text === '' && $uploaded === []) {
            return 'Pesan teks atau berkas lampiran wajib diisi.';
        }

        $message = LiveChatMessage::create([
            'thread_id' => $thread->id,
            'sender_type' => $role === 'user' ? 'USER' : 'ADMIN',
            'sender_id' => $sender->id,
            'sender_name' => trim(($sender->pangkat ? $sender->pangkat . ' ' : '') . $sender->name),
            'message' => $text !== '' ? mb_substr($text, 0, 5000) : null,
            'attachments' => $uploaded ?: null,
            'is_read' => false,
        ]);

        // Penanda belum dibaca untuk pihak penerima.
        $recipientColumn = $role === 'user' ? 'unread_admin' : 'unread_user';
        $thread->update([
            'last_message_at' => now(),
            $recipientColumn => (int) $thread->{$recipientColumn} + 1,
            'operator_id' => $role === 'operator' ? $sender->id : $thread->operator_id,
        ]);
        self::clearTyping($thread, $sender);

        return $message;
    }

    /** Penerima notifikasi pesan: pihak lain dalam utas. */
    public static function recipientIds(LiveChatThread $thread, ?int $senderId): array
    {
        if ($thread->isDirect()) {
            $ids = [(int) $thread->user_id, (int) $thread->peer_id];
        } elseif ((int) $senderId === (int) $thread->user_id) {
            $ids = User::query()->get()->filter(fn ($u) => self::isOperator($u))->pluck('id')->map(fn ($i) => (int) $i)->all();
        } else {
            $ids = [(int) $thread->user_id];
        }
        return array_values(array_unique(array_filter($ids, fn ($id) => $id && $id !== (int) $senderId)));
    }

    /** Penerima panggilan: lawan bicara langsung, atau operator yang menangani utas layanan. */
    public static function calleeId(LiveChatThread $thread, User $caller): ?int
    {
        if ($thread->isDirect()) {
            return (int) ((int) $thread->user_id === (int) $caller->id ? $thread->peer_id : $thread->user_id);
        }
        if ((int) $thread->user_id !== (int) $caller->id) {
            return (int) $thread->user_id;
        }
        if ($thread->operator_id) {
            return (int) $thread->operator_id;
        }
        // Operator yang sedang daring lebih dulu, lalu operator mana pun.
        $operators = User::query()->where('is_active', true)->get()->filter(fn ($u) => self::isOperator($u));
        $online = $operators->first(fn ($u) => self::isOnline($u->id));
        return ($online ?? $operators->first())?->id;
    }

    // ------------------------------------------------------------ kehadiran & mengetik

    public static function touchPresence(int $userId): void
    {
        Cache::put("live_chat:user_presence:{$userId}", now()->timestamp, now()->addMinutes(2));
    }

    public static function isOnline(int $userId): bool
    {
        return Cache::has("live_chat:user_presence:{$userId}");
    }

    public static function setTyping(LiveChatThread $thread, User $user): void
    {
        Cache::put("live_chat:typing:{$thread->uuid}:u{$user->id}", true, now()->addSeconds(4));
        // Kunci lama (USER/ADMIN) untuk klien versi sebelumnya.
        $role = self::roleIn($thread, $user);
        Cache::put("live_chat:typing:{$thread->uuid}:" . ($role === 'user' ? 'USER' : 'ADMIN'), true, now()->addSeconds(4));
    }

    public static function clearTyping(LiveChatThread $thread, User $user): void
    {
        Cache::forget("live_chat:typing:{$thread->uuid}:u{$user->id}");
        $role = self::roleIn($thread, $user);
        Cache::forget("live_chat:typing:{$thread->uuid}:" . ($role === 'user' ? 'USER' : 'ADMIN'));
    }

    /** Apakah pihak lain sedang mengetik (dilihat dari sudut pandang $viewer). */
    public static function isTyping(LiveChatThread $thread, User $viewer): bool
    {
        $role = self::roleIn($thread, $viewer);
        if ($thread->isDirect()) {
            $otherId = $role === 'user' ? $thread->peer_id : $thread->user_id;
            return Cache::has("live_chat:typing:{$thread->uuid}:u{$otherId}");
        }
        return Cache::has("live_chat:typing:{$thread->uuid}:" . ($role === 'user' ? 'ADMIN' : 'USER'));
    }

    /** Detail percakapan terbuka: utas, pesan, status mengetik & panggilan. */
    public static function detail(LiveChatThread $thread, User $viewer, ?int $afterId = null): array
    {
        self::markRead($thread, $viewer);
        // Tanpa afterId: 300 pesan terbaru (urut lama → baru). Dengan afterId: hanya pesan baru.
        $messages = $afterId
            ? $thread->messages()->where('id', '>', $afterId)->reorder('id')->limit(200)->get()
            : $thread->messages()->reorder('id', 'desc')->limit(300)->get()->reverse()->values();

        return [
            'thread' => self::summary($thread->fresh(['user', 'peer', 'latestMessage']), $viewer),
            'messages' => $messages,
            'is_typing' => self::isTyping($thread, $viewer),
            'call' => Cache::get("live_chat:call:{$thread->uuid}"),
        ];
    }

    public static function totalUnread(User $user): int
    {
        return (int) collect(self::listFor($user, '', 200))->sum('unread');
    }
}
