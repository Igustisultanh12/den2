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
 *
 * Identitas Super Admin tidak pernah ditampilkan ke pengguna lain:
 *  - Personel/Komandan/Admin tidak dapat mencari atau chat pribadi dengan Super Admin.
 *  - Pesan Super Admin tampil sebagai "Layanan Aplikasi" (nama & sender_id disamarkan).
 *  - Utas "Layanan Aplikasi" (channel 'app') hanya dibalas Super Admin dan berjalan dengan
 *    konfirmasi: PENDING (menunggu konfirmasi) → OPEN (diterima) → CLOSED (selesai/ditolak).
 */
class ChatConversations
{
    public const APP_SUPPORT_NAME = 'Layanan Aplikasi';
    public const STATUS_PENDING = 'PENDING';

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    public const AUDIO_EXTENSIONS = ['m4a', 'mp4a', 'mp4', 'aac', 'webm', 'weba', 'ogg', 'oga', 'opus', 'mp3', 'mpga', 'wav'];
    public const FILE_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'];
    public const MAX_TOTAL_BYTES = 20 * 1024 * 1024;

    public static function isOperator(User $user): bool
    {
        return MobileAccess::isAdmin($user) || MobileAccess::isDeveloper($user);
    }

    public static function isSuperAdmin(User $user): bool
    {
        return MobileAccess::isSuperAdmin($user);
    }

    /** ID seluruh akun Super Admin (di-cache per request). */
    public static function superAdminIds(): array
    {
        return once(fn () => User::superAdmins()->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    public static function isSuperAdminId(?int $userId): bool
    {
        return $userId !== null && in_array((int) $userId, self::superAdminIds(), true);
    }

    /** Chat pribadi antara non-Super Admin dan Super Admin disembunyikan dari pihak non-Super Admin. */
    protected static function hidesSuperAdminPeer(LiveChatThread $thread, User $viewer): bool
    {
        if (self::isSuperAdmin($viewer)) {
            return false;
        }
        $otherId = (int) $thread->user_id === (int) $viewer->id ? $thread->peer_id : $thread->user_id;
        return self::isSuperAdminId($otherId !== null ? (int) $otherId : null);
    }

    /** 'user' | 'peer' | 'operator' | null (tidak berhak). */
    public static function roleIn(LiveChatThread $thread, User $user): ?string
    {
        if ((int) $thread->user_id === (int) $user->id) {
            return $thread->isDirect() && self::hidesSuperAdminPeer($thread, $user) ? null : 'user';
        }
        if ($thread->isDirect()) {
            if ((int) $thread->peer_id !== (int) $user->id) {
                return null;
            }
            return self::hidesSuperAdminPeer($thread, $user) ? null : 'peer';
        }
        if ($thread->isAppSupport()) {
            return self::isSuperAdmin($user) ? 'operator' : null;
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
        $superAdmin = self::isSuperAdmin($user);
        $search = trim($search);

        $threads = LiveChatThread::with(['user', 'peer', 'latestMessage'])
            ->where(function ($q) use ($user, $operator, $superAdmin) {
                $q->where('user_id', $user->id)->orWhere('peer_id', $user->id);
                if ($operator) {
                    // Utas Operator Dinas (bukan Layanan Aplikasi).
                    $q->orWhere(fn ($s) => $s->whereNull('peer_id')->where(
                        fn ($c) => $c->whereNull('channel')->orWhere('channel', '!=', LiveChatThread::CHANNEL_APP)
                    ));
                }
                if ($superAdmin) {
                    // Utas Layanan Aplikasi hanya terlihat oleh Super Admin.
                    $q->orWhere(fn ($s) => $s->whereNull('peer_id')->where('channel', LiveChatThread::CHANNEL_APP));
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

        $items = $threads->map(fn ($t) => self::summary($t, $user))->filter()->values();
        if ($superAdmin) {
            // Permintaan Layanan Aplikasi yang menunggu konfirmasi selalu di atas.
            $items = $items->sortBy(fn ($i) => $i['can_respond'] ? 0 : 1)->values();
        }
        $items = $items->all();

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
                'can_send' => true,
                'can_call' => true,
                'can_respond' => false,
                'awaiting_confirmation' => false,
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
        $app = $t->isAppSupport();
        $other = self::otherParty($t, $viewer);
        $latest = $t->latestMessage;
        $awaiting = $app && $t->status === self::STATUS_PENDING && self::hasPendingRequest($t);

        if ($other) {
            $title = trim(($other->pangkat ? $other->pangkat . ' ' : '') . $other->name);
            $subtitle = $other->jabatan ?: ($other->nrp ? 'NRP ' . $other->nrp : null);
            if ($app) {
                $subtitle = self::APP_SUPPORT_NAME . ' · ' . self::appStatusLabel($t, $awaiting, true);
            }
        } else {
            $title = $app ? self::APP_SUPPORT_NAME : 'Operator Dinas';
            $subtitle = $app ? self::appStatusLabel($t, $awaiting, false) : 'Layanan & laporan ke Admin';
        }

        return [
            'uuid' => $t->uuid,
            'kind' => $t->isDirect() ? 'direct' : ($app ? 'app' : 'support'),
            'role' => $role,
            'title' => $title,
            'subtitle' => $subtitle,
            'avatar' => $other?->avatar,
            'partner' => $other ? self::userCard($other) : null,
            'status' => $t->status,
            'unread' => self::unreadFor($t, $role),
            'is_online' => $other ? self::isOnline($other->id) : false,
            'is_typing' => self::isTyping($t, $viewer),
            'last_message_at' => $t->last_message_at?->toIso8601String(),
            'last_message_human' => $t->last_message_at?->diffForHumans(),
            'latest_message' => $latest ? self::maskSender([
                'sender_id' => $latest->sender_id,
                'sender_type' => $latest->sender_type,
                'sender_name' => $latest->sender_name,
                'message' => $latest->message,
                'preview' => self::preview($latest),
                'created_at' => $latest->created_at?->toIso8601String(),
            ], $viewer) : null,
            'can_send' => self::sendBlockReason($t, $role) === null,
            'can_call' => self::canCall($t, $viewer),
            // Super Admin dapat menerima/menolak permintaan Layanan Aplikasi.
            'can_respond' => $app && $role === 'operator' && $t->status === self::STATUS_PENDING,
            'awaiting_confirmation' => $awaiting,
        ];
    }

    /** Status utas Layanan Aplikasi untuk subjudul daftar percakapan. */
    protected static function appStatusLabel(LiveChatThread $t, bool $awaiting, bool $forSuperAdmin): string
    {
        return match (true) {
            $t->status === 'OPEN' => $forSuperAdmin ? 'Sesi aktif' : 'Terhubung dengan Layanan Aplikasi',
            $awaiting => 'Menunggu konfirmasi',
            $t->status === self::STATUS_PENDING => $forSuperAdmin ? 'Belum ada permintaan' : 'Tulis pesan untuk mengajukan chat',
            default => $forSuperAdmin ? 'Sesi selesai' : 'Sesi selesai — kirim pesan untuk mengajukan lagi',
        };
    }

    /** Personel sudah mengirim permintaan (pesan) sejak sesi Layanan Aplikasi terakhir diajukan. */
    public static function hasPendingRequest(LiveChatThread $t): bool
    {
        return $t->messages()
            ->where('sender_id', $t->user_id)
            ->when($t->requested_at, fn ($q) => $q->where('created_at', '>=', $t->requested_at))
            ->exists();
    }

    /** Samarkan pengirim Super Admin menjadi "Layanan Aplikasi" bagi penonton non-Super Admin. */
    public static function maskSender(array $message, User $viewer): array
    {
        if (!self::isSuperAdmin($viewer) && self::isSuperAdminId(isset($message['sender_id']) ? (int) $message['sender_id'] : null)) {
            $message['sender_id'] = null;
            $message['sender_name'] = self::APP_SUPPORT_NAME;
        }
        return $message;
    }

    /** Pesan siap kirim ke klien (identitas Super Admin disamarkan). */
    public static function presentMessage(LiveChatMessage $m, User $viewer): array
    {
        return self::maskSender($m->toArray(), $viewer);
    }

    /** Alasan pesan tidak dapat dikirim oleh peran ini, atau null bila boleh. */
    public static function sendBlockReason(LiveChatThread $thread, ?string $role): ?string
    {
        if ($role === null) {
            return 'Akses ditolak.';
        }
        if ($thread->isAppSupport()) {
            // Personel hanya dapat mengirim satu pesan permintaan sampai Super Admin mengonfirmasi.
            if ($role === 'user' && $thread->status === self::STATUS_PENDING && self::hasPendingRequest($thread)) {
                return 'Permintaan chat Anda sedang menunggu konfirmasi Layanan Aplikasi.';
            }
            return null;
        }
        if ($thread->status === 'CLOSED') {
            return 'Sesi obrolan ini telah ditutup.';
        }
        return null;
    }

    /**
     * Panggilan suara/video tidak tersedia bila dapat membuka identitas Super Admin:
     * utas Layanan Aplikasi, atau Super Admin menelepon personel dari utas Operator Dinas.
     */
    public static function canCall(LiveChatThread $thread, User $caller): bool
    {
        if ($thread->isAppSupport()) {
            return false;
        }
        if (!$thread->isDirect() && self::isSuperAdmin($caller)) {
            return false;
        }
        return true;
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
        $base = fn () => LiveChatThread::where('user_id', $user->id)->whereNull('peer_id')
            ->where(fn ($c) => $c->whereNull('channel')->orWhere('channel', '!=', LiveChatThread::CHANNEL_APP));
        $thread = $base()->where('status', 'OPEN')->latest('last_message_at')->first()
            ?? $base()->latest('last_message_at')->first();

        return $thread ?? LiveChatThread::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => 'Pusat Komunikasi Dinas',
            'status' => 'OPEN',
            'channel' => LiveChatThread::CHANNEL_OPERATOR,
            'last_message_at' => now(),
            'unread_admin' => 0,
            'unread_user' => 0,
        ]);
    }

    /** Utas Layanan Aplikasi milik pengguna (satu utas per pengguna; dibuat bila belum ada). */
    public static function appThreadFor(User $user): LiveChatThread
    {
        abort_if(self::isSuperAdmin($user), 422, 'Super Admin membalas Layanan Aplikasi, bukan mengajukannya.');

        $thread = LiveChatThread::where('user_id', $user->id)->whereNull('peer_id')
            ->where('channel', LiveChatThread::CHANNEL_APP)->latest('id')->first();

        return $thread ?? LiveChatThread::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => self::APP_SUPPORT_NAME,
            'status' => self::STATUS_PENDING,
            'channel' => LiveChatThread::CHANNEL_APP,
            'requested_at' => now(),
            'last_message_at' => now(),
            'unread_admin' => 0,
            'unread_user' => 0,
        ]);
    }

    /**
     * Super Admin menerima atau menolak permintaan chat Layanan Aplikasi.
     */
    public static function respondAppRequest(LiveChatThread $thread, User $superAdmin, bool $accept): void
    {
        abort_unless($thread->isAppSupport() && self::isSuperAdmin($superAdmin), 403, 'Hanya Layanan Aplikasi yang dapat mengonfirmasi permintaan ini.');
        abort_unless($thread->status === self::STATUS_PENDING, 422, 'Permintaan ini sudah dikonfirmasi.');

        $thread->update(['status' => $accept ? 'OPEN' : 'CLOSED', 'operator_id' => $superAdmin->id]);
        self::systemMessage($thread, $accept
            ? 'Permintaan chat diterima. Layanan Aplikasi siap membantu Anda.'
            : 'Permintaan chat ditolak oleh Layanan Aplikasi. Anda dapat mengajukan lagi nanti.');
    }

    /** Super Admin mengakhiri / membuka kembali sesi Layanan Aplikasi. */
    public static function setAppSessionOpen(LiveChatThread $thread, bool $open): void
    {
        $thread->update(['status' => $open ? 'OPEN' : 'CLOSED']);
        self::systemMessage($thread, $open
            ? 'Sesi chat dibuka kembali oleh Layanan Aplikasi.'
            : 'Sesi chat diakhiri oleh Layanan Aplikasi. Kirim pesan baru untuk mengajukan chat lagi.');
    }

    public static function systemMessage(LiveChatThread $thread, string $text): LiveChatMessage
    {
        $message = LiveChatMessage::create([
            'thread_id' => $thread->id,
            'sender_type' => 'SYSTEM',
            'sender_name' => self::APP_SUPPORT_NAME,
            'message' => $text,
            'is_read' => false,
        ]);
        $thread->update(['last_message_at' => now(), 'unread_user' => (int) $thread->unread_user + 1]);
        return $message;
    }

    /** Percakapan langsung dua personel (satu utas untuk setiap pasangan). */
    public static function directThread(User $me, User $other): LiveChatThread
    {
        abort_if((int) $me->id === (int) $other->id, 422, 'Tidak dapat mengirim pesan ke diri sendiri.');
        // Super Admin tidak dapat dihubungi lewat chat pribadi (identitasnya dirahasiakan),
        // dan menghubungi personel lewat Layanan Aplikasi.
        if (self::isSuperAdmin($other) && !self::isSuperAdmin($me)) {
            abort(404, 'Personel tidak ditemukan.');
        }
        abort_if(self::isSuperAdmin($me) && !self::isSuperAdmin($other), 422, 'Super Admin menghubungi personel melalui Layanan Aplikasi.');

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

    /**
     * Kontak yang dapat dihubungi: seluruh personel aktif selain diri sendiri.
     * Super Admin tidak pernah muncul bagi non-Super Admin; Super Admin hanya melihat sesama Super Admin.
     */
    public static function contacts(User $me, string $q = '', int $limit = 30): array
    {
        $q = trim($q);
        return User::query()
            ->where('id', '!=', $me->id)
            ->where('is_active', true)
            ->when(self::isSuperAdmin($me), fn ($s) => $s->superAdmins(), fn ($s) => $s->visibleTo($me))
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
        if ($blocked = self::sendBlockReason($thread, $role)) {
            return $blocked;
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

        if ($thread->isAppSupport()) {
            if ($role === 'user' && $thread->status !== 'OPEN' && $thread->status !== self::STATUS_PENDING) {
                // Sesi selesai/ditolak → pesan ini menjadi permintaan chat baru.
                $thread->update(['status' => self::STATUS_PENDING, 'requested_at' => now()]);
            } elseif ($role === 'operator' && $thread->status !== 'OPEN') {
                // Super Admin langsung membalas = menerima permintaan.
                $wasPending = $thread->status === self::STATUS_PENDING;
                $thread->update(['status' => 'OPEN', 'operator_id' => $sender->id]);
                if ($wasPending) {
                    self::systemMessage($thread, 'Permintaan chat diterima. Layanan Aplikasi siap membantu Anda.');
                }
            }
        }

        // Nama Super Admin tidak pernah disimpan di utas layanan, agar tidak bocor ke notifikasi/klien lama.
        $senderName = !$thread->isDirect() && self::isSuperAdmin($sender)
            ? self::APP_SUPPORT_NAME
            : trim(($sender->pangkat ? $sender->pangkat . ' ' : '') . $sender->name);

        $message = LiveChatMessage::create([
            'thread_id' => $thread->id,
            'sender_type' => $role === 'user' ? 'USER' : 'ADMIN',
            'sender_id' => $sender->id,
            'sender_name' => $senderName,
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
        } elseif ($thread->isAppSupport() && (int) $senderId === (int) $thread->user_id) {
            $ids = self::superAdminIds();
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
        if (!self::canCall($thread, $caller)) {
            return null;
        }
        if ($thread->isDirect()) {
            return (int) ((int) $thread->user_id === (int) $caller->id ? $thread->peer_id : $thread->user_id);
        }
        if ((int) $thread->user_id !== (int) $caller->id) {
            return (int) $thread->user_id;
        }
        // Super Admin tidak pernah ditelepon personel (identitasnya akan terlihat di layar panggilan).
        if ($thread->operator_id && !self::isSuperAdminId((int) $thread->operator_id)) {
            return (int) $thread->operator_id;
        }
        // Operator yang sedang daring lebih dulu, lalu operator mana pun.
        $operators = User::query()->where('is_active', true)->get()
            ->filter(fn ($u) => self::isOperator($u) && !self::isSuperAdmin($u));
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
            'messages' => $messages->map(fn ($m) => self::presentMessage($m, $viewer))->values(),
            'is_typing' => self::isTyping($thread, $viewer),
            'call' => Cache::get("live_chat:call:{$thread->uuid}"),
        ];
    }

    public static function totalUnread(User $user): int
    {
        return (int) collect(self::listFor($user, '', 200))->sum('unread');
    }
}
