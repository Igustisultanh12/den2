<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
    settings: { type: Object },
    redirect: { type: String, default: null },
});

const page = usePage();

const targetRedirect = computed(() => {
    if (props.redirect) return props.redirect;
    if (typeof window !== 'undefined') {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('redirect') || null;
    }
    return null;
});

const pageSettings = computed(() => props.settings || page.props.settings || {});
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

onMounted(() => {
    if (page.props.flash?.error) {
        Swal.fire({
            icon: 'error',
            title: 'AUTENTIKASI GOOGLE GAGAL',
            text: page.props.flash.error,
            confirmButtonColor: '#dc2626',
            background: 'rgba(15, 23, 42, 0.92)',
            color: '#ffffff',
            customClass: {
                popup: 'swal2-dark-glass border border-white/10 shadow-2xl rounded-[2rem]',
                confirmButton: 'bg-red-600 hover:bg-red-700 text-white font-black text-xs uppercase tracking-widest px-8 py-3 rounded-xl shadow-lg shadow-red-500/20'
            },
        });
    } else if (page.props.flash?.message) {
        Swal.fire({
            icon: 'success',
            title: 'AKTIVASI OTORITAS AKUN BERHASIL',
            text: page.props.flash.message,
            confirmButtonText: 'SIAP LOGIN',
            confirmButtonColor: '#f97316',
            background: 'rgba(15, 23, 42, 0.92)',
            color: '#ffffff',
            customClass: {
                popup: 'swal2-dark-glass border border-white/10 shadow-2xl rounded-[2rem]',
                confirmButton: 'bg-orange-500 hover:bg-orange-600 text-white font-black text-xs uppercase tracking-widest px-8 py-3 rounded-xl shadow-lg shadow-orange-500/20'
            },
        });
    }
});

const isLockingGPS = ref(false);
const gpsWarning = ref(null);

const form = useForm({
    email: '',
    password: '',
    remember: false,
    latitude: null,
    longitude: null,
    redirect: '',
});

/**
 * FUNGSI: Deteksi GPS Kedinasan & Peringatan Lokasi
 */
const showLocationWarning = (resolve, lat = null, lng = null) => {
    isLockingGPS.value = false;
    form.latitude = lat;
    form.longitude = lng;
    gpsWarning.value = "Anda belum mengaktifkan lokasi";

    Swal.fire({
        icon: 'warning',
        title: 'PERINGATAN LOKASI',
        text: 'Anda belum mengaktifkan lokasi',
        showCancelButton: true,
        confirmButtonText: 'Lanjutkan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#475569',
        background: 'rgba(15, 23, 42, 0.95)',
        color: '#ffffff',
        customClass: {
            popup: 'swal2-dark-glass border border-white/10 shadow-2xl rounded-[2rem]',
            confirmButton: 'bg-amber-500 hover:bg-amber-600 text-white font-black text-xs uppercase tracking-widest px-6 py-3 rounded-xl shadow-lg shadow-amber-500/20',
            cancelButton: 'bg-slate-700 hover:bg-slate-600 text-white font-bold text-xs uppercase tracking-wider px-6 py-3 rounded-xl'
        },
    }).then((result) => {
        if (result.isConfirmed) {
            resolve();
        } else {
            form.latitude = null;
            form.longitude = null;
        }
    });
};

const lockLocation = () => {
    return new Promise((resolve) => {
        isLockingGPS.value = true;
        gpsWarning.value = null;

        if (!navigator.geolocation) {
            showLocationWarning(resolve);
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const accuracy = position.coords.accuracy;
                const isMocked = position.mocked || (position.coords && position.coords.isMocked);

                // Jika terdeteksi mocked eksplisit atau akurasi perkiraan kasar (> 250m seperti di PC/laptop)
                if (isMocked || accuracy > 250 || !lat || !lng) {
                    showLocationWarning(resolve, lat, lng);
                    return;
                }

                form.latitude = lat;
                form.longitude = lng;
                isLockingGPS.value = false;
                resolve();
            },
            (error) => {
                showLocationWarning(resolve);
            },
            { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
        );
    });
};

const submit = async () => {
    try {
        await lockLocation();

        const currentRedirect = targetRedirect.value;
        form.redirect = currentRedirect;

        const postRoute = currentRedirect 
            ? route('login', { redirect: currentRedirect })
            : route('login');

        form.post(postRoute, {
            onFinish: () => form.reset('password'),
        });
    } catch (e) {
        console.warn("Autentikasi dihentikan:", e);
    }
};
</script>

<template>
    <Head title="Otoritas Login - SINDEN" />

    <div 
        class="min-h-screen flex text-slate-800 font-sans bg-cover bg-center relative transition-all duration-300 bg-slate-950"
        :style="loginBg ? { backgroundImage: `url(${loginBg})` } : {}"
    >
        <!-- Dark overlay -->
        <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-[2px] z-0"></div>

        <!-- Main Wrapper -->
        <div class="relative z-10 w-full min-h-screen flex flex-col lg:flex-row">
            
            <!-- Left Column: Single Floating Logo & App Info -->
            <div class="hidden lg:flex lg:w-1/2 flex-col justify-between p-16 text-white z-10">
                <div></div>

                <div class="my-auto max-w-lg space-y-6 flex flex-col items-center text-center mx-auto">
                    <!-- Single Main Logo (Efek Mentul-Mentul / Floating Animation) -->
                    <div class="flex justify-center animate-float-slow">
                        <img v-if="configuredLogo" :src="configuredLogo" class="h-36 lg:h-44 object-contain drop-shadow-[0_10px_25px_rgba(0,0,0,0.6)]" alt="Logo SINDEN" />
                        <div v-else class="w-32 h-32 rounded-3xl bg-blue-600/30 backdrop-blur-md border border-blue-500/40 flex items-center justify-center shadow-2xl">
                            <span class="font-black text-white text-5xl">S</span>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <h2 class="text-3xl font-extrabold tracking-tight uppercase text-white drop-shadow-md">
                            {{ appName }}
                        </h2>
                        <p class="text-xs leading-relaxed max-w-md mx-auto text-slate-300 font-medium"> Sistem Informasi Detasemen Intelijen  Integrasi Otoritas Akses, Pengawasan Lokasi, Administrasi Surat & Logistik Terpadu.
                        </p>
                    </div>
                </div>

                <p class="text-xs text-slate-400 text-center font-medium">
                    <AppCopyright />
                </p>
            </div>

            <!-- Right Column: Dark Floating Card Form Login -->
            <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-10 z-10 my-auto">
                <div class="w-full max-w-md p-8 sm:p-10 rounded-2xl shadow-2xl bg-slate-900/90 backdrop-blur-md border border-white/10 text-white animate-float-card">
                    <div class="mb-8">
                        <h3 class="text-xl font-bold text-white">Masuk Akun</h3>
                        <p class="text-xs mt-1 text-slate-300"> Gunakan akun internal Anda untuk mengakses sistem dashboard SINDEN.
                        </p>
                    </div>

                    <!-- Indikator Akses Tautan Berbagi Terproteksi -->
                    <div v-if="targetRedirect && targetRedirect.includes('shared-folder')" class="mb-6 p-3.5 rounded-xl bg-blue-500/10 border border-blue-500/25 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-500/20 flex items-center justify-center shrink-0 text-blue-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <div class="text-xs text-blue-200 leading-relaxed">
                            <span class="font-semibold block text-white text-xs">Akses Tautan Berbagi Terproteksi</span>
                            Silakan masuk dengan akun dinas Anda untuk membuka folder.
                        </div>
                    </div>

                    <form @submit.prevent="submit" class="space-y-5">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-2 text-slate-300"> NRP / USERNAME / EMAIL *
                            </label>
                            <input 
                                type="text"v-model="form.email"class="w-full px-4 py-2.5 rounded-lg border text-sm outline-none transition duration-150 bg-white/10 border-white/10 text-white placeholder-slate-400 focus:ring-2 focus:ring-white/20 focus:border-white"placeholder="Masukkan NRP, Username, atau Email"required 
                                autofocus
                            />
                            <InputError class="mt-1 text-xs text-red-400" :message="form.errors.email" />
                        </div>

                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300"> Kata Kunci
                                </label>
                                <Link v-if="canResetPassword" :href="route('password.request.custom')" class="text-xs font-medium text-orange-400 hover:underline"> Lupa Password?
                                </Link>
                            </div>
                            <input 
                                type="password"v-model="form.password"class="w-full px-4 py-2.5 rounded-lg border text-sm outline-none transition duration-150 bg-white/10 border-white/10 text-white placeholder-slate-400 focus:ring-2 focus:ring-white/20 focus:border-white"placeholder=""required 
                            />
                            <InputError class="mt-1 text-xs text-red-400" :message="form.errors.password" />
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 text-sm text-slate-300 select-none cursor-pointer">
                                <input 
                                    type="checkbox"v-model="form.remember"class="w-4 h-4 rounded bg-white/10 border-white/10 text-orange-500 focus:ring-0 cursor-pointer" 
                                /> Ingat Saya
                            </label>
                        </div>

                        <button 
                            type="submit" 
                            :disabled="form.processing || isLockingGPS"class="w-full py-3 px-4 text-white text-sm font-semibold rounded-lg shadow-lg bg-orange-500 hover:bg-orange-600 shadow-orange-500/20 transition duration-150 disabled:opacity-50 cursor-pointer flex justify-center items-center gap-2"
                        >
                            <template v-if="isLockingGPS">
                                <svg class="animate-spin h-4 w-4 mr-2 text-white inline" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Mengunci GPS...</span>
                            </template>
                            <template v-else-if="form.processing">
                                <span>Memverifikasi...</span>
                            </template>
                            <template v-else>
                                <span>Masuk Sistem</span>
                            </template>
                        </button>

                        <!-- Opsi Masuk dengan Akun Google (Hanya tampil jika diaktifkan & terkonfigurasi) -->
                        <div v-if="pageSettings.google_login_enabled !== false">
                            <div class="relative my-3 flex items-center justify-center">
                                <div class="border-t border-white/15 w-full"></div>
                                <span class="bg-slate-900 px-3 text-[10px] font-bold uppercase tracking-widest text-slate-400 select-none">
                                    Atau
                                </span>
                            </div>

                            <!-- Tombol Masuk dengan Akun Google -->
                            <a 
                                :href="route('auth.google.redirect', { redirect: targetRedirect, action: 'login' })"
                                class="w-full py-2.5 px-4 bg-white hover:bg-slate-100 text-slate-800 text-xs font-bold rounded-lg shadow-md transition duration-150 flex justify-center items-center gap-2.5 cursor-pointer border border-slate-200 select-none"
                            >
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24">
                                    <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/>
                                    <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/>
                                    <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.99 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
                                    <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                                </svg>
                                <span>Masuk dengan Akun Google</span>
                            </a>
                        </div>
                    </form>

                    <!-- Status GPS Verification Widget -->
                    <div class="mt-4 p-3 rounded-xl border transition-all flex items-center gap-3"
                         :class="form.latitude ? 'bg-emerald-950/40 border-emerald-500/40' : (gpsWarning ? 'bg-amber-950/30 border-amber-500/30' : 'bg-slate-800/50 border-slate-700/50')">
                        <div class="h-2.5 w-2.5 rounded-full shrink-0 transition-all" 
                            :class="[
                                form.latitude ? 'bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.8)]' : (gpsWarning ? 'bg-amber-400' : 'bg-slate-400'), 
                                isLockingGPS ? 'animate-ping' : ''
                            ]"
                        ></div>
                        <div class="flex flex-col overflow-hidden text-left">
                            <p class="text-[10px] font-bold uppercase leading-tight" 
                               :class="form.latitude ? 'text-emerald-300' : (gpsWarning ? 'text-amber-300' : 'text-slate-300')">
                                {{ form.latitude ? 'GPS Presisi Terkunci' : (gpsWarning ? 'Peringatan Lokasi' : 'Verifikasi GPS Sistem') }}
                            </p>
                            <p class="text-[9px] text-slate-400 uppercase mt-0.5 tracking-tight truncate">
                                {{ gpsWarning || (form.latitude ? `Koordinat: ${form.latitude.toFixed(4)}, ${form.longitude.toFixed(4)}` : 'Disarankan Mengaktifkan GPS') }}
                            </p>
                        </div>
                    </div>

                    <!-- Fitur Publik: Pelacakan Status Pengajuan SC (Tanpa Login) -->
                    <div class="mt-5">
                        <Link 
                            :href="route('tracking-sc.index')"
                            class="w-full py-3 px-4 bg-slate-800/80 hover:bg-slate-700/90 text-white rounded-xl border border-blue-500/30 hover:border-blue-400 text-xs font-extrabold uppercase tracking-wider transition duration-150 flex items-center justify-between shadow-md group"
                        >
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-blue-600/30 border border-blue-500/40 text-blue-400 flex items-center justify-center font-black text-xs group-hover:bg-blue-600 group-hover:text-white transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                    </svg>
                                </div>
                                <div class="text-left">
                                    <span class="block text-white text-[11px] font-black tracking-wide">Cek Riwayat Pengajuan SC</span>
                                    <span class="block text-[9px] text-slate-400 font-medium normal-case">Layanan publik mandiri via NRP / NIP / NIK</span>
                                </div>
                            </div>
                            <svg class="w-4 h-4 text-slate-400 group-hover:text-blue-400 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </Link>
                    </div>

                    <div class="mt-6 text-center border-t border-white/10 pt-6 flex justify-between text-xs text-slate-300">
                        <Link href="/aktivasi" class="text-orange-400 hover:underline">Aktivasi Akun</Link>
                        <span>Belum punya akun? <Link :href="route('register')" class="font-semibold text-orange-400 hover:underline">Daftar Sekarang</Link></span>
                    </div>
                </div>
            </div>
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
    transform: translateY(-10px);
  }
}

@keyframes float-card {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-6px);
  }
}
</style>