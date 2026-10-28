<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Chat\LiveChatController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SpJagaController;
use App\Models\LiveChatThread;
use App\Models\SignatureRequest;
use App\Models\SpJaga;
use App\Support\ChatConversations as Chat;
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

    /** Pengecekan akses dilakukan ChatConversations (peserta utas / operator utas layanan). */
    private function thread(Request $request, string $uuid): LiveChatThread
    {
        return Chat::find($uuid, $request->user());
    }

    // ================================================================ CHAT

    /** Daftar percakapan (chat pribadi + Operator Dinas) gaya WhatsApp. */
    public function index(Request $request)
    {
        $user = $request->user();
        Chat::touchPresence($user->id);
        $conversations = Chat::listFor($user, (string) $request->input('search', ''));

        return response()->json([
            'status' => 'success',
            'is_operator' => Chat::isOperator($user),
            'me' => ['id' => $user->id],
            'conversations' => $conversations,
            'total_unread' => (int) collect($conversations)->sum('unread'),
            'incoming_call' => Cache::get("live_chat:user_call:{$user->id}"),
        ]);
    }

    /** Polling: ?uuid= percakapan aktif, ?after= hanya pesan baru. */
    public function sync(Request $request)
    {
        return $this->chat()->sync($request);
    }

    public function messages(Request $request, string $uuid)
    {
        return $this->chat()->getMessages($request, $uuid);
    }

    public function send(Request $request, string $uuid)
    {
        try {
            return $this->chat()->sendMessage($request, $uuid);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => collect($e->errors())->flatten()->first()], 422);
        }
    }

    public function typing(Request $request, string $uuid)
    {
        return $this->chat()->typing($request, $uuid);
    }

    /** Jumlah pesan belum dibaca + pesan terakhir (untuk notifikasi HP). */
    public function unread(Request $request)
    {
        $user = $request->user();
        $conversations = collect(Chat::listFor($user, '', 200));
        $latest = $conversations->where('unread', '>', 0)->first();

        return response()->json([
            'status' => 'success',
            'unread' => (int) $conversations->sum('unread'),
            'latest' => $latest && $latest['latest_message'] ? [
                'id' => null,
                'sender_name' => $latest['latest_message']['sender_name'],
                'message' => $latest['latest_message']['preview'],
                'created_at' => $latest['latest_message']['created_at'],
            ] : null,
            'thread_uuid' => $latest['uuid'] ?? null,
        ]);
    }

    /** Kontak: seluruh personel aktif (semua pengguna chat). */
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
        $request->headers->set('Accept', 'application/json');
        $this->chat()->destroy($request, $uuid);
        return response()->json(['status' => 'success', 'message' => 'Percakapan berhasil dihapus.']);
    }

    // ================================================================ PANGGILAN

    public function incomingCall(Request $request)
    {
        $user = $request->user();
        Chat::touchPresence($user->id);
        $call = Cache::get("live_chat:user_call:{$user->id}");
        return response()->json(['status' => 'success', 'call' => $call ?: null]);
    }

    public function initiateCall(Request $request, string $uuid)
    {
        return $this->chat()->initiateCall($request, $uuid);
    }

    public function callSignal(Request $request, string $uuid)
    {
        return $this->chat()->getCallSignal($request, $uuid);
    }

    public function sendCallSignal(Request $request, string $uuid)
    {
        return $this->chat()->sendCallSignal($request, $uuid);
    }

    public function endCall(Request $request, string $uuid)
    {
        return $this->chat()->endCall($request, $uuid);
    }

    public function agoraToken(Request $request, string $uuid)
    {
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
