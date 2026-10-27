<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    status: {
        type: String,
    },
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

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <Head title="Lupa Password - SINDEN" />

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
                        <p class="text-xs leading-relaxed max-w-md mx-auto text-slate-300 font-medium"> Pemulihan Kata Kunci  Intelligence Digital System SINDEN Detasemen Intelijen.
                        </p>
                    </div>
                </div>

                <p class="text-xs text-slate-400 text-center font-medium">
                    <AppCopyright />
                </p>
            </div>

            <!-- Right Column: Dark Floating Card Form Lupa Password (Efek Mentul-Mentul) -->
            <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-10 z-10 my-auto">
                <div class="w-full max-w-md p-8 sm:p-10 rounded-2xl shadow-2xl bg-slate-900/90 backdrop-blur-md border border-white/10 text-white animate-float-card">
                    <div class="mb-6">
                        <h3 class="text-xl font-bold text-white uppercase tracking-tight">Lupa Kata Kunci</h3>
                        <p class="text-xs mt-1 text-slate-300 font-medium leading-relaxed"> Ketikkan email dinas Anda yang terdaftar untuk menerima tautan pemulihan kata kunci.
                        </p>
                    </div>

                    <div v-if="status" class="mb-4 p-3 rounded-xl bg-emerald-950/60 border border-emerald-500/40 text-xs font-bold text-emerald-400">
                        {{ status }}
                    </div>

                    <form @submit.prevent="submit" class="space-y-5">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-2 text-slate-300"> Email Dinas Terdaftar *
                            </label>
                            <input 
                                type="email"v-model="form.email"class="w-full px-4 py-2.5 rounded-lg border text-sm outline-none transition duration-150 bg-white/10 border-white/10 text-white placeholder-slate-400 focus:ring-2 focus:ring-white/20 focus:border-white"placeholder="Masukkan email dinas Anda"required 
                                autofocus
                            />
                            <InputError class="mt-1 text-xs text-red-400" :message="form.errors.email" />
                        </div>

                        <button 
                            type="submit" 
                            :disabled="form.processing"class="w-full py-3.5 px-4 text-white text-xs font-black uppercase tracking-wider rounded-lg shadow-lg bg-orange-500 hover:bg-orange-600 shadow-orange-500/20 transition duration-150 disabled:opacity-50 cursor-pointer flex justify-center items-center gap-2"
                        >
                            <span>Kirim Link Reset Password</span>
                            <span></span>
                        </button>
                    </form>

                    <div class="mt-6 text-center border-t border-white/10 pt-6 flex justify-between items-center text-xs">
                        <Link :href="route('password.request.custom')" class="text-orange-400 hover:underline"> Reset Pakai Token 6-Digit
                        </Link>
                        <Link :href="route('login')" class="text-xs font-semibold text-orange-400 hover:underline">
                             Kembali ke Login
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
