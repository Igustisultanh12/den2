<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Chat\LiveChatController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SpJagaController;
use App\Models\LiveChatThread;
use App\Models\SignatureRequest;
use App\Models\SpJaga;
use App\Support\MobileAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * API mobile untuk Chat Dinas, panggilan video/suara (Agora), pratinjau
 * dokumen TTE, dan TTE SP Jaga.
 *
 * Logika chat & sinyal panggilan memakai LiveChatController milik web agar
 * data dan perilaku identik (panggilan Android ↔ web saling terhubung).
 * Endpoint ini menambahkan pemeriksaan kepemilikan utas yang di web belum ada.
 */
class MobileCommsController extends Controller
{
    private function chat(): LiveChatController
    {
        return app(LiveChatController::class);
    }

    private function isOperator(Request $request): bool
    {
        return MobileAccess::can($request->user(), 'chat.manage');
    }

    /** Utas yang boleh diakses: operator → semua; personel → miliknya saja. */
    private function thread(Request $request, string $uuid): LiveChatThread
    {
        $thread = LiveChatThread::where('uuid', $uuid)->firstOrFail();
        if (!$this->isOperator($request) && (int) $thread->user_id !== (int) $request->user()->id) {
            abort(403, 'Anda tidak memiliki akses ke percakapan ini.');
        }
        return $thread;
    }

    private function presence(int $userId): void
    {
        Cache::put("live_chat:user_presence:{$userId}", now()->timestamp, now()->addMinutes(5));
    }

    // ================================================================ CHAT

    /**
     * Halaman awal chat. Operator: daftar utas. Personel: utas miliknya
     * (dibuat otomatis seperti halaman web) beserta pesan.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $this->presence($user->id);

        if ($this->isOperator($request)) {
            $response = $this->chat()->sync($request);
            $data = $response->getData(true);
            return response()->json(['status' => 'success', 'is_operator' => true] + $data);
        }

        $thread = $this->ownThread($user->id);
        if (!$thread) {
            $thread = LiveChatThread::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'subject' => 'Pusat Komunikasi Dinas',
                'status' => 'OPEN',
                'last_message_at' => now(),
                'unread_admin' => 0,
                'unread_user' => 0,
            ]);
        }
        if ($thread->unread_user > 0) {
            $thread->update(['unread_user' => 0]);
        }
        $thread->messages()->where('sender_type', '!=', 'USER')->where('is_read', false)->update(['is_read' => true]);

        return response()->json([
            'status' => 'success',
            'is_operator' => false,
            'thread' => $thread->only(['id', 'uuid', 'subject', 'status', 'unread_user']),
            'messages' => $thread->messages()->orderBy('id')->get(),
            'is_typing' => Cache::has("live_chat:typing:{$thread->uuid}:ADMIN"),
            'incoming_call' => Cache::get("live_chat:user_call:{$user->id}"),
        ]);
    }

    /** Polling: format sama dengan /live-chat/sync milik web. */
    public function sync(Request $request)
    {
        if ($this->isOperator($request)) {
            if ($request->filled('uuid')) {
                $this->thread($request, (string) $request->input('uuid'));
            }
            return $this->chat()->sync($request);
        }

        // Personel: utas yang diminta (bukan sekadar utas pertama seperti di web),
        // agar utas baru yang dibuka operator setelah utas lama ditutup tetap sinkron.
        $user = $request->user();
        $this->presence($user->id);
        $thread = $request->filled('uuid')
            ? $this->thread($request, (string) $request->input('uuid'))
            : $this->ownThread($user->id);

        $messages = [];
        if ($thread) {
            if ($thread->unread_user > 0) {
                $thread->update(['unread_user' => 0]);
            }
            $thread->messages()->where('sender_type', '!=', 'USER')->where('is_read', false)->update(['is_read' => true]);
            $messages = $thread->messages()->orderBy('id')->get();
        }

        return response()->json([
            'thread' => $thread,
            'messages' => $messages,
            'is_typing' => $thread ? Cache::has("live_chat:typing:{$thread->uuid}:ADMIN") : false,
            'call' => $thread ? Cache::get("live_chat:call:{$thread->uuid}") : null,
            'incoming_call' => Cache::get("live_chat:user_call:{$user->id}"),
        ]);
    }

    private function ownThread(int $userId): ?LiveChatThread
    {
        return LiveChatThread::where('user_id', $userId)->where('status', 'OPEN')->latest('last_message_at')->first()
            ?? LiveChatThread::where('user_id', $userId)->latest('last_message_at')->first();
    }

    public function messages(Request $request, string $uuid)
    {
        $this->thread($request, $uuid);
        return $this->chat()->getMessages($request, $uuid);
    }

    public function send(Request $request, string $uuid)
    {
        $this->thread($request, $uuid);
        try {
            return $this->chat()->sendMessage($request, $uuid);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => collect($e->errors())->flatten()->first()], 422);
        }
    }

    public function typing(Request $request, string $uuid)
    {
        $this->thread($request, $uuid);
        return $this->chat()->typing($request, $uuid);
    }

    /** Jumlah pesan belum dibaca + pesan terakhir (untuk notifikasi HP). */
    public function unread(Request $request)
    {
        $user = $request->user();
        if ($this->isOperator($request)) {
            $latest = LiveChatThread::with('latestMessage')->where('unread_admin', '>', 0)->orderByDesc('last_message_at')->first();
            return response()->json([
                'status' => 'success',
                'unread' => (int) LiveChatThread::sum('unread_admin'),
                'latest' => $latest?->latestMessage?->only(['id', 'sender_name', 'message', 'created_at']),
                'thread_uuid' => $latest?->uuid,
            ]);
        }
        $thread = LiveChatThread::with('latestMessage')->where('user_id', $user->id)->orderByDesc('last_message_at')->first();
        return response()->json([
            'status' => 'success',
            'unread' => (int) ($thread?->unread_user ?? 0),
            'latest' => $thread?->latestMessage?->only(['id', 'sender_name', 'message', 'created_at']),
            'thread_uuid' => $thread?->uuid,
        ]);
    }

    // ---------------------------------------------------- operator saja

    public function searchUsers(Request $request)
    {
        return $this->chat()->searchUsers($request);
    }

    public function start(Request $request)
    {
        try {
            return $this->chat()->startChat($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => collect($e->errors())->flatten()->first()], 422);
        }
    }

    public function toggleStatus(Request $request, string $uuid)
    {
        return $this->chat()->toggleStatus($request, $uuid);
    }

    public function destroy(Request $request, string $uuid)
    {
        $thread = LiveChatThread::where('uuid', $uuid)->firstOrFail();
        $thread->purgeFiles();
        $thread->messages()->delete();
        $thread->delete();
        return response()->json(['status' => 'success', 'message' => 'Utas percakapan dinas berhasil dihapus.']);
    }

    // ================================================================ PANGGILAN

    public function incomingCall(Request $request)
    {
        $user = $request->user();
        $this->presence($user->id);
        $call = Cache::get("live_chat:user_call:{$user->id}");
        return response()->json(['status' => 'success', 'call' => $call ?: null]);
    }

    public function initiateCall(Request $request, string $uuid)
    {
        $this->thread($request, $uuid);
        return $this->chat()->initiateCall($request, $uuid);
    }

    public function callSignal(Request $request, string $uuid)
    {
        $this->thread($request, $uuid);
        return $this->chat()->getCallSignal($request, $uuid);
    }

    public function sendCallSignal(Request $request, string $uuid)
    {
        $this->thread($request, $uuid);
        return $this->chat()->sendCallSignal($request, $uuid);
    }

    public function endCall(Request $request, string $uuid)
    {
        $this->thread($request, $uuid);
        return $this->chat()->endCall($request, $uuid);
    }

    public function agoraToken(Request $request, string $uuid)
    {
        $this->thread($request, $uuid);
        return $this->chat()->getAgoraToken($request, $uuid);
    }

    /** Halaman media panggilan (Agora Web SDK) untuk WebView aplikasi. */
    public function viconPage()
    {
        return response()
            ->view('mobile.vicon')
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }

    // ================================================================ PRATINJAU TTE

    /** Dokumen yang diajukan untuk TTE: Komandan/Admin (signature.approve) atau pengajunya. */
    public function signaturePdf(Request $request, int $id)
    {
        $user = $request->user();
        $sr = SignatureRequest::findOrFail($id);
        $canApprove = MobileAccess::can($user, 'signature.approve');
        if (!$canApprove && (int) $sr->user_id !== (int) $user->id) {
            return response()->json(['status' => 'error', 'code' => 'forbidden', 'message' => 'Hanya Komandan, Admin, atau pengaju yang dapat melihat dokumen ini.'], 403);
        }
        $path = (string) $sr->file_path;
        if ($path === '' || str_contains($path, '..') || !Storage::disk('public')->exists($path)) {
            return response()->json(['status' => 'error', 'message' => 'Berkas dokumen tidak ditemukan.'], 404);
        }
        // Belum disahkan: tampilkan pratinjau dengan QR TTE di posisi yang dipilih pengaju.
        $content = null;
        if (!in_array(strtolower((string) $sr->status), ['approved', 'signed', 'selesai'], true)) {
            try {
                $content = \App\Http\Controllers\SignatureRequestController::renderPreview($sr);
            } catch (\Throwable $e) {
                Log::warning('[TTE] Pratinjau QR gagal, kirim dokumen asli: ' . $e->getMessage());
            }
        }
        return response($content ?? Storage::disk('public')->get($path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="TTE_' . $sr->id . '.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    // ================================================================ TTE SP JAGA

    public function pendingSpJaga(Request $request)
    {
        $items = SpJaga::where('status', 'pending_signature')->orderByDesc('id')->get()->map(fn (SpJaga $sp) => [
            'id' => $sp->id,
            'nomor_sprin' => $sp->nomor_sprin,
            'bulan' => (int) $sp->bulan,
            'tahun' => (int) $sp->tahun,
            'status' => $sp->status,
            'total_personel' => (int) ($sp->total_personel_count ?? 0),
            'tanggal_surat' => $sp->tanggal_surat instanceof \DateTimeInterface ? $sp->tanggal_surat->format('Y-m-d') : (string) $sp->tanggal_surat,
        ]);
        return response()->json(['status' => 'success', 'data' => $items]);
    }

    public function approveSpJaga(Request $request, int $id)
    {
        $sp = SpJaga::findOrFail($id);
        if ($sp->status !== 'pending_signature') {
            return response()->json(['status' => 'error', 'message' => 'SP Jaga ini tidak sedang menunggu tanda tangan.'], 422);
        }
        return mobile_delegate(
            fn () => app(SpJagaController::class)->approveTte($request, $id),
            'Surat Perintah Jaga berhasil disahkan.'
        );
    }
}
