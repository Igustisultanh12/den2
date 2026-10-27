<script setup>
import { useForm, Head, Link, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import { computed } from 'vue';
import Swal from 'sweetalert2';

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

const form = useForm({
    nrp: '',
    token: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('aktivasi.proses'), {
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'AKTIVASI OTORITAS AKUN BERHASIL',
                text: 'Aktivasi Akun Berhasil! Akun Anda telah aktif. Silakan login menggunakan Email dan Password Anda.',
                confirmButtonText: 'MASUK KE SISTEM',
                confirmButtonColor: '#f97316',
                background: 'rgba(15, 23, 42, 0.92)',
                color: '#ffffff',
                customClass: {
                    popup: 'swal2-dark-glass border border-white/10 shadow-2xl rounded-[2rem]',
                    confirmButton: 'bg-orange-500 hover:bg-orange-600 text-white font-black text-xs uppercase tracking-widest px-8 py-3 rounded-xl shadow-lg shadow-orange-500/20'
                },
                allowOutsideClick: false,
            });
        },
        onError: (errors) => {
            const errText = errors.token || errors.nrp || 'NRP / Email atau Token Aktivasi tidak valid.';
            Swal.fire({
                icon: 'error',
                title: 'AKTIVASI OTORITAS GAGAL',
                text: errText,
                confirmButtonText: 'PERIKSA KEMBALI',
                confirmButtonColor: '#dc2626',
                background: 'rgba(15, 23, 42, 0.92)',
                color: '#ffffff',
                customClass: {
                    popup: 'swal2-dark-glass border border-white/10 shadow-2xl rounded-[2rem]',
                    confirmButton: 'bg-red-600 hover:bg-red-700 text-white font-black text-xs uppercase tracking-widest px-8 py-3 rounded-xl shadow-lg shadow-red-600/20'
                },
            });
        },
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Aktivasi Akun Personel - SINDEN" />

    <div 
        class="min-h-screen flex text-slate-800 font-sans bg-cover bg-center relative transition-all duration-300 bg-slate-950"
        :style="loginBg ? { backgroundImage: `url(${loginBg})` } : {}"
    >
        <!-- Dark overlay -->
        <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-[2px] z-0"></div>

        <!-- Main Wrapper -->
        <div class="relative z-10 w-full min-h-screen flex flex-col lg:flex-row">
            
            <!-- Left Column: Floating Logo & App Info (Efek Mentul-Mentul) -->
            <div class="hidden lg:flex lg:w-1/2 flex-col justify-between p-16 text-white z-10">
                <div></div>

                <div class="my-auto max-w-lg space-y-6 flex flex-col items-center text-center mx-auto">
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
                        <p class="text-xs leading-relaxed max-w-md mx-auto text-slate-300 font-medium"> Aktivasi Otoritas Akun Personel  Intelligence Digital System SINDEN Detasemen Intelijen.
                        </p>
                    </div>
                </div>

                <p class="text-xs text-slate-400 text-center font-medium">
                    <AppCopyright />
                </p>
            </div>

            <!-- Right Column: Dark Floating Card Form Aktivasi (Efek Mentul-Mentul) -->
            <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-10 z-10 my-auto">
                <div class="w-full max-w-md p-8 sm:p-10 rounded-2xl shadow-2xl bg-slate-900/90 backdrop-blur-md border border-white/10 text-white animate-float-card">
                    <div class="mb-8">
                        <h3 class="text-xl font-bold text-white uppercase tracking-tight">Aktivasi Personel</h3>
                        <p class="text-xs mt-1 text-slate-300 font-medium"> Masukkan NRP atau Email beserta Token Aktivasi yang telah dikirimkan oleh sistem ke WhatsApp Anda.
                        </p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-5">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-2 text-slate-300"> NRP / EMAIL PERSONEL *
                            </label>
                            <input 
                                type="text"v-model="form.nrp"class="w-full px-4 py-2.5 rounded-lg border text-sm outline-none transition duration-150 bg-white/10 border-white/10 text-white uppercase font-bold placeholder-slate-400 focus:ring-2 focus:ring-white/20 focus:border-white"placeholder="Masukkan NRP atau Email Anda"required 
                                autofocus
                            />
                            <InputError class="mt-1 text-xs text-red-400" :message="form.errors.nrp" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-2 text-slate-300"> Token Aktivasi (Cek WA) *
                            </label>
                            <input 
                                type="text"v-model="form.token"class="w-full px-4 py-2.5 rounded-lg border text-sm outline-none transition duration-150 bg-white/10 border-white/10 text-white font-mono font-bold uppercase placeholder-slate-400 focus:ring-2 focus:ring-white/20 focus:border-white"placeholder="SINDEN-XXXXXX"required 
                            />
                            <InputError class="mt-1 text-xs text-red-400" :message="form.errors.token" />
                        </div>

                        <div class="pt-2 border-t border-white/10">
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-2 text-slate-300"> Buat Password Baru *
                            </label>
                            <input 
                                type="password"v-model="form.password"class="w-full px-4 py-2.5 rounded-lg border text-sm outline-none transition duration-150 bg-white/10 border-white/10 text-white placeholder-slate-400 focus:ring-2 focus:ring-white/20 focus:border-white"placeholder="Minimal 8 Karakter"required 
                            />
                            <InputError class="mt-1 text-xs text-red-400" :message="form.errors.password" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-2 text-slate-300"> Konfirmasi Password *
                            </label>
                            <input 
                                type="password"v-model="form.password_confirmation"class="w-full px-4 py-2.5 rounded-lg border text-sm outline-none transition duration-150 bg-white/10 border-white/10 text-white placeholder-slate-400 focus:ring-2 focus:ring-white/20 focus:border-white"placeholder="Ulangi Password Baru"required 
                            />
                        </div>

                        <button 
                            type="submit" 
                            :disabled="form.processing"class="w-full py-3.5 px-4 text-white text-xs font-black uppercase tracking-wider rounded-lg shadow-lg bg-orange-500 hover:bg-orange-600 shadow-orange-500/20 transition duration-150 disabled:opacity-50 cursor-pointer flex justify-center items-center gap-2"
                        >
                            <span>Aktifkan Otoritas Akun</span>
                            <span></span>
                        </button>
                    </form>

                    <div class="mt-6 text-center border-t border-white/10 pt-6">
                        <Link :href="route('login')" class="text-xs font-semibold text-orange-400 hover:underline">
                             Kembali ke Halaman Login
                        </Link>
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