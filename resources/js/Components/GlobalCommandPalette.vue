<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});
const isAnggotaSintel = computed(() => {
    const role = (currentUser.value?.role || '').toLowerCase().replace(/[\s_-]+/g, '');
    const jab = (currentUser.value?.jabatan || '').toLowerCase();
    return role === 'anggotasintel' || jab.includes('anggota sintel');
});

const isOpen = ref(false);
const searchQuery = ref('');
const results = ref({
    navigation: [],
    personnel: [],
    letters: [],
    skhpp: [],
});
const isLoading = ref(false);
const selectedIndex = ref(0);
const searchInput = ref(null);

let debounceTimer = null;

const flattenedResults = computed(() => {
    const list = [];
    if (results.value.navigation?.length) {
        results.value.navigation.forEach(item => list.push({ ...item, group: 'Navigasi Menu' }));
    }
    if (results.value.personnel?.length) {
        results.value.personnel.forEach(item => list.push({ ...item, group: 'Personel Satuan' }));
    }
    if (results.value.letters?.length) {
        results.value.letters.forEach(item => list.push({ ...item, group: 'Arsip Surat & Naskah' }));
    }
    if (results.value.skhpp?.length) {
        results.value.skhpp.forEach(item => list.push({ ...item, group: 'Dokumen SKHPP' }));
    }
    return list;
});

const openPalette = () => {
    isOpen.value = true;
    searchQuery.value = '';
    selectedIndex.value = 0;
    fetchResults('');
    nextTick(() => {
        searchInput.value?.focus();
    });
};

const closePalette = () => {
    isOpen.value = false;
    searchQuery.value = '';
};

const fetchResults = async (q) => {
    try {
        isLoading.value = true;
        const res = await fetch(`/api/global-search?q=${encodeURIComponent(q)}`);
        if (res.ok) {
            const data = await res.json();
            results.value = data;
            selectedIndex.value = 0;
        }
    } catch (e) {
        console.error('Gagal pencarian global:', e);
    } finally {
        isLoading.value = false;
    }
};

watch(searchQuery, (newVal) => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetchResults(newVal);
    }, 200);
});

const handleKeydown = (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        if (isOpen.value) {
            closePalette();
        } else {
            openPalette();
        }
    } else if (isOpen.value) {
        if (e.key === 'Escape') {
            e.preventDefault();
            closePalette();
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (flattenedResults.value.length > 0) {
                selectedIndex.value = (selectedIndex.value + 1) % flattenedResults.value.length;
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (flattenedResults.value.length > 0) {
                selectedIndex.value = (selectedIndex.value - 1 + flattenedResults.value.length) % flattenedResults.value.length;
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (flattenedResults.value[selectedIndex.value]) {
                selectItem(flattenedResults.value[selectedIndex.value]);
            }
        }
    }
};

const selectItem = (item) => {
    if (!item?.url) return;
    closePalette();
    router.visit(item.url);
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('open-command-palette', openPalette);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('open-command-palette', openPalette);
    if (debounceTimer) clearTimeout(debounceTimer);
});

defineExpose({
    openPalette,
    closePalette,
});
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-[200] bg-slate-950/70 backdrop-blur-sm flex items-start justify-center p-3 sm:p-6 pt-16 sm:pt-24 animate-in fade-in duration-150">
        
        <!-- Palette Modal Container -->
        <div 
            class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[80vh] animate-in zoom-in-95 duration-150"
            @click.stop
        >
            <!-- Search Header -->
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3 bg-slate-50/50">
                <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input 
                    ref="searchInput"
                    type="text" 
                    v-model="searchQuery"
                    :placeholder="isAnggotaSintel ? 'Ketik pencarian cepat berkas SC, SP Jaga...' : 'Ketik pencarian cepat (nama personel, nomor surat, SKHPP, pinjaman)...'"
                    class="w-full bg-transparent border-none text-sm font-bold text-slate-900 placeholder-slate-400 focus:outline-hidden focus:ring-0"
                />
                <div v-if="isLoading" class="shrink-0 text-slate-400">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                </div>
                <button 
                    @click="closePalette"
                    class="px-2 py-1 bg-slate-200/80 hover:bg-slate-300 text-[10px] font-mono font-black uppercase text-slate-600 rounded-md transition cursor-pointer"
                >
                    ESC
                </button>
            </div>

            <!-- Results List -->
            <div class="overflow-y-auto p-3 space-y-4 flex-1 custom-scrollbar text-xs">
                
                <!-- If No Query & Default Nav -->
                <div v-if="!searchQuery && results.navigation?.length > 0" class="space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 px-3 block">Navigasi Utama Kedinasan</span>
                    <div 
                        v-for="(item, idx) in results.navigation" 
                        :key="'nav-' + idx"
                        @click="selectItem(item)"
                        :class="selectedIndex === idx ? 'bg-indigo-600 text-white' : 'hover:bg-slate-100 text-slate-800'"
                        class="p-3 rounded-2xl cursor-pointer flex items-center justify-between transition group"
                    >
                        <div class="flex items-center gap-3">
                            <div :class="selectedIndex === idx ? 'bg-white/20 text-white' : 'bg-indigo-50 text-indigo-600'" class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </div>
                            <div>
                                <span class="font-black block text-xs">{{ item.title }}</span>
                                <span :class="selectedIndex === idx ? 'text-indigo-200' : 'text-slate-400'" class="text-[11px] block">{{ item.description }}</span>
                            </div>
                        </div>
                        <span :class="selectedIndex === idx ? 'text-white' : 'text-slate-400'" class="text-[10px] font-mono font-bold">&rarr;</span>
                    </div>
                </div>

                <!-- Grouped Results when Searching -->
                <template v-else-if="flattenedResults.length > 0">
                    <div 
                        v-for="(item, idx) in flattenedResults" 
                        :key="'res-' + idx"
                        @click="selectItem(item)"
                        :class="selectedIndex === idx ? 'bg-indigo-600 text-white' : 'hover:bg-slate-100 text-slate-800'"
                        class="p-3 rounded-2xl cursor-pointer flex items-center justify-between transition group"
                    >
                        <div class="flex items-center gap-3">
                            <div :class="selectedIndex === idx ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'" class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0">
                                <svg v-if="item.group === 'Personel Satuan'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                <svg v-else-if="item.group === 'Arsip Surat & Naskah'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <svg v-else-if="item.group === 'Dokumen SKHPP'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </div>
                            <div>
                                <span class="font-black block text-xs">{{ item.title }}</span>
                                <span :class="selectedIndex === idx ? 'text-indigo-200' : 'text-slate-500'" class="text-[11px] block">{{ item.subtitle || item.description }}</span>
                            </div>
                        </div>
                        <span :class="selectedIndex === idx ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'" class="text-[9px] font-black uppercase px-2 py-0.5 rounded-md">
                            {{ item.group }}
                        </span>
                    </div>
                </template>

                <!-- If Empty -->
                <div v-else class="py-12 text-center text-slate-400 space-y-1">
                    <p class="font-bold">Tidak ditemukan hasil untuk "{{ searchQuery }}"</p>
                    <p class="text-[11px]">Coba cari dengan kata kunci NRP, nama, nomor surat, atau kode pengajuan.</p>
                </div>

            </div>

            <!-- Footer Keys -->
            <div class="px-5 py-3 border-t border-slate-100 bg-slate-50/70 flex items-center justify-between text-[11px] text-slate-500">
                <div class="flex items-center gap-4">
                    <span class="flex items-center gap-1"><kbd class="px-1.5 py-0.5 bg-slate-200 rounded font-mono text-[10px]">↑</kbd> <kbd class="px-1.5 py-0.5 bg-slate-200 rounded font-mono text-[10px]">↓</kbd> Navigasi</span>
                    <span class="flex items-center gap-1"><kbd class="px-1.5 py-0.5 bg-slate-200 rounded font-mono text-[10px]">↵</kbd> Buka Halaman</span>
                    <span class="flex items-center gap-1"><kbd class="px-1.5 py-0.5 bg-slate-200 rounded font-mono text-[10px]">Esc</kbd> Tutup</span>
                </div>
                <span class="font-black text-indigo-600 uppercase text-[10px]">Pencarian Global SINDEN</span>
            </div>

        </div>

    </div>
</template>
