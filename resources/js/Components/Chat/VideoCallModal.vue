<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/90 backdrop-blur-md p-0 sm:p-4 select-none overscroll-none touch-none">
    
    <!-- 1. KONDISI A: PANGGILAN KELUAR (OUTGOING CALLING) -->
    <div 
      v-if="callStatus === 'OUTGOING'" 
      class="w-full h-full sm:h-auto max-w-md bg-slate-900 border-0 sm:border border-slate-700/80 rounded-none sm:rounded-3xl p-6 sm:p-8 text-center text-white shadow-2xl space-y-6 relative overflow-hidden flex flex-col justify-center"
    >
      <!-- Background Ambient Glow -->
      <div class="absolute -top-24 -left-24 w-48 h-48 bg-blue-600/20 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -bottom-24 -right-24 w-48 h-48 bg-emerald-600/20 rounded-full blur-3xl pointer-events-none"></div>

      <div class="space-y-2">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-blue-500/10 text-blue-400 border border-blue-500/20">
          <span class="w-2 h-2 rounded-full bg-blue-400 animate-ping"></span>
          Panggilan Video Dinas
        </span>
        <h3 class="text-base font-bold text-slate-200">Menghubungkan Jalur Satelit...</h3>
      </div>

      <!-- Avatar dengan Animasi Gelombang Berdenyut -->
      <div class="relative w-28 h-28 mx-auto flex items-center justify-center">
        <div class="absolute inset-0 rounded-full bg-blue-500/20 animate-ping"></div>
        <div class="absolute -inset-2 rounded-full border border-blue-500/30 animate-pulse"></div>
        <img 
          :src="getAvatarUrl(partnerUser)" 
          class="w-24 h-24 object-cover rounded-full border-2 border-blue-400 shadow-xl relative z-10"
        />
      </div>

      <div class="space-y-1">
        <h4 class="text-lg font-black text-white">
          {{ partnerUser?.pangkat ? partnerUser.pangkat + ' ' : '' }}{{ partnerUser?.name }}
        </h4>
        <p class="text-xs text-slate-400">
          <span v-if="partnerUser?.nrp">NRP: {{ partnerUser.nrp }} &bull; </span>
          <span v-if="partnerUser?.jabatan">{{ partnerUser.jabatan }}</span>
          <span v-else>Dinas Denintel Koarmada 2</span>
        </p>
      </div>

      <div v-if="partnerUser?.is_online === false" class="px-3 py-2 bg-amber-500/10 border border-amber-500/20 rounded-xl text-[11px] text-amber-300">
        Perhatian: Personel saat ini berstatus offline. Sinyal panggilan tetap dipancarkan dan akan berdering saat personel membuka aplikasi.
      </div>

      <div class="space-y-1">
        <p class="text-xs text-slate-400 animate-pulse">
          Menunggu lawan bicara menerima sambungan...
        </p>
        <p class="text-[11px] font-mono text-slate-500">
          Batas waktu panggil: {{ Math.max(0, 40 - outgoingSeconds) }} detik
        </p>
      </div>

      <!-- Tombol Batalkan Panggilan -->
      <div class="pt-2">
        <button 
          @click="cancelOutgoingCall" 
          type="button" 
          class="w-full py-3 px-6 bg-red-600 hover:bg-red-700 active:scale-95 text-white text-xs font-black rounded-2xl shadow-lg transition flex items-center justify-center gap-2 cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <span>Batalkan Panggilan</span>
        </button>
      </div>
    </div>

    <!-- 2. KONDISI B: PANGGILAN MASUK (INCOMING CALL DIALOG) -->
    <div 
      v-else-if="callStatus === 'INCOMING'" 
      class="w-full h-full sm:h-auto max-w-md bg-slate-900 border-0 sm:border border-slate-700/80 rounded-none sm:rounded-3xl p-6 sm:p-8 text-center text-white shadow-2xl space-y-6 relative overflow-hidden flex flex-col justify-center"
    >
      <div class="absolute -top-20 -right-20 w-40 h-40 bg-emerald-500/20 rounded-full blur-3xl pointer-events-none"></div>

      <div class="space-y-2">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
          <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
          Panggilan Video Dinas Masuk
        </span>
        <h3 class="text-base font-bold text-slate-200">Permintaan Sambungan Langsung</h3>
      </div>

      <!-- Avatar Pemanggil -->
      <div class="relative w-28 h-28 mx-auto flex items-center justify-center">
        <div class="absolute inset-0 rounded-full bg-emerald-500/20 animate-ping"></div>
        <div class="absolute -inset-2 rounded-full border border-emerald-500/30 animate-pulse"></div>
        <img 
          :src="getAvatarUrl(activeCaller)" 
          class="w-24 h-24 object-cover rounded-full border-2 border-emerald-400 shadow-xl relative z-10"
        />
      </div>

      <div class="space-y-1">
        <h4 class="text-lg font-black text-white">
          {{ activeCaller?.pangkat ? activeCaller.pangkat + ' ' : '' }}{{ activeCaller?.name }}
        </h4>
        <p class="text-xs text-slate-400">
          Pusat Komunikasi Dinas SINDEN
        </p>
      </div>

      <!-- Tombol Aksi Terima & Tolak -->
      <div class="grid grid-cols-2 gap-3 pt-2">
        <button 
          @click="rejectIncomingCall" 
          type="button" 
          class="py-3 px-4 bg-slate-800 hover:bg-red-600/90 text-slate-200 hover:text-white text-xs font-black rounded-2xl border border-slate-700 transition flex items-center justify-center gap-2 cursor-pointer active:scale-95"
        >
          <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <span>Tolak</span>
        </button>

        <button 
          @click="acceptIncomingCall" 
          type="button" 
          class="py-3 px-4 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-black rounded-2xl shadow-lg transition flex items-center justify-center gap-2 cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
          <span>Terima Video</span>
        </button>
      </div>
    </div>

    <!-- 3. KONDISI C: RUANG VICON AKTIF (CONNECTED / CONNECTING ROOM) -->
    <div 
      v-else 
      :class="isFullscreen ? 'fixed inset-0 z-50 rounded-none' : 'w-full max-w-5xl h-[100dvh] sm:h-[90vh] sm:max-h-[760px] rounded-none sm:rounded-3xl border-0 sm:border border-slate-800'"
      class="bg-slate-900 shadow-2xl flex flex-col overflow-hidden relative overscroll-none"
    >
      <!-- Bilah Header Atas Ruang Vicon -->
      <div class="h-14 px-3 sm:px-6 bg-slate-950/90 backdrop-blur-md border-b border-slate-800/80 flex items-center justify-between shrink-0 z-20">
        <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
          <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse shrink-0"></div>
          <div class="min-w-0">
            <h4 class="text-xs sm:text-sm font-black text-white flex items-center gap-1.5 sm:gap-2 truncate">
              <span class="truncate">{{ partnerUser?.pangkat ? partnerUser.pangkat + ' ' : '' }}{{ partnerUser?.name }}</span>
              <span class="hidden sm:inline text-[10px] font-bold text-slate-400 bg-slate-800 px-2 py-0.5 rounded-md shrink-0">
                {{ partnerUser?.jabatan || 'Dinas' }}
              </span>
            </h4>
            <div class="flex items-center gap-1.5 sm:gap-2 text-[10px] text-slate-400">
              <span>Durasi: <strong class="text-slate-200 font-mono">{{ formatDuration(callDuration) }}</strong></span>
              <span>&bull;</span>
              <span class="text-emerald-400 font-semibold truncate">{{ connectionStatusText }}</span>
            </div>
          </div>
        </div>

        <!-- Tombol Aksi Kanan Header -->
        <div class="flex items-center gap-2 shrink-0">
          <!-- Lencana Keamanan Militer -->
          <div class="hidden md:flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-950/60 border border-emerald-800/60 text-[10px] font-bold text-emerald-400">
            <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            <span>Terenkripsi E2E (Agora SD-RTN)</span>
          </div>

          <!-- Tombol Fullscreen -->
          <button 
            @click="toggleFullscreen" 
            type="button" 
            class="p-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-xl transition cursor-pointer"
            :title="isFullscreen ? 'Kecilkan Layar' : 'Layar Penuh'"
          >
            <svg v-if="!isFullscreen" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
            </svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Area Layar Video Utama (Remote Video & Overlay) -->
      <div class="flex-1 min-h-0 bg-slate-950 relative flex items-center justify-center overflow-hidden">
        
        <!-- Wadah Pemutar Video Lawan Bicara (Remote Video) -->
        <div 
          ref="remoteVideoContainerRef" 
          id="remote-video-player"
          class="w-full h-full object-cover sm:object-contain relative overflow-hidden"
        ></div>

        <!-- Placeholder jika Remote Video Belum Terhubung atau Kamera Lawan Nonaktif -->
        <div 
          v-if="!isRemoteMediaActive || isRemoteVideoOff" 
          class="absolute inset-0 flex flex-col items-center justify-center bg-slate-950/90 text-center space-y-3 p-6 z-10"
        >
          <div class="relative w-24 h-24 mx-auto flex items-center justify-center">
            <div v-if="!isRemoteMediaActive" class="absolute inset-0 rounded-full border border-blue-500/20 animate-ping"></div>
            <img 
              :src="getAvatarUrl(partnerUser)" 
              class="w-24 h-24 object-cover rounded-2xl border-2 border-slate-700 shadow-xl relative z-10" 
            />
          </div>
          <div>
            <h4 class="text-base font-bold text-white">{{ partnerUser?.pangkat ? partnerUser.pangkat + ' ' : '' }}{{ partnerUser?.name }}</h4>
            <p class="text-xs text-slate-400 mt-0.5">
              {{ !isRemoteMediaActive ? 'Menghubungkan jalur vicon langsung bebas hambatan...' : 'Kamera lawan bicara sedang dinonaktifkan' }}
            </p>
          </div>
          <div v-if="!isRemoteMediaActive" class="flex items-center gap-2 text-xs text-blue-400">
            <span class="w-2 h-2 rounded-full bg-blue-500 animate-ping"></span>
            <span>{{ connectionStatusText }}</span>
          </div>
        </div>

        <!-- Wadah Video Lokal Pengguna (Miniatur PiP di Sudut Kanan Bawah) -->
        <div 
          class="absolute bottom-3 right-3 sm:bottom-4 sm:right-4 w-28 sm:w-44 aspect-[3/4] sm:aspect-video bg-slate-900 border-2 border-slate-700/90 rounded-xl sm:rounded-2xl shadow-2xl overflow-hidden z-20 group transition-all"
        >
          <div 
            ref="localVideoContainerRef" 
            id="local-video-player"
            class="w-full h-full object-cover"
          ></div>

          <!-- Placeholder saat Kamera Lokal Dimatikan -->
          <div 
            v-if="isCameraOff" 
            class="absolute inset-0 bg-slate-900 flex flex-col items-center justify-center text-slate-400 z-10"
          >
            <svg class="w-6 h-6 text-slate-500 mb-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
            <span class="text-[10px] font-bold">Kamera Mati</span>
          </div>

          <!-- Lencana Nama Lokal -->
          <div class="absolute bottom-1.5 left-2 right-2 flex items-center justify-between pointer-events-none text-[9px] font-bold text-white/90 z-20">
            <span class="truncate bg-slate-950/70 px-1.5 py-0.5 rounded">Anda</span>
            <span v-if="isMuted" class="bg-red-600/90 px-1.5 py-0.5 rounded">Mute</span>
          </div>
        </div>
      </div>

      <!-- Bilah Kontrol Bawah (Toolbar Melayang Gaya Militer Asli) -->
      <div class="h-16 sm:h-20 pb-[env(safe-area-inset-bottom,0px)] px-2 sm:px-4 bg-slate-950/95 backdrop-blur-md border-t border-slate-800 flex items-center justify-around sm:justify-center gap-2 sm:gap-4 shrink-0 z-20">
        
        <!-- 1. Tombol Mikrofon (Mute/Unmute) -->
        <button 
          @click="toggleMute" 
          type="button" 
          :class="isMuted ? 'bg-red-600 text-white' : 'bg-slate-800 hover:bg-slate-700 text-slate-200'"
          class="p-3 sm:p-3.5 rounded-xl sm:rounded-2xl shadow-md transition cursor-pointer flex flex-col items-center gap-1 active:scale-95"
          :title="isMuted ? 'Nyalakan Mikrofon' : 'Matikan Mikrofon'"
        >
          <svg v-if="!isMuted" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
          </svg>
          <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
          </svg>
        </button>

        <!-- 2. Tombol Kamera (On/Off) -->
        <button 
          @click="toggleCamera" 
          type="button" 
          :class="isCameraOff ? 'bg-red-600 text-white' : 'bg-slate-800 hover:bg-slate-700 text-slate-200'"
          class="p-3 sm:p-3.5 rounded-xl sm:rounded-2xl shadow-md transition cursor-pointer flex flex-col items-center gap-1 active:scale-95"
          :title="isCameraOff ? 'Nyalakan Kamera' : 'Matikan Kamera'"
        >
          <svg v-if="!isCameraOff" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
          <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
          </svg>
        </button>

        <!-- 3. Tombol Flip / Ganti Kamera -->
        <button 
          @click="flipCamera" 
          type="button" 
          class="p-3 sm:p-3.5 rounded-xl sm:rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-200 shadow-md transition cursor-pointer flex flex-col items-center gap-1 active:scale-95"
          title="Ganti Sudut Kamera"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
        </button>

        <!-- 4. Tombol Akhiri Panggilan (Hang Up) -->
        <button 
          @click="hangUpCall" 
          type="button" 
          class="py-3 px-5 sm:px-7 bg-red-600 hover:bg-red-700 active:scale-95 text-white font-black text-xs sm:text-sm rounded-xl sm:rounded-2xl shadow-xl transition flex items-center gap-2 cursor-pointer ml-1"
          title="Akhiri Panggilan Dinas"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 8l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M5 3a2 2 0 00-2 2v1c0 8.284 6.716 15 15 15h1a2 2 0 002-2v-3.28a1 1 0 00-.684-.948l-4.493-1.498a1 1 0 00-1.21.502l-1.13 2.257a11.042 11.042 0 01-5.516-5.517l2.257-1.128a1 1 0 00.502-1.21L9.228 3.684A1 1 0 008.279 3H5z" />
          </svg>
          <span>Akhiri</span>
        </button>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { 
  startIncomingCallRing, 
  stopIncomingCallRing, 
  startOutgoingDialRing, 
  stopOutgoingDialRing, 
  playCallEndedSound 
} from '@/Utils/sound';

const props = defineProps({
  show: Boolean,
  threadUuid: String,
  userRole: { type: String, default: 'ADMIN' }, // 'ADMIN', 'OPERATOR', 'USER'
  currentUser: Object,
  partnerUser: Object,
  incomingCallData: Object,
  urlPrefix: { type: String, default: '' },
});

const emit = defineEmits(['close', 'call-ended', 'call-accepted']);

// App ID Agora Resmi
const AGORA_APP_ID = '19daeb63b0ec46f2b02197c9fbbe81d6';

// State Status Panggilan
const callStatus = ref('IDLE'); // 'IDLE' | 'OUTGOING' | 'INCOMING' | 'CONNECTING' | 'CONNECTED' | 'ENDED'
const callDuration = ref(0);
const connectionStatusText = ref('Menghubungkan...');
const activeCaller = ref(null);
const isInitiator = ref(false);
const isFullscreen = ref(false);

// State Penanda Waktu Timeout
const outgoingSeconds = ref(0);
const connectingSeconds = ref(0);
let outgoingTimeoutTimer = null;
let connectingTimeoutTimer = null;

// State Media & Kontrol
const isMuted = ref(false);
const isCameraOff = ref(false);
const isRemoteVideoOff = ref(false);
const isRemoteMediaActive = ref(false);

// Elemen DOM Referensi Agora
const localVideoContainerRef = ref(null);
const remoteVideoContainerRef = ref(null);

// Objek Agora RTC
let agoraClient = null;
let isInitializingAgora = false;
let localAudioTrack = null;
let localVideoTrack = null;
let signalingTimer = null;
let durationTimer = null;
let availableCameras = [];
let currentCameraIndex = 0;

// URL basis panggilan
const baseUrl = computed(() => {
  if (props.urlPrefix && props.urlPrefix.length > 0) {
    return `/${props.urlPrefix}/live-chat/${props.threadUuid}/call`;
  }
  return `/live-chat/${props.threadUuid}/call`;
});

// Nama Saluran Bersama Unik per Utas Percakapan
const channelName = computed(() => {
  const cleanId = (props.threadUuid || 'dinas').replace(/[^a-zA-Z0-9]/g, '');
  return `SINDEN_${cleanId}`;
});

// Penentuan peran pemanggil
const isCaller = computed(() => {
  if (props.incomingCallData?.caller) {
    const callerType = (props.incomingCallData.caller.type || '').toUpperCase();
    const currentRole = (props.userRole || '').toUpperCase();
    if (callerType === currentRole) return true;
    if (['ADMIN', 'OPERATOR'].includes(callerType) && ['ADMIN', 'OPERATOR'].includes(currentRole)) return true;
    return false;
  }
  return Boolean(isInitiator.value);
});

const getAvatarUrl = (user) => {
  if (!user) return 'https://ui-avatars.com/api/?name=User&background=1e293b&color=94a3b8';
  if (user.avatar) {
    return user.avatar.startsWith('http') ? user.avatar : `/storage/${user.avatar}`;
  }
  if (user.photo) {
    return `/documents/private-stream?path=${encodeURIComponent(user.photo)}`;
  }
  return `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name || 'P')}&background=1e293b&color=94a3b8`;
};

const formatDuration = (totalSeconds) => {
  const m = Math.floor(totalSeconds / 60);
  const s = totalSeconds % 60;
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
};

const toggleFullscreen = () => {
  isFullscreen.value = !isFullscreen.value;
};

// ==========================================
// 1. PEMUAT PUSTAKA RESMI AGORA RTC WEB SDK
// ==========================================
const loadAgoraScript = () => {
  return new Promise((resolve, reject) => {
    if (typeof window !== 'undefined' && window.AgoraRTC) {
      resolve(window.AgoraRTC);
      return;
    }
    const existingScript = document.getElementById('agora-rtc-sdk');
    if (existingScript) {
      existingScript.addEventListener('load', () => resolve(window.AgoraRTC));
      existingScript.addEventListener('error', (e) => reject(e));
      return;
    }
    const script = document.createElement('script');
    script.id = 'agora-rtc-sdk';
    script.src = 'https://download.agora.io/sdk/release/AgoraRTC_N-4.20.2.js';
    script.async = true;
    script.onload = () => resolve(window.AgoraRTC);
    script.onerror = (e) => reject(e);
    document.head.appendChild(script);
  });
};

// ==========================================
// 2. INISIASI & PENGHUBUNGAN AGORA RTC
// ==========================================
const initAgoraRoom = async () => {
  if (agoraClient || isInitializingAgora) {
    return;
  }

  isInitializingAgora = true;
  connectionStatusText.value = 'Mempersiapkan jalur audio video...';
  await nextTick();

  try {
    const AgoraRTC = await loadAgoraScript();

    // Pastikan modal masih terbuka dan panggilan belum dibatalkan
    if (!props.show || callStatus.value === 'IDLE' || callStatus.value === 'ENDED') {
      return;
    }

    // Mode komunikasi terarah (RTC) dengan codec VP8 yang didukung seluruh peramban
    const client = AgoraRTC.createClient({ mode: 'rtc', codec: 'vp8' });
    agoraClient = client;

    // Dengarkan peristiwa saat lawan bicara mempublikasikan video atau audio
    client.on('user-published', async (user, mediaType) => {
      try {
        await client.subscribe(user, mediaType);

        if (mediaType === 'video') {
          isRemoteMediaActive.value = true;
          isRemoteVideoOff.value = false;
          await nextTick();
          if (remoteVideoContainerRef.value && user.videoTrack) {
            user.videoTrack.play(remoteVideoContainerRef.value);
          }
          handleConnected();
        }

        if (mediaType === 'audio') {
          if (user.audioTrack) {
            user.audioTrack.play();
          }
          handleConnected();
        }
      } catch (subErr) {
        console.warn('Kendala subscribe lawan bicara:', subErr);
      }
    });

    // Tangani saat lawan bicara mematikan kamera
    client.on('user-unpublished', (user, mediaType) => {
      if (mediaType === 'video') {
        isRemoteVideoOff.value = true;
      }
    });

    // Tangani saat lawan bicara keluar dari bilik panggilan
    client.on('user-left', () => {
      hangUpCall();
    });

    // Masuk ke saluran Agora RTC
    const uid = Number(props.currentUser?.id) || Math.floor(Math.random() * 900000) + 100000;
    let token = null;
    let appId = AGORA_APP_ID;

    try {
      const resToken = await axios.get(`${baseUrl.value}/agora-token?uid=${uid}`);
      if (resToken.data?.token) {
        token = resToken.data.token;
      }
      if (resToken.data?.appId) {
        appId = resToken.data.appId;
      }
    } catch (tokenErr) {
      console.debug('Pengambilan token dinas fallback ke mode App ID:', tokenErr);
    }

    // Periksa apakah panggilan dibatalkan/ditutup selama menunggu token
    if (!props.show || !agoraClient || agoraClient !== client || callStatus.value === 'IDLE' || callStatus.value === 'ENDED') {
      try { client.leave(); } catch (e) {}
      return;
    }

    await client.join(appId, channelName.value, token, uid);

    if (!props.show || !agoraClient || agoraClient !== client || callStatus.value === 'IDLE' || callStatus.value === 'ENDED') {
      try { client.leave(); } catch (e) {}
      return;
    }

    // Buat aliran mikrofon dan kamera lokal pengguna
    try {
      localAudioTrack = await AgoraRTC.createMicrophoneAudioTrack({
        AEC: true, // Acoustic Echo Cancellation
        ANS: true, // Automatic Noise Suppression
        AGC: true, // Automatic Gain Control
      });
    } catch (micErr) {
      console.warn('Akses mikrofon ditolak atau tidak tersedia:', micErr);
      isMuted.value = true;
    }

    try {
      localVideoTrack = await AgoraRTC.createCameraVideoTrack({
        encoderConfig: '720p_2',
        facingMode: 'user',
      });
    } catch (camErr) {
      console.warn('Akses kamera ditolak atau tidak tersedia:', camErr);
      isCameraOff.value = true;
    }

    // Batalkan aliran jika panggilan ditutup saat meminta izin mic/kamera
    if (!props.show || !agoraClient || agoraClient !== client || callStatus.value === 'IDLE' || callStatus.value === 'ENDED') {
      if (localAudioTrack) { try { localAudioTrack.close(); } catch (e) {} localAudioTrack = null; }
      if (localVideoTrack) { try { localVideoTrack.close(); } catch (e) {} localVideoTrack = null; }
      try { client.leave(); } catch (e) {}
      return;
    }

    // Publikasikan aliran lokal ke jaringan Agora SD-RTN
    const tracksToPublish = [];
    if (localAudioTrack) tracksToPublish.push(localAudioTrack);
    if (localVideoTrack) tracksToPublish.push(localVideoTrack);

    if (tracksToPublish.length > 0 && client) {
      await client.publish(tracksToPublish);
    }

    // Mainkan tampilan video lokal di elemen miniatur PiP
    await nextTick();
    if (localVideoContainerRef.value && localVideoTrack) {
      localVideoTrack.play(localVideoContainerRef.value);
    }

    // Ambil daftar kamera perangkat untuk keperluan flip kamera
    try {
      availableCameras = await AgoraRTC.getCameras();
    } catch (e) {}

    connectionStatusText.value = 'Tersambung ke Saluran';

  } catch (err) {
    console.error('Gagal menginisialisasi Agora RTC:', err);
    connectionStatusText.value = 'Kendala Inisialisasi';
  } finally {
    isInitializingAgora = false;
  }
};

const handleConnected = () => {
  if (callStatus.value !== 'CONNECTED') {
    callStatus.value = 'CONNECTED';
    connectionStatusText.value = 'Tersambung (Agora SD-RTN)';
    stopOutgoingDialRing();
    stopIncomingCallRing();
    stopOutgoingTimeout();
    stopConnectingTimer();
    startDurationTimer();

    // Beritahu backend bahwa sesi panggilan telah aktif terhubung
    axios.post(`${baseUrl.value}/signal`, {
      action: 'connected',
      status: 'CONNECTED',
    }).catch(() => {});
  }
};

// ==========================================
// 3. PENANGAN BATAS WAKTU (TIMEOUT HANDLERS)
// ==========================================
const startOutgoingTimeout = () => {
  stopOutgoingTimeout();
  outgoingSeconds.value = 0;
  outgoingTimeoutTimer = setInterval(() => {
    outgoingSeconds.value++;
    if (outgoingSeconds.value >= 40) {
      stopOutgoingTimeout();
      Swal.fire({
        icon: 'info',
        title: 'Panggilan Tidak Terjawab',
        text: 'Lawan bicara tidak menjawab panggilan dinas. Silakan kirim pesan tertulis pada obrolan.',
        confirmButtonColor: '#2563eb',
      });
      cancelOutgoingCall();
    }
  }, 1000);
};

const stopOutgoingTimeout = () => {
  if (outgoingTimeoutTimer) {
    clearInterval(outgoingTimeoutTimer);
    outgoingTimeoutTimer = null;
  }
  outgoingSeconds.value = 0;
};

const startConnectingTimer = () => {
  stopConnectingTimer();
  connectingSeconds.value = 0;
  connectingTimeoutTimer = setInterval(() => {
    connectingSeconds.value++;
    if (connectingSeconds.value >= 35) {
      stopConnectingTimer();
      if (callStatus.value !== 'CONNECTED') {
        hangUpCall();
      }
    }
  }, 1000);
};

const stopConnectingTimer = () => {
  if (connectingTimeoutTimer) {
    clearInterval(connectingTimeoutTimer);
    connectingTimeoutTimer = null;
  }
  connectingSeconds.value = 0;
};

const startDurationTimer = () => {
  if (durationTimer) clearInterval(durationTimer);
  callDuration.value = 0;
  durationTimer = setInterval(() => {
    callDuration.value++;
  }, 1000);
};

// ==========================================
// 4. KONTROL MIKROFON, KAMERA & FLIP
// ==========================================
const toggleMute = () => {
  if (localAudioTrack) {
    const nextState = !isMuted.value;
    localAudioTrack.setEnabled(!nextState);
    isMuted.value = nextState;
  }
};

const toggleCamera = () => {
  if (localVideoTrack) {
    const nextState = !isCameraOff.value;
    localVideoTrack.setEnabled(!nextState);
    isCameraOff.value = nextState;
  }
};

const flipCamera = async () => {
  if (localVideoTrack && availableCameras.length > 1) {
    try {
      currentCameraIndex = (currentCameraIndex + 1) % availableCameras.length;
      const nextCam = availableCameras[currentCameraIndex];
      await localVideoTrack.setDevice(nextCam.deviceId);
    } catch (err) {
      console.warn('Gagal beralih kamera:', err);
    }
  }
};

// ==========================================
// 5. LOGIKA PANGGILAN KELUAR & MASUK
// ==========================================
const startCall = async () => {
  try {
    isInitiator.value = true;
    callStatus.value = 'OUTGOING';
    startOutgoingDialRing();
    startOutgoingTimeout();

    // Inisiasi panggilan ke backend SINDEN
    const res = await axios.post(`${baseUrl.value}/initiate`, {
      call_type: 'video',
      channel: channelName.value,
    });

    if (res.data.success) {
      startSignalingPoll();
    } else {
      throw new Error(res.data.message || 'Gagal memulai panggilan dinas');
    }
  } catch (err) {
    console.error('Gagal memulai panggilan video:', err);
    cleanupMedia();
    emit('close');
  }
};

const acceptIncomingCall = async () => {
  isInitiator.value = false;
  stopIncomingCallRing();
  stopOutgoingTimeout();
  callStatus.value = 'CONNECTING';
  startConnectingTimer();

  try {
    await axios.post(`${baseUrl.value}/signal`, {
      action: 'accept',
      status: 'ACCEPTED',
      sender: 'callee',
    });

    startSignalingPoll();
    emit('call-accepted');

    // Langsung buka dan gabungkan ke bilik Agora RTC
    await initAgoraRoom();
  } catch (err) {
    console.error('Peringatan saat menerima panggilan:', err);
    startSignalingPoll();
    await initAgoraRoom();
  }
};

const rejectIncomingCall = async () => {
  stopIncomingCallRing();
  stopConnectingTimer();
  stopSignalingPoll();
  cleanupMedia();
  try {
    await axios.post(`${baseUrl.value}/end`, {
      reason: 'rejected',
    });
  } catch (e) {}
  callStatus.value = 'IDLE';
  emit('close');
};

const cancelOutgoingCall = async () => {
  stopOutgoingDialRing();
  stopOutgoingTimeout();
  stopConnectingTimer();
  stopSignalingPoll();
  cleanupMedia();
  try {
    await axios.post(`${baseUrl.value}/end`, {
      reason: 'canceled',
    });
  } catch (e) {}
  callStatus.value = 'IDLE';
  emit('close');
};

const hangUpCall = async () => {
  stopIncomingCallRing();
  stopOutgoingDialRing();
  stopOutgoingTimeout();
  stopConnectingTimer();
  stopSignalingPoll();
  playCallEndedSound();

  const finalDuration = callDuration.value;
  cleanupMedia();

  try {
    await axios.post(`${baseUrl.value}/end`, {
      reason: 'ended',
      duration_seconds: finalDuration,
    });
  } catch (e) {}

  callStatus.value = 'IDLE';
  emit('call-ended', finalDuration);
  emit('close');
};

const handleRemoteEnded = (reason = 'ended') => {
  stopIncomingCallRing();
  stopOutgoingDialRing();
  stopOutgoingTimeout();
  stopConnectingTimer();
  stopSignalingPoll();
  playCallEndedSound();

  const finalDuration = callDuration.value;
  cleanupMedia();

  callStatus.value = 'IDLE';
  emit('call-ended', finalDuration);
  emit('close');
};

const resumeActiveCall = async (callData) => {
  if (!callData) return;
  const isCallerUser = isCaller.value;
  isInitiator.value = isCallerUser;
  stopIncomingCallRing();
  stopOutgoingDialRing();
  stopOutgoingTimeout();

  callStatus.value = (callData.status === 'CONNECTED') ? 'CONNECTED' : 'CONNECTING';
  if (callData.status === 'CONNECTED') {
    startDurationTimer();
  } else {
    startConnectingTimer();
  }

  if (!agoraClient && !isInitializingAgora) {
    await initAgoraRoom();
  }
  startSignalingPoll();
};

// ==========================================
// 6. SINKRONISASI SINYAL STATUS PANGGILAN
// ==========================================
let missedPollCount = 0;

const startSignalingPoll = () => {
  stopSignalingPoll();
  missedPollCount = 0;

  signalingTimer = setInterval(async () => {
    if (!props.threadUuid) return;

    try {
      const res = await axios.get(`${baseUrl.value}/signal`);
      const call = res.data?.call;

      if (!call) {
        missedPollCount++;
        if (missedPollCount >= 10) {
          handleRemoteEnded('Panggilan terputus');
        }
        return;
      }

      missedPollCount = 0;

      if (call.status === 'ENDED' || call.status === 'REJECTED') {
        handleRemoteEnded(call.status === 'REJECTED' ? 'Panggilan ditolak' : 'Panggilan selesai');
        return;
      }

      const isCallerUser = isCaller.value;

      // 1. Pemanggil mendeteksi penerima menerima panggilan
      if (isCallerUser && (call.status === 'ACCEPTED' || call.status === 'CONNECTED') && (callStatus.value === 'OUTGOING' || callStatus.value === 'CONNECTING')) {
        stopOutgoingDialRing();
        stopOutgoingTimeout();
        callStatus.value = 'CONNECTING';
        if (!agoraClient && !isInitializingAgora) {
          await initAgoraRoom();
        }
      }

      // 2. Jika status di server telah CONNECTED
      if (call.status === 'CONNECTED' && callStatus.value !== 'CONNECTED') {
        handleConnected();
      }

    } catch (err) {
      console.debug('Penyelarasan sinyal panggilan:', err);
    }
  }, 1000);
};

const stopSignalingPoll = () => {
  if (signalingTimer) {
    clearInterval(signalingTimer);
    signalingTimer = null;
  }
};

// ==========================================
// 7. PEMBERSIHAN SUMBER DAYA & EVENT SIKLUS
// ==========================================
const cleanupMedia = () => {
  isInitializingAgora = false;
  stopSignalingPoll();
  stopOutgoingTimeout();
  stopConnectingTimer();
  isInitiator.value = false;
  isRemoteMediaActive.value = false;

  if (durationTimer) {
    clearInterval(durationTimer);
    durationTimer = null;
  }

  if (localAudioTrack) {
    try {
      localAudioTrack.stop();
      localAudioTrack.close();
    } catch (e) {}
    localAudioTrack = null;
  }

  if (localVideoTrack) {
    try {
      localVideoTrack.stop();
      localVideoTrack.close();
    } catch (e) {}
    localVideoTrack = null;
  }

  if (agoraClient) {
    try {
      agoraClient.leave();
      agoraClient.removeAllListeners();
    } catch (e) {}
    agoraClient = null;
  }

  if (localVideoContainerRef.value) {
    localVideoContainerRef.value.innerHTML = '';
  }
  if (remoteVideoContainerRef.value) {
    remoteVideoContainerRef.value.innerHTML = '';
  }

  callDuration.value = 0;
  isMuted.value = false;
  isCameraOff.value = false;
  isRemoteVideoOff.value = false;
  connectionStatusText.value = 'Menghubungkan...';
};

// Pantau properti show
watch(
  () => props.show,
  (newVal) => {
    if (newVal) {
      if (props.incomingCallData) {
        activeCaller.value = props.incomingCallData.caller;
        const status = props.incomingCallData.status;
        const isCallerUser = isCaller.value;
        isInitiator.value = isCallerUser;

        if (status === 'RINGING') {
          if (isCallerUser) {
            callStatus.value = 'OUTGOING';
            startOutgoingDialRing();
            startOutgoingTimeout();
          } else {
            callStatus.value = 'INCOMING';
            startIncomingCallRing();
          }
        } else if (['ACCEPTED', 'CONNECTING', 'CONNECTED'].includes(status)) {
          resumeActiveCall(props.incomingCallData);
        }
      } else {
        startCall();
      }
    } else {
      cleanupMedia();
    }
  },
  { immediate: true }
);

watch(
  () => props.incomingCallData,
  (callData) => {
    if (!callData) return;
    activeCaller.value = callData.caller;
    const isCallerUser = isCaller.value;

    if (callData.status === 'RINGING' && !isCallerUser && callStatus.value !== 'INCOMING' && callStatus.value !== 'CONNECTING' && callStatus.value !== 'CONNECTED') {
      isInitiator.value = false;
      callStatus.value = 'INCOMING';
      startIncomingCallRing();
    } else if (['ACCEPTED', 'CONNECTING', 'CONNECTED'].includes(callData.status) && callStatus.value === 'IDLE' && props.show) {
      resumeActiveCall(callData);
    }
  }
);

onMounted(() => {
  loadAgoraScript().catch(() => {});
});

onUnmounted(() => {
  cleanupMedia();
  stopIncomingCallRing();
  stopOutgoingDialRing();
  stopOutgoingTimeout();
  stopConnectingTimer();
});
</script>
