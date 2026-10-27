<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Swal from 'sweetalert2';
import GlobalCommandPalette from '@/Components/GlobalCommandPalette.vue';

const page = usePage();

/**
 * Data Pengaturan & Identitas Personel
 */
const settings = computed(() => page.props.settings || {});
const agencyName = computed(() => settings.value.agency_name || 'DENINTEL KOARMADA 2');
const appLogo = computed(() => {
    const logo = settings.value.agency_logo || settings.value.logo;
    if (!logo) return null;
    return (logo.startsWith('http') || logo.startsWith('/storage')) ? logo : '/storage/' + logo;
});
const isSuperAdmin = computed(() => {
    const r = (page.props.auth.user?.role || '').toLowerCase().replace(/[\s_-]+/g, '');
    return r === 'superadmin' || Boolean(page.props.auth.user?.is_super_admin);
});
const isAdmin = computed(() => {
    const r = (page.props.auth.user?.role || '').toLowerCase().replace(/[\s_-]+/g, '');
    return isSuperAdmin.value || r === 'admin' || Boolean(page.props.auth.user?.is_admin);
});
const user = computed(() => page.props.auth.user);
// Selaras dengan MobileAccess::canViewTracking (Admin, Super Admin, Komandan).
const canViewTracking = computed(() => isAdmin.value || (page.props.auth.user?.role || '').toLowerCase() === 'komandan');

const commandPaletteRef = ref(null);

const openGlobalCommandPalette = () => {
    commandPaletteRef.value?.openPalette();
};

/**
 * SISTEM NOTIFIKASI KEDINASAN & IN-APP BELL
 */
import { router } from '@inertiajs/vue3';

const isNotifOpen = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);
const isLoadingNotifs = ref(false);

const fetchNotifications = async () => {
    try {
        isLoadingNotifs.value = true;
        const res = await fetch(route('notifications.api'));
        if (res.ok) {
            const data = await res.json();
            notifications.value = data.notifications || [];
            unreadCount.value = data.unreadCount || 0;
        }
    } catch (e) {
        console.error("Gagal mengambil notifikasi sistem:", e);
    } finally {
        isLoadingNotifs.value = false;
    }
};

const formatTimeAgo = (dateStr) => {
    if (!dateStr) return 'Baru saja';
    const date = new Date(dateStr);
    const now = new Date();
    const diffSec = Math.floor((now - date) / 1000);
    if (diffSec < 60) return 'Baru saja';
    const diffMin = Math.floor(diffSec / 60);
    if (diffMin < 60) return `${diffMin}m yang lalu`;
    const diffHour = Math.floor(diffMin / 60);
    if (diffHour < 24) return `${diffHour}j yang lalu`;
    const diffDay = Math.floor(diffHour / 24);
    return `${diffDay}hr yang lalu`;
};

const handleNotifClick = async (notif) => {
    try {
        await fetch(route('notifications.read', notif.id), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': page.props.csrf_token,
                'Accept': 'application/json'
            }
        });
        notif.is_read = true;
        if (unreadCount.value > 0) unreadCount.value--;
    } catch (e) {}

    isNotifOpen.value = false;

    if (notif.link) {
        router.visit(notif.link);
    } else {
        Swal.fire({
            title: `<span class="uppercase font-bold text-sm tracking-wider text-slate-900">${notif.title}</span>`,
            html: `<p class="text-xs font-medium text-slate-600 leading-relaxed">${notif.message}</p>`,
            icon: notif.type || 'info',
            confirmButtonText: 'Tutup',
            confirmButtonColor: '#2563eb',
        });
    }
};

const markAllRead = async () => {
    try {
        await fetch(route('notifications.read-all'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': page.props.csrf_token,
                'Accept': 'application/json'
            }
        });
        notifications.value.forEach(n => n.is_read = true);
        unreadCount.value = 0;
    } catch (e) {}
};

/**
 * OTORITAS KAS & REKENING KOMANDAN
 */
const canAccessCommanderAccount = computed(() => {
    return isAdmin.value || user.value.name === 'Suma Nurhasanah' || user.value.role === 'komandan' || user.value.name === 'I Gusti Sultan H.A, A.Md.Kom';
});

const canAccessMitra = computed(() => {
    return isAdmin.value || Boolean(user.value.can_access_mitra) || user.value.name === 'I Gusti Sultan H.A, A.Md.Kom';
});

const canManageKoperasi = computed(() => {
    return isAdmin.value || Boolean(user.value.can_manage_koperasi) || user.value.name === 'I Gusti Sultan H.A, A.Md.Kom';
});

const isAnggotaSintel = computed(() => {
    if (isAdmin.value) return false;
    const r = (user.value?.role || '').toLowerCase().replace(/[\s_-]+/g, '');
    const j = (user.value?.jabatan || '').toLowerCase();
    return r === 'anggotasintel' || r === 'personelsintel' || j.includes('sintel');
});

const isMobileMenuOpen = ref(false);

const isMobile = ref(false);
const checkMobile = () => {
    isMobile.value = window.innerWidth < 1024;
};

let notifTimer = null;

onMounted(() => {
    checkMobile();
    window.addEventListener('resize', checkMobile);

    fetchNotifications();
    notifTimer = setInterval(fetchNotifications, 5000); // Poll notifikasi setiap 5 detik
});

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile);
    if (notifTimer) clearInterval(notifTimer);
});
</script>

<template>
    <div class="min-h-screen bg-[#F8FAFC] flex text-[#334155] font-sans antialiased">
        
        <!-- Mobile Sidebar Drawer Overlay & Menu -->
        <div v-if="isMobileMenuOpen" class="fixed inset-0 z-50 lg:hidden flex">
            <!-- Backdrop Overlay -->
            <div 
                class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity"
                @click="isMobileMenuOpen = false"
            ></div>

            <!-- Drawer Content -->
            <aside class="relative flex-1 w-full max-w-xs bg-white flex flex-col justify-between shadow-2xl h-full overflow-y-auto z-50">
                <div>
                    <!-- Mobile Drawer Header -->
                    <div class="pt-6 pb-4 px-6 flex items-center justify-between border-b border-slate-100">
                        <Link :href="isAnggotaSintel ? route('sc-submissions.index') : route('dashboard')" @click="isMobileMenuOpen = false" class="flex items-center gap-3">
                            <img v-if="appLogo" :src="appLogo" alt="Logo" class="w-8 h-8 object-contain select-none shrink-0" />
                            <div v-else class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center font-extrabold text-base shadow-sm shrink-0"> S
                            </div>
                            <div class="text-xl font-extrabold tracking-tight text-slate-900"> SINDEN 2<span class="text-[#2563EB]">.</span>
                            </div>
                        </Link>

                        <button @click="isMobileMenuOpen = false" class="p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <!-- Mobile Sidebar Navigation Links -->
                    <nav class="px-4 py-4 space-y-5">
                        <!-- UTAMA -->
                        <div v-if="!isAnggotaSintel" class="space-y-1">
                            <p class="px-4 text-[10px] font-extrabold text-[#94A3B8] uppercase tracking-widest mb-2">Utama</p>
                            <Link 
                                :href="route('dashboard')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('dashboard') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-3.5 px-4 py-3 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                                <span>Dashboard Utama</span>
                            </Link>

                            <Link 
                                :href="route('chat.index')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('chat.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                <span class="flex-1">Komunikasi & VC</span>
                                <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-black rounded-lg uppercase tracking-wider">Vicon</span>
                            </Link>
                        </div>

                        <!-- SURAT & NASKAH / LAYANAN SINTEL -->
                        <div class="space-y-1">
                            <p class="px-4 text-[10px] font-extrabold text-[#94A3B8] uppercase tracking-widest mb-2">
                                {{ isAnggotaSintel ? 'Menu Anggota Sintel' : 'Surat & Naskah' }}
                            </p>
                            <Link 
                                v-if="!isAnggotaSintel"
                                :href="route('letter-logs.index')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('letter-logs.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <span>Agenda Surat</span>
                            </Link>

                            <Link 
                                v-if="!isAnggotaSintel"
                                :href="route('letters.index')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('letters.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                <span>Buat & Draf Surat</span>
                            </Link>

                            <Link 
                                v-if="!isAnggotaSintel"
                                :href="route('skhpp.index')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('skhpp.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                <span>Penerbitan SKHPP</span>
                            </Link>

                            <Link 
                                :href="route('sc-submissions.index')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('sc-submissions.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"
                                class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-cyan-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                </svg>
                                <span>{{ isAnggotaSintel ? 'Tracking SC' : 'Tracking Pengajuan SC' }}</span>
                            </Link>

                            <Link 
                                :href="route('sp-jaga.index')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('sp-jaga.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                <span>{{ isAnggotaSintel ? 'Melihat Jadwal SP Jaga' : 'SP Jaga Siaga' }}</span>
                            </Link>

                            <Link 
                                v-if="!isAnggotaSintel"
                                :href="route('categories.index')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('categories.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 11h.01M7 15h.01M11 7h7M11 11h7M11 15h7"></path></svg>
                                <span>Kategori Surat</span>
                            </Link>
                        </div>

                        <!-- VALIDASI & TTE -->
                        <div v-if="!isAnggotaSintel" class="space-y-1">
                            <p class="px-4 text-[10px] font-extrabold text-[#94A3B8] uppercase tracking-widest mb-2">Validasi & TTE</p>
                            
                            <Link 
                                :href="route('signature.index')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('signature.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                <span>Tanda Tangan Digital</span>
                            </Link>

                            <Link 
                                :href="route('printing.index')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('printing.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"
                                class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                <span>Layanan Printing</span>
                            </Link>
                        </div>

                        <!-- LOGISTIK & FINANSIAL -->
                        <div v-if="!isAnggotaSintel" class="space-y-1">
                            <p class="px-4 text-[10px] font-extrabold text-[#94A3B8] uppercase tracking-widest mb-2">Logistik & Keuangan</p>
                            <Link 
                                :href="route('simpan-pinjam.index')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('simpan-pinjam.index') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"
                                class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span class="flex-1">Simpan Pinjam</span>
                            </Link>

                            <Link 
                                v-if="canManageKoperasi"
                                :href="route('simpan-pinjam.kelola')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('simpan-pinjam.kelola') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"
                                class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h4m-4 0V11m0 0h4m-4 0H7m4 0v10"></path></svg>
                                <span class="flex-1">Kelola Simpan Pinjam</span>
                                <span class="px-1.5 py-0.5 bg-violet-100 text-violet-700 text-[9px] font-extrabold rounded-md uppercase">Pengurus</span>
                            </Link>

                            
                            <Link 
                                v-if="canAccessCommanderAccount"
                                :href="route('commander.index')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('commander.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                <span>Rekening Komandan</span>
                            </Link>

                            <Link 
                                v-if="canAccessMitra"
                                :href="route('mitras.index')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('mitras.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h4m-4 0V11m0 0h4m-4 0H7m4 0v10"></path></svg>
                                <span>Pencatatan Mitra</span>
                            </Link>

                            <Link 
                                v-if="isAdmin"
                                :href="route('backup.index')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('backup.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                                <span>Explorer Backup</span>
                            </Link>
                        </div>

                        <!-- PENGAMANAN -->
                        <div v-if="!isAnggotaSintel" class="space-y-1">
                            <p class="px-4 text-[10px] font-extrabold text-[#94A3B8] uppercase tracking-widest mb-2">Pengamanan</p>
                            
                            <Link 
                                :href="route('soldier-violations.index')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('soldier-violations.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                <span>Catatan Pelanggaran</span>
                            </Link>

                            <Link 
                                :href="route('activities.index')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('activities.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><circle cx="12" cy="11" r="3"></circle></svg>
                                <span>Radar Kegiatan</span>
                            </Link>
                        </div>

                        <!-- SISTEM -->
                        <div v-if="!isAnggotaSintel" class="space-y-1 pb-6">
                            <p class="px-4 text-[10px] font-extrabold text-[#94A3B8] uppercase tracking-widest mb-2">Sistem</p>

                            <Link
                                v-if="canViewTracking"
                                :href="route('tracking.index')"
                                @click="isMobileMenuOpen = false"
                                :class="route().current('tracking.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'" class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                <span>Pantau Personel</span>
                            </Link>

                            <Link
                                v-if="isAdmin"
                                :href="route('users.index')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('users.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                <span>Kelola Pengguna</span>
                            </Link>

                            <Link 
                                :href="route('visitor-logs.index')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('visitor-logs.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 012-2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                <span>Log Pengunjung & Audit</span>
                            </Link>

                            <Link 
                                v-if="isSuperAdmin"
                                :href="route('system-check.index')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('system-check.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>
                                <span>Cek Sistem & Server</span>
                            </Link>

                            <Link 
                                v-if="isSuperAdmin"
                                :href="route('settings.index')" 
                                @click="isMobileMenuOpen = false"
                                :class="route().current('settings.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-xs' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-xs transition duration-150"
                            >
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <span>Pengaturan Sistem</span>
                            </Link>
                        </div>
                    </nav>
                </div>

                <!-- Footer Drawer Profile & Logout -->
                <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                    <Link 
                        :href="route('profile.edit')" 
                        @click="isMobileMenuOpen = false"class="flex items-center gap-3 mb-3 p-2 rounded-xl bg-white border border-slate-200/80 shadow-xs"
                    >
                        <div class="w-8 h-8 rounded-xl bg-[#2563EB] text-white flex items-center justify-center font-bold text-xs uppercase shrink-0 overflow-hidden">
                            <img v-if="user.avatar" :src="'/storage/' + user.avatar" class="w-full h-full object-cover" alt="Foto Profil" />
                            <span v-else>{{ user.name ? user.name.substring(0, 2).toUpperCase() : 'US' }}</span>
                        </div>
                        <div class="text-xs truncate flex-1" v-if="user">
                            <p class="font-bold text-slate-800 truncate">{{ user.name }}</p>
                            <p class="text-slate-400 truncate text-[10px]">NRP. {{ user.nrp || '--------' }}</p>
                        </div>
                    </Link>
                    
                    <Link :href="route('logout')" method="post" as="button" class="w-full py-2 px-3 text-center text-xs text-[#EF4444] bg-red-50 hover:bg-red-100 rounded-xl font-bold transition duration-150 cursor-pointer block"> Keluar Sistem
                    </Link>
                </div>
            </aside>
        </div>

        <!-- Desktop Sidebar (SISFOPERSKC Style) -->
        <aside class="hidden lg:flex w-72 bg-white border-r border-[#E2E8F0] flex-col justify-between shadow-sm shrink-0 z-30 sticky top-0 h-screen overflow-y-auto">
            <div>
                <!-- Brand Header & Logo -->
                <div class="pt-8 pb-6 px-7 flex flex-col select-none border-b border-slate-100">
                    <Link :href="isAnggotaSintel ? route('sc-submissions.index') : route('dashboard')" class="flex items-center gap-3">
                        <img v-if="appLogo" :src="appLogo" alt="Logo" class="w-8 h-8 object-contain select-none shrink-0" />
                        <div v-else class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center font-extrabold text-base shadow-sm shrink-0"> S
                        </div>
                        <div class="text-2xl font-extrabold tracking-tight text-slate-900"> SINDEN 2<span class="text-[#2563EB]">.</span>
                        </div>
                    </Link>
                    <span class="text-[9px] font-bold tracking-wider text-[#94A3B8] uppercase mt-2 truncate">
                        {{ agencyName }}
                    </span>
                </div>

                <!-- Sidebar Nav Menu -->
                <nav class="px-4 py-3 space-y-5">
                    
                    <!-- UTAMA -->
                    <div v-if="!isAnggotaSintel" class="space-y-1">
                        <p class="px-5 text-[10px] font-extrabold text-[#94A3B8] uppercase tracking-widest mb-2">Utama</p>
                        <Link 
                            :href="route('dashboard')" 
                            :class="route().current('dashboard') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                            <span>Dashboard Utama</span>
                        </Link>

                        <Link 
                            :href="route('chat.index')" 
                            :class="route().current('chat.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                            <span class="flex-1">Komunikasi & VC</span>
                            <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-black rounded-lg uppercase tracking-wider">Vicon</span>
                        </Link>
                    </div>

                    <!-- ADMINISTRASI SURAT / LAYANAN SINTEL -->
                    <div class="space-y-1">
                        <p class="px-5 text-[10px] font-extrabold text-[#94A3B8] uppercase tracking-widest mb-2">
                            {{ isAnggotaSintel ? 'Menu Anggota Sintel' : 'Surat & Naskah' }}
                        </p>
                        
                        <Link 
                            v-if="!isAnggotaSintel"
                            :href="route('letter-logs.index')" 
                            :class="route().current('letter-logs.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <span>Agenda Surat</span>
                        </Link>

                        <Link 
                            v-if="!isAnggotaSintel"
                            :href="route('letters.index')" 
                            :class="route().current('letters.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            <span>Buat & Draf Surat</span>
                        </Link>

                        <Link 
                            v-if="!isAnggotaSintel"
                            :href="route('skhpp.index')" 
                            :class="route().current('skhpp.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            <span>Penerbitan SKHPP</span>
                        </Link>

                        <Link 
                            :href="route('sc-submissions.index')" 
                            :class="route().current('sc-submissions.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"
                            class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-cyan-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 012-2h2a2 2 0 002-2M9 5a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                            </svg>
                            <span>{{ isAnggotaSintel ? 'Tracking SC' : 'Tracking Pengajuan SC' }}</span>
                        </Link>

                        <Link 
                            :href="route('sp-jaga.index')" 
                            :class="route().current('sp-jaga.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            <span>{{ isAnggotaSintel ? 'Melihat Jadwal SP Jaga' : 'SP Jaga Siaga' }}</span>
                        </Link>

                        <Link 
                            v-if="!isAnggotaSintel"
                            :href="route('categories.index')" 
                            :class="route().current('categories.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 11h.01M7 15h.01M11 7h7M11 11h7M11 15h7"></path></svg>
                            <span>Kategori Surat</span>
                        </Link>
                    </div>

                    <!-- VALIDASI & TTE -->
                    <div v-if="!isAnggotaSintel" class="space-y-1">
                        <p class="px-5 text-[10px] font-extrabold text-[#94A3B8] uppercase tracking-widest mb-2">Validasi & TTE</p>
                        
                        <Link 
                            :href="route('signature.index')" 
                            :class="route().current('signature.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-purple-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            <span>Tanda Tangan Digital</span>
                        </Link>

                        <Link 
                            :href="route('printing.index')" 
                            :class="route().current('printing.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"
                            class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            <span>Layanan Printing</span>
                        </Link>
                    </div>

                    <!-- LOGISTIK & FINANSIAL -->
                    <div v-if="!isAnggotaSintel" class="space-y-1">
                        <p class="px-5 text-[10px] font-extrabold text-[#94A3B8] uppercase tracking-widest mb-2">Logistik & Keuangan</p>
                        
                        <Link 
                            :href="route('simpan-pinjam.index')" 
                            :class="route().current('simpan-pinjam.index') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"
                            class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span class="flex-1">Simpan Pinjam</span>
                        </Link>

                        <Link 
                            v-if="canManageKoperasi"
                            :href="route('simpan-pinjam.kelola')" 
                            :class="route().current('simpan-pinjam.kelola') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"
                            class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h4m-4 0V11m0 0h4m-4 0H7m4 0v10"></path></svg>
                            <span class="flex-1">Kelola Simpan Pinjam</span>
                            <span class="px-2 py-0.5 bg-violet-100 text-violet-700 text-[10px] font-black rounded-lg uppercase tracking-wider">Pengurus</span>
                        </Link>

                        <Link 
                            v-if="canAccessCommanderAccount"
                            :href="route('commander.index')" 
                            :class="route().current('commander.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-emerald-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                            <span>Rekening Komandan</span>
                        </Link>

                        <Link 
                            v-if="canAccessMitra"
                            :href="route('mitras.index')" 
                            :class="route().current('mitras.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h4m-4 0V11m0 0h4m-4 0H7m4 0v10"></path></svg>
                            <span>Pencatatan Mitra</span>
                        </Link>

                        <Link 
                            v-if="isAdmin"
                            :href="route('backup.index')" 
                            :class="route().current('backup.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                            <span>Explorer Backup</span>
                        </Link>
                    </div>

                    <!-- SEKTOR PENGAMANAN -->
                    <div v-if="!isAnggotaSintel" class="space-y-1">
                        <p class="px-5 text-[10px] font-extrabold text-[#94A3B8] uppercase tracking-widest mb-2">Pengamanan</p>
                        
                        <Link 
                            :href="route('soldier-violations.index')" 
                            :class="route().current('soldier-violations.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-rose-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            <span>Catatan Pelanggaran</span>
                        </Link>

                        <Link 
                            :href="route('activities.index')" 
                            :class="route().current('activities.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><circle cx="12" cy="11" r="3"></circle></svg>
                            <span>Radar Kegiatan</span>
                        </Link>
                    </div>

                    <!-- PENGATURAN & AKSES -->
                    <div v-if="!isAnggotaSintel" class="space-y-1 pb-6">
                        <p class="px-5 text-[10px] font-extrabold text-[#94A3B8] uppercase tracking-widest mb-2">Sistem</p>

                        <Link
                            v-if="canViewTracking"
                            :href="route('tracking.index')"
                            :class="route().current('tracking.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'" class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            <span>Pantau Personel</span>
                        </Link>

                        <Link
                            v-if="isAdmin"
                            :href="route('users.index')" 
                            :class="route().current('users.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            <span>Kelola Pengguna</span>
                        </Link>

                        <Link 
                            :href="route('visitor-logs.index')" 
                            :class="route().current('visitor-logs.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 012-2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                            <span>Log Pengunjung & Audit</span>
                        </Link>

                        <Link 
                            v-if="isSuperAdmin"
                            :href="route('system-check.index')" 
                            :class="route().current('system-check.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>
                            <span>Cek Sistem & Server</span>
                        </Link>

                        <Link 
                            v-if="isSuperAdmin"
                            :href="route('settings.index')" 
                            :class="route().current('settings.*') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]' : 'text-[#64748B] hover:text-slate-800 font-medium hover:bg-slate-50'"class="group flex items-center gap-4 px-5 py-3.5 rounded-2xl text-[14px] transition duration-150"
                        >
                            <svg class="w-5 h-5 opacity-80 group-hover:opacity-100 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <span>Pengaturan Sistem</span>
                        </Link>
                    </div>

                </nav>
            </div>

            <!-- Profile Widget Footer Sidebar -->
            <div class="p-5 border-t border-[#E2E8F0] bg-slate-50/50">
                <Link 
                    :href="route('profile.edit')" 
                    :class="route().current('profile.edit') ? 'bg-[#2563EB]/5 text-[#2563EB]' : 'hover:bg-slate-100/80'"class="flex items-center gap-3.5 mb-3 p-2 rounded-xl transition duration-150 group cursor-pointer"
                >
                    <div class="w-10 h-10 rounded-xl bg-[#2563EB]/10 flex items-center justify-center font-bold text-xs text-[#2563EB] uppercase select-none group-hover:bg-[#2563EB] group-hover:text-white transition duration-150 shrink-0 overflow-hidden">
                        <img v-if="user.avatar" :src="'/storage/' + user.avatar" class="w-full h-full object-cover" alt="Foto Profil" />
                        <span v-else>{{ user.name ? user.name.substring(0, 2).toUpperCase() : 'US' }}</span>
                    </div>
                    <div class="text-xs truncate flex-1" v-if="user">
                        <p class="font-bold text-slate-800 truncate group-hover:text-[#2563EB] transition duration-150">
                            {{ user.name }}
                        </p>
                        <p class="text-slate-400 truncate text-[11px] mt-0.5"> NRP. {{ user.nrp || '--------' }}
                        </p>
                    </div>
                </Link>
                
                <Link :href="route('logout')" method="post" as="button" class="w-full py-2.5 px-3 text-center text-xs text-[#EF4444] hover:bg-red-50 rounded-xl font-bold border border-transparent hover:border-red-100/50 transition duration-150 cursor-pointer block"> Keluar Sistem
                </Link>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            
            <!-- Header Topbar Desktop & Mobile -->
            <header class="bg-white border-b border-[#E2E8F0] sticky top-0 z-20 shadow-xs h-16 flex items-center px-3 sm:px-8 justify-between gap-2 sm:gap-4">
                
                <!-- Toggle Mobile & Search Bar -->
                <div class="flex items-center gap-2 sm:gap-3 flex-1 min-w-0">
                    <button @click="isMobileMenuOpen = !isMobileMenuOpen" class="lg:hidden p-2 rounded-xl text-slate-600 hover:bg-slate-100 shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>

                    <!-- Global Command Palette Trigger Button (Desktop & Tablet) -->
                    <button 
                        @click="openGlobalCommandPalette"
                        class="hidden sm:flex max-w-md w-full relative items-center bg-[#F8FAFC] hover:bg-slate-100/90 border border-[#E2E8F0] rounded-2xl pl-10 pr-14 py-2 text-xs font-semibold text-slate-400 text-left transition cursor-pointer group shadow-xs"
                    >
                        <svg class="w-4 h-4 text-slate-400 group-hover:text-indigo-600 transition absolute left-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <span class="truncate">{{ isAnggotaSintel ? 'Cari berkas SC, SP Jaga...' : 'Cari menu, nomor surat, personel, SKHPP, pinjaman...' }}</span>
                        <span class="hidden md:inline-block absolute right-3 bg-white border border-slate-200 rounded-lg px-2 py-0.5 text-[9px] font-mono text-slate-500 font-bold shadow-xs">Ctrl K</span>
                    </button>

                    <!-- Compact Search Trigger for Mobile Only -->
                    <button 
                        @click="openGlobalCommandPalette"
                        class="flex sm:hidden p-2 rounded-xl text-slate-500 hover:bg-slate-100"
                        title="Pencarian Cepat"
                    >
                        <svg class="w-5 h-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </button>
                </div>

                <!-- Right Header Actions (Notif & Profile) -->
                <div class="flex items-center gap-1.5 sm:gap-3 shrink-0">
                    
                    <!-- Notifikasi Popover (Lonceng Notifikasi Sistem Real-time) -->
                    <div class="relative">
                        <button @click="isNotifOpen = !isNotifOpen" class="p-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-xl transition relative flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <!-- Badge Indikator Bulatan Biru/Merah Real-Time -->
                            <span v-if="unreadCount > 0" class="absolute top-1 right-1 h-3.5 w-3.5 bg-blue-600 rounded-full animate-ping opacity-75"></span>
                            <span v-if="unreadCount > 0" class="absolute top-1 right-1 min-w-[14px] h-3.5 px-1 bg-blue-600 text-white font-extrabold text-[8px] rounded-full flex items-center justify-center border border-white shadow-xs">
                                {{ unreadCount > 9 ? '9+' : unreadCount }}
                            </span>
                        </button>

                        <div v-if="isNotifOpen" class="absolute right-0 mt-3 w-80 sm:w-96 bg-white rounded-2xl shadow-2xl border border-slate-100 p-4 z-50 animate-float-card">
                            <div class="flex justify-between items-center pb-3 border-b border-slate-100">
                                <div class="flex items-center gap-2">
                                    <h4 class="font-extrabold text-xs uppercase tracking-wider text-slate-800">Notifikasi Sistem</h4>
                                    <span v-if="unreadCount > 0" class="text-[9px] font-bold bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full border border-blue-100">{{ unreadCount }} Belum Dibaca</span>
                                </div>
                                <button v-if="unreadCount > 0" @click="markAllRead" class="text-[10px] font-bold text-blue-600 hover:text-blue-800 transition"> Tandai Semua Dibaca
                                </button>
                            </div>

                            <div class="divide-y divide-slate-100 max-h-80 overflow-y-auto my-2 custom-scrollbar">
                                <div v-for="notif in notifications" :key="notif.id" 
                                    @click="handleNotifClick(notif)" 
                                    :class="!notif.is_read ? 'bg-blue-50/50 hover:bg-blue-50' : 'hover:bg-slate-50'"class="py-3 px-2.5 rounded-xl cursor-pointer transition flex items-start gap-3">
                                    <div class="mt-0.5 shrink-0">
                                        <span v-if="!notif.is_read" class="w-2 h-2 rounded-full bg-blue-600 block shadow-xs"></span>
                                        <span v-else class="w-2 h-2 rounded-full bg-slate-200 block"></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold text-slate-800 leading-snug truncate">{{ notif.title }}</p>
                                        <p class="text-[10px] text-slate-600 leading-relaxed mt-0.5 line-clamp-2">{{ notif.message }}</p>
                                        <span class="text-[9px] font-semibold text-slate-400 mt-1 block">{{ formatTimeAgo(notif.created_at) }}</span>
                                    </div>
                                </div>

                                <div v-if="notifications.length === 0" class="py-8 text-center text-slate-400 text-xs font-medium"> Belum ada notifikasi masuk.
                                </div>
                            </div>

                            <!-- Footer Dropdown Popover: Lihat Semua Notifikasi -->
                            <div class="pt-2 border-t border-slate-100 text-center">
                                <Link :href="route('notifications.index')" @click="isNotifOpen = false" class="text-[11px] font-extrabold text-blue-600 hover:text-blue-800 transition py-1.5 block uppercase tracking-wider"> Lihat Semua Notifikasi 
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Dropdown Profil -->
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button class="flex items-center gap-2 p-1 sm:p-1.5 hover:bg-slate-100 rounded-2xl transition group">
                                <div class="h-8 w-8 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold text-xs shrink-0 uppercase shadow-sm overflow-hidden">
                                    <img v-if="user.avatar" :src="'/storage/' + user.avatar" class="w-full h-full object-cover" alt="Foto Profil" />
                                    <span v-else>{{ user.name.charAt(0) }}</span>
                                </div>
                                <div class="hidden md:flex flex-col text-left max-w-[130px]">
                                    <span class="text-xs font-bold text-slate-800 truncate leading-tight">{{ user.name }}</span>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider truncate">NRP. {{ user.nrp || '--------' }}</span>
                                </div>
                                <svg class="w-4 h-4 text-slate-400 group-hover:text-slate-600 transition hidden sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('profile.edit')" class="text-xs font-bold text-slate-700"> Edit Profil </DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button" class="text-rose-600 font-bold text-xs"> Keluar Sistem 
                            </DropdownLink>
                        </template>
                    </Dropdown>

                </div>
            </header>

            <!-- Main Scrollable Content -->
            <main class="flex-1 overflow-y-auto p-3 sm:p-6 lg:p-8">
                <slot />

                <footer class="mt-8 pt-4 border-t border-slate-200 text-center text-[10px] sm:text-[11px] font-medium text-slate-400">
                    <AppCopyright />
                </footer>
            </main>

        </div>

    </div>

    <!-- GLOBAL COMMAND PALETTE MODAL (CTRL + K) -->
    <GlobalCommandPalette ref="commandPaletteRef" />
</template>