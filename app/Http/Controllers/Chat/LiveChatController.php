<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\LiveChatThread;
use App\Models\LiveChatMessage;
use App\Models\User;
use App\Models\Setting;
use App\Services\AgoraTokenService;
use App\Support\ChatConversations as Chat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Chat Dinas gaya WhatsApp: setiap personel dapat mengobrol dengan personel lain
 * (chat pribadi) maupun dengan Operator Dinas (utas layanan). Logika bersama ada di
 * App\Support\ChatConversations agar web dan aplikasi Android identik.
 */
class LiveChatController extends Controller
{
    /** Halaman Chat Dinas (satu halaman untuk semua peran). */
    public function index(Request $request)
    {
        $user = Auth::user();
        Chat::touchPresence($user->id);

        $active = null;
        if ($uuid = $request->input('uuid')) {
            $thread = LiveChatThread::with(['user', 'peer'])->where('uuid', $uuid)->first();
            if ($thread && Chat::roleIn($thread, $user)) {
                $active = Chat::detail($thread, $user);
            }
        }

        return Inertia::render('Chat/Index', [
            'conversations' => Chat::listFor($user),
            'initialActive' => $active,
            'initialCall' => Cache::get("live_chat:user_call:{$user->id}"),
            'isOperator' => Chat::isOperator($user),
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'pangkat' => $user->pangkat,
                'avatar' => $user->avatar,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Polling: daftar percakapan + percakapan aktif (?uuid=) + panggilan masuk.
     * ?after=<id pesan terakhir> hanya mengirim pesan baru.
     */
    public function sync(Request $request)
    {
        $user = Auth::user();
        Chat::touchPresence($user->id);

        $active = null;
        if ($uuid = $request->input('uuid')) {
            $thread = Chat::find((string) $uuid, $user);
            $active = Chat::detail($thread, $user, $request->integer('after') ?: null);
        }

        $conversations = Chat::listFor($user, (string) $request->input('search', ''));

        return response()->json([
            'conversations' => $conversations,
            'total_unread' => (int) collect($conversations)->sum('unread'),
            'active' => $active,
            'incoming_call' => Cache::get("live_chat:user_call:{$user->id}"),
        ]);
    }

    public function getMessages(Request $request, $uuid)
    {
        $user = Auth::user();
        $thread = Chat::find((string) $uuid, $user);
        return response()->json(Chat::detail($thread, $user));
    }

    /** Kirim pesan teks, lampiran, atau pesan suara (voice_note=1 + duration_ms). */
    public function sendMessage(Request $request, $uuid)
    {
        $user = Auth::user();
        $thread = Chat::find((string) $uuid, $user);

        $request->validate([
            'message' => 'nullable|string|max:5000',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file',
            'voice_note' => 'nullable|boolean',
            'duration_ms' => 'nullable|integer|min:0|max:1800000',
        ]);

        $voice = $request->boolean('voice_note') ? (int) $request->input('duration_ms', 0) : null;
        $result = Chat::send($thread, $user, $request->input('message'), $request->file('attachments', []), $voice);

        if (is_string($result)) {
            return response()->json(['success' => false, 'error' => $result, 'message' => $result], 422);
        }

        return response()->json(['success' => true, 'message' => $result]);
    }

    public function typing(Request $request, $uuid)
    {
        $user = Auth::user();
        Chat::setTyping(Chat::find((string) $uuid, $user), $user);
        return response()->json(['success' => true]);
    }

    /** Daftar personel yang dapat dihubungi (semua personel aktif). */
    public function searchUsers(Request $request)
    {
        return response()->json(['users' => Chat::contacts(Auth::user(), (string) $request->input('q', ''))]);
    }

    /**
     * Buka/buat percakapan: user_id → chat pribadi dengan personel tersebut;
     * support=1 → utas layanan Operator Dinas milik pengguna.
     */
    public function startChat(Request $request)
    {
        $user = Auth::user();
        if ($request->boolean('support')) {
            $thread = Chat::supportThreadFor($user);
        } else {
            $request->validate(['user_id' => 'required|integer|exists:users,id']);
            $target = User::findOrFail($request->integer('user_id'));
            if (!$target->is_active) {
                return response()->json(['success' => false, 'message' => 'Akun personel tersebut tidak aktif.'], 422);
            }
            $thread = Chat::directThread($user, $target);
        }

        return response()->json(['success' => true, 'uuid' => $thread->uuid]);
    }

    /** Buka/tutup utas layanan (khusus operator). */
    public function toggleStatus(Request $request, $uuid)
    {
        $user = Auth::user();
        $thread = Chat::find((string) $uuid, $user);
        abort_unless(!$thread->isDirect() && Chat::isOperator($user), 403, 'Hanya operator yang dapat menutup utas layanan.');

        $newStatus = $thread->status === 'OPEN' ? 'CLOSED' : 'OPEN';
        $thread->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'status' => $newStatus,
            'message' => $newStatus === 'CLOSED' ? 'Sesi obrolan dinas telah ditutup.' : 'Sesi obrolan dinas telah dibuka kembali.',
        ]);
    }

    /** Hapus percakapan: utas layanan oleh operator; chat pribadi oleh salah satu peserta. */
    public function destroy(Request $request, $uuid)
    {
        $user = Auth::user();
        $thread = Chat::find((string) $uuid, $user);
        abort_unless($thread->isDirect() || Chat::isOperator($user), 403, 'Hanya operator yang dapat menghapus utas layanan.');

        $thread->purgeFiles();
        $thread->messages()->delete();
        $thread->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Percakapan berhasil dihapus.']);
        }
        return redirect()->route('chat.index')->with('success', 'Percakapan berhasil dihapus.');
    }

    /** Unduh lampiran — hanya peserta percakapan pemilik berkas. */
    public function downloadAttachment(Request $request)
    {
        $path = (string) $request->query('path', '');
        if (!preg_match('#^chat_attachments/([0-9a-fA-F-]{36})/[^/]+$#', $path, $m) || str_contains($path, '..')) {
            abort(404, 'Berkas lampiran tidak ditemukan.');
        }
        Chat::find($m[1], Auth::user());
        abort_unless(Storage::disk('public')->exists($path), 404, 'Berkas lampiran tidak ditemukan.');

        return Storage::disk('public')->download($path);
    }

    // ==========================================
    // PANGGILAN VIDEO & SUARA (Agora; sinyal lewat cache)
    // ==========================================

    private function partyCard(?User $u, string $type, string $fallbackName): array
    {
        return [
            'type' => $type,
            'id' => $u?->id,
            'name' => $u?->name ?? $fallbackName,
            'pangkat' => $u?->pangkat ?? ($type === 'ADMIN' ? 'Pengelola Dinas' : 'TNI AL'),
            'avatar' => $u?->avatar,
        ];
    }

    public function initiateCall(Request $request, $uuid)
    {
        $user = Auth::user();
        $thread = Chat::find((string) $uuid, $user);
        $role = Chat::roleIn($thread, $user);

        $calleeUserId = Chat::calleeId($thread, $user);
        if (!$calleeUserId) {
            return response()->json(['success' => false, 'message' => 'Belum ada penerima panggilan yang tersedia.'], 422);
        }
        $callee = User::find($calleeUserId);
        $calleeName = !$thread->isDirect() && $role === 'user' ? 'Operator Dinas Sinden 2' : 'Personel';

        $callData = [
            'call_id' => (string) Str::uuid(),
            'thread_uuid' => $thread->uuid,
            'status' => 'RINGING', // RINGING, ACCEPTED, CONNECTED, REJECTED, ENDED
            'caller' => $this->partyCard($user, $role === 'user' ? 'USER' : 'ADMIN', 'Personel'),
            'callee' => $this->partyCard($callee, $role === 'user' ? 'ADMIN' : 'USER', $calleeName),
            'call_type' => $request->input('call_type') === 'audio' ? 'audio' : 'video',
            'offer' => null,
            'answer' => null,
            'created_at' => now()->timestamp,
            'started_at' => null,
            'ended_at' => null,
            'callee_user_id' => $calleeUserId,
        ];

        Cache::forget("live_chat:call:{$thread->uuid}:candidates_caller");
        Cache::forget("live_chat:call:{$thread->uuid}:candidates_callee");
        Cache::put("live_chat:call:{$thread->uuid}", $callData, now()->addMinutes(10));
        Cache::put("live_chat:user_call:{$calleeUserId}", $callData, now()->addSeconds(45));
        // Dering di HP penerima walau aplikasi tertutup (FCM).
        \App\Support\PushNotifier::incomingCall((int) $calleeUserId, $callData);

        return response()->json(['success' => true, 'call' => $callData]);
    }

    public function getCallSignal(Request $request, $uuid)
    {
        Chat::find((string) $uuid, Auth::user());
        $callData = Cache::get("live_chat:call:{$uuid}");

        if ($callData) {
            $callData['candidates_caller'] = Cache::get("live_chat:call:{$uuid}:candidates_caller", []);
            $callData['candidates_callee'] = Cache::get("live_chat:call:{$uuid}:candidates_callee", []);
        }

        return response()->json(['call' => $callData]);
    }

    public function sendCallSignal(Request $request, $uuid)
    {
        $user = Auth::user();
        Chat::find((string) $uuid, $user);
        $callData = Cache::get("live_chat:call:{$uuid}");

        if (!$callData) {
            return response()->json(['error' => 'Sesi panggilan tidak ditemukan atau telah kedaluwarsa.'], 404);
        }

        $newStatus = $request->input('status');
        $action = $request->input('action');
        if (!$newStatus && $action) {
            $newStatus = ['accept' => 'ACCEPTED', 'connected' => 'CONNECTED', 'reject' => 'REJECTED'][$action] ?? null;
        }
        if ($newStatus && !in_array($newStatus, ['ACCEPTED', 'CONNECTING', 'CONNECTED', 'REJECTED'], true)) {
            $newStatus = null;
        }

        $previousStatus = $callData['status'] ?? null;
        if ($newStatus) {
            $callData['status'] = $newStatus;
            if ($newStatus === 'CONNECTED' && empty($callData['started_at'])) {
                $callData['started_at'] = now()->timestamp;
            }
        }
        if ($answer = $request->input('answer')) {
            $callData['answer'] = $answer;
            if ($callData['status'] === 'RINGING') {
                $callData['status'] = 'ACCEPTED';
            }
        }

        // Sisi pemanggil ditentukan dari ORANGNYA (bukan peran), agar panggilan antar
        // dua personel biasa tidak tertukar.
        $newCandidates = $request->input('candidates', []);
        if (is_array($newCandidates) && $newCandidates !== []) {
            $isCaller = (int) ($callData['caller']['id'] ?? 0) === (int) $user->id;
            $key = $isCaller ? "live_chat:call:{$uuid}:candidates_caller" : "live_chat:call:{$uuid}:candidates_callee";
            Cache::put($key, array_merge((array) Cache::get($key, []), $newCandidates), now()->addMinutes(10));
        }

        Cache::put("live_chat:call:{$uuid}", $callData, now()->addMinutes(10));

        // Dering dihentikan begitu panggilan diterima/ditolak (juga di HP lain milik penerima).
        if ($previousStatus === 'RINGING' && $callData['status'] !== 'RINGING') {
            $calleeId = $callData['callee_user_id'] ?? $callData['callee']['id'] ?? null;
            if ($calleeId) {
                Cache::forget("live_chat:user_call:{$calleeId}");
            }
            \App\Support\PushNotifier::callEnded($calleeId ? (int) $calleeId : null, $callData);
        }

        return response()->json(['success' => true, 'call' => $callData]);
    }

    public function endCall(Request $request, $uuid)
    {
        $user = Auth::user();
        $thread = Chat::find((string) $uuid, $user);
        $callData = Cache::get("live_chat:call:{$uuid}");
        $reason = (string) $request->input('reason', 'ended'); // ended | rejected | canceled | missed
        $durationSeconds = max(0, (int) $request->input('duration_seconds', 0));

        Cache::forget("live_chat:call:{$uuid}:candidates_caller");
        Cache::forget("live_chat:call:{$uuid}:candidates_callee");

        $alreadyEnded = $callData && in_array($callData['status'] ?? '', ['ENDED', 'REJECTED'], true);
        if ($callData) {
            $callData['status'] = $reason === 'rejected' ? 'REJECTED' : 'ENDED';
            $callData['ended_at'] = now()->timestamp;
            Cache::put("live_chat:call:{$uuid}", $callData, now()->addSeconds(30));

            $calleeId = $callData['callee_user_id'] ?? $callData['callee']['id'] ?? null;
            if ($calleeId) {
                Cache::forget("live_chat:user_call:{$calleeId}");
            }
            \App\Support\PushNotifier::callEnded($calleeId ? (int) $calleeId : null, $callData);
        }

        // Satu catatan riwayat per panggilan (kedua sisi bisa sama-sama menekan "tutup").
        if ($callData && !$alreadyEnded) {
            $kind = ($callData['call_type'] ?? 'video') === 'audio' ? 'suara' : 'video';
            $logMsg = match (true) {
                $durationSeconds > 0 => sprintf('Panggilan %s selesai. (Durasi: %02d:%02d)', $kind, intdiv($durationSeconds, 60), $durationSeconds % 60),
                $reason === 'rejected' => "Panggilan {$kind} ditolak.",
                $reason === 'canceled' => "Panggilan {$kind} dibatalkan.",
                default => "Panggilan {$kind} tidak terjawab.",
            };

            LiveChatMessage::create([
                'thread_id' => $thread->id,
                'sender_type' => 'SYSTEM',
                'sender_name' => 'Sistem Vicon Dinas',
                'message' => $logMsg,
                'is_read' => true,
            ]);
            $thread->update(['last_message_at' => now()]);
        }

        return response()->json(['success' => true, 'message' => 'Panggilan berhasil diakhiri.']);
    }

    /** Kredensial Agora RTC (token bertanda tangan bila App Certificate diatur). */
    public function getAgoraToken(Request $request, $uuid)
    {
        $user = Auth::user();
        $thread = Chat::find((string) $uuid, $user);
        $channelName = 'SINDEN_' . preg_replace('/[^a-zA-Z0-9]/', '', $thread->uuid);
        // UID selalu ID akun: token Agora terikat ke UID, jadi klien tidak boleh memilih sendiri.
        $uid = (int) $user->id;

        $appId = Setting::where('key', 'agora_app_id')->value('value') ?: config('services.agora.app_id');

        return response()->json([
            'success' => true,
            'appId' => $appId,
            'channel' => $channelName,
            'token' => AgoraTokenService::generateToken($channelName, $uid),
            'uid' => $uid,
        ]);
    }

    public function getIceServers(Request $request)
    {
        return response()->json([
            'iceServers' => [
                ['urls' => 'stun:stun.l.google.com:19302'],
                ['urls' => 'stun:stun1.l.google.com:19302'],
                ['urls' => 'stun:stun.cloudflare.com:3478'],
            ],
        ]);
    }
}
