<script setup> import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    settings: Object, // Menerima data settings dari Controller
});

// Ambil data logo dan nama instansi secara dinamis
// Ambil data logo secara dinamis dari storage
const appLogo = computed(() => {
    // Jika ada logo yang diunggah di settings, arahkan ke path storage
    if (props.settings?.agency_logo) {
        return `/storage/${props.settings.agency_logo}`;
    }
    // Fallback: Jika logo kosong, bisa diarahkan ke logo default atau placeholder
    return '/storage/uploads/default-logo.jpg'; 
});

const agencyName = computed(() => props.settings?.agency_name || 'Denintel Koarmada 2');
</script>

<template>
    <Head :title="`${agencyName} | Digital Intelligence`" />

    <div class="relative min-h-screen flex flex-col items-center justify-center bg-[#080808] text-white font-sans selection:bg-indigo-500 overflow-hidden">
        
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute top-[-10%] left-[-5%] w-[600px] h-[600px] bg-purple-600/20 blur-[150px] rounded-full animate-pulse"></div>
            <div class="absolute top-[20%] right-[-10%] w-[500px] h-[500px] bg-blue-600/15 blur-[130px] rounded-full"></div>
            <div class="absolute bottom-[-15%] right-[10%] w-[700px] h-[700px] bg-fuchsia-600/10 blur-[160px] rounded-full opacity-50"></div>
        </div>

        <header class="absolute top-0 w-full px-12 py-8 flex justify-between items-center z-50">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 bg-white/5 backdrop-blur-xl border border-white/10 rounded-full flex items-center justify-center shadow-2xl overflow-hidden">
                    <img :src="appLogo" :alt="agencyName" class="h-full w-full object-cover shadow-inner" />
                </div>
                <span class="text-[11px] font-black uppercase tracking-[0.4em] text-white/80">{{ agencyName }}</span>
            </div>
            
            <nav v-if="canLogin" class="flex gap-8">
                <Link v-if="!$page.props.auth.user" :href="route('login')" class="text-[10px] font-bold uppercase tracking-widest text-white/50 hover:text-white transition-colors">Sign In</Link>
                <Link v-else :href="route('dashboard')" class="text-[10px] font-bold uppercase tracking-widest text-indigo-400 hover:text-indigo-300 transition-colors">Go to Dashboard</Link>
            </nav>
        </header>

        <main class="relative z-10 text-center px-6">
            <div class="mb-6 inline-flex items-center gap-3 px-4 py-1.5 bg-white/5 backdrop-blur-md border border-white/10 rounded-full">
                <div class="h-1.5 w-1.5 rounded-full bg-indigo-500 animate-ping"></div>
                <span class="text-[9px] font-black uppercase tracking-[0.3em] text-indigo-300">System Secure & Active</span>
            </div>

            <h1 class="text-6xl md:text-8xl font-black tracking-tighter uppercase leading-none mb-6"> SELAMAT DATANG <br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-400 to-fuchsia-400 italic"> SI SINDEN
                </span>
            </h1>

            <div class="flex flex-col md:flex-row items-center justify-center gap-4">
                <Link
                    v-if="$page.props.auth.user "
                    :href="route('dashboard')"class="px-10 py-4 bg-indigo-600 hover:bg-indigo-500 text-white rounded-full text-[10px] font-black uppercase tracking-[0.2em] transition-all shadow-[0_0_40px_rgba(79,70,229,0.3)] active:scale-95"
                > Access Command Center
                </Link>

                <template v-else>
                    <Link
                        :href="route('login')"class="px-12 py-4 bg-white text-black rounded-full text-[10px] font-black uppercase tracking-[0.2em] transition-all hover:shadow-[0_0_30px_rgba(255,255,255,0.2)] active:scale-95"
                    > Otoritas Login
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="route('register')"class="px-12 py-4 bg-white/5 backdrop-blur-md border border-white/10 text-white rounded-full text-[10px] font-black uppercase tracking-[0.2em] transition-all hover:bg-white/10 active:scale-95"
                    > Registrasi Personel
                    </Link>
                </template>
            </div>
        </main>

        <footer class="absolute bottom-10 w-full px-12 flex justify-between items-center z-10 text-white/20">
            <div class="text-[9px] font-bold uppercase tracking-[0.2em]">VER. 2026.01</div>
            <div class="flex gap-6 items-center">
                <span class="text-[9px] font-bold uppercase tracking-[0.2em]">{{ agencyName }}</span>
                <div class="h-4 w-[1px] bg-white/10"></div>
                <span class="text-[9px] font-bold tracking-[0.1em]"><AppCopyright /></span>
            </div>
        </footer>

    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');

:deep(body) {
    background-color: #080808;
    font-family: 'Inter', sans-serif;
}
</style>