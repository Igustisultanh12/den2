<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    submission: Object,
    all_submissions: Array,
    searched_identifier: String,
    not_found: Boolean,
    stages: Array,
});

const page = usePage();
const pageSettings = computed(() => page.props.settings || {});
const appName = computed(() => pageSettings.value.agency_name || pageSettings.value.app_name || 'SINDEN');
const configuredLogo = computed(() => {
    const logo = pageSettings.value.agency_logo || pageSettings.value.logo || pageSettings.value.logo_tni;
    if (!logo) return null;
    return (logo.startsWith('http') || logo.startsWith('/storage')) ? logo : '/storage/' + logo;
});
const loginBg = computed(() => {
    const bg = pageSettings.value.login_background;
    if (!bg) return null;
    return (bg.startsWith('http') || bg.startsWith('/storage')) ? bg : '/storage/' + bg;
});

const searchInput = ref(props.searched_identifier || '');
const isSearching = ref(false);

const activeSubmission = ref(props.submission);

watch(() => props.submission, (newVal) => {
    activeSubmission.value = newVal;
});

const selectSubmission = (sub) => {
    activeSubmission.value = sub;
};

const handleSearch = () => {
    const query = searchInput.value.trim();
    if (!query) return;

    isSearching.value = true;
    router.get(route('tracking-sc.index'), { identifier: query }, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            isSearching.value = false;
        }
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }) + ' WIB';
};
</script>

<template>
    <Head title="Pelacakan Pengajuan Security Clearance (SC) - SINDEN" />

    <div 
        class="min-h-screen flex flex-col justify-between text-slate-100 font-sans bg-cover bg-center bg-fixed relative transition-all duration-300 bg-slate-950 selection:bg-orange-500 selection:text-white"
        :style="loginBg ? { backgroundImage: `url(${loginBg})` } : {}"
    >
        <!-- Dark overlay matching Login.vue -->
        <div class="absolute inset-0 bg-slate-950/85 backdrop-blur-[2px] z-0 pointer-events-none"></div>

        <!-- Main Wrapper with Relative z-10 -->
        <div class="relative z-10 flex-1 flex flex-col justify-between">
            
            <!-- Header Kedinasan Glassmorphism -->
            <header class="border-b border-white/10 bg-slate-900/80 backdrop-blur-md sticky top-0 z-30">
                <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3.5 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <img v-if="configuredLogo" :src="configuredLogo" class="h-9 w-9 object-contain drop-shadow-[0_4px_10px_rgba(0,0,0,0.5)]" alt="Logo Kedinasan" />
                        <div v-else class="w-9 h-9 rounded-xl bg-orange-600/30 border border-orange-500/40 flex items-center justify-center font-black text-white text-base">
                            S
                        </div>
                        <div>
                            <span class="text-[9px] font-mono font-bold text-orange-400 uppercase tracking-widest block">PORTAL RESMI SINDEN</span>
                            <h1 class="text-xs sm:text-sm font-extrabold text-white tracking-tight">Pelacakan Security Clearance (SC)</h1>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Container -->
            <main class="max-w-4xl mx-auto px-4 sm:px-6 py-8 sm:py-12 w-full space-y-8 flex-1">
                
                <!-- Hero Section: Floating Single Logo & App Title like Login.vue -->
                <div class="text-center space-y-4">
                    <div class="flex justify-center animate-float-slow">
                        <img v-if="configuredLogo" :src="configuredLogo" class="h-28 sm:h-36 object-contain drop-shadow-[0_10px_25px_rgba(0,0,0,0.6)]" alt="Logo SINDEN" />
                        <div v-else class="w-24 h-24 rounded-3xl bg-blue-600/30 backdrop-blur-md border border-blue-500/40 flex items-center justify-center shadow-2xl">
                            <span class="font-black text-white text-4xl">S</span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 border border-white/10 text-orange-400 text-[10px] font-mono font-black uppercase tracking-widest shadow-sm">
                            <span class="w-2 h-2 rounded-full bg-orange-400 animate-pulse"></span>
                            PELAYANAN MANDIRI PUBLIK
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight uppercase text-white drop-shadow-md">
                            {{ appName }}
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-300 font-medium max-w-lg mx-auto leading-relaxed">
                            Sistem Informasi Detasemen Intelijen — Layanan publik pengecekan perkembangan berkas Security Clearance (SC) secara mandiri, transparan, dan terintegrasi.
                        </p>
                    </div>
                </div>

                <!-- Search Form Card (Identik dengan form login di Login.vue) -->
                <div class="bg-slate-900/90 backdrop-blur-md border border-white/10 rounded-2xl p-6 sm:p-8 shadow-2xl text-white animate-float-card">
                    <div class="mb-5 text-left">
                        <h3 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            Lacak Status Berkas SC
                        </h3>
                        <p class="text-xs text-slate-300 mt-0.5">
                            Gunakan nomor identitas kedinasan (NRP / NIP / NIK / NIM) atau kode pelacakan untuk memantau tahapan berkas.
                        </p>
                    </div>

                    <form @submit.prevent="handleSearch" class="flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <input 
                                type="text" 
                                v-model="searchInput" 
                                required 
                                placeholder="Masukkan NRP / NIP / NIK / NIM atau Kode Pelacakan..." 
                                class="w-full pl-10 pr-4 py-2.5 sm:py-3 rounded-lg border text-sm outline-none transition duration-150 bg-white/10 border-white/10 text-white placeholder-slate-400 focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 font-semibold"
                            />
                        </div>
                        <button 
                            type="submit"
                            :disabled="isSearching"
                            class="px-6 py-2.5 sm:py-3 text-white text-sm font-semibold rounded-lg shadow-lg bg-orange-500 hover:bg-orange-600 shadow-orange-500/20 transition duration-150 disabled:opacity-50 cursor-pointer flex items-center justify-center gap-2 shrink-0"
                        >
                            <svg v-if="isSearching" class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ isSearching ? 'Memeriksa...' : 'Cek Status' }}</span>
                        </button>
                    </form>
                    <p class="text-[10px] text-slate-400 font-medium mt-3">
                        *Pemeriksaan terintegrasi langsung dengan database Sintel Mako Koarmada 2 tanpa memerlukan login.
                    </p>
                </div>

                <!-- HASIL TIDAK DITEMUKAN -->
                <div v-if="not_found" class="bg-slate-900/90 backdrop-blur-md border border-rose-500/30 rounded-2xl p-6 sm:p-8 text-center max-w-xl mx-auto space-y-3 shadow-2xl text-white">
                    <div class="w-12 h-12 rounded-2xl bg-rose-500/20 border border-rose-500/30 text-rose-400 mx-auto flex items-center justify-center font-black text-xl">
                        !
                    </div>
                    <h3 class="text-base font-extrabold text-white">Data Pengajuan Tidak Ditemukan</h3>
                    <p class="text-xs text-slate-300 leading-relaxed max-w-md mx-auto font-medium">
                        Tidak ditemukan berkas pengajuan Security Clearance dengan nomor identitas <span class="font-mono font-bold text-white uppercase">{{ searched_identifier }}</span>. Pastikan nomor yang dimasukkan tepat atau berkas Anda telah diregistrasikan oleh petugas Denintel.
                    </p>
                </div>

                <!-- JIKA HASIL DITEMUKAN -->
                <div v-if="activeSubmission" class="space-y-6">
                    
                    <!-- Pemilihan Tab jika ada beberapa riwayat untuk identitas yang sama -->
                    <div v-if="all_submissions && all_submissions.length > 1" class="flex flex-wrap items-center gap-2 pb-1">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mr-2">Ditemukan {{ all_submissions.length }} Berkas:</span>
                        <button 
                            v-for="(sub, idx) in all_submissions" 
                            :key="sub.id"
                            @click="selectSubmission(sub)"
                            :class="activeSubmission.id === sub.id ? 'bg-orange-500 text-white border-orange-400 shadow-md' : 'bg-slate-900/80 text-slate-300 border-white/10 hover:border-white/20'"
                            class="px-3.5 py-1.5 rounded-xl border text-xs font-bold transition cursor-pointer"
                        >
                            Berkas #{{ all_submissions.length - idx }} ({{ sub.tracking_code }})
                        </button>
                    </div>

                    <!-- KARTU IDENTITAS PEMOHON -->
                    <div class="bg-slate-900/90 backdrop-blur-md border border-white/10 rounded-2xl p-6 sm:p-8 shadow-2xl text-white">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-white/10 pb-6">
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="px-2.5 py-0.5 bg-orange-500/20 text-orange-400 border border-orange-500/30 rounded-full text-[10px] font-mono font-bold uppercase">
                                        {{ activeSubmission.tracking_code }}
                                    </span>
                                    <span :class="{
                                        'bg-emerald-500/20 text-emerald-400 border-emerald-500/30': activeSubmission.current_stage === 10 || activeSubmission.status === 'selesai',
                                        'bg-amber-500/20 text-amber-400 border-amber-500/30': activeSubmission.current_stage < 10 && activeSubmission.status !== 'ditolak',
                                        'bg-rose-500/20 text-rose-400 border-rose-500/30': activeSubmission.status === 'ditolak',
                                    }" class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border">
                                        {{ activeSubmission.status === 'selesai' || activeSubmission.current_stage === 10 ? 'SELESAI (SIAP DIAMBIL)' : 'DALAM PROSES' }}
                                    </span>
                                </div>
                                <h3 class="text-xl sm:text-2xl font-black text-white">
                                    {{ activeSubmission.nama }}
                                </h3>
                                <p class="text-xs text-slate-300 font-mono mt-0.5">
                                    {{ activeSubmission.pangkat_korps ? activeSubmission.pangkat_korps + ' - ' : '' }}{{ activeSubmission.identifier_type.toUpperCase() }}. {{ activeSubmission.identifier_number }}
                                </p>
                            </div>

                            <!-- Progress Circle & Stage Info -->
                            <div class="flex items-center gap-4 self-start md:self-auto bg-slate-950/60 border border-white/10 p-3.5 rounded-xl">
                                <div class="text-right">
                                    <span class="text-[10px] text-slate-400 uppercase font-bold block">Kemajuan Berkas</span>
                                    <span class="text-lg font-mono font-black text-white">{{ activeSubmission.current_stage }}/10</span>
                                </div>
                                <div class="w-14 h-14 rounded-full border-4 border-slate-800 flex items-center justify-center font-mono font-black text-sm"
                                     :class="activeSubmission.current_stage === 10 ? 'border-emerald-500 text-emerald-400' : 'border-orange-500 text-orange-400'">
                                    {{ activeSubmission.progress_percentage }}%
                                </div>
                            </div>
                        </div>

                        <!-- Metadata Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs pt-6">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase block">Satuan / Kesatuan:</span>
                                <span class="font-extrabold text-slate-100 mt-0.5 block">{{ activeSubmission.kesatuan || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase block">Keperluan SC:</span>
                                <span class="font-extrabold text-slate-100 mt-0.5 block">{{ activeSubmission.keperluan || 'Kedinasan' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase block">Status SKHPP:</span>
                                <span class="font-bold text-slate-100 mt-0.5 block">
                                    <template v-if="activeSubmission.skhpp_id">
                                        <span class="text-emerald-400 font-extrabold flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            TTE Komandan
                                        </span>
                                    </template>
                                    <template v-else-if="activeSubmission.file_skhpp">
                                        <span class="text-blue-400 font-extrabold flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            TTD Basah (Unggahan)
                                        </span>
                                    </template>
                                    <template v-else>
                                        <span class="text-slate-400">{{ activeSubmission.nomor_skhpp || 'Menunggu Pengesahan' }}</span>
                                    </template>
                                </span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase block">Nomor SC Resmi:</span>
                                <span class="font-mono font-extrabold text-emerald-400 mt-0.5 block text-sm">{{ activeSubmission.nomor_sc || 'Menunggu Terbit' }}</span>
                            </div>
                        </div>

                        <!-- KOTAK KHUSUS NOMOR SC RESMI KEDINASAN -->
                        <div v-if="activeSubmission.nomor_sc" class="mt-6 pt-6 border-t border-white/10 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-emerald-950/40 p-4 rounded-xl border border-emerald-500/30">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-emerald-600/20 border border-emerald-500/40 text-emerald-400 flex items-center justify-center font-black shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                </div>
                                <div>
                                    <span class="text-[10px] font-mono font-bold text-emerald-400 uppercase tracking-widest block">NOMOR SURAT SECURITY CLEARANCE (SC) RESMI:</span>
                                    <span class="text-base sm:text-xl font-mono font-black text-white tracking-wide">{{ activeSubmission.nomor_sc }}</span>
                                </div>
                            </div>
                            <div class="text-[11px] font-bold text-emerald-300 bg-emerald-900/60 px-3 py-1.5 rounded-xl border border-emerald-700/40 shrink-0">
                                Terdaftar Sah di Denintel Koarmada 2
                            </div>
                        </div>
                    </div>

                    <!-- BANNER KHUSUS TAHAP 10 (SIAP DIAMBIL DI SINTEL MAKO Koarmada 2) -->
                    <div v-if="activeSubmission.current_stage === 10 || activeSubmission.status === 'selesai'" class="bg-gradient-to-r from-emerald-950/80 via-slate-900/90 to-teal-950/80 backdrop-blur-md border border-emerald-500/50 rounded-2xl p-6 sm:p-8 shadow-2xl flex flex-col md:flex-row items-start md:items-center justify-between gap-6 text-white">
                        <div class="flex items-start gap-4">
                            <div class="w-14 h-14 rounded-2xl bg-emerald-600 text-white flex items-center justify-center font-black text-2xl shrink-0 shadow-lg shadow-emerald-600/30">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <div class="space-y-1.5">
                                <span class="px-2.5 py-0.5 bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 rounded-full text-[10px] font-black uppercase tracking-wider">
                                    TAHAP AKHIR TERPENUHI - SELESAI
                                </span>
                                <h4 class="text-lg sm:text-xl font-black text-white">
                                    Dokumen SC Telah Terbit & Siap Diambil di Sintel Mako Koarmada 2
                                </h4>
                                <p class="text-xs text-slate-300 leading-relaxed font-medium max-w-2xl">
                                    Surat Security Clearance (SC) atas nama personel bersangkutan telah resmi ditandatangani dan selesai diproses. Berkas fisik sah asli siap diambil oleh pemohon di <b class="text-white">Sintel Mako Koarmada 2</b> dengan menunjukkan identitas kedinasan.
                                </p>
                                <div class="pt-2">
                                    <a 
                                        href="https://maps.app.goo.gl/STtPpz4G6G2DfnKC7" 
                                        target="_blank" 
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-black uppercase tracking-wider transition shadow-lg shadow-emerald-600/30 cursor-pointer"
                                    >
                                        <svg class="w-4 h-4 text-emerald-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span>Buka Maps</span>
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="shrink-0 flex flex-col items-start md:items-end gap-2">
                            <div class="font-mono text-xs font-bold text-emerald-300 bg-emerald-950/80 px-4 py-2.5 rounded-xl border border-emerald-600/40">
                                No. SC: {{ activeSubmission.nomor_sc || 'Tercatat di Mako' }}
                            </div>
                            <span class="text-[11px] font-bold text-emerald-400 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                                Status: Siap Diambil Pemohon
                            </span>
                        </div>
                    </div>

                    <!-- 10-STAGE TIMELINE TRACKER -->
                    <div class="bg-slate-900/90 backdrop-blur-md border border-white/10 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6 text-white">
                        <div>
                            <span class="text-[10px] font-mono font-bold text-orange-400 uppercase tracking-widest block">ALUR 10 TAHAPAN KEDINASAN</span>
                            <h4 class="text-base sm:text-lg font-black text-white">
                                Linimasa Perkembangan Berkas Pengajuan
                            </h4>
                        </div>

                        <!-- Stage Stepper List -->
                        <div class="space-y-3">
                            <div 
                                v-for="stg in stages" 
                                :key="stg.id"
                                :class="[
                                    (stg.id < activeSubmission.current_stage || (stg.id === 10 && (activeSubmission.current_stage === 10 || activeSubmission.status === 'selesai'))) ? 'bg-emerald-950/20 border-emerald-500/30' : '',
                                    (stg.id === activeSubmission.current_stage && activeSubmission.current_stage < 10 && activeSubmission.status !== 'selesai') ? 'bg-orange-950/30 border-orange-500 ring-2 ring-orange-500/20' : '',
                                    (stg.id > activeSubmission.current_stage && activeSubmission.status !== 'selesai') ? 'bg-white/5 border-white/10 opacity-60' : ''
                                ]"
                                class="p-4 sm:p-5 rounded-2xl border transition-all duration-200"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-start gap-3.5">
                                        <!-- Icon Status Step -->
                                        <div 
                                            :class="[
                                                (stg.id < activeSubmission.current_stage || (stg.id === 10 && (activeSubmission.current_stage === 10 || activeSubmission.status === 'selesai'))) ? 'bg-emerald-500 text-white' : '',
                                                (stg.id === activeSubmission.current_stage && activeSubmission.current_stage < 10 && activeSubmission.status !== 'selesai') ? 'bg-orange-500 text-white animate-pulse' : '',
                                                (stg.id > activeSubmission.current_stage && activeSubmission.status !== 'selesai') ? 'bg-slate-800 text-slate-500' : ''
                                            ]"
                                            class="w-8 h-8 rounded-xl flex items-center justify-center font-mono font-bold text-xs shrink-0 shadow"
                                        >
                                            <template v-if="stg.id < activeSubmission.current_stage || (stg.id === 10 && (activeSubmission.current_stage === 10 || activeSubmission.status === 'selesai'))">
                                                ✓
                                            </template>
                                            <template v-else>
                                                {{ stg.id }}
                                            </template>
                                        </div>

                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-[10px] font-mono uppercase font-bold"
                                                      :class="stg.id <= activeSubmission.current_stage ? 'text-orange-400' : 'text-slate-500'">
                                                    Tahap {{ stg.id }}
                                                </span>
                                                <span v-if="stg.id === activeSubmission.current_stage && activeSubmission.status !== 'selesai' && activeSubmission.current_stage < 10" 
                                                      class="px-2 py-0.5 bg-orange-500/20 text-orange-300 border border-orange-500/30 rounded-full text-[9px] font-extrabold uppercase animate-pulse">
                                                    Sedang Berjalan
                                                </span>
                                                <span v-else-if="stg.id < activeSubmission.current_stage || (stg.id === 10 && (activeSubmission.current_stage === 10 || activeSubmission.status === 'selesai'))"
                                                      class="px-2 py-0.5 bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 rounded-full text-[9px] font-bold uppercase">
                                                    Terpenuhi
                                                </span>
                                            </div>
                                            <h5 class="text-sm font-extrabold text-white mt-0.5">
                                                {{ stg.title }}
                                            </h5>
                                            <p class="text-xs text-slate-300 mt-1 leading-relaxed">
                                                {{ stg.desc }}
                                            </p>

                                            <!-- Tombol Buka Maps Khusus Tahap 10 jika aktif atau selesai -->
                                            <div v-if="stg.id === 10 && (activeSubmission.current_stage === 10 || activeSubmission.status === 'selesai')" class="mt-3">
                                                <a 
                                                    href="https://maps.app.goo.gl/STtPpz4G6G2DfnKC7" 
                                                    target="_blank" 
                                                    rel="noopener noreferrer"
                                                    class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-black uppercase tracking-wider transition shadow-md cursor-pointer"
                                                >
                                                    <svg class="w-3.5 h-3.5 text-emerald-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg>
                                                    <span>Buka Maps</span>
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                    </svg>
                                                </a>
                                            </div>

                                            <!-- Keterangan Khusus Tahap Aktif -->
                                            <div v-else-if="stg.id === activeSubmission.current_stage && activeSubmission.catatan_petugas" class="mt-2.5 p-3 rounded-xl bg-slate-950/70 border border-orange-500/30 text-xs">
                                                <span class="text-[10px] font-bold text-orange-400 uppercase block mb-0.5">Catatan Petugas:</span>
                                                <p class="text-slate-200 font-medium italic">"{{ activeSubmission.catatan_petugas }}"</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIWAYAT LOG PERJALANAN BERKAS (AUDIT LOGS) -->
                    <div v-if="activeSubmission.logs && activeSubmission.logs.length > 0" class="bg-slate-900/90 backdrop-blur-md border border-white/10 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-4 text-white">
                        <h4 class="text-sm font-extrabold text-white uppercase tracking-wider flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-orange-500"></span>
                            Catatan Waktu & Riwayat Pembaruan
                        </h4>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-white/5 border-b border-white/10 text-[10px] font-mono uppercase text-slate-400">
                                        <th class="p-3">Waktu</th>
                                        <th class="p-3">Tahapan</th>
                                        <th class="p-3">Keterangan / Catatan</th>
                                        <th class="p-3">Petugas</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/10 font-medium text-slate-300">
                                    <tr v-for="log in activeSubmission.logs" :key="log.id" class="hover:bg-white/5 transition">
                                        <td class="p-3 font-mono text-[11px] text-slate-400 shrink-0">
                                            {{ formatDate(log.created_at) }}
                                        </td>
                                        <td class="p-3">
                                            <span class="font-extrabold text-white">Tahap {{ log.stage }}:</span> {{ log.stage_title }}
                                        </td>
                                        <td class="p-3 text-slate-300">
                                            {{ log.notes || '-' }}
                                        </td>
                                        <td class="p-3 text-[11px] text-slate-400">
                                            {{ log.user_name || 'Petugas Kedinasan' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </main>

            <!-- Footer matching Login.vue -->
            <footer class="border-t border-white/10 bg-slate-950/80 backdrop-blur-md py-6 text-center text-xs text-slate-400 font-medium">
                <div class="max-w-6xl mx-auto px-4">
                    <AppCopyright />
                </div>
            </footer>

        </div>
    </div>
</template>

<style scoped>
.animate-float-slow {
  animation: float-logo 5s ease-in-out infinite;
}

.animate-float-card {
  animation: float-card 6s ease-in-out infinite;
}

@keyframes float-logo {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-8px);
  }
}

@keyframes float-card {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-5px);
  }
}
</style>
