<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, nextTick } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    personnel: { type: Array, default: () => [] },
    onlineThresholdSeconds: { type: Number, default: 300 },
});

const POLL_MS = 10000;
const DEFAULT_CENTER = [-7.2575, 112.7521]; // Surabaya (Koarmada II)

const people = ref(props.personnel);
const lastSync = ref(new Date());
const syncError = ref(false);
const search = ref('');
const filter = ref('all'); // all | online | offline
const selectedId = ref(null);
const trailHours = ref(12);
const trailLoading = ref(false);

const mapEl = ref(null);
let map = null;
let pollTimer = null;
let trailLayer = null;
const markers = new Map();

const onlineCount = computed(() => people.value.filter(p => p.is_online).length);

const visiblePeople = computed(() => {
    const q = search.value.trim().toLowerCase();
    return people.value
        .filter(p => filter.value === 'all' || (filter.value === 'online' ? p.is_online : !p.is_online))
        .filter(p => !q || [p.name, p.nrp, p.pangkat, p.jabatan].some(v => (v || '').toLowerCase().includes(q)))
        .sort((a, b) => Number(b.is_online) - Number(a.is_online) || (a.age_seconds ?? 1e12) - (b.age_seconds ?? 1e12));
});

const selected = computed(() => people.value.find(p => p.user_id === selectedId.value) || null);

const ageLabel = (seconds) => {
    if (seconds === null || seconds === undefined) return '-';
    if (seconds < 60) return `${seconds} dtk lalu`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)} mnt lalu`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)} jam lalu`;
    return `${Math.floor(seconds / 86400)} hari lalu`;
};

const initials = (name) => (name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(s => s[0]).join('').toUpperCase();

const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const markerIcon = (p, active) => L.divIcon({
    className: '',
    iconSize: [36, 36],
    iconAnchor: [18, 18],
    html: `<div style="width:36px;height:36px;border-radius:9999px;display:flex;align-items:center;justify-content:center;
        font:700 11px/1 Inter,sans-serif;color:#fff;border:3px solid ${active ? '#f59e0b' : '#fff'};
        box-shadow:0 2px 6px rgba(15,23,42,.35);background:${p.is_online ? '#059669' : '#64748b'}">${escapeHtml(initials(p.name))}</div>`,
});

const popupHtml = (p) => `
    <div style="font:12px/1.4 Inter,sans-serif;min-width:180px">
        <div style="font-weight:700;font-size:13px">${escapeHtml(p.pangkat || '')} ${escapeHtml(p.name)}</div>
        <div style="color:#64748b">${escapeHtml(p.jabatan || p.nrp || '')}</div>
        <div style="margin-top:6px">${p.is_online ? '🟢 Online' : '⚪ Offline'} · ${escapeHtml(ageLabel(p.age_seconds))}</div>
        ${p.accuracy ? `<div>Akurasi ±${Math.round(p.accuracy)} m</div>` : ''}
        ${p.battery !== null && p.battery !== undefined ? `<div>Baterai ${p.battery}%${p.is_charging ? ' (mengisi)' : ''}</div>` : ''}
    </div>`;

const renderMarkers = () => {
    if (!map) return;
    const seen = new Set();
    for (const p of people.value) {
        seen.add(p.user_id);
        const pos = [p.latitude, p.longitude];
        const active = p.user_id === selectedId.value;
        let m = markers.get(p.user_id);
        if (!m) {
            m = L.marker(pos, { icon: markerIcon(p, active) }).addTo(map);
            m.on('click', () => selectPerson(p.user_id, false));
            markers.set(p.user_id, m);
        } else {
            m.setLatLng(pos);
            m.setIcon(markerIcon(p, active));
        }
        m.setZIndexOffset(active ? 1000 : (p.is_online ? 100 : 0));
        m.bindPopup(popupHtml(p));
    }
    for (const [id, m] of markers) {
        if (!seen.has(id)) {
            map.removeLayer(m);
            markers.delete(id);
        }
    }
};

const fitAll = () => {
    if (!map || people.value.length === 0) return;
    const bounds = L.latLngBounds(people.value.map(p => [p.latitude, p.longitude]));
    map.fitBounds(bounds.pad(0.2), { maxZoom: 15 });
};

const refresh = async () => {
    try {
        const res = await fetch(route('tracking.data'), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error(res.status);
        const json = await res.json();
        people.value = json.personnel || [];
        lastSync.value = new Date();
        syncError.value = false;
        renderMarkers();
    } catch (e) {
        syncError.value = true;
    }
};

const loadTrail = async () => {
    if (trailLayer) {
        map.removeLayer(trailLayer);
        trailLayer = null;
    }
    if (!selectedId.value) return;
    trailLoading.value = true;
    try {
        const res = await fetch(route('tracking.history', { userId: selectedId.value, hours: trailHours.value }), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const json = await res.json();
        const pts = (json.points || []).map(p => [p.latitude, p.longitude]);
        if (pts.length > 1) {
            trailLayer = L.polyline(pts, { color: '#2563eb', weight: 4, opacity: 0.75 }).addTo(map);
        }
    } finally {
        trailLoading.value = false;
    }
};

const selectPerson = async (id, pan = true) => {
    selectedId.value = id;
    renderMarkers();
    const p = selected.value;
    if (p && pan) {
        map.setView([p.latitude, p.longitude], Math.max(map.getZoom(), 16));
        markers.get(id)?.openPopup();
    }
    await loadTrail();
};

const clearSelection = () => {
    selectedId.value = null;
    if (trailLayer) {
        map.removeLayer(trailLayer);
        trailLayer = null;
    }
    renderMarkers();
};

const openInMaps = (p) => window.open(`https://www.google.com/maps?q=${p.latitude},${p.longitude}`, '_blank', 'noopener');

onMounted(async () => {
    await nextTick();
    map = L.map(mapEl.value, { zoomControl: true }).setView(DEFAULT_CENTER, 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);
    renderMarkers();
    fitAll();
    pollTimer = setInterval(refresh, POLL_MS);
});

onBeforeUnmount(() => {
    clearInterval(pollTimer);
    map?.remove();
    map = null;
});
</script>

<template>
    <Head title="Pantau Personel" />

    <AuthenticatedLayout>
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
                <div>
                    <h1 class="text-xl font-extrabold text-slate-800">Pantau Personel</h1>
                    <p class="text-xs text-slate-500">
                        Posisi realtime dari aplikasi Sinden 2 Android · diperbarui otomatis setiap {{ POLL_MS / 1000 }} detik
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-700 font-bold">{{ onlineCount }} online</span>
                    <span class="px-3 py-1.5 rounded-full bg-slate-100 text-slate-600 font-bold">{{ people.length }} personel</span>
                    <span :class="syncError ? 'text-rose-600' : 'text-slate-400'" class="font-medium">
                        {{ syncError ? 'Gagal sinkron' : 'Sinkron ' + lastSync.toLocaleTimeString('id-ID') }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[320px_1fr] gap-4">
                <aside class="bg-white rounded-3xl border border-slate-200 p-4 flex flex-col min-h-[300px] lg:h-[calc(100vh-220px)]">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Cari nama, NRP, jabatan"
                        class="w-full rounded-2xl border-slate-200 bg-slate-50 text-xs px-4 py-2.5 focus:ring-blue-500 focus:border-blue-600"
                    >
                    <div class="flex gap-1.5 mt-3">
                        <button
                            v-for="f in [{ k: 'all', l: 'Semua' }, { k: 'online', l: 'Online' }, { k: 'offline', l: 'Offline' }]"
                            :key="f.k"
                            type="button"
                            class="px-3 py-1.5 rounded-full text-[11px] font-bold transition"
                            :class="filter === f.k ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            @click="filter = f.k"
                        >{{ f.l }}</button>
                    </div>

                    <div class="mt-3 flex-1 overflow-y-auto space-y-1.5 pr-1">
                        <p v-if="visiblePeople.length === 0" class="text-xs text-slate-400 text-center py-8">
                            Belum ada personel yang mengirim lokasi.
                        </p>
                        <button
                            v-for="p in visiblePeople"
                            :key="p.user_id"
                            type="button"
                            class="w-full text-left rounded-2xl px-3 py-2.5 border transition flex items-center gap-3"
                            :class="selectedId === p.user_id ? 'border-amber-400 bg-amber-50' : 'border-transparent hover:bg-slate-50'"
                            @click="selectPerson(p.user_id)"
                        >
                            <span
                                class="shrink-0 w-9 h-9 rounded-full flex items-center justify-center text-[11px] font-bold text-white"
                                :class="p.is_online ? 'bg-emerald-600' : 'bg-slate-400'"
                            >{{ initials(p.name) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-bold text-slate-800 truncate">{{ p.pangkat }} {{ p.name }}</span>
                                <span class="block text-[11px] text-slate-500 truncate">
                                    {{ p.is_online ? 'Online' : 'Offline' }} · {{ ageLabel(p.age_seconds) }}
                                    <template v-if="p.battery !== null && p.battery !== undefined"> · 🔋{{ p.battery }}%</template>
                                </span>
                            </span>
                        </button>
                    </div>
                </aside>

                <section class="relative bg-white rounded-3xl border border-slate-200 overflow-hidden min-h-[420px] lg:h-[calc(100vh-220px)]">
                    <div ref="mapEl" class="absolute inset-0 z-0"></div>

                    <div class="absolute top-3 right-3 z-[500] flex gap-2">
                        <button type="button" class="px-3 py-2 rounded-xl bg-white/95 shadow text-[11px] font-bold text-slate-700 hover:bg-white" @click="fitAll">
                            Tampilkan semua
                        </button>
                    </div>

                    <div
                        v-if="selected"
                        class="absolute bottom-3 left-3 right-3 sm:right-auto sm:w-96 z-[500] bg-white/95 backdrop-blur rounded-2xl shadow-lg p-4 text-xs"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-extrabold text-slate-800 text-sm truncate">{{ selected.pangkat }} {{ selected.name }}</p>
                                <p class="text-slate-500 truncate">{{ selected.jabatan || selected.nrp || '-' }}</p>
                            </div>
                            <button type="button" class="text-slate-400 hover:text-slate-700 font-bold" @click="clearSelection">✕</button>
                        </div>
                        <div class="grid grid-cols-2 gap-x-3 gap-y-1 mt-3 text-slate-600">
                            <span>Status</span><span class="font-bold" :class="selected.is_online ? 'text-emerald-600' : 'text-slate-500'">{{ selected.is_online ? 'Online' : 'Offline' }}</span>
                            <span>Pembaruan</span><span class="font-bold text-slate-800">{{ ageLabel(selected.age_seconds) }}</span>
                            <span>Akurasi</span><span class="font-bold text-slate-800">{{ selected.accuracy ? '±' + Math.round(selected.accuracy) + ' m' : '-' }}</span>
                            <span>Baterai</span><span class="font-bold text-slate-800">{{ selected.battery ?? '-' }}{{ selected.battery !== null && selected.battery !== undefined ? '%' : '' }}</span>
                            <span>Perangkat</span><span class="font-bold text-slate-800 truncate">{{ selected.device || '-' }}</span>
                        </div>
                        <div class="flex items-center gap-2 mt-3">
                            <label class="text-slate-500">Jejak</label>
                            <select v-model.number="trailHours" class="rounded-xl border-slate-200 text-[11px] py-1 pl-2 pr-7" @change="loadTrail">
                                <option :value="1">1 jam</option>
                                <option :value="6">6 jam</option>
                                <option :value="12">12 jam</option>
                                <option :value="24">24 jam</option>
                            </select>
                            <span v-if="trailLoading" class="text-slate-400">memuat…</span>
                            <button type="button" class="ml-auto px-3 py-1.5 rounded-xl bg-blue-600 text-white font-bold" @click="openInMaps(selected)">
                                Buka di Maps
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
