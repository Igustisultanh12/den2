<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue'; 
import axios from 'axios';
import QrcodeVue from 'qrcode.vue';

const props = defineProps({
    settings: Object
});

const form = useForm({
    agency_name: props.settings.agency_name || '',
    start_number: props.settings.start_number || 1,
    wa_notifications_enabled: props.settings.wa_notifications_enabled !== undefined ? String(props.settings.wa_notifications_enabled) : '1',
    mobile_api_base_url: props.settings.mobile_api_base_url || 'https://sinden2.my.id',
    mobile_apk_download_url: props.settings.mobile_apk_download_url || 'https://sinden2.my.id/download/sinden-mobile.apk',
    mobile_api_status: props.settings.mobile_api_status || 'AKTIF',
    mobile_min_version: props.settings.mobile_min_version || '1.0.0',
    mobile_theme_mode: props.settings.mobile_theme_mode || 'system',
    mobile_primary_color: props.settings.mobile_primary_color || '#1E3A8A',
    mobile_accent_color: props.settings.mobile_accent_color || '#0284C7',
    mobile_card_radius: props.settings.mobile_card_radius || 20,
    mobile_show_balance_card: props.settings.mobile_show_balance_card !== undefined ? String(props.settings.mobile_show_balance_card) : '1',
    mobile_show_summary_trio: props.settings.mobile_show_summary_trio !== undefined ? String(props.settings.mobile_show_summary_trio) : '1',
    mobile_show_quick_actions: props.settings.mobile_show_quick_actions !== undefined ? String(props.settings.mobile_show_quick_actions) : '1',
    google_client_id: props.settings.google_client_id || '',
    google_client_secret: props.settings.google_client_secret || '',
    google_login_enabled: props.settings.google_login_enabled !== undefined ? String(props.settings.google_login_enabled) : '1',
    agora_app_id: props.settings.agora_app_id || '19daeb63b0ec46f2b02197c9fbbe81d6',
    agora_app_certificate: props.settings.agora_app_certificate || '',
    turnstile_enabled: props.settings.turnstile_enabled !== undefined ? String(props.settings.turnstile_enabled) : '0',
    turnstile_site_key: props.settings.turnstile_site_key || '',
    turnstile_secret_key: '',
    turnstile_secret_clear: false,
    fcm_enabled: props.settings.fcm_enabled !== undefined ? String(props.settings.fcm_enabled) : '1',
    fcm_google_services: '',
    fcm_service_account: '',
    fcm_clear: false,
    logo: null,
    login_background: null,
    signature_file: null,
    favicon: null,
    _method: 'PUT' 
});

// --- PEMBARUAN APLIKASI ANDROID (unggah APK) ---
const appUpdate = computed(() => props.settings.app_update || null);
const uploadLimit = computed(() => Number(props.settings.app_update_limit || 0));
const fmtSize = (b) => (b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB');
const fmtDate = (iso) => { try { return new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }); } catch (e) { return iso; } };
const uploadLimitLabel = computed(() => (uploadLimit.value > 0 && uploadLimit.value < 1e15 ? fmtSize(uploadLimit.value) : 'tanpa batas'));
const apkForm = useForm({ apk: null, notes: '', version: '', notify: true });
const apkError = ref('');
const apkInput = ref(null);
const pickApk = (event) => {
    apkError.value = '';
    const file = event.target.files && event.target.files[0];
    if (!file) { apkForm.apk = null; return; }
    if (!file.name.toLowerCase().endsWith('.apk')) {
        apkError.value = 'Pilih berkas berekstensi .apk.';
        apkForm.apk = null; event.target.value = ''; return;
    }
    if (uploadLimit.value > 0 && file.size > uploadLimit.value) {
        apkError.value = `Ukuran APK ${fmtSize(file.size)} melebihi batas unggah server (${fmtSize(uploadLimit.value)}). Naikkan upload_max_filesize & post_max_size PHP (mis. 100M) dan client_max_body_size Nginx di aaPanel.`;
        apkForm.apk = null; event.target.value = ''; return;
    }
    apkForm.apk = file;
};
const uploadApk = () => {
    if (!apkForm.apk) { apkError.value = 'Pilih berkas APK terlebih dahulu.'; return; }
    apkForm.post(route('settings.app-update.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { apkForm.reset(); if (apkInput.value) apkInput.value.value = ''; },
        onError: (errors) => { apkError.value = Object.values(errors)[0] || 'Unggah APK gagal.'; },
    });
};
const deleteApk = () => {
    if (!confirm('Hapus APK pembaruan dari server? Aplikasi di HP tidak akan ditawari pembaruan ini lagi.')) return;
    router.delete(route('settings.app-update.destroy'), { preserveScroll: true });
};

// Baca berkas JSON Firebase dari perangkat admin ke kolom formulir (tidak diunggah sebagai file).
const readJsonFile = (event, field) => {
    const file = event.target.files && event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => { form[field] = String(reader.result || ''); };
    reader.readAsText(file);
    event.target.value = '';
};

const showClientSecret = ref(false);
const showAgoraCertificate = ref(false);
const showTurnstileSecret = ref(false);
const copiedCallback = ref(false);

const callbackUrl = computed(() => {
    if (typeof window !== 'undefined') {
        return `${window.location.origin}/auth/google/callback`;
    }
    return 'https://sinden2.my.id/auth/google/callback';
});

const copyCallbackUrl = async () => {
    try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(callbackUrl.value);
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = callbackUrl.value;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        }
        copiedCallback.value = true;
        setTimeout(() => {
            copiedCallback.value = false;
        }, 2500);
    } catch (err) {
        console.error('Gagal menyalin:', err);
    }
};

const logoPreview = ref(props.settings.agency_logo ? '/storage/' + props.settings.agency_logo : null);
const bgPreview = ref(props.settings.login_background ? '/storage/' + props.settings.login_background : null);
const signaturePreview = ref(props.settings.commander_signature ? '/storage/' + props.settings.commander_signature : null);
const faviconPreview = ref(props.settings.favicon ? '/storage/' + props.settings.favicon : null);

const handleLogoChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.logo = file;
        logoPreview.value = URL.createObjectURL(file);
    }
};

const handleBgChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.login_background = file;
        bgPreview.value = URL.createObjectURL(file);
    }
};

const handleSignatureChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.signature_file = file;
        signaturePreview.value = URL.createObjectURL(file);
    }
};

const handleFaviconChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.favicon = file;
        faviconPreview.value = URL.createObjectURL(file);
    }
};

const submit = () => {
    form.post(route('settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Success updated
        }
    });
};

// --- LOGIKA WHATSAPP GATEWAY ---
const waData = ref({ status: 'LOADING', qr: null, pairingCode: null });
let waInterval = null;

const checkStatus = async () => {
    try {
        const res = await axios.get(route('settings.wa-status'));
        waData.value = {
            status: res.data.status === 'ONLINE' ? 'CONNECTED' : (res.data.status === 'WAITING_SCAN' ? 'WAITING' : 'OFFLINE'),
            qr: res.data.qr,
            pairingCode: res.data.pairing_code
        };
    } catch (error) {
        waData.value = { status: 'OFFLINE', qr: null, pairingCode: null };
    }
};

const generateNewCode = async () => {
    waData.value.status = 'LOADING';
    await checkStatus();
};

onMounted(() => {
    checkStatus();
    waInterval = setInterval(checkStatus, 5000); 
});

onUnmounted(() => { if (waInterval) clearInterval(waInterval); });
</script>

<template>
    <Head title="Pengaturan Instansi" />
    <AuthenticatedLayout>
        <div class="max-w-5xl mx-auto py-6 space-y-8 font-sans">
            
            <div class="flex justify-between items-center bg-white p-6 rounded-3xl border border-slate-200 shadow-xs">
                <div>
                    <h2 class="font-extrabold text-xl text-slate-900 uppercase tracking-tight">Pengaturan Sistem SINDEN</h2>
                    <p class="text-xs text-slate-500 font-semibold mt-1">Konfigurasi Identitas, Background Login, Logo, dan Integrasi</p>
                </div>
                <span class="text-[10px] font-extrabold bg-blue-50 text-blue-600 px-3 py-1 rounded-full uppercase tracking-wider">Admin Control</span>
            </div>

            <!-- Status WA Gateway Card -->
            <div class="bg-white p-8 rounded-3xl shadow-xs border border-slate-200 overflow-hidden space-y-6">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-6">
                    <div class="flex items-center gap-4">
                        <div :class="waData.status === 'CONNECTED' ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600'"class="w-14 h-14 rounded-2xl flex items-center justify-center transition-colors duration-500 shadow-xs">
                            <span v-if="waData.status === 'CONNECTED'" class="text-2xl animate-pulse"></span>
                            <span v-else class="text-2xl"></span>
                        </div>
                        <div>
                            <h3 class="font-extrabold text-slate-900 uppercase text-base tracking-tight">STATUS WA GATEWAY</h3>
                            <div class="flex items-center gap-2 mt-1">
                                <div :class="waData.status === 'CONNECTED' ? 'bg-emerald-500 animate-ping' : 'bg-rose-500'" class="w-2 h-2 rounded-full"></div>
                                <span class="text-[11px] font-extrabold uppercase" :class="waData.status === 'CONNECTED' ? 'text-emerald-600' : 'text-rose-600'">
                                    {{ waData.status === 'CONNECTED' ? 'TERHUBUNG / LIVE' : 'TERPUTUS / OFFLINE' }}
                                </span>
                            </div>
                            <button @click="generateNewCode" v-if="waData.status !== 'CONNECTED'" class="mt-2 text-[10px] font-extrabold text-blue-600 uppercase border-b border-dashed border-blue-300 hover:text-blue-800 transition"> Refresh Status Koneksi
                            </button>
                        </div>
                    </div>

                    <div v-if="waData.status !== 'CONNECTED' && waData.qr" class="bg-white p-4 rounded-3xl shadow-xl border-4 border-slate-900">
                        <p class="text-[9px] text-slate-900 font-extrabold uppercase mb-2 text-center tracking-wider">SCAN QR AKTIVASI</p>
                        <qrcode-vue :value="waData.qr" :size="150" level="H" class="mx-auto" />
                        <p class="mt-2 text-[8px] text-slate-400 font-bold text-center uppercase">Gunakan WhatsApp Web di HP</p>
                    </div>

                    <div v-else-if="waData.status !== 'CONNECTED' && waData.pairingCode" class="bg-slate-900 p-6 rounded-3xl shadow-xl">
                        <p class="text-[9px] text-slate-300 font-extrabold uppercase mb-3 text-center tracking-wider">KODE PAIRING ANDA</p>
                        <div class="flex gap-2">
                            <span v-for="(char, index) in waData.pairingCode" :key="index" class="bg-white text-slate-900 px-3 py-2 rounded-lg font-black text-xl font-mono shadow-inner border-b-4 border-slate-300">
                                {{ char }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Sakelar Kontrol Master Notifikasi Otomatis WhatsApp -->
                <div class="pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <h4 class="text-xs font-black uppercase text-slate-900 tracking-wider">MODUL NOTIFIKASI OTOMATIS WHATSAPP</h4>
                        <p class="text-[10px] text-slate-500 font-semibold mt-0.5">Aktifkan atau nonaktifkan pengiriman pesan WhatsApp otomatis ke HP pemohon saat ada pengajuan, pengesahan, atau revisi.</p>
                    </div>
                    <div class="flex items-center gap-3 bg-slate-50 p-2.5 px-4 rounded-2xl border border-slate-200">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" :checked="form.wa_notifications_enabled === '1'" @change="form.wa_notifications_enabled = $event.target.checked ? '1' : '0'" class="sr-only peer" />
                            <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                        </label>
                        <span class="text-xs font-black uppercase tracking-wider" :class="form.wa_notifications_enabled === '1' ? 'text-emerald-600' : 'text-rose-600'">
                            {{ form.wa_notifications_enabled === '1' ? 'NOTIFIKASI WA AKTIF' : 'NOTIFIKASI WA NONAKTIF' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Form Configuration -->
            <div class="bg-white p-8 rounded-3xl shadow-xs border border-slate-200">
                <form @submit.prevent="submit" class="space-y-8">
                    
                    <!-- File Uploads Grid: Logo, Background Login, Favicon -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Logo Instansi -->
                        <div class="flex flex-col items-center p-6 bg-slate-50 rounded-3xl border-2 border-dashed border-slate-200 group hover:border-blue-400 transition">
                            <div class="relative w-28 h-28 rounded-2xl border-4 border-white shadow-md flex items-center justify-center overflow-hidden bg-white mb-4">
                                <img v-if="logoPreview" :src="logoPreview" class="object-contain w-full h-full p-2" />
                                <span v-else class="text-slate-300 font-black text-3xl uppercase">
                                    {{ form.agency_name ? form.agency_name.charAt(0) : '?' }}
                                </span>
                            </div>
                            <div class="text-center">
                                <label class="cursor-pointer bg-white px-4 py-2 rounded-xl border border-slate-200 text-[10px] font-extrabold uppercase tracking-wider text-blue-600 hover:bg-blue-600 hover:text-white transition shadow-xs inline-block"> Ganti Logo Instansi
                                    <input type="file" @change="handleLogoChange" class="hidden" accept="image/*" />
                                </label>
                                <p class="text-[9px] text-slate-400 mt-2 uppercase font-bold">Maksimal: 2MB (JPG/PNG)</p>
                            </div>
                        </div>

                        <!-- Background Halaman Login -->
                        <div class="flex flex-col items-center p-6 bg-slate-50 rounded-3xl border-2 border-dashed border-slate-200 group hover:border-blue-400 transition">
                            <div class="relative w-28 h-28 rounded-2xl border-4 border-white shadow-md flex items-center justify-center overflow-hidden bg-slate-900 mb-4">
                                <img v-if="bgPreview" :src="bgPreview" class="object-cover w-full h-full opacity-80" />
                                <span v-else class="text-slate-400 font-black text-xs uppercase text-center p-2"> BG LOGIN
                                </span>
                            </div>
                            <div class="text-center">
                                <label class="cursor-pointer bg-white px-4 py-2 rounded-xl border border-slate-200 text-[10px] font-extrabold uppercase tracking-wider text-blue-600 hover:bg-blue-600 hover:text-white transition shadow-xs inline-block"> Background Login
                                    <input type="file" @change="handleBgChange" class="hidden" accept="image/*" />
                                </label>
                                <p class="text-[9px] text-slate-400 mt-2 uppercase font-bold">Maksimal: 5MB (Full HD)</p>
                            </div>
                        </div>

                        <!-- Favicon Web -->
                        <div class="flex flex-col items-center p-6 bg-slate-50 rounded-3xl border-2 border-dashed border-slate-200 group hover:border-blue-400 transition">
                            <div class="relative w-28 h-28 rounded-2xl border-4 border-white shadow-md flex items-center justify-center overflow-hidden bg-white mb-4">
                                <img v-if="faviconPreview" :src="faviconPreview" class="object-contain w-14 h-14" />
                                <span v-else class="text-slate-300 font-black text-3xl uppercase">
                                    
                                </span>
                            </div>
                            <div class="text-center">
                                <label class="cursor-pointer bg-white px-4 py-2 rounded-xl border border-slate-200 text-[10px] font-extrabold uppercase tracking-wider text-blue-600 hover:bg-blue-600 hover:text-white transition shadow-xs inline-block"> Ganti Favicon Web
                                    <input type="file" @change="handleFaviconChange" class="hidden" accept="image/x-icon,image/png,image/jpeg" />
                                </label>
                                <p class="text-[9px] text-slate-400 mt-2 uppercase font-bold">ICO/PNG. Maks: 1MB</p>
                            </div>
                        </div>
                    </div>

                    <!-- Text Input Fields -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider ms-1">Nama Instansi / Aplikasi</label>
                            <input v-model="form.agency_name" type="text" class="w-full rounded-2xl border-slate-200 bg-slate-50 focus:bg-white focus:ring-blue-500 focus:border-blue-600 uppercase text-xs font-bold px-4 py-3 transition" placeholder="CONTOH: DENINTEL Koarmada 2">
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[10px] font-extrabold text-blue-600 uppercase tracking-wider ms-1 flex items-center gap-1"> Nomor Urut Surat Awal
                            </label>
                            <input v-model="form.start_number" type="number" min="1" class="w-full rounded-2xl border-blue-200 bg-blue-50/50 focus:ring-blue-500 focus:border-blue-600 text-xs font-extrabold px-4 py-3 text-blue-700">
                        </div>

                        <!-- TTD Komandan Specimen -->
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-extrabold text-purple-600 uppercase tracking-wider ms-1 flex items-center gap-1"> Spesimen Tanda Tangan Komandan
                            </label>
                            <div class="flex items-center gap-4 p-3 bg-purple-50/40 rounded-2xl border border-purple-100">
                                <div class="w-14 h-14 bg-white rounded-xl border border-purple-200 flex items-center justify-center overflow-hidden shrink-0">
                                    <img v-if="signaturePreview" :src="signaturePreview" class="max-w-full max-h-full object-contain p-1" />
                                    <span v-else class="text-[8px] text-purple-300 font-bold uppercase text-center">KOSONG</span>
                                </div>
                                <div class="flex-1">
                                    <label class="block text-[10px] font-extrabold text-purple-600 uppercase cursor-pointer hover:underline"> Pilih File PNG Transparan
                                        <input type="file" @change="handleSignatureChange" class="hidden" accept="image/png" />
                                    </label>
                                    <p class="text-[8px] text-slate-400 leading-tight mt-0.5">Wajib PNG Tanpa Background agar tidak menutupi teks surat.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PENGATURAN KONTROL & INTEGRASI APK MOBILE ANDROID -->
                    <div class="pt-8 border-t border-slate-100 space-y-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-black text-sm">
                                ±
                            </div>
                            <div>
                                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">PENGATURAN KONTROL & INTEGRASI APLIKASI MOBILE (APK ANDROID)</h3>
                                <p class="text-[10px] text-slate-500 font-semibold">Atur Server Base URL, Link Unduh APK, dan Status Layanan API Mobile SINDEN</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-indigo-50/40 p-6 rounded-3xl border border-indigo-100">
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-extrabold text-indigo-900 uppercase tracking-wider ms-1">Base URL Endpoint API Mobile</label>
                                <input v-model="form.mobile_api_base_url" type="text" class="w-full rounded-2xl border-indigo-200 bg-white focus:ring-indigo-500 focus:border-indigo-600 font-mono text-xs font-bold px-4 py-3 text-slate-800" placeholder="https://sinden2.my.id">
                                <p class="text-[9px] text-indigo-500 font-bold ms-1">Domain utama tempat API HP terhubung.</p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-[10px] font-extrabold text-indigo-900 uppercase tracking-wider ms-1">Status Layanan API Mobile</label>
                                <select v-model="form.mobile_api_status" class="w-full rounded-2xl border-indigo-200 bg-white focus:ring-indigo-500 focus:border-indigo-600 text-xs font-extrabold px-4 py-3 text-slate-800">
                                    <option value="AKTIF">AKTIF (NORMAL OPERATIONAL)</option>
                                    <option value="PEMELIHARAAN">PEMELIHARAAN (MAINTENANCE MODE)</option>
                                </select>
                                <p class="text-[9px] text-indigo-500 font-bold ms-1">Bila Pemeliharaan, HP tidak dapat login.</p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-[10px] font-extrabold text-indigo-900 uppercase tracking-wider ms-1">Link Unduh Installer APK Resmi</label>
                                <input v-model="form.mobile_apk_download_url" type="text" class="w-full rounded-2xl border-indigo-200 bg-white focus:ring-indigo-500 focus:border-indigo-600 font-mono text-xs font-bold px-4 py-3 text-slate-800" placeholder="https://sinden2.my.id/download/sinden-mobile.apk">
                                <p class="text-[9px] text-indigo-500 font-bold ms-1">URL langsung untuk mendownload APK terbaru.</p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-[10px] font-extrabold text-indigo-900 uppercase tracking-wider ms-1">Versi Minimum APK Mobile</label>
                                <input v-model="form.mobile_min_version" type="text" class="w-full rounded-2xl border-indigo-200 bg-white focus:ring-indigo-500 focus:border-indigo-600 text-xs font-extrabold px-4 py-3 text-slate-800" placeholder="1.0.0">
                                <p class="text-[9px] text-indigo-500 font-bold ms-1">Versi minimum aplikasi Android yang diizinkan.</p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-[10px] font-extrabold text-indigo-900 uppercase tracking-wider ms-1">Mode Tema Mobile</label>
                                <select v-model="form.mobile_theme_mode" class="w-full rounded-2xl border-indigo-200 bg-white focus:ring-indigo-500 focus:border-indigo-600 text-xs font-extrabold px-4 py-3 text-slate-800">
                                    <option value="system">Mengikuti Sistem HP (Otomatis)</option>
                                    <option value="light">Tema Terang (Light Mode)</option>
                                    <option value="dark">Tema Gelap (Dark Mode)</option>
                                </select>
                                <p class="text-[9px] text-indigo-500 font-bold ms-1">Tampilan warna tema default aplikasi di HP.</p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-[10px] font-extrabold text-indigo-900 uppercase tracking-wider ms-1">Warna Primer Mobile</label>
                                <div class="flex items-center gap-2">
                                    <input v-model="form.mobile_primary_color" type="color" class="w-10 h-10 rounded-xl border border-indigo-200 cursor-pointer p-0.5 bg-white">
                                    <input v-model="form.mobile_primary_color" type="text" class="flex-1 rounded-2xl border-indigo-200 bg-white focus:ring-indigo-500 focus:border-indigo-600 font-mono text-xs font-bold px-4 py-3 text-slate-800" placeholder="#1E3A8A">
                                </div>
                                <p class="text-[9px] text-indigo-500 font-bold ms-1">Warna kartu saldo dan bilah atas aplikasi.</p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-[10px] font-extrabold text-indigo-900 uppercase tracking-wider ms-1">Warna Aksen Mobile</label>
                                <div class="flex items-center gap-2">
                                    <input v-model="form.mobile_accent_color" type="color" class="w-10 h-10 rounded-xl border border-indigo-200 cursor-pointer p-0.5 bg-white">
                                    <input v-model="form.mobile_accent_color" type="text" class="flex-1 rounded-2xl border-indigo-200 bg-white focus:ring-indigo-500 focus:border-indigo-600 font-mono text-xs font-bold px-4 py-3 text-slate-800" placeholder="#0284C7">
                                </div>
                                <p class="text-[9px] text-indigo-500 font-bold ms-1">Warna tombol sorotan dan badge aktif.</p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-[10px] font-extrabold text-indigo-900 uppercase tracking-wider ms-1">Radius Sudut Kartu (DP)</label>
                                <input v-model="form.mobile_card_radius" type="number" min="8" max="32" class="w-full rounded-2xl border-indigo-200 bg-white focus:ring-indigo-500 focus:border-indigo-600 text-xs font-extrabold px-4 py-3 text-slate-800" placeholder="20">
                                <p class="text-[9px] text-indigo-500 font-bold ms-1">Tingkat kelengkungan sudut kartu dashboard (8 - 32 dp).</p>
                            </div>

                            <div class="md:col-span-2 pt-4 border-t border-indigo-100 flex flex-wrap gap-4 items-center">
                                <label class="flex items-center gap-2 cursor-pointer bg-white px-4 py-2.5 rounded-2xl border border-indigo-200">
                                    <input type="checkbox" :checked="form.mobile_show_balance_card === '1'" @change="form.mobile_show_balance_card = $event.target.checked ? '1' : '0'" class="rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-[11px] font-extrabold text-slate-700">Tampilkan Kartu Saldo Utama</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer bg-white px-4 py-2.5 rounded-2xl border border-indigo-200">
                                    <input type="checkbox" :checked="form.mobile_show_summary_trio === '1'" @change="form.mobile_show_summary_trio = $event.target.checked ? '1' : '0'" class="rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-[11px] font-extrabold text-slate-700">Tampilkan Trio Ringkasan Keuangan</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer bg-white px-4 py-2.5 rounded-2xl border border-indigo-200">
                                    <input type="checkbox" :checked="form.mobile_show_quick_actions === '1'" @change="form.mobile_show_quick_actions = $event.target.checked ? '1' : '0'" class="rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-[11px] font-extrabold text-slate-700">Tampilkan Modul Akses Cepat</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- INTEGRASI GOOGLE OAUTH & SINGLE SIGN-ON (SSO) -->
                    <div class="pt-8 border-t border-slate-100 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-white border border-slate-200 shadow-xs flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24">
                                        <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/>
                                        <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/>
                                        <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.99 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
                                        <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">INTEGRASI GOOGLE OAUTH & SINGLE SIGN-ON (SSO)</h3>
                                    <p class="text-[10px] text-slate-500 font-semibold">Kelola Kredensial Google OAuth langsung dari Admin tanpa perlu akses terminal VPS</p>
                                </div>
                            </div>
                            
                            <!-- Toggle switch status OAuth -->
                            <div class="flex items-center gap-3 bg-slate-50 p-2.5 px-4 rounded-2xl border border-slate-200">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" :checked="form.google_login_enabled === '1'" @change="form.google_login_enabled = $event.target.checked ? '1' : '0'" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                                <span class="text-xs font-black uppercase tracking-wider" :class="form.google_login_enabled === '1' ? 'text-blue-600' : 'text-slate-400'">
                                    {{ form.google_login_enabled === '1' ? 'LOGIN GOOGLE AKTIF' : 'LOGIN GOOGLE NONAKTIF' }}
                                </span>
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-slate-50 to-blue-50/30 p-6 rounded-3xl border border-slate-200 space-y-6">
                            <!-- Input Client ID & Secret -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-extrabold text-slate-700 uppercase tracking-wider ms-1 flex items-center gap-1.5">
                                        <span>Google Client ID</span>
                                        <span class="text-[9px] text-rose-500 font-black">*</span>
                                    </label>
                                    <input 
                                        v-model="form.google_client_id" 
                                        type="text" 
                                        class="w-full rounded-2xl border-slate-200 bg-white focus:ring-blue-500 focus:border-blue-600 font-mono text-xs font-semibold px-4 py-3 text-slate-800 transition" 
                                        placeholder="contoh: 1234567890-abcdef.apps.googleusercontent.com"
                                    />
                                    <p class="text-[9px] text-slate-400 font-medium ms-1">Client ID OAuth 2.0 Web Client dari Google Cloud Console.</p>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-extrabold text-slate-700 uppercase tracking-wider ms-1 flex items-center justify-between">
                                        <div class="flex items-center gap-1.5">
                                            <span>Google Client Secret</span>
                                            <span class="text-[9px] text-rose-500 font-black">*</span>
                                        </div>
                                        <button 
                                            type="button" 
                                            @click="showClientSecret = !showClientSecret"
                                            class="text-[9px] font-bold text-blue-600 hover:text-blue-800 transition uppercase cursor-pointer"
                                        >
                                            {{ showClientSecret ? 'Sembunyikan' : 'Tampilkan' }}
                                        </button>
                                    </label>
                                    <div class="relative">
                                        <input 
                                            v-model="form.google_client_secret" 
                                            :type="showClientSecret ? 'text' : 'password'" 
                                            class="w-full rounded-2xl border-slate-200 bg-white focus:ring-blue-500 focus:border-blue-600 font-mono text-xs font-semibold px-4 py-3 text-slate-800 transition pr-10" 
                                            placeholder="contoh: GOCSPX-xxxxxxxxxxxxxxxx"
                                        />
                                        <button 
                                            type="button"
                                            @click="showClientSecret = !showClientSecret"
                                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 cursor-pointer"
                                        >
                                            <svg v-if="!showClientSecret" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                            </svg>
                                        </button>
                                    </div>
                                    <p class="text-[9px] text-slate-400 font-medium ms-1">Client Secret rahasia yang diberikan oleh Google Cloud Console.</p>
                                </div>
                            </div>

                            <!-- Callback URL / Authorized Redirect URIs Box -->
                            <div class="p-4 bg-white rounded-2xl border border-blue-100 shadow-xs space-y-2">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <label class="text-[10px] font-extrabold text-blue-900 uppercase tracking-wider flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                        </svg>
                                        <span>Authorized Redirect URI (Wajib Didaftarkan di Google Cloud Console)</span>
                                    </label>
                                    <button 
                                        type="button" 
                                        @click="copyCallbackUrl"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-wider transition self-start sm:self-auto cursor-pointer"
                                        :class="copiedCallback ? 'bg-emerald-600 text-white' : 'bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white border border-blue-200'"
                                    >
                                        <svg v-if="!copiedCallback" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>{{ copiedCallback ? 'Tersalin ke Clipboard!' : 'Salin URI' }}</span>
                                    </button>
                                </div>
                                <div class="bg-slate-900 text-emerald-400 font-mono text-xs px-4 py-3 rounded-xl select-all break-all border border-slate-800">
                                    <span>{{ callbackUrl }}</span>
                                </div>
                                <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-[10px] text-amber-900 space-y-1">
                                    <p class="font-bold flex items-center gap-1">
                                        <span>Panduan Google Cloud Console:</span>
                                    </p>
                                    <ol class="list-decimal list-inside space-y-0.5 text-[9.5px] text-amber-800 font-medium pl-1">
                                        <li>Buka Google Cloud Console di bagian <b>APIs & Services &rarr; Credentials</b>.</li>
                                        <li>Buka <b>OAuth 2.0 Client IDs</b> tipe Web application Anda.</li>
                                        <li>Pada bagian <b>"Authorized redirect URIs"</b>, klik <b>"+ ADD URI"</b> lalu tempel URI di atas.</li>
                                        <li>Klik <b>Save</b>. Kredensial yang disimpan pada form ini langsung aktif seketika tanpa perlu restart server atau edit file .env di VPS!</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- INTEGRASI VICON & PANGGILAN VIDEO (AGORA RTC) -->
                    <div class="pt-8 border-t border-slate-100 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-blue-50 border border-blue-200 text-blue-600 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">INTEGRASI VICON & PANGGILAN VIDEO (AGORA RTC)</h3>
                                    <p class="text-[10px] text-slate-500 font-semibold">Atur Kredensial Agora App ID dan Primary Certificate untuk Panggilan Video & Suara Kedinasan</p>
                                </div>
                            </div>
                            <span class="text-[10px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-full uppercase tracking-wider">
                                Vicon Engine
                            </span>
                        </div>

                        <div class="bg-gradient-to-br from-slate-50 to-blue-50/30 p-6 rounded-3xl border border-slate-200 space-y-6">
                            <!-- Input App ID & Certificate -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-extrabold text-slate-700 uppercase tracking-wider ms-1 flex items-center gap-1.5">
                                        <span>Agora App ID</span>
                                        <span class="text-[9px] text-rose-500 font-black">*</span>
                                    </label>
                                    <input 
                                        v-model="form.agora_app_id" 
                                        type="text" 
                                        class="w-full rounded-2xl border-slate-200 bg-white focus:ring-blue-500 focus:border-blue-600 font-mono text-xs font-semibold px-4 py-3 text-slate-800 transition" 
                                        placeholder="contoh: 19daeb63b0ec46f2b02197c9fbbe81d6"
                                    />
                                    <p class="text-[9px] text-slate-400 font-medium ms-1">App ID resmi yang diperoleh dari proyek Agora Developer Console.</p>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-extrabold text-slate-700 uppercase tracking-wider ms-1 flex items-center justify-between">
                                        <div class="flex items-center gap-1.5">
                                            <span>Agora App Certificate</span>
                                            <span class="text-[9px] text-slate-400 font-normal">(Opsional)</span>
                                        </div>
                                        <button 
                                            type="button" 
                                            @click="showAgoraCertificate = !showAgoraCertificate"
                                            class="text-[9px] font-bold text-blue-600 hover:text-blue-800 transition uppercase cursor-pointer"
                                        >
                                            {{ showAgoraCertificate ? 'Sembunyikan' : 'Tampilkan' }}
                                        </button>
                                    </label>
                                    <div class="relative">
                                        <input 
                                            v-model="form.agora_app_certificate" 
                                            :type="showAgoraCertificate ? 'text' : 'password'" 
                                            class="w-full rounded-2xl border-slate-200 bg-white focus:ring-blue-500 focus:border-blue-600 font-mono text-xs font-semibold px-4 py-3 text-slate-800 transition pr-10" 
                                            placeholder="contoh: f20e73efeed44842b2d861723549f8ea"
                                        />
                                        <button 
                                            type="button" 
                                            @click="showAgoraCertificate = !showAgoraCertificate"
                                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 cursor-pointer"
                                        >
                                            <svg v-if="!showAgoraCertificate" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                            </svg>
                                        </button>
                                    </div>
                                    <p class="text-[9px] text-slate-400 font-medium ms-1">Primary Certificate untuk enkripsi token keamanan. Kosongkan jika proyek Agora menggunakan mode App ID tanpa certificate.</p>
                                </div>
                            </div>

                            <!-- Petunjuk Konfigurasi Agora -->
                            <div class="p-4 bg-white rounded-2xl border border-blue-100 shadow-xs space-y-2">
                                <div class="flex items-center gap-2 font-bold text-blue-900 text-xs">
                                    <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Panduan Konfigurasi Agora RTC:</span>
                                </div>
                                <ul class="list-disc list-inside space-y-1 text-[10px] text-slate-600 font-medium pl-1">
                                    <li><b>Mode App ID Saja</b>: Jika proyek Agora Anda dibuat tanpa mengaktifkan App Certificate, cukup isi kolom <b>Agora App ID</b> dan kosongkan <b>Agora App Certificate</b>.</li>
                                    <li><b>Mode Token Terenkripsi</b>: Jika App Certificate diaktifkan di Agora Console, isi kedua kolom di atas. Server SINDEN akan secara otomatis membangkitkan token keamanan dinamis saat sesi panggilan dimulai.</li>
                                    <li>Perubahan yang disimpan langsung berlaku seketika pada bilik panggilan video tanpa perlu memuat ulang atau menyetel ulang server.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- KEAMANAN LOGIN APLIKASI MOBILE (CLOUDFLARE TURNSTILE) -->
                    <div class="pt-8 border-t border-slate-100 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-orange-50 border border-orange-200 text-orange-600 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">KEAMANAN LOGIN APLIKASI MOBILE (CLOUDFLARE TURNSTILE)</h3>
                                    <p class="text-[10px] text-slate-500 font-semibold">Verifikasi keamanan saat Masuk &amp; Aktivasi Akun di aplikasi Android Sinden 2</p>
                                </div>
                            </div>
                            <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" class="rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                       :checked="form.turnstile_enabled === '1'"
                                       @change="form.turnstile_enabled = $event.target.checked ? '1' : '0'" />
                                <span class="text-[10px] font-extrabold uppercase tracking-wider" :class="form.turnstile_enabled === '1' ? 'text-emerald-700' : 'text-slate-500'">
                                    {{ form.turnstile_enabled === '1' ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </label>
                        </div>

                        <div class="bg-gradient-to-br from-slate-50 to-orange-50/30 p-6 rounded-3xl border border-slate-200 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-extrabold text-slate-700 uppercase tracking-wider ms-1">Site Key (publik)</label>
                                    <input v-model="form.turnstile_site_key" type="text"
                                           class="w-full rounded-2xl border-slate-200 bg-white focus:ring-orange-500 focus:border-orange-600 font-mono text-xs font-semibold px-4 py-3 text-slate-800 transition"
                                           placeholder="contoh: 0x4AAAAAAA..." />
                                    <p class="text-[9px] text-slate-400 font-medium ms-1">Ditampilkan pada widget di aplikasi. Aman untuk dibagikan.</p>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-extrabold text-slate-700 uppercase tracking-wider ms-1 flex items-center justify-between">
                                        <span>Secret Key (rahasia)</span>
                                        <button type="button" @click="showTurnstileSecret = !showTurnstileSecret" class="text-[9px] font-bold text-orange-600 hover:text-orange-800 uppercase cursor-pointer">
                                            {{ showTurnstileSecret ? 'Sembunyikan' : 'Tampilkan' }}
                                        </button>
                                    </label>
                                    <input v-model="form.turnstile_secret_key" :type="showTurnstileSecret ? 'text' : 'password'" autocomplete="new-password"
                                           class="w-full rounded-2xl border-slate-200 bg-white focus:ring-orange-500 focus:border-orange-600 font-mono text-xs font-semibold px-4 py-3 text-slate-800 transition"
                                           :placeholder="props.settings.turnstile_secret_set ? 'Tersimpan (terenkripsi) — isi untuk mengganti' : 'Tempel secret key dari Cloudflare'" />
                                    <p class="text-[9px] font-medium ms-1" :class="props.settings.turnstile_secret_set ? 'text-emerald-600' : 'text-rose-500'">
                                        {{ props.settings.turnstile_secret_set ? 'Secret key sudah tersimpan terenkripsi dan tidak ditampilkan kembali.' : 'Secret key belum diatur — Turnstile belum dapat diaktifkan.' }}
                                    </p>
                                    <label v-if="props.settings.turnstile_secret_set" class="inline-flex items-center gap-2 text-[9px] text-slate-500 ms-1">
                                        <input type="checkbox" v-model="form.turnstile_secret_clear" class="rounded border-slate-300 text-rose-600" /> Hapus secret key tersimpan
                                    </label>
                                </div>
                            </div>
                            <div class="p-4 bg-white rounded-2xl border border-orange-100 space-y-2">
                                <p class="font-bold text-orange-900 text-xs">Panduan Cloudflare Turnstile:</p>
                                <ul class="list-disc list-inside space-y-1 text-[10px] text-slate-600 font-medium pl-1">
                                    <li>Buat widget di <b>Cloudflare Dashboard &rarr; Turnstile</b>, lalu tambahkan hostname <b>sinden2.my.id</b>.</li>
                                    <li>Salin <b>Site Key</b> dan <b>Secret Key</b> ke kolom di atas, centang <b>Aktif</b>, lalu simpan.</li>
                                    <li>Saat aktif, aplikasi versi lama yang belum mendukung Turnstile tidak dapat masuk &mdash; pastikan personel memperbarui aplikasi.</li>
                                    <li>Perubahan berlaku seketika tanpa memperbarui aplikasi maupun menyetel ulang server.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- NOTIFIKASI PUSH APLIKASI (FIREBASE CLOUD MESSAGING) -->
                    <div class="pt-8 border-t border-slate-100 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-amber-50 border border-amber-200 text-amber-600 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">NOTIFIKASI PUSH APLIKASI (FIREBASE CLOUD MESSAGING)</h3>
                                    <p class="text-[10px] text-slate-500 font-semibold">Warta dinas &amp; Chat Dinas tetap muncul di HP walau aplikasi ditutup</p>
                                </div>
                            </div>
                            <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500"
                                       :checked="form.fcm_enabled === '1'"
                                       @change="form.fcm_enabled = $event.target.checked ? '1' : '0'" />
                                <span class="text-[10px] font-extrabold uppercase tracking-wider" :class="props.settings.fcm_ready && form.fcm_enabled === '1' ? 'text-emerald-700' : 'text-slate-500'">
                                    {{ props.settings.fcm_ready && form.fcm_enabled === '1' ? 'Aktif' : 'Belum aktif' }}
                                </span>
                            </label>
                        </div>

                        <div class="bg-gradient-to-br from-slate-50 to-amber-50/30 p-6 rounded-3xl border border-slate-200 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-extrabold text-slate-700 uppercase tracking-wider ms-1">1. google-services.json (aplikasi Android)</label>
                                    <input type="file" accept=".json,application/json" @change="readJsonFile($event, 'fcm_google_services')"
                                           class="block w-full text-[10px] text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:bg-amber-100 file:text-amber-800 file:font-bold" />
                                    <textarea v-model="form.fcm_google_services" rows="3"
                                              class="w-full rounded-2xl border-slate-200 bg-white font-mono text-[10px] px-3 py-2 text-slate-700"
                                              placeholder="Pilih berkas atau tempel isi google-services.json"></textarea>
                                    <p class="text-[9px] font-medium ms-1" :class="props.settings.fcm_client_project ? 'text-emerald-600' : 'text-rose-500'">
                                        {{ props.settings.fcm_client_project ? 'Tersimpan — project: ' + props.settings.fcm_client_project : 'Belum diatur.' }}
                                    </p>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-extrabold text-slate-700 uppercase tracking-wider ms-1">2. Service account (rahasia)</label>
                                    <input type="file" accept=".json,application/json" @change="readJsonFile($event, 'fcm_service_account')"
                                           class="block w-full text-[10px] text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:bg-amber-100 file:text-amber-800 file:font-bold" />
                                    <textarea v-model="form.fcm_service_account" rows="3" autocomplete="off"
                                              class="w-full rounded-2xl border-slate-200 bg-white font-mono text-[10px] px-3 py-2 text-slate-700"
                                              :placeholder="props.settings.fcm_service_account_project ? 'Tersimpan (terenkripsi) — pilih berkas baru untuk mengganti' : 'Pilih berkas JSON service account Firebase'"></textarea>
                                    <p class="text-[9px] font-medium ms-1" :class="props.settings.fcm_service_account_project ? 'text-emerald-600' : 'text-rose-500'">
                                        {{ props.settings.fcm_service_account_project ? 'Tersimpan terenkripsi — project: ' + props.settings.fcm_service_account_project : 'Belum diatur.' }}
                                    </p>
                                </div>
                            </div>
                            <label v-if="props.settings.fcm_service_account_project || props.settings.fcm_client_project" class="inline-flex items-center gap-2 text-[9px] text-slate-500 ms-1">
                                <input type="checkbox" v-model="form.fcm_clear" class="rounded border-slate-300 text-rose-600" /> Hapus konfigurasi Firebase tersimpan
                            </label>
                            <div class="p-4 bg-white rounded-2xl border border-amber-100 space-y-2">
                                <p class="font-bold text-amber-900 text-xs">Panduan Firebase (gratis):</p>
                                <ul class="list-disc list-inside space-y-1 text-[10px] text-slate-600 font-medium pl-1">
                                    <li>Buka <b>console.firebase.google.com</b> &rarr; <b>Add project</b> (Google Analytics boleh dimatikan).</li>
                                    <li>Tambahkan aplikasi <b>Android</b> dengan package <b>id.my.sinden2.app</b> &rarr; unduh <b>google-services.json</b> &rarr; pilih di kolom 1.</li>
                                    <li><b>Project settings &rarr; Service accounts &rarr; Generate new private key</b> &rarr; pilih berkas JSON itu di kolom 2.</li>
                                    <li>Simpan. Aplikasi otomatis mendaftar notifikasi saat dibuka berikutnya &mdash; tanpa memperbarui APK.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-slate-100 flex justify-end items-center gap-4">
                        <transition leave-active-class="transition ease-in duration-1000" leave-from-class="opacity-100" leave-to-class="opacity-0">
                            <p v-if="form.recentlySuccessful" class="text-xs font-extrabold text-emerald-600 uppercase tracking-wider"> Konfigurasi Berhasil Disimpan
                            </p>
                        </transition>
                        
                        <button :disabled="form.processing"class="bg-blue-600 text-white px-8 py-3.5 rounded-2xl font-extrabold uppercase text-xs tracking-wider hover:bg-blue-700 transition shadow-lg shadow-blue-500/20 disabled:opacity-50 active:scale-95">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Konfigurasi System' }}
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Pembaruan Aplikasi Android (APK diunggah Admin, diunduh aplikasi di HP) -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-black">&#8593;</div>
                    <div>
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">PEMBARUAN APLIKASI ANDROID (UNGGAH APK)</h3>
                        <p class="text-[10px] text-slate-500 font-semibold">APK yang diunggah di sini menjadi sumber pembaruan di aplikasi Sinden 2. Personel menerima pemberitahuan dan dapat langsung mengunduh &amp; memasangnya.</p>
                    </div>
                </div>

                <div v-if="appUpdate" class="p-4 rounded-2xl bg-emerald-50/60 border border-emerald-100 flex flex-col md:flex-row md:items-center gap-3">
                    <div class="flex-1 space-y-1">
                        <p class="text-xs font-black text-emerald-900">Versi aktif di server: v{{ appUpdate.version }} <span class="font-semibold text-emerald-700">(kode {{ appUpdate.version_code }}, {{ fmtSize(appUpdate.size) }})</span></p>
                        <p class="text-[10px] text-emerald-800 font-semibold">Diunggah {{ fmtDate(appUpdate.uploaded_at) }} oleh {{ appUpdate.uploaded_by }}<span v-if="appUpdate.notified"> &middot; pemberitahuan ke {{ appUpdate.notified }} personel</span></p>
                        <p v-if="appUpdate.notes" class="text-[10px] text-slate-600 whitespace-pre-line">{{ appUpdate.notes }}</p>
                        <p class="text-[9px] font-mono text-slate-400 break-all">SHA-256 {{ appUpdate.sha256 }}</p>
                    </div>
                    <button type="button" @click="deleteApk" class="self-start md:self-center px-4 py-2 rounded-xl border border-rose-200 text-rose-600 text-[10px] font-extrabold uppercase tracking-wider hover:bg-rose-50">Hapus APK</button>
                </div>
                <p v-else class="text-[11px] text-slate-500 font-semibold">Belum ada APK pembaruan di server.</p>

                <form @submit.prevent="uploadApk" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-extrabold text-slate-700 uppercase tracking-wider ms-1">Berkas APK (Sinden2-x.y.z.apk)</label>
                        <input ref="apkInput" type="file" accept=".apk,application/vnd.android.package-archive" @change="pickApk"
                               class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-xl file:border-0 file:bg-emerald-600 file:px-4 file:py-2 file:text-xs file:font-bold file:text-white hover:file:bg-emerald-700" />
                        <p class="text-[9px] text-slate-500 font-bold ms-1">Nomor versi dibaca otomatis dari APK. Batas unggah server: {{ uploadLimitLabel }}.</p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-extrabold text-slate-700 uppercase tracking-wider ms-1">Catatan pembaruan (ditampilkan di HP)</label>
                        <textarea v-model="apkForm.notes" rows="3" maxlength="2000" class="w-full rounded-2xl border-slate-200 bg-slate-50 focus:bg-white text-xs px-4 py-3" placeholder="Contoh: Pelacakan lokasi lebih hemat baterai, perbaikan presensi."></textarea>
                    </div>
                    <label class="flex items-center gap-2 text-[11px] font-bold text-slate-700">
                        <input type="checkbox" v-model="apkForm.notify" class="rounded border-slate-300 text-emerald-600" /> Kirim pemberitahuan pembaruan ke HP semua personel
                    </label>
                    <div class="flex items-center justify-end gap-3">
                        <div v-if="apkForm.progress" class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full bg-emerald-500 transition-all" :style="{ width: apkForm.progress.percentage + '%' }"></div>
                        </div>
                        <button :disabled="apkForm.processing || !apkForm.apk" class="bg-emerald-600 text-white px-6 py-3 rounded-2xl font-extrabold uppercase text-xs tracking-wider hover:bg-emerald-700 disabled:opacity-50">
                            {{ apkForm.processing ? `Mengunggah ${apkForm.progress ? apkForm.progress.percentage + '%' : '…'}` : 'Unggah Pembaruan' }}
                        </button>
                    </div>
                    <p v-if="apkError" class="md:col-span-2 text-[11px] font-bold text-rose-600">{{ apkError }}</p>
                </form>
                <p class="text-[9px] text-slate-400 font-semibold">APK harus ditandatangani kunci resmi Sinden 2 (hasil rilis GitHub Actions). HP menolak APK dengan tanda tangan berbeda.</p>
            </div>

            <!-- Quick Link Stamp Setting -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs flex items-center justify-between group hover:border-blue-500 transition cursor-pointer" 
                 @click="router.get(route('stamp.setting'))">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center text-xl group-hover:scale-105 transition-transform">
                        
                    </div>
                    <div class="flex flex-col text-left">
                        <span class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Konfigurasi Stempel Digital</span>
                        <span class="text-[9px] text-slate-400 font-bold uppercase mt-0.5">Update Master Stempel PNG Komandan</span>
                    </div>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 group-hover:text-blue-600 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                </svg>
            </div>

        </div>
    </AuthenticatedLayout>
</template>