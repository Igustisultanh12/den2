<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\LiveChatThread;
use App\Models\LiveChatMessage;
use App\Models\User;
use App\Models\Setting;
use App\Services\AgoraTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class LiveChatController extends Controller
{
    /**
     * Halaman Utama Pusat Komunikasi Dinas & Panggilan Video
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $isAdmin = $user->isAdmin() || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';

        if ($isAdmin) {
            return $this->adminView($request, $user);
        }

        return $this->userView($request, $user);
    }

    /**
     * Tampilan untuk Administrator / Operator Dinas
     */
    protected function adminView(Request $request, User $user)
    {
        $this->touchUserPresence($user->id);

        $status = $request->input('status', 'all');
        $search = trim((string) $request->input('search', ''));
        $selectedUuid = $request->input('uuid');

        $query = LiveChatThread::with(['user', 'latestMessage'])
            ->when($status !== 'all', function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('subject', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                               ->orWhere('nrp', 'like', "%{$search}%")
                               ->orWhere('pangkat', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('last_message_at', 'desc')
            ->orderBy('id', 'desc');

        $threads = $query->paginate(20)->withQueryString();

        $threads->getCollection()->transform(function ($th) {
            $isOnline = Cache::has("live_chat:user_presence:{$th->user_id}");
            $isTyping = Cache::has("live_chat:typing:{$th->uuid}:USER");

            return [
                'id' => $th->id,
                'uuid' => $th->uuid,
                'subject' => $th->subject,
                'status' => $th->status,
                'last_message_at' => $th->last_message_at?->diffForHumans() ?? 'Belum ada pesan',
                'unread_admin' => $th->unread_admin,
                'is_online' => $isOnline,
                'is_typing' => $isTyping,
                'user' => [
                    'id' => $th->user?->id,
                    'name' => $th->user?->name ?? 'Personel',
                    'pangkat' => $th->user?->pangkat ?? 'TNI AL',
                    'nrp' => $th->user?->nrp ?? '-',
                    'avatar' => $th->user?->avatar,
                    'role' => $th->user?->role ?? 'user',
                ],
                'latest_message' => $th->latestMessage ? [
                    'sender_name' => $th->latestMessage->sender_name,
                    'sender_type' => $th->latestMessage->sender_type,
                    'message' => $th->latestMessage->message,
                    'has_attachments' => !empty($th->latestMessage->attachments),
                    'created_at' => $th->latestMessage->created_at->format('H:i'),
                ] : null,
            ];
        });

        // Tentukan utas aktif terpilih
        $activeThread = null;
        $activeMessages = [];

        if ($selectedUuid) {
            $activeThreadModel = LiveChatThread::with('user')->where('uuid', $selectedUuid)->first();
        } else {
            $activeThreadModel = $threads->getCollection()->first() ? LiveChatThread::with('user')->find($threads->getCollection()->first()['id']) : null;
        }

        if ($activeThreadModel) {
            // Reset unread_admin
            if ($activeThreadModel->unread_admin > 0) {
                $activeThreadModel->update(['unread_admin' => 0]);
            }
            $activeThreadModel->messages()
                ->where('sender_type', 'USER')
                ->where('is_read', false)
                ->update(['is_read' => true]);

            $isOnline = Cache::has("live_chat:user_presence:{$activeThreadModel->user_id}");
            $isTyping = Cache::has("live_chat:typing:{$activeThreadModel->uuid}:USER");

            $callData = Cache::get("live_chat:call:{$activeThreadModel->uuid}");
            $activeCall = ($callData && in_array($callData['status'], ['RINGING', 'ACCEPTED', 'CONNECTING', 'CONNECTED'])) ? $callData : null;

            $activeThread = [
                'id' => $activeThreadModel->id,
                'uuid' => $activeThreadModel->uuid,
                'subject' => $activeThreadModel->subject,
                'status' => $activeThreadModel->status,
                'is_online' => $isOnline,
                'is_typing' => $isTyping,
                'user' => [
                    'id' => $activeThreadModel->user?->id,
                    'name' => $activeThreadModel->user?->name ?? 'Personel',
                    'pangkat' => $activeThreadModel->user?->pangkat ?? 'TNI AL',
                    'nrp' => $activeThreadModel->user?->nrp ?? '-',
                    'avatar' => $activeThreadModel->user?->avatar,
                    'phone' => $activeThreadModel->user?->phone,
                ],
                'call' => $activeCall,
            ];

            $activeMessages = $activeThreadModel->messages()->orderBy('id', 'asc')->get();
        }

        $stats = [
            'total_open' => LiveChatThread::where('status', 'OPEN')->count(),
            'total_unread' => LiveChatThread::sum('unread_admin'),
            'total_threads' => LiveChatThread::count(),
        ];

        // Deteksi panggilan masuk langsung untuk Admin
        $adminIncomingCall = Cache::get("live_chat:user_call:{$user->id}");

        return Inertia::render('Chat/AdminIndex', [
            'threads' => $threads,
            'activeThread' => $activeThread,
            'activeMessages' => $activeMessages,
            'chatStats' => $stats,
            'initialCall' => $adminIncomingCall,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'pangkat' => $user->pangkat ?? 'Pengelola Dinas',
                'avatar' => $user->avatar,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Tampilan untuk Pengguna / Personel Reguler
     */
    protected function userView(Request $request, User $user)
    {
        $this->touchUserPresence($user->id);

        $thread = LiveChatThread::where('user_id', $user->id)
            ->where('status', 'OPEN')
            ->latest('last_message_at')
            ->first();

        if (!$thread) {
            $thread = LiveChatThread::where('user_id', $user->id)
                ->latest('last_message_at')
                ->first();
        }

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
        $thread->messages()
            ->where('sender_type', '!=', 'USER')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = $thread->messages()->orderBy('id', 'asc')->get();

        $callData = Cache::get("live_chat:call:{$thread->uuid}");
        $activeCall = ($callData && in_array($callData['status'], ['RINGING', 'ACCEPTED', 'CONNECTING', 'CONNECTED'])) ? $callData : null;

        $userIncomingCall = Cache::get("live_chat:user_call:{$user->id}");

        return Inertia::render('Chat/UserIndex', [
            'thread' => [
                'id' => $thread->id,
                'uuid' => $thread->uuid,
                'subject' => $thread->subject,
                'status' => $thread->status,
                'unread_user' => $thread->unread_user,
            ],
            'messages' => $messages,
            'initialCall' => $userIncomingCall ?: $activeCall,
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'pangkat' => $user->pangkat ?? 'TNI AL',
                'nrp' => $user->nrp ?? '-',
                'avatar' => $user->avatar,
            ],
        ]);
    }

    /**
     * Endpoint Polling Sinkronisasi Pesan & Status Panggilan
     */
    public function sync(Request $request)
    {
        $user = Auth::user();
        $this->touchUserPresence($user->id);

        $isAdmin = $user->isAdmin() || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';
        $activeUuid = $request->input('uuid');

        $incomingCall = Cache::get("live_chat:user_call:{$user->id}");

        if ($isAdmin) {
            $status = $request->input('status', 'all');
            $search = trim((string) $request->input('search', ''));
            $activeUuid = $request->input('uuid') ?: $request->input('thread');

            $threads = LiveChatThread::with(['user', 'latestMessage'])
                ->when($status !== 'all', function ($q) use ($status) {
                    $q->where('status', $status);
                })
                ->when($search !== '', function ($q) use ($search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('subject', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($uq) use ($search) {
                                $uq->where('name', 'like', "%{$search}%")
                                   ->orWhere('nrp', 'like', "%{$search}%")
                                   ->orWhere('pangkat', 'like', "%{$search}%");
                            });
                    });
                })
                ->orderBy('last_message_at', 'desc')
                ->orderBy('id', 'desc')
                ->limit(30)
                ->get()
                ->map(function ($th) {
                    $isOnline = Cache::has("live_chat:user_presence:{$th->user_id}");
                    return [
                        'id' => $th->id,
                        'uuid' => $th->uuid,
                        'subject' => $th->subject,
                        'status' => $th->status,
                        'last_message_at' => $th->last_message_at?->diffForHumans() ?? 'Belum ada pesan',
                        'unread_admin' => $th->unread_admin,
                        'is_online' => $isOnline,
                        'user' => [
                            'id' => $th->user?->id,
                            'name' => $th->user?->name ?? 'Personel',
                            'pangkat' => $th->user?->pangkat ?? 'TNI AL',
                            'nrp' => $th->user?->nrp ?? '-',
                            'avatar' => $th->user?->avatar,
                        ],
                        'latest_message' => $th->latestMessage ? [
                            'sender_name' => $th->latestMessage->sender_name,
                            'sender_type' => $th->latestMessage->sender_type,
                            'message' => $th->latestMessage->message,
                            'has_attachments' => !empty($th->latestMessage->attachments),
                            'created_at' => $th->latestMessage->created_at->format('H:i'),
                        ] : null,
                    ];
                });

            $unreadTotal = (int) LiveChatThread::sum('unread_admin');
            $openTotal = (int) LiveChatThread::where('status', 'OPEN')->count();
            $totalCount = (int) LiveChatThread::count();

            $activeMessages = [];
            $isTyping = false;
            $callData = null;
            $activePresence = null;

            if ($activeUuid) {
                $thread = LiveChatThread::with('user')->where('uuid', $activeUuid)->first();
                if ($thread) {
                    $activeMessages = $thread->messages()->orderBy('id', 'asc')->get();
                    $isTyping = Cache::has("live_chat:typing:{$activeUuid}:USER");
                    $callData = Cache::get("live_chat:call:{$activeUuid}");
                    $activePresence = [
                        'is_online' => Cache::has("live_chat:user_presence:{$thread->user_id}"),
                    ];

                    // Reset unread_admin
                    if ($thread->unread_admin > 0) {
                        $thread->update(['unread_admin' => 0]);
                    }
                    $thread->messages()
                        ->where('sender_type', 'USER')
                        ->where('is_read', false)
                        ->update(['is_read' => true]);
                }
            }

            return response()->json([
                'threads' => $threads,
                'total_unread' => $unreadTotal,
                'total_open' => $openTotal,
                'active_messages' => $activeMessages,
                'is_typing' => $isTyping,
                'presence' => $activePresence,
                'call' => $callData,
                'incoming_call' => $incomingCall,
                'stats' => [
                    'total_open' => $openTotal,
                    'total_unread' => $unreadTotal,
                    'total_threads' => $totalCount,
                ],
            ]);
        }

        // Untuk Personel / User Reguler
        $thread = LiveChatThread::where('user_id', $user->id)->first();
        $messages = [];
        $isTyping = false;
        $callData = null;

        if ($thread) {
            $messages = $thread->messages()->orderBy('id', 'asc')->get();
            $isTyping = Cache::has("live_chat:typing:{$thread->uuid}:ADMIN");
            $callData = Cache::get("live_chat:call:{$thread->uuid}");

            if ($thread->unread_user > 0) {
                $thread->update(['unread_user' => 0]);
            }
            $thread->messages()
                ->where('sender_type', '!=', 'USER')
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        return response()->json([
            'thread' => $thread,
            'messages' => $messages,
            'is_typing' => $isTyping,
            'call' => $callData,
            'incoming_call' => $incomingCall,
        ]);
    }

    /**
     * Mengambil daftar pesan pada utas tertentu
     */
    public function getMessages(Request $request, $uuid)
    {
        $user = Auth::user();
        $isAdmin = $user->isAdmin() || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';

        $thread = LiveChatThread::where('uuid', $uuid)->firstOrFail();

        if (!$isAdmin && $thread->user_id !== $user->id) {
            abort(403, 'Akses ditolak.');
        }

        if ($isAdmin) {
            $thread->update(['unread_admin' => 0]);
            $thread->messages()->where('sender_type', 'USER')->update(['is_read' => true]);
        } else {
            $thread->update(['unread_user' => 0]);
            $thread->messages()->where('sender_type', '!=', 'USER')->update(['is_read' => true]);
        }

        return response()->json([
            'messages' => $thread->messages()->orderBy('id', 'asc')->get(),
        ]);
    }

    /**
     * Mengirim pesan baru beserta berkas lampiran
     */
    public function sendMessage(Request $request, $uuid)
    {
        $user = Auth::user();
        $isAdmin = $user->isAdmin() || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';

        $thread = LiveChatThread::where('uuid', $uuid)->firstOrFail();

        if (!$isAdmin && $thread->user_id !== $user->id) {
            return response()->json(['error' => 'Akses ditolak.'], 403);
        }

        if ($thread->status === 'CLOSED') {
            return response()->json(['error' => 'Sesi obrolan ini telah ditutup.'], 403);
        }

        $request->validate([
            'message' => 'nullable|string|max:5000',
            'attachments' => 'nullable|array',
            'attachments.*' => 'nullable|file',
        ]);

        $files = $request->file('attachments', []);
        $uploadedFiles = [];

        if (!empty($files)) {
            $totalBytes = 0;
            foreach ($files as $f) {
                $totalBytes += $f->getSize();
            }

            if ($totalBytes > 20 * 1024 * 1024) {
                return response()->json(['error' => 'Total ukuran lampiran melebihi batas 20MB.'], 422);
            }

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'];
            $destDir = "chat_attachments/{$thread->uuid}";

            foreach ($files as $file) {
                $ext = strtolower($file->getClientOriginalExtension());
                if (!in_array($ext, $allowedExtensions)) {
                    return response()->json(['error' => "Format berkas [{$file->getClientOriginalName()}] tidak diizinkan."], 422);
                }

                $cleanName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $file->getClientOriginalName());
                // Ekstensi ditentukan dari ISI berkas & whitelist (store() biasa bisa menghasilkan .php
                // untuk "foto.jpg" yang berisi kode PHP).
                $storedPath = \App\Support\SafeImage::storeUpload($file, $destDir, $allowedExtensions);
                if ($storedPath === null) {
                    return response()->json(['error' => "Isi berkas [{$cleanName}] tidak sesuai format yang diizinkan."], 422);
                }

                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']);

                $uploadedFiles[] = [
                    'original_name' => $cleanName,
                    'file_path' => $storedPath,
                    'url' => Storage::disk('public')->url($storedPath),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'is_image' => $isImage,
                ];
            }
        }

        $messageContent = strip_tags(trim((string) $request->input('message')));
        if ($messageContent === '' && empty($uploadedFiles)) {
            return response()->json(['error' => 'Pesan teks atau berkas lampiran wajib diisi.'], 422);
        }

        $senderType = $isAdmin ? 'ADMIN' : 'USER';
        $pangkat = $user->pangkat ? $user->pangkat . ' ' : '';
        $senderName = "{$pangkat}{$user->name}";

        $message = LiveChatMessage::create([
            'thread_id' => $thread->id,
            'sender_type' => $senderType,
            'sender_id' => $user->id,
            'sender_name' => $senderName,
            'message' => $messageContent !== '' ? $messageContent : null,
            'attachments' => !empty($uploadedFiles) ? $uploadedFiles : null,
            'is_read' => false,
        ]);

        if ($isAdmin) {
            $thread->update([
                'last_message_at' => now(),
                'unread_user' => $thread->unread_user + 1,
            ]);
            Cache::forget("live_chat:typing:{$thread->uuid}:ADMIN");
        } else {
            $thread->update([
                'last_message_at' => now(),
                'unread_admin' => $thread->unread_admin + 1,
            ]);
            Cache::forget("live_chat:typing:{$thread->uuid}:USER");
        }

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    /**
     * Indikator sedang mengetik pesan
     */
    public function typing(Request $request, $uuid)
    {
        $user = Auth::user();
        $isAdmin = $user->isAdmin() || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';
        $type = $isAdmin ? 'ADMIN' : 'USER';

        Cache::put("live_chat:typing:{$uuid}:{$type}", true, now()->addSeconds(4));

        return response()->json(['success' => true]);
    }

    /**
     * Pencarian Personel untuk memulai percakapan baru (Khusus Admin)
     */
    public function searchUsers(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['users' => []]);
        }

        $users = User::where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('nrp', 'like', "%{$q}%")
                    ->orWhere('pangkat', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            })
            ->where('id', '!=', Auth::id())
            ->limit(15)
            ->get();

        $userIds = $users->pluck('id')->toArray();
        $openThreads = LiveChatThread::whereIn('user_id', $userIds)
            ->where('status', 'OPEN')
            ->pluck('uuid', 'user_id');

        $results = $users->map(function ($u) use ($openThreads) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'pangkat' => $u->pangkat ?? 'TNI AL',
                'nrp' => $u->nrp ?? '-',
                'avatar' => $u->avatar,
                'role' => $u->role,
                'existing_thread_uuid' => $openThreads[$u->id] ?? null,
            ];
        });

        return response()->json(['users' => $results]);
    }

    /**
     * Membuka atau membuat percakapan dengan pengguna target
     */
    public function startChat(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $targetUser = User::findOrFail($request->user_id);

        $thread = LiveChatThread::where('user_id', $targetUser->id)
            ->where('status', 'OPEN')
            ->first();

        if (!$thread) {
            $thread = LiveChatThread::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $targetUser->id,
                'operator_id' => Auth::id(),
                'subject' => 'Komunikasi Dinas - ' . ($targetUser->pangkat ? $targetUser->pangkat . ' ' : '') . $targetUser->name,
                'status' => 'OPEN',
                'last_message_at' => now(),
                'unread_admin' => 0,
                'unread_user' => 0,
            ]);
        }

        return response()->json([
            'success' => true,
            'uuid' => $thread->uuid,
        ]);
    }

    /**
     * Buka / Tutup status sesi obrolan
     */
    public function toggleStatus(Request $request, $uuid)
    {
        $thread = LiveChatThread::where('uuid', $uuid)->firstOrFail();
        $newStatus = $thread->status === 'OPEN' ? 'CLOSED' : 'OPEN';
        $thread->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'status' => $newStatus,
            'message' => $newStatus === 'CLOSED' ? 'Sesi obrolan dinas telah ditutup.' : 'Sesi obrolan dinas telah dibuka kembali.',
        ]);
    }

    /**
     * Hapus utas obrolan beserta seluruh berkas lampiran
     */
    public function destroy(Request $request, $uuid)
    {
        $thread = LiveChatThread::where('uuid', $uuid)->firstOrFail();
        $thread->purgeFiles();
        $thread->messages()->delete();
        $thread->delete();

        return redirect()->route('chat.index')->with('success', 'Utas percakapan dinas berhasil dihapus.');
    }

    /**
     * Mengunduh berkas lampiran obrolan secara aman
     */
    public function downloadAttachment(Request $request)
    {
        $path = $request->query('path');
        if (!$path || !Storage::disk('public')->exists($path)) {
            abort(404, 'Berkas lampiran tidak ditemukan.');
        }

        return Storage::disk('public')->download($path);
    }

    // ==========================================
    // PERSINYALAN PANGGILAN VIDEO & SUARA (WEBRTC)
    // ==========================================

    /**
     * Memulai panggilan dinas keluar (WebRTC P2P)
     */
    public function initiateCall(Request $request, $uuid)
    {
        $user = Auth::user();
        $thread = LiveChatThread::with('user')->where('uuid', $uuid)->firstOrFail();

        $isAdmin = $user->isAdmin() || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';

        if ($isAdmin) {
            $caller = [
                'type' => 'ADMIN',
                'id' => $user->id,
                'name' => $user->name,
                'pangkat' => $user->pangkat ?? 'Pengelola Dinas',
                'avatar' => $user->avatar,
            ];
            $callee = [
                'type' => 'USER',
                'id' => $thread->user_id,
                'name' => $thread->user?->name ?? 'Personel',
                'pangkat' => $thread->user?->pangkat ?? 'TNI AL',
                'avatar' => $thread->user?->avatar,
            ];
            $calleeUserId = $thread->user_id;
        } else {
            $caller = [
                'type' => 'USER',
                'id' => $user->id,
                'name' => $user->name,
                'pangkat' => $user->pangkat ?? 'TNI AL',
                'avatar' => $user->avatar,
            ];
            $callee = [
                'type' => 'ADMIN',
                'id' => $thread->operator_id,
                'name' => 'Operator Dinas SINDEN',
                'pangkat' => 'Pengelola Dinas',
                'avatar' => null,
            ];
            $calleeUserId = $thread->operator_id ?: User::where('role', 'admin')->value('id');
        }

        $initialCandidates = $request->input('candidates', []);
        Cache::put("live_chat:call:{$thread->uuid}:candidates_caller", is_array($initialCandidates) ? $initialCandidates : [], now()->addMinutes(10));
        Cache::forget("live_chat:call:{$thread->uuid}:candidates_callee");

        $callData = [
            'call_id' => (string) Str::uuid(),
            'thread_uuid' => $thread->uuid,
            'status' => 'RINGING', // RINGING, ACCEPTED, CONNECTING, CONNECTED, REJECTED, ENDED
            'caller' => $caller,
            'callee' => $callee,
            'call_type' => $request->input('call_type', 'video'), // 'video' atau 'audio'
            'offer' => $request->input('offer', null),
            'answer' => null,
            'created_at' => now()->timestamp,
            'started_at' => null,
            'ended_at' => null,
            'callee_user_id' => $calleeUserId,
        ];

        Cache::put("live_chat:call:{$thread->uuid}", $callData, now()->addMinutes(10));

        if ($calleeUserId) {
            Cache::put("live_chat:user_call:{$calleeUserId}", $callData, now()->addSeconds(45));
            // Dering di HP penerima walau aplikasi tertutup (FCM).
            \App\Support\PushNotifier::incomingCall((int) $calleeUserId, $callData);
        }

        return response()->json([
            'success' => true,
            'call' => $callData,
        ]);
    }

    /**
     * Mengambil status sinyal WebRTC panggilan aktif
     */
    public function getCallSignal(Request $request, $uuid)
    {
        $callData = Cache::get("live_chat:call:{$uuid}");

        if ($callData) {
            $callData['candidates_caller'] = Cache::get("live_chat:call:{$uuid}:candidates_caller", []);
            $callData['candidates_callee'] = Cache::get("live_chat:call:{$uuid}:candidates_callee", []);
        }

        return response()->json([
            'call' => $callData,
        ]);
    }

    /**
     * Mengirimkan sinyal balik WebRTC (Answer SDP / ICE Candidate / Status)
     */
    public function sendCallSignal(Request $request, $uuid)
    {
        $callData = Cache::get("live_chat:call:{$uuid}");

        if (!$callData) {
            return response()->json(['error' => 'Sesi panggilan tidak ditemukan atau telah kedaluwarsa.'], 404);
        }

        $user = Auth::user();
        $isAdmin = $user->isAdmin() || $user->name === 'I Gusti Sultan H.A, A.Md.Kom';
        $senderRole = $isAdmin ? 'ADMIN' : 'USER';

        $action = $request->input('action');
        $newStatus = $request->input('status');

        if (!$newStatus && $action) {
            if ($action === 'accept') {
                $newStatus = 'ACCEPTED';
            } elseif ($action === 'connected') {
                $newStatus = 'CONNECTED';
            } elseif ($action === 'reject') {
                $newStatus = 'REJECTED';
            }
        }

        $previousStatus = $callData['status'] ?? null;

        // 1. Perbarui status panggilan jika ada
        if ($newStatus) {
            $callData['status'] = $newStatus;
            if ($newStatus === 'CONNECTED' && empty($callData['started_at'])) {
                $callData['started_at'] = now()->timestamp;
            }
        }

        // 2. Simpan Answer SDP jika dikirimkan oleh Callee
        $answer = $request->input('answer');
        if ($answer) {
            $callData['answer'] = $answer;
            if ($callData['status'] === 'RINGING') {
                $callData['status'] = 'ACCEPTED';
            }
        }

        // 3. Tambahkan ICE Candidates baru ke daftar cache
        $newCandidates = $request->input('candidates', []);
        if (is_array($newCandidates) && !empty($newCandidates)) {
            $isCaller = ($callData['caller']['type'] === $senderRole);
            $cacheKey = $isCaller
                ? "live_chat:call:{$uuid}:candidates_caller"
                : "live_chat:call:{$uuid}:candidates_callee";

            $existing = Cache::get($cacheKey, []);
            if (!is_array($existing)) $existing = [];

            $merged = array_merge($existing, $newCandidates);
            Cache::put($cacheKey, $merged, now()->addMinutes(10));
        }

        Cache::put("live_chat:call:{$uuid}", $callData, now()->addMinutes(10));

        // Bersihkan notifikasi dering jika panggilan sudah diterima
        if (in_array($callData['status'], ['ACCEPTED', 'CONNECTING', 'CONNECTED', 'REJECTED', 'ENDED'])) {
            if (!empty($callData['callee']['id'])) {
                Cache::forget("live_chat:user_call:{$callData['callee']['id']}");
            }
            if ($previousStatus === 'RINGING') {
                $calleeId = $callData['callee_user_id'] ?? $callData['callee']['id'] ?? null;
                if ($calleeId) {
                    Cache::forget("live_chat:user_call:{$calleeId}");
                }
                // Hentikan dering di HP lain milik penerima (mis. dijawab dari web).
                \App\Support\PushNotifier::callEnded($calleeId ? (int) $calleeId : null, $callData);
            }
        }

        return response()->json([
            'success' => true,
            'call' => $callData,
        ]);
    }

    /**
     * Mengakhiri panggilan dinas
     */
    public function endCall(Request $request, $uuid)
    {
        $callData = Cache::get("live_chat:call:{$uuid}");
        $reason = $request->input('reason', 'ended'); // 'ended', 'rejected', 'canceled', 'missed'
        $durationSeconds = (int) $request->input('duration_seconds', 0);

        Cache::forget("live_chat:call:{$uuid}:candidates_caller");
        Cache::forget("live_chat:call:{$uuid}:candidates_callee");

        if ($callData) {
            $callData['status'] = ($reason === 'rejected') ? 'REJECTED' : 'ENDED';
            $callData['ended_at'] = now()->timestamp;
            Cache::put("live_chat:call:{$uuid}", $callData, now()->addSeconds(30));

            if (!empty($callData['callee']['id'])) {
                Cache::forget("live_chat:user_call:{$callData['callee']['id']}");
            }
            $calleeId = $callData['callee_user_id'] ?? $callData['callee']['id'] ?? null;
            if ($calleeId) {
                Cache::forget("live_chat:user_call:{$calleeId}");
            }
            \App\Support\PushNotifier::callEnded($calleeId ? (int) $calleeId : null, $callData);
        }

        $thread = LiveChatThread::where('uuid', $uuid)->first();
        if ($thread) {
            if ($durationSeconds > 0) {
                $minutes = floor($durationSeconds / 60);
                $seconds = $durationSeconds % 60;
                $formattedTime = sprintf('%02d:%02d', $minutes, $seconds);
                $logMsg = "Panggilan video dinas selesai. (Durasi: {$formattedTime})";
            } elseif ($reason === 'rejected') {
                $logMsg = "Panggilan video dinas ditolak.";
            } elseif ($reason === 'canceled') {
                $logMsg = "Panggilan video dinas dibatalkan.";
            } else {
                $logMsg = "Panggilan video dinas tidak terjawab.";
            }

            LiveChatMessage::create([
                'thread_id' => $thread->id,
                'sender_type' => 'SYSTEM',
                'sender_name' => 'Sistem Vicon Dinas',
                'message' => $logMsg,
                'is_read' => true,
            ]);

            $thread->update(['last_message_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Panggilan berhasil diakhiri.',
        ]);
    }

    /**
     * Mengambil kredensial Agora RTC untuk konektivitas vicon alternatif
     */
    public function getAgoraToken(Request $request, $uuid)
    {
        $user = Auth::user();
        $thread = LiveChatThread::where('uuid', $uuid)->firstOrFail();
        $channelName = 'SINDEN_' . preg_replace('/[^a-zA-Z0-9]/', '', $thread->uuid);
        $uid = (int) ($request->input('uid') ?: ($user->id ?? 1));

        $appId = Setting::where('key', 'agora_app_id')->value('value') ?: config('services.agora.app_id');
        $token = AgoraTokenService::generateToken($channelName, $uid);

        return response()->json([
            'success' => true,
            'appId' => $appId,
            'channel' => $channelName,
            'token' => $token,
            'uid' => $uid,
        ]);
    }

    /**
     * Daftar Server STUN Publik Google & Cloudflare untuk WebRTC P2P
     */
    public function getIceServers(Request $request)
    {
        $stunServers = [
            ['urls' => 'stun:stun.l.google.com:19302'],
            ['urls' => 'stun:stun1.l.google.com:19302'],
            ['urls' => 'stun:stun2.l.google.com:19302'],
            ['urls' => 'stun:stun3.l.google.com:19302'],
            ['urls' => 'stun:stun4.l.google.com:19302'],
            ['urls' => 'stun:stun.cloudflare.com:3478'],
            ['urls' => 'stun:stun.services.mozilla.com'],
        ];

        return response()->json([
            'iceServers' => $stunServers,
        ]);
    }

    /**
     * Memperbarui status kehadiran pengguna di cache
     */
    protected function touchUserPresence(int $userId): void
    {
        Cache::put("live_chat:user_presence:{$userId}", now()->timestamp, now()->addMinutes(5));
    }
}
