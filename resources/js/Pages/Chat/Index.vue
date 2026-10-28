<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import VideoCallModal from '@/Components/Chat/VideoCallModal.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    conversations: { type: Array, default: () => [] },
    initialActive: { type: Object, default: null },
    initialCall: { type: Object, default: null },
    isOperator: { type: Boolean, default: false },
    currentUser: { type: Object, required: true },
});

const POLL_MS = 2500;

const conversations = ref(props.conversations);
const search = ref('');
const active = ref(props.initialActive?.thread || null);
const messages = ref(props.initialActive?.messages || []);
const partnerTyping = ref(false);
const loadingThread = ref(false);
const errorText = ref('');

const text = ref('');
const pendingFiles = ref([]);
const sending = ref(false);
const fileInput = ref(null);
const scroller = ref(null);
const lightbox = ref(null);

// Chat baru
const showPicker = ref(false);
const contactQuery = ref('');
const contacts = ref([]);
const contactsLoading = ref(false);

// Panggilan
const showCall = ref(false);
const callData = ref(null);

let pollTimer = null;
let typingSentAt = 0;

const me = computed(() => props.currentUser.id);
const isMine = (m) => m.sender_type !== 'SYSTEM' && Number(m.sender_id) === Number(me.value);
const lastMessageId = computed(() => messages.value.length ? messages.value[messages.value.length - 1].id : null);

const filteredConversations = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return conversations.value;
    return conversations.value.filter((c) => [c.title, c.subtitle].some((v) => (v || '').toLowerCase().includes(q)));
});

const initials = (name) => (name || '?').replace(/^(Letda|Lettu|Kapten|Mayor|Letkol|Kolonel|Serka|Serma|Serda|Sertu|Kopda|Koptu|Kopka|Klk|Kls)\S*\s*/i, '')
    .split(/\s+/).filter(Boolean).slice(0, 2).map((s) => s[0]).join('').toUpperCase() || '?';
const avatarUrl = (avatar) => (avatar ? (avatar.startsWith('http') ? avatar : `/storage/${avatar}`) : null);
const timeLabel = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    const now = new Date();
    if (d.toDateString() === now.toDateString()) return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
};
const clock = (iso) => (iso ? new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '');
// Jalur relatif agar tetap tampil walau APP_URL server belum sesuai domain.
const fileUrl = (att) => (att.file_path ? `/storage/${att.file_path}` : att.url);
const downloadUrl = (att) => `/live-chat/attachment/download?path=${encodeURIComponent(att.file_path)}`;
const sizeLabel = (b) => (!b ? '' : b < 1024 * 1024 ? `${Math.round(b / 1024)} KB` : `${(b / 1024 / 1024).toFixed(1)} MB`);
const durationLabel = (ms) => {
    const s = Math.round((ms || 0) / 1000);
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
};

const scrollToBottom = async (force = false) => {
    await nextTick();
    const el = scroller.value;
    if (!el) return;
    const nearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 160;
    if (force || nearBottom) el.scrollTop = el.scrollHeight;
};

// ------------------------------------------------------------------ data

const applyActive = (data, append) => {
    if (!data) return;
    active.value = data.thread;
    partnerTyping.value = !!data.is_typing;
    if (append) {
        const known = new Set(messages.value.map((m) => m.id));
        const fresh = (data.messages || []).filter((m) => !known.has(m.id));
        if (fresh.length) {
            messages.value = [...messages.value, ...fresh];
            scrollToBottom();
        }
    } else {
        messages.value = data.messages || [];
        scrollToBottom(true);
    }
};

const poll = async () => {
    if (document.hidden) return;
    try {
        const params = {};
        if (active.value?.uuid) {
            params.uuid = active.value.uuid;
            if (lastMessageId.value) params.after = lastMessageId.value;
        }
        const { data } = await axios.get('/live-chat/sync', { params });
        conversations.value = data.conversations || [];
        if (data.active && data.active.thread?.uuid === active.value?.uuid) applyActive(data.active, true);
        handleIncomingCall(data.incoming_call);
    } catch (e) {
        if (e.response?.status === 403 || e.response?.status === 404) {
            active.value = null;
            messages.value = [];
        }
    }
};

const openConversation = async (conv) => {
    errorText.value = '';
    let uuid = conv.uuid;
    loadingThread.value = true;
    try {
        if (!uuid) {
            // Entri "Operator Dinas" yang belum pernah dipakai → buat utas layanan.
            const { data } = await axios.post('/live-chat/start', { support: true });
            uuid = data.uuid;
        }
        const { data } = await axios.get(`/live-chat/${uuid}/messages`);
        applyActive(data, false);
        history.replaceState(null, '', `/live-chat?uuid=${uuid}`);
        poll();
    } catch (e) {
        errorText.value = e.response?.data?.message || 'Percakapan tidak dapat dibuka.';
    } finally {
        loadingThread.value = false;
    }
};

const closeConversation = () => {
    active.value = null;
    messages.value = [];
    history.replaceState(null, '', '/live-chat');
};

// ------------------------------------------------------------------ chat baru

const loadContacts = async () => {
    contactsLoading.value = true;
    try {
        const { data } = await axios.get('/live-chat/search-users', { params: { q: contactQuery.value } });
        contacts.value = data.users || [];
    } finally {
        contactsLoading.value = false;
    }
};
let contactTimer = null;
watch(contactQuery, () => {
    clearTimeout(contactTimer);
    contactTimer = setTimeout(loadContacts, 300);
});
const openPicker = () => {
    showPicker.value = true;
    contactQuery.value = '';
    loadContacts();
};
const startWith = async (user) => {
    try {
        const { data } = await axios.post('/live-chat/start', { user_id: user.id });
        showPicker.value = false;
        await openConversation({ uuid: data.uuid });
    } catch (e) {
        errorText.value = e.response?.data?.message || 'Gagal memulai percakapan.';
    }
};

// ------------------------------------------------------------------ kirim pesan

const onTyping = () => {
    if (!active.value?.uuid || Date.now() - typingSentAt < 2500) return;
    typingSentAt = Date.now();
    axios.post(`/live-chat/${active.value.uuid}/typing`).catch(() => {});
};

const pickFiles = (e) => {
    const files = Array.from(e.target.files || []);
    pendingFiles.value = [...pendingFiles.value, ...files].slice(0, 10);
    e.target.value = '';
};
const removeFile = (i) => pendingFiles.value.splice(i, 1);

const postMessage = async (form) => {
    sending.value = true;
    errorText.value = '';
    try {
        const { data } = await axios.post(`/live-chat/${active.value.uuid}/send`, form);
        if (data.message && !messages.value.some((m) => m.id === data.message.id)) {
            messages.value = [...messages.value, data.message];
        }
        scrollToBottom(true);
        poll();
        return true;
    } catch (e) {
        errorText.value = e.response?.data?.error || e.response?.data?.message || 'Pesan gagal dikirim.';
        return false;
    } finally {
        sending.value = false;
    }
};

const send = async () => {
    if (sending.value || !active.value?.uuid) return;
    const body = text.value.trim();
    if (!body && !pendingFiles.value.length) return;
    const form = new FormData();
    if (body) form.append('message', body);
    pendingFiles.value.forEach((f) => form.append('attachments[]', f));
    if (await postMessage(form)) {
        text.value = '';
        pendingFiles.value = [];
    }
};

const onEnter = (e) => {
    if (e.shiftKey) return;
    e.preventDefault();
    send();
};

// ------------------------------------------------------------------ pesan suara

const recording = ref(false);
const recordMs = ref(0);
let recorder = null;
let recordChunks = [];
let recordStream = null;
let recordTimer = null;
let recordStartedAt = 0;
let recordCancelled = false;

const pickAudioMime = () => {
    const opts = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];
    return opts.find((m) => window.MediaRecorder?.isTypeSupported?.(m)) || '';
};

const startRecording = async () => {
    if (recording.value || !active.value?.uuid) return;
    errorText.value = '';
    if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
        errorText.value = 'Browser ini tidak mendukung perekaman suara.';
        return;
    }
    try {
        recordStream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } });
    } catch (e) {
        errorText.value = 'Izin mikrofon ditolak. Aktifkan mikrofon untuk mengirim pesan suara.';
        return;
    }
    const mime = pickAudioMime();
    recorder = new MediaRecorder(recordStream, mime ? { mimeType: mime, audioBitsPerSecond: 32000 } : undefined);
    recordChunks = [];
    recordCancelled = false;
    recorder.ondataavailable = (e) => { if (e.data?.size) recordChunks.push(e.data); };
    recorder.onstop = finishRecording;
    recorder.start(250);
    recordStartedAt = Date.now();
    recordMs.value = 0;
    recording.value = true;
    recordTimer = setInterval(() => {
        recordMs.value = Date.now() - recordStartedAt;
        if (recordMs.value >= 5 * 60 * 1000) stopRecording(); // batas 5 menit
    }, 200);
};

const stopRecording = (cancel = false) => {
    if (!recorder || recorder.state === 'inactive') return;
    recordCancelled = cancel;
    recorder.stop();
};

const finishRecording = async () => {
    clearInterval(recordTimer);
    recordStream?.getTracks().forEach((t) => t.stop());
    recording.value = false;
    const duration = Date.now() - recordStartedAt;
    if (recordCancelled || duration < 700 || !recordChunks.length) return;

    const type = recorder.mimeType || 'audio/webm';
    const ext = type.includes('mp4') ? 'm4a' : type.includes('ogg') ? 'ogg' : 'webm';
    const blob = new Blob(recordChunks, { type });
    const form = new FormData();
    form.append('voice_note', '1');
    form.append('duration_ms', String(duration));
    form.append('attachments[]', new File([blob], `pesan-suara.${ext}`, { type }));
    await postMessage(form);
};

// ------------------------------------------------------------------ operator & hapus

const toggleStatus = async () => {
    const { data } = await axios.post(`/live-chat/${active.value.uuid}/status`);
    active.value = { ...active.value, status: data.status };
};
const removeConversation = async () => {
    if (!window.confirm('Hapus percakapan ini beserta seluruh pesan dan lampirannya?')) return;
    await axios.delete(`/live-chat/${active.value.uuid}`, { headers: { Accept: 'application/json' } });
    closeConversation();
    poll();
};

// ------------------------------------------------------------------ panggilan

const handleIncomingCall = (call) => {
    if (!call || showCall.value) return;
    if (call.status === 'RINGING' && Number(call.caller?.id) !== Number(me.value)) {
        callData.value = call;
        showCall.value = true;
    }
};
const startCall = () => {
    if (!active.value?.uuid) return;
    callData.value = null;
    showCall.value = true;
};
const closeCall = () => {
    showCall.value = false;
    callData.value = null;
    poll();
};
const callThreadUuid = computed(() => callData.value?.thread_uuid || active.value?.uuid);
const callPartner = computed(() => {
    if (callData.value) {
        const c = Number(callData.value.caller?.id) === Number(me.value) ? callData.value.callee : callData.value.caller;
        return { ...c };
    }
    const p = active.value?.partner;
    return p ? { ...p, is_online: active.value.is_online } : { name: active.value?.title || 'Operator Dinas', pangkat: '' };
});

// ------------------------------------------------------------------ siklus

onMounted(() => {
    scrollToBottom(true);
    handleIncomingCall(props.initialCall);
    pollTimer = setInterval(poll, POLL_MS);
    document.addEventListener('visibilitychange', poll);
});
onUnmounted(() => {
    clearInterval(pollTimer);
    clearInterval(recordTimer);
    document.removeEventListener('visibilitychange', poll);
    recordStream?.getTracks().forEach((t) => t.stop());
});
</script>

<template>
    <Head title="Chat Dinas" />
    <AuthenticatedLayout>
        <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden grid grid-cols-1 lg:grid-cols-[340px_1fr] h-[calc(100vh-150px)] min-h-[520px]">
            <!-- ============ DAFTAR PERCAKAPAN ============ -->
            <aside class="flex flex-col border-r border-slate-200 min-h-0" :class="active ? 'hidden lg:flex' : 'flex'">
                <div class="p-4 border-b border-slate-100 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-lg font-extrabold text-slate-800">Chat Dinas</h1>
                            <p class="text-[11px] text-slate-500">Pesan, pesan suara & panggilan video</p>
                        </div>
                        <button type="button" @click="openPicker" class="px-3 py-2 rounded-xl bg-blue-600 text-white text-xs font-bold hover:bg-blue-700">+ Chat baru</button>
                    </div>
                    <input v-model="search" type="search" placeholder="Cari percakapan"
                           class="w-full rounded-2xl border-slate-200 bg-slate-50 text-xs px-4 py-2.5 focus:ring-blue-500 focus:border-blue-600" />
                </div>
                <div class="flex-1 overflow-y-auto">
                    <p v-if="!filteredConversations.length" class="text-xs text-slate-400 text-center py-10">Belum ada percakapan. Tekan "Chat baru".</p>
                    <button v-for="c in filteredConversations" :key="c.uuid || 'support'" type="button" @click="openConversation(c)"
                            class="w-full flex items-center gap-3 px-4 py-3 text-left border-b border-slate-50 hover:bg-slate-50 transition"
                            :class="active?.uuid && c.uuid === active.uuid ? 'bg-blue-50' : ''">
                        <span class="relative shrink-0">
                            <img v-if="avatarUrl(c.avatar)" :src="avatarUrl(c.avatar)" class="w-11 h-11 rounded-full object-cover" alt="" />
                            <span v-else class="w-11 h-11 rounded-full flex items-center justify-center text-xs font-bold text-white"
                                  :class="c.kind === 'support' ? 'bg-indigo-600' : 'bg-slate-500'">{{ c.kind === 'support' && c.role === 'user' ? 'OD' : initials(c.title) }}</span>
                            <span v-if="c.is_online" class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-emerald-500 border-2 border-white"></span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="text-sm font-bold text-slate-800 truncate">{{ c.title }}</span>
                                <span class="text-[10px] shrink-0" :class="c.unread ? 'text-emerald-600 font-bold' : 'text-slate-400'">{{ timeLabel(c.last_message_at) }}</span>
                            </span>
                            <span class="flex items-center justify-between gap-2 mt-0.5">
                                <span class="text-xs truncate" :class="c.is_typing ? 'text-emerald-600 italic' : 'text-slate-500'">
                                    {{ c.is_typing ? 'sedang mengetik…' : (c.latest_message ? (Number(c.latest_message.sender_id) === Number(me) ? 'Anda: ' : '') + c.latest_message.preview : c.subtitle) }}
                                </span>
                                <span v-if="c.unread" class="shrink-0 min-w-[20px] h-5 px-1.5 rounded-full bg-emerald-500 text-white text-[10px] font-bold flex items-center justify-center">{{ c.unread }}</span>
                            </span>
                        </span>
                    </button>
                </div>
            </aside>

            <!-- ============ PERCAKAPAN AKTIF ============ -->
            <section class="flex flex-col min-h-0 bg-slate-50" :class="active ? 'flex' : 'hidden lg:flex'">
                <div v-if="!active" class="flex-1 flex flex-col items-center justify-center text-center p-8 text-slate-400">
                    <p class="text-sm font-semibold">Pilih percakapan atau mulai chat baru</p>
                    <p class="text-xs mt-1">Anda dapat mengirim pesan ke Komandan maupun personel lain.</p>
                </div>

                <template v-else>
                    <header class="flex items-center gap-3 px-4 py-3 bg-white border-b border-slate-200">
                        <button type="button" class="lg:hidden text-slate-500 font-bold px-1" @click="closeConversation">←</button>
                        <span class="w-10 h-10 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0"
                              :class="active.kind === 'support' ? 'bg-indigo-600' : 'bg-slate-500'">{{ active.kind === 'support' && active.role === 'user' ? 'OD' : initials(active.title) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-slate-800 truncate">{{ active.title }}</p>
                            <p class="text-[11px]" :class="partnerTyping ? 'text-emerald-600 italic' : 'text-slate-500'">
                                {{ partnerTyping ? 'sedang mengetik…' : (active.is_online ? 'online' : (active.subtitle || '')) }}
                            </p>
                        </div>
                        <button type="button" @click="startCall" title="Panggilan video"
                                class="w-10 h-10 rounded-full bg-emerald-600 text-white flex items-center justify-center hover:bg-emerald-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                        </button>
                        <button v-if="isOperator && active.kind === 'support'" type="button" @click="toggleStatus"
                                class="px-3 py-2 rounded-xl bg-slate-100 text-slate-700 text-[11px] font-bold hover:bg-slate-200">{{ active.status === 'OPEN' ? 'Tutup utas' : 'Buka utas' }}</button>
                        <button v-if="active.kind === 'direct' || isOperator" type="button" @click="removeConversation" title="Hapus percakapan"
                                class="px-3 py-2 rounded-xl bg-rose-50 text-rose-600 text-[11px] font-bold hover:bg-rose-100">Hapus</button>
                    </header>

                    <div ref="scroller" class="flex-1 overflow-y-auto px-4 py-4 space-y-2">
                        <p v-if="loadingThread" class="text-center text-xs text-slate-400">Memuat…</p>
                        <p v-else-if="!messages.length" class="text-center text-xs text-slate-400 py-8">Belum ada pesan. Sapa lebih dulu.</p>
                        <template v-for="m in messages" :key="m.id">
                            <div v-if="m.sender_type === 'SYSTEM'" class="flex justify-center">
                                <span class="text-[11px] bg-slate-200/70 text-slate-600 px-3 py-1 rounded-full">{{ m.message }}</span>
                            </div>
                            <div v-else class="flex" :class="isMine(m) ? 'justify-end' : 'justify-start'">
                                <div class="max-w-[80%] rounded-2xl px-3 py-2 shadow-sm"
                                     :class="isMine(m) ? 'bg-emerald-100 text-slate-800 rounded-br-md' : 'bg-white text-slate-800 rounded-bl-md'">
                                    <p v-if="!isMine(m) && active.kind === 'support'" class="text-[10px] font-bold text-indigo-600 mb-0.5">{{ m.sender_name }}</p>
                                    <div v-for="(att, i) in (m.attachments || [])" :key="i" class="mb-1">
                                        <!-- Foto tampil langsung -->
                                        <img v-if="att.is_image" :src="fileUrl(att)" alt="Foto" loading="lazy" @click="lightbox = fileUrl(att)"
                                             class="rounded-xl max-h-72 w-auto cursor-zoom-in object-cover" />
                                        <!-- Pesan suara / audio -->
                                        <div v-else-if="att.is_audio" class="flex items-center gap-2 min-w-[220px]">
                                            <span class="text-lg">🎤</span>
                                            <audio :src="fileUrl(att)" controls preload="metadata" class="h-9 w-56"></audio>
                                            <span v-if="att.duration_ms" class="text-[10px] text-slate-500">{{ durationLabel(att.duration_ms) }}</span>
                                        </div>
                                        <!-- Berkas lain -->
                                        <a v-else :href="downloadUrl(att)" class="flex items-center gap-2 rounded-xl bg-black/5 px-3 py-2 text-xs hover:bg-black/10">
                                            <span>📎</span><span class="truncate font-semibold">{{ att.original_name }}</span>
                                            <span class="text-slate-500 shrink-0">{{ sizeLabel(att.size) }}</span>
                                        </a>
                                    </div>
                                    <p v-if="m.message" class="text-sm whitespace-pre-wrap break-words">{{ m.message }}</p>
                                    <p class="text-[10px] text-slate-400 text-right mt-0.5">
                                        {{ clock(m.created_at) }}<span v-if="isMine(m)" class="ml-1" :class="m.is_read ? 'text-sky-500' : ''">✓✓</span>
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>

                    <footer class="bg-white border-t border-slate-200 p-3 space-y-2">
                        <p v-if="errorText" class="text-xs text-rose-600 font-semibold">{{ errorText }}</p>
                        <p v-if="active.status === 'CLOSED'" class="text-xs text-slate-500">Utas ini telah ditutup operator.</p>
                        <div v-if="pendingFiles.length" class="flex flex-wrap gap-2">
                            <span v-for="(f, i) in pendingFiles" :key="i" class="flex items-center gap-1 bg-slate-100 rounded-lg px-2 py-1 text-[11px]">
                                {{ f.name }} <button type="button" class="text-rose-600 font-bold" @click="removeFile(i)">×</button>
                            </span>
                        </div>

                        <!-- Sedang merekam pesan suara -->
                        <div v-if="recording" class="flex items-center gap-3">
                            <span class="w-3 h-3 rounded-full bg-rose-500 animate-pulse"></span>
                            <span class="text-sm font-bold text-slate-700 tabular-nums">{{ durationLabel(recordMs) }}</span>
                            <span class="text-xs text-slate-500 flex-1">Merekam pesan suara…</span>
                            <button type="button" @click="stopRecording(true)" class="px-3 py-2 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold">Batal</button>
                            <button type="button" @click="stopRecording(false)" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold">Kirim</button>
                        </div>

                        <div v-else class="flex items-end gap-2">
                            <input ref="fileInput" type="file" multiple class="hidden" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,audio/*" @change="pickFiles" />
                            <button type="button" @click="fileInput.click()" :disabled="active.status === 'CLOSED'" title="Lampirkan foto/berkas"
                                    class="w-10 h-10 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center hover:bg-slate-200 disabled:opacity-40">📎</button>
                            <textarea v-model="text" rows="1" @keydown.enter="onEnter" @input="onTyping" :disabled="active.status === 'CLOSED'"
                                      placeholder="Ketik pesan" class="flex-1 resize-none rounded-2xl border-slate-200 bg-slate-50 text-sm px-4 py-2.5 max-h-32 focus:ring-blue-500 focus:border-blue-600"></textarea>
                            <button v-if="text.trim() || pendingFiles.length" type="button" @click="send" :disabled="sending"
                                    class="w-10 h-10 rounded-full bg-emerald-600 text-white flex items-center justify-center hover:bg-emerald-700 disabled:opacity-50">➤</button>
                            <button v-else type="button" @click="startRecording" :disabled="active.status === 'CLOSED'" title="Rekam pesan suara"
                                    class="w-10 h-10 rounded-full bg-emerald-600 text-white flex items-center justify-center hover:bg-emerald-700 disabled:opacity-40">🎤</button>
                        </div>
                    </footer>
                </template>
            </section>
        </div>

        <!-- Pilih kontak untuk chat baru -->
        <div v-if="showPicker" class="fixed inset-0 z-40 bg-slate-900/50 flex items-center justify-center p-4" @click.self="showPicker = false">
            <div class="bg-white rounded-3xl w-full max-w-md max-h-[80vh] flex flex-col overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center gap-2">
                    <input v-model="contactQuery" type="search" placeholder="Cari nama, NRP, atau pangkat" autofocus
                           class="flex-1 rounded-2xl border-slate-200 bg-slate-50 text-sm px-4 py-2.5 focus:ring-blue-500 focus:border-blue-600" />
                    <button type="button" class="text-slate-500 font-bold px-2" @click="showPicker = false">✕</button>
                </div>
                <div class="flex-1 overflow-y-auto">
                    <p v-if="contactsLoading" class="text-xs text-slate-400 text-center py-6">Memuat…</p>
                    <p v-else-if="!contacts.length" class="text-xs text-slate-400 text-center py-6">Personel tidak ditemukan.</p>
                    <button v-for="u in contacts" :key="u.id" type="button" @click="startWith(u)"
                            class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-slate-50 border-b border-slate-50">
                        <span class="relative">
                            <span class="w-10 h-10 rounded-full bg-slate-500 text-white text-xs font-bold flex items-center justify-center">{{ initials(u.name) }}</span>
                            <span v-if="u.is_online" class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-emerald-500 border-2 border-white"></span>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-slate-800 truncate">{{ u.pangkat }} {{ u.name }}</span>
                            <span class="block text-[11px] text-slate-500 truncate">{{ u.jabatan || (u.nrp ? 'NRP ' + u.nrp : u.role) }}</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Foto diperbesar -->
        <div v-if="lightbox" class="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4" @click="lightbox = null">
            <img :src="lightbox" class="max-w-full max-h-full object-contain" alt="Foto" />
            <a :href="lightbox" download class="absolute top-4 right-4 px-3 py-2 rounded-xl bg-white/90 text-slate-800 text-xs font-bold" @click.stop>Unduh</a>
        </div>

        <VideoCallModal
            v-if="showCall && callThreadUuid"
            :show="showCall"
            :thread-uuid="callThreadUuid"
            :user-role="active?.role === 'user' ? 'USER' : 'ADMIN'"
            :current-user="currentUser"
            :partner-user="callPartner"
            :incoming-call-data="callData"
            url-prefix=""
            @close="closeCall"
            @call-ended="closeCall"
        />
    </AuthenticatedLayout>
</template>
