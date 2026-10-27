<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
    initialMetrics: {
        type: Object,
        default: () => ({}),
    }
});

// --- STATE METRIK SERVER ---
const metrics = ref(props.initialMetrics || {});
const isRefreshing = ref(false);
const lastUpdated = ref(new Date());

// Auto-refresh interval
const autoRefreshRate = ref(0); // 0 = nonaktif, 3 = 3s, 5 = 5s, 10 = 10s
let refreshIntervalTimer = null;

// --- STATE SPEEDTEST BY OOKLA ---
const isSpeedtestRunning = ref(false);
const speedtestResult = ref(null);
const speedtestStage = ref(''); // 'pinging', 'downloading', 'uploading', 'finalizing'

// Helper format waktu terakhir update
const formattedLastUpdated = computed(() => {
    return lastUpdated.value.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
});

// Ambil metrik terbaru dari backend
const fetchMetrics = async (silent = false) => {
    if (!silent) isRefreshing.value = true;
    try {
        const res = await axios.get(route('system-check.metrics'));
        metrics.value = res.data;
        lastUpdated.value = new Date();
    } catch (err) {
        if (!silent) {
            Swal.fire({
                icon: 'error',
                title: 'Gagal Memperbarui Metrik',
                text: 'Terjadi gangguan saat mengambil data kinerja server.',
            });
        }
    } finally {
        if (!silent) isRefreshing.value = false;
    }
};

// Ganti interval auto-refresh
const setAutoRefreshRate = (rate) => {
    autoRefreshRate.value = rate;
    if (refreshIntervalTimer) {
        clearInterval(refreshIntervalTimer);
        refreshIntervalTimer = null;
    }

    if (rate > 0) {
        refreshIntervalTimer = setInterval(() => {
            fetchMetrics(true);
        }, rate * 1000);
    }
};

// Jalankan Speedtest by Ookla
const runSpeedtest = async () => {
    if (isSpeedtestRunning.value) return;

    isSpeedtestRunning.value = true;
    speedtestStage.value = 'Menghubungkan ke Server Uji Ookla...';

    // Tahapan simulasi visual sembari menunggu respons backend
    const stageTimer1 = setTimeout(() => {
        if (isSpeedtestRunning.value) speedtestStage.value = 'Mengukur Latensi (Ping & Jitter)...';
    }, 2000);

    const stageTimer2 = setTimeout(() => {
        if (isSpeedtestRunning.value) speedtestStage.value = 'Menguji Kecepatan Unduh (Download)...';
    }, 6000);

    const stageTimer3 = setTimeout(() => {
        if (isSpeedtestRunning.value) speedtestStage.value = 'Menguji Kecepatan Unggah (Upload)...';
    }, 14000);

    try {
        const response = await axios.post(route('system-check.speedtest'), {}, {
            timeout: 95000 // 95 detik
        });

        speedtestResult.value = response.data;
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Speedtest Selesai!',
            text: `Download: ${response.data.download_mbps} Mbps | Upload: ${response.data.upload_mbps ?? '-'} Mbps`,
            showConfirmButton: false,
            timer: 4000,
        });
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Speedtest Gagal',
            text: err.response?.data?.message || 'Server membutuhkan waktu lebih lama atau koneksi sedang sibuk.',
        });
    } finally {
        clearTimeout(stageTimer1);
        clearTimeout(stageTimer2);
        clearTimeout(stageTimer3);
        isSpeedtestRunning.value = false;
        speedtestStage.value = '';
    }
};

const copyCommand = (cmd) => {
    navigator.clipboard.writeText(cmd);
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Perintah disalin ke clipboard!',
        showConfirmButton: false,
        timer: 2000,
    });
};

onMounted(() => {
    // Jalankan auto refresh default jika diinginkan atau biarkan manual
});

onUnmounted(() => {
    if (refreshIntervalTimer) {
        clearInterval(refreshIntervalTimer);
    }
});
</script>

<template>
    <Head title="Cek Sistem & Monitoring Server - Denintel" />

    <AuthenticatedLayout>
        <div class="py-6 px-3 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
            
            <!-- 1. HEADER HALAMAN & CONTROL BAR -->
            <div class="bg-white rounded-3xl p-5 sm:p-6 shadow-xs border border-slate-200 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3 sm:gap-4">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-indigo-600 flex items-center justify-center text-white shadow-md shadow-indigo-200 shrink-0">
                        <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="text-base sm:text-xl font-black text-slate-900 uppercase tracking-tight">
                                Cek Sistem & Monitoring Server
                            </h1>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-300">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse mr-1.5"></span>
                                Live Monitoring
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Status Host: <strong class="text-slate-800 font-mono">{{ metrics.server?.hostname }}</strong> &bull; Uptime: <span class="text-indigo-600 font-semibold">{{ metrics.server?.uptime_human }}</span>
                        </p>
                    </div>
                </div>

                <!-- Control Buttons -->
                <div class="flex items-center gap-2 flex-wrap">
                    <!-- Selector Auto Refresh -->
                    <div class="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 text-xs">
                        <span class="text-[10px] font-bold text-slate-400 uppercase px-2">Auto:</span>
                        <button 
                            @click="setAutoRefreshRate(0)"
                            :class="autoRefreshRate === 0 ? 'bg-white text-slate-900 font-bold shadow-2xs' : 'text-slate-500 hover:text-slate-800'"
                            class="px-2 py-1 rounded-lg transition cursor-pointer">
                            Mati
                        </button>
                        <button 
                            @click="setAutoRefreshRate(3)"
                            :class="autoRefreshRate === 3 ? 'bg-indigo-600 text-white font-bold shadow-2xs' : 'text-slate-500 hover:text-slate-800'"
                            class="px-2 py-1 rounded-lg transition cursor-pointer">
                            3s
                        </button>
                        <button 
                            @click="setAutoRefreshRate(5)"
                            :class="autoRefreshRate === 5 ? 'bg-indigo-600 text-white font-bold shadow-2xs' : 'text-slate-500 hover:text-slate-800'"
                            class="px-2 py-1 rounded-lg transition cursor-pointer">
                            5s
                        </button>
                    </div>

                    <!-- Tombol Refresh Manual -->
                    <button 
                        @click="fetchMetrics(false)"
                        :disabled="isRefreshing"
                        class="px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold uppercase transition shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                        <svg class="w-3.5 h-3.5" :class="isRefreshing ? 'animate-spin' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>{{ isRefreshing ? 'Memuat...' : 'Perbarui' }}</span>
                    </button>
                </div>
            </div>

            <!-- 2. KARTU EMPAT PILAR KINERJA (CPU, RAM, DISK, VRAM/GPU) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <!-- PILAR 1: KINERJA CPU -->
                <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-xs flex flex-col justify-between relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                            </div>
                            <span class="text-xs font-black uppercase tracking-wider text-slate-700">CPU Processor</span>
                        </div>
                        <span 
                            :class="metrics.cpu?.status === 'danger' ? 'bg-rose-100 text-rose-700' : (metrics.cpu?.status === 'warning' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800')"
                            class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase">
                            {{ metrics.cpu?.status === 'danger' ? 'Kritis' : (metrics.cpu?.status === 'warning' ? 'Tinggi' : 'Normal') }}
                        </span>
                    </div>

                    <div class="my-4 flex items-baseline justify-between">
                        <div>
                            <span class="text-3xl font-black text-slate-900 tracking-tight">{{ metrics.cpu?.usage_percent || 0 }}%</span>
                            <span class="text-xs text-slate-400 ml-1 font-bold">Terpakai</span>
                        </div>
                        <span class="text-xs font-mono font-bold text-slate-500">{{ metrics.cpu?.cores }} vCPU Core</span>
                    </div>

                    <!-- Progress Bar CPU -->
                    <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                        <div 
                            class="h-full rounded-full transition-all duration-500"
                            :class="metrics.cpu?.usage_percent > 85 ? 'bg-rose-500' : (metrics.cpu?.usage_percent > 70 ? 'bg-amber-500' : 'bg-blue-600')"
                            :style="{ width: `${metrics.cpu?.usage_percent || 0}%` }">
                        </div>
                    </div>

                    <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-[10px] text-slate-400 font-semibold">
                        <span>Load Avg: {{ metrics.cpu?.load_avg_1m }} &bull; {{ metrics.cpu?.load_avg_5m }} &bull; {{ metrics.cpu?.load_avg_15m }}</span>
                        <span class="truncate max-w-[110px]" :title="metrics.cpu?.model">{{ metrics.cpu?.model }}</span>
                    </div>
                </div>

                <!-- PILAR 2: KINERJA RAM / MEMORI -->
                <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-xs flex flex-col justify-between relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            </div>
                            <span class="text-xs font-black uppercase tracking-wider text-slate-700">RAM Sistem</span>
                        </div>
                        <span 
                            :class="metrics.ram?.status === 'danger' ? 'bg-rose-100 text-rose-700' : (metrics.ram?.status === 'warning' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800')"
                            class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase">
                            {{ metrics.ram?.status === 'danger' ? 'Kritis' : (metrics.ram?.status === 'warning' ? 'Tinggi' : 'Normal') }}
                        </span>
                    </div>

                    <div class="my-4 flex items-baseline justify-between">
                        <div>
                            <span class="text-3xl font-black text-slate-900 tracking-tight">{{ metrics.ram?.usage_percent || 0 }}%</span>
                            <span class="text-xs text-slate-400 ml-1 font-bold">Terpakai</span>
                        </div>
                        <span class="text-xs font-mono font-bold text-slate-500">{{ metrics.ram?.used_human }} / {{ metrics.ram?.total_human }}</span>
                    </div>

                    <!-- Progress Bar RAM -->
                    <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                        <div 
                            class="h-full rounded-full transition-all duration-500"
                            :class="metrics.ram?.usage_percent > 85 ? 'bg-rose-500' : (metrics.ram?.usage_percent > 70 ? 'bg-amber-500' : 'bg-indigo-600')"
                            :style="{ width: `${metrics.ram?.usage_percent || 0}%` }">
                        </div>
                    </div>

                    <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-[10px] text-slate-400 font-semibold">
                        <span>Cached: {{ metrics.ram?.cached_human }}</span>
                        <span>Swap: {{ metrics.ram?.swap_used_human }} / {{ metrics.ram?.swap_total_human }}</span>
                    </div>
                </div>

                <!-- PILAR 3: KESEHATAN & KAPASITAS HARDISK -->
                <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-xs flex flex-col justify-between relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7zm0 4h16M4 14h16"/></svg>
                            </div>
                            <span class="text-xs font-black uppercase tracking-wider text-slate-700">Hardisk Storage</span>
                        </div>
                        <span 
                            :class="metrics.disk?.overall_health === 'critical' ? 'bg-rose-100 text-rose-700' : (metrics.disk?.overall_health === 'warning' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800')"
                            class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase">
                            {{ metrics.disk?.overall_health === 'critical' ? 'Kritis' : (metrics.disk?.overall_health === 'warning' ? 'Waspada' : 'Sehat') }}
                        </span>
                    </div>

                    <div class="my-4 flex items-baseline justify-between">
                        <div>
                            <span class="text-3xl font-black text-slate-900 tracking-tight">{{ metrics.disk?.usage_percent || 0 }}%</span>
                            <span class="text-xs text-slate-400 ml-1 font-bold">Terisi</span>
                        </div>
                        <span class="text-xs font-mono font-bold text-slate-500">{{ metrics.disk?.used_human }} / {{ metrics.disk?.total_human }}</span>
                    </div>

                    <!-- Progress Bar Disk -->
                    <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                        <div 
                            class="h-full rounded-full transition-all duration-500"
                            :class="metrics.disk?.usage_percent > 85 ? 'bg-rose-500' : (metrics.disk?.usage_percent > 70 ? 'bg-amber-500' : 'bg-emerald-600')"
                            :style="{ width: `${metrics.disk?.usage_percent || 0}%` }">
                        </div>
                    </div>

                    <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-[10px] text-slate-400 font-semibold">
                        <span>Sisa Bebas: <strong class="text-emerald-600">{{ metrics.disk?.free_human }}</strong></span>
                        <span>App Storage: {{ metrics.disk?.app_storage_usage }}</span>
                    </div>
                </div>

                <!-- PILAR 4: VRAM / GRAFIS GPU -->
                <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-xs flex flex-col justify-between relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <span class="text-xs font-black uppercase tracking-wider text-slate-700">VRAM / GPU</span>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-slate-100 text-slate-700">
                            {{ metrics.vram?.has_gpu ? 'Dedicated' : 'Virtual Shared' }}
                        </span>
                    </div>

                    <div class="my-4 flex items-baseline justify-between">
                        <div>
                            <span class="text-2xl font-black text-slate-900 tracking-tight">
                                {{ metrics.vram?.has_gpu ? `${metrics.vram?.usage_percent}%` : 'Shared' }}
                            </span>
                            <span class="text-xs text-slate-400 ml-1 font-bold">{{ metrics.vram?.has_gpu ? 'VRAM Terpakai' : 'RAM Sistem' }}</span>
                        </div>
                        <span class="text-xs font-mono font-bold text-slate-500">
                            {{ metrics.vram?.has_gpu ? metrics.vram?.total_human : 'VPS Headless' }}
                        </span>
                    </div>

                    <!-- Progress Bar VRAM jika ada -->
                    <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                        <div 
                            class="h-full rounded-full bg-purple-600 transition-all duration-500"
                            :style="{ width: `${metrics.vram?.has_gpu ? metrics.vram?.usage_percent : 10}%` }">
                        </div>
                    </div>

                    <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-[10px] text-slate-400 font-semibold truncate">
                        <span class="truncate" :title="metrics.vram?.name">{{ metrics.vram?.name }}</span>
                    </div>
                </div>

            </div>

            <!-- 3. BAGIAN SPEEDTEST BY OOKLA -->
            <div class="bg-white rounded-3xl p-5 sm:p-6 border border-slate-200 shadow-xs space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-slate-900 to-indigo-950 flex items-center justify-center text-white shadow-md shrink-0">
                            <!-- Speedometer Icon -->
                            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-sm sm:text-base font-black text-slate-900 uppercase tracking-tight">
                                    Speedtest by Ookla
                                </h2>
                                <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    {{ metrics.speedtest_cli_available ? 'CLI Resmi Terdeteksi' : 'Built-in Network Engine' }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500">
                                Uji kecepatan throughput internet server secara real-time (Ping, Download, Upload, dan Kestabilan Uplink).
                            </p>
                        </div>
                    </div>

                    <!-- Tombol Picu Speedtest -->
                    <button 
                        @click="runSpeedtest"
                        :disabled="isSpeedtestRunning"
                        class="w-full sm:w-auto px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-black uppercase tracking-wider transition shadow-md hover:shadow-lg flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                        <span v-if="isSpeedtestRunning" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                        <span>{{ isSpeedtestRunning ? (speedtestStage || 'Menguji Kecepatan...') : 'Mulai Speedtest Sekarang' }}</span>
                    </button>
                </div>

                <!-- Status Berjalan Speedtest Animasi -->
                <div v-if="isSpeedtestRunning" class="p-6 bg-slate-900 text-white rounded-2xl flex flex-col items-center justify-center space-y-3 animate-pulse">
                    <div class="w-12 h-12 border-4 border-indigo-400 border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-xs font-black uppercase tracking-wider text-indigo-300">{{ speedtestStage }}</p>
                    <p class="text-[11px] text-slate-400">Pengujian membutuhkan waktu 10 hingga 30 detik untuk hasil akurat.</p>
                </div>

                <!-- Hasil Speedtest Tampil -->
                <div v-else-if="speedtestResult" class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-4 bg-slate-50 rounded-2xl border border-slate-200">
                    <!-- Download -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-2xs">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Download (Unduh)</p>
                        <p class="text-2xl sm:text-3xl font-black text-emerald-600 mt-1">{{ speedtestResult.download_mbps }} <span class="text-xs text-slate-500 font-bold">Mbps</span></p>
                    </div>

                    <!-- Upload -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-2xs">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Upload (Unggah)</p>
                        <p class="text-2xl sm:text-3xl font-black text-indigo-600 mt-1">{{ speedtestResult.upload_mbps ?? '-' }} <span class="text-xs text-slate-500 font-bold">Mbps</span></p>
                        <p v-if="speedtestResult.upload_mbps == null" class="text-[10px] text-slate-400 mt-0.5">Tidak terukur tanpa Ookla CLI</p>
                    </div>

                    <!-- Ping & Jitter -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-2xs">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Latency / Ping</p>
                        <p class="text-2xl sm:text-3xl font-black text-slate-900 mt-1">{{ speedtestResult.ping }} <span class="text-xs text-slate-500 font-bold">ms</span></p>
                        <p class="text-[10px] text-slate-400 mt-0.5">Jitter: {{ speedtestResult.jitter ?? '-' }} ms</p>
                    </div>

                    <!-- Provider & Server Info -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-2xs">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Server & ISP</p>
                        <p class="text-xs font-bold text-slate-800 truncate mt-1" :title="speedtestResult.isp">{{ speedtestResult.isp }}</p>
                        <p class="text-[10px] text-slate-400 truncate mt-0.5" :title="speedtestResult.server">{{ speedtestResult.server }}</p>
                        <a v-if="speedtestResult.result_url" :href="speedtestResult.result_url" target="_blank" class="text-[10px] text-indigo-600 hover:underline font-bold mt-1 inline-block">
                            Lihat Sertifikat Hasil &rarr;
                        </a>
                    </div>
                </div>

                <!-- Petunjuk Pemasangan CLI Ookla jika belum terpasang -->
                <div v-if="!metrics.speedtest_cli_available" class="p-3.5 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-900 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>Untuk hasil resmi bersertifikasi Ookla, pasang CLI resmi di terminal VPS Anda:</span>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <code class="px-2 py-1 bg-white rounded border border-amber-300 font-mono text-[11px] text-slate-800 select-all">sudo apt install speedtest</code>
                        <button 
                            @click="copyCommand('sudo apt install speedtest')"
                            class="px-2 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded font-bold text-[10px] uppercase transition cursor-pointer">
                            Salin
                        </button>
                    </div>
                </div>
            </div>

            <!-- 4. PANTAU KESEHATAN HARDISK & PARTISI SISTEM -->
            <div class="bg-white rounded-3xl p-5 sm:p-6 border border-slate-200 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm sm:text-base font-black text-slate-900 uppercase tracking-tight">
                            Pantau Kesehatan & Partisi Hardisk
                        </h2>
                        <p class="text-xs text-slate-500">
                            Analisis pembagian volume penyimpanan, pemakaian blok, dan status Inodes filesystem.
                        </p>
                    </div>
                    <span class="text-xs font-mono font-bold text-slate-600 bg-slate-100 px-3 py-1 rounded-xl">
                        Total: {{ metrics.disk?.total_human }}
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                <th class="p-3.5">Mount Point</th>
                                <th class="p-3.5">Filesystem Device</th>
                                <th class="p-3.5 text-center">Kapasitas (Terpakai / Total)</th>
                                <th class="p-3.5 text-center">Persentase</th>
                                <th class="p-3.5 text-center">Inodes (File Descriptors)</th>
                                <th class="p-3.5 text-right">Status Kesehatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            <tr v-for="(part, idx) in metrics.disk?.partitions" :key="idx" class="hover:bg-slate-50/70 transition">
                                <!-- Mount Point -->
                                <td class="p-3.5 font-bold text-slate-800 font-mono">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                        <span>{{ part.mount }}</span>
                                    </div>
                                </td>

                                <!-- Filesystem Device -->
                                <td class="p-3.5 text-slate-500 font-mono text-[11px] break-all min-w-[160px]">
                                    {{ part.filesystem }}
                                </td>

                                <!-- Kapasitas -->
                                <td class="p-3.5 text-center font-mono font-bold text-slate-700 whitespace-nowrap">
                                    {{ part.used_human }} / {{ part.total_human }}
                                    <span class="text-[10px] text-slate-400 font-normal block">Sisa: {{ part.free_human }}</span>
                                </td>

                                <!-- Persentase -->
                                <td class="p-3.5 text-center">
                                    <div class="flex items-center gap-2 justify-center max-w-[140px] mx-auto">
                                        <div class="flex-1 bg-slate-100 rounded-full h-2 overflow-hidden">
                                            <div 
                                                class="h-full rounded-full transition-all duration-500"
                                                :class="part.usage_percent > 85 ? 'bg-rose-500' : (part.usage_percent > 70 ? 'bg-amber-500' : 'bg-emerald-600')"
                                                :style="{ width: `${part.usage_percent}%` }">
                                            </div>
                                        </div>
                                        <span class="font-mono font-bold text-[11px] text-slate-800 min-w-8 text-right">{{ part.usage_percent }}%</span>
                                    </div>
                                </td>

                                <!-- Inodes -->
                                <td class="p-3.5 text-center font-mono text-[11px] text-slate-600">
                                    <template v-if="part.inodes?.inodes_total > 0">
                                        <span>{{ part.inodes?.inodes_percent }}%</span>
                                        <span class="text-[9px] text-slate-400 block">({{ part.inodes?.inodes_used?.toLocaleString() }} items)</span>
                                    </template>
                                    <template v-else>
                                        <span class="text-slate-400">Normal</span>
                                    </template>
                                </td>

                                <!-- Status Kesehatan -->
                                <td class="p-3.5 text-right whitespace-nowrap">
                                    <span
                                        :class="part.health_status === 'critical' ? 'bg-rose-100 text-rose-800 border-rose-300' : (part.health_status === 'warning' ? 'bg-amber-100 text-amber-800 border-amber-300' : 'bg-emerald-100 text-emerald-800 border-emerald-300')"
                                        :title="part.health_status === 'critical' ? 'Ruang hampir habis' : (part.health_status === 'warning' ? 'Pemakaian tinggi' : 'Kondisi optimal')"
                                        class="inline-flex items-center gap-1.5 whitespace-nowrap px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border">
                                        <span
                                            class="w-1.5 h-1.5 rounded-full shrink-0"
                                            :class="part.health_status === 'critical' ? 'bg-rose-500' : (part.health_status === 'warning' ? 'bg-amber-500' : 'bg-emerald-500')"
                                        ></span>
                                        {{ part.health_status === 'critical' ? 'Hampir Penuh' : (part.health_status === 'warning' ? 'Waspada' : 'Sehat') }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 5. INFORMASI EKOSISTEM SERVER, DATABASE, & JARINGAN (DKK) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                
                <!-- Lingkungan Server -->
                <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-xs space-y-3">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>
                        <span>Sistem Operasi & Web Server</span>
                    </h3>
                    <div class="divide-y divide-slate-100 text-xs">
                        <div class="py-2 flex justify-between">
                            <span class="text-slate-400">OS</span>
                            <span class="font-bold text-slate-800 text-right">{{ metrics.server?.os }}</span>
                        </div>
                        <div class="py-2 flex justify-between">
                            <span class="text-slate-400">Kernel</span>
                            <span class="font-mono text-slate-800">{{ metrics.server?.kernel }}</span>
                        </div>
                        <div class="py-2 flex justify-between">
                            <span class="text-slate-400">Arsitektur</span>
                            <span class="font-bold text-slate-800">{{ metrics.server?.architecture }}</span>
                        </div>
                        <div class="py-2 flex justify-between">
                            <span class="text-slate-400">PHP Version</span>
                            <span class="font-mono font-bold text-indigo-600">v{{ metrics.server?.php_version }}</span>
                        </div>
                        <div class="py-2 flex justify-between">
                            <span class="text-slate-400">Memory Limit</span>
                            <span class="font-mono text-slate-800">{{ metrics.server?.php_memory_limit }}</span>
                        </div>
                    </div>
                </div>

                <!-- Database & OPcache -->
                <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-xs space-y-3">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7zm0 4h16M4 14h16"/></svg>
                        <span>Basis Data & Cache Engine</span>
                    </h3>
                    <div class="divide-y divide-slate-100 text-xs">
                        <div class="py-2 flex justify-between">
                            <span class="text-slate-400">Database Driver</span>
                            <span class="font-bold text-slate-800">{{ metrics.database?.driver }}</span>
                        </div>
                        <div class="py-2 flex justify-between">
                            <span class="text-slate-400">Database Name</span>
                            <span class="font-mono font-bold text-slate-800">{{ metrics.database?.database_name }}</span>
                        </div>
                        <div class="py-2 flex justify-between">
                            <span class="text-slate-400">Ukuran Data</span>
                            <span class="font-mono font-bold text-emerald-600">{{ metrics.database?.size_human }}</span>
                        </div>
                        <div class="py-2 flex justify-between">
                            <span class="text-slate-400">Jumlah Tabel</span>
                            <span class="font-mono text-slate-800">{{ metrics.database?.total_tables }} tabel</span>
                        </div>
                        <div class="py-2 flex justify-between">
                            <span class="text-slate-400">Zend OPcache</span>
                            <span class="font-bold" :class="metrics.server?.opcache_enabled ? 'text-emerald-600' : 'text-slate-400'">
                                {{ metrics.server?.opcache_enabled ? 'Aktif (Optimal)' : 'Nonaktif' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Jaringan & Waktu Server -->
                <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-xs space-y-3">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span>Traffic Jaringan & Waktu</span>
                    </h3>
                    <div class="divide-y divide-slate-100 text-xs">
                        <div class="py-2 flex justify-between">
                            <span class="text-slate-400">Waktu Server</span>
                            <span class="font-mono text-slate-800 text-right">{{ metrics.server?.server_time }}</span>
                        </div>
                        <div class="py-2 flex justify-between">
                            <span class="text-slate-400">Zona Waktu</span>
                            <span class="font-bold text-slate-800">{{ metrics.server?.timezone }}</span>
                        </div>
                        <div v-for="iface in metrics.network?.interfaces" :key="iface.name" class="py-2 flex justify-between">
                            <span class="text-slate-400">{{ iface.name }} (Rx / Tx)</span>
                            <span class="font-mono text-[11px] text-slate-700">{{ iface.rx_human }} / {{ iface.tx_human }}</span>
                        </div>
                        <div class="py-2 flex justify-between text-[11px]">
                            <span class="text-slate-400">Pembaruan Terakhir</span>
                            <span class="font-bold text-indigo-600">{{ formattedLastUpdated }}</span>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </AuthenticatedLayout>
</template>
