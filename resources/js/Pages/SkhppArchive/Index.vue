<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    archives: { type: Object, default: () => ({ data: [], links: [], total: 0 }) },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    year: Number,
    years: { type: Array, default: () => [] },
    priorities: { type: Object, default: () => ({}) },
    statusLabels: { type: Object, default: () => ({}) },
    canManage: Boolean,
});

const page = usePage();

// --- NOTIFIKASI FLASH ---
watch(() => page.props.flash, (flash) => {
    if (flash?.success) Swal.fire({ icon: 'success', title: 'Berhasil', text: flash.success, confirmButtonColor: '#059669' });
    else if (flash?.error) Swal.fire({ icon: 'error', title: 'Gagal', text: flash.error, confirmButtonColor: '#dc2626' });
}, { deep: true, immediate: true });

// --- FILTER ---
const filterForm = ref({
    search: props.filters.search || '',
    jenis: props.filters.jenis || '',
    status: props.filters.status || '',
    sumber: props.filters.sumber || '',
    tahun: props.filters.tahun || '',
});

let searchTimer = null;
const applyFilters = () => {
    const params = Object.fromEntries(Object.entries(filterForm.value).filter(([, v]) => v !== '' && v !== null));
    router.get(route('skhpp-archives.index'), params, { preserveState: true, preserveScroll: true, replace: true });
};
watch(() => filterForm.value.search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 400);
});
watch(() => [filterForm.value.jenis, filterForm.value.status, filterForm.value.sumber, filterForm.value.tahun], applyFilters);

// --- MINTA NOMOR SKHPP MANUAL ---
const showRequestModal = ref(false);
const preview = ref({ formatted: '', sequence: null, loading: false });
const today = () => new Date().toISOString().slice(0, 10);

const requestForm = useForm({
    jenis: 'D',
    priority: 'R',
    tanggal: today(),
    nama: '',
    perihal: '',
    peruntukan: '',
    keterangan: '',
    custom_nomor_urut: '',
    file: null,
});

const loadPreview = async () => {
    preview.value.loading = true;
    try {
        const { data } = await axios.get(route('skhpp-archives.next-number'), {
            params: { jenis: requestForm.jenis, priority: requestForm.priority, tanggal: requestForm.tanggal },
        });
        preview.value = { ...data, loading: false };
    } catch (e) {
        preview.value = { formatted: '', sequence: null, loading: false };
    }
};

const openRequest = (jenis = 'D') => {
    requestForm.reset();
    requestForm.clearErrors();
    requestForm.jenis = jenis;
    requestForm.tanggal = today();
    showRequestModal.value = true;
    loadPreview();
};

watch(() => [requestForm.jenis, requestForm.priority, requestForm.tanggal], () => {
    if (showRequestModal.value) loadPreview();
});

const previewNumber = computed(() => {
    if (requestForm.custom_nomor_urut && preview.value.formatted) {
        const parts = preview.value.formatted.split(' / ');
        parts[1] = String(requestForm.custom_nomor_urut);
        return parts.join(' / ');
    }
    return preview.value.formatted;
});

const submitRequest = () => {
    requestForm.post(route('skhpp-archives.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { showRequestModal.value = false; },
    });
};

// --- UNGGAH PDF ---
const uploadTarget = ref(null);
const uploadForm = useForm({ file: null });

const openUpload = (archive) => {
    uploadTarget.value = archive;
    uploadForm.reset();
    uploadForm.clearErrors();
};

const submitUpload = () => {
    uploadForm.post(route('skhpp-archives.upload', uploadTarget.value.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { uploadTarget.value = null; },
    });
};

// --- SIMPAN PDF OTOMATIS DARI SKHPP SINDEN ---
const generating = ref(null);
const generatePdf = (archive) => {
    generating.value = archive.id;
    router.post(route('skhpp-archives.generate-pdf', archive.id), {}, {
        preserveScroll: true,
        onFinish: () => { generating.value = null; },
    });
};

// --- LIHAT PDF ---
const pdfUrl = ref('');
const pdfTitle = ref('');
const openPdf = (archive) => {
    pdfTitle.value = archive.nomor_skhpp || archive.perihal;
    pdfUrl.value = route('skhpp-archives.file', archive.id) + '?t=' + Date.now();
};

// --- UBAH DATA ---
const editTarget = ref(null);
const editForm = useForm({ nama: '', perihal: '', peruntukan: '', keterangan: '' });
const canEdit = (a) => props.canManage || a.created_by === page.props.auth.user?.id;

const openEdit = (archive) => {
    editTarget.value = archive;
    editForm.nama = archive.nama || '';
    editForm.perihal = archive.perihal || '';
    editForm.peruntukan = archive.peruntukan || '';
    editForm.keterangan = archive.keterangan || '';
    editForm.clearErrors();
};

const submitEdit = () => {
    editForm.put(route('skhpp-archives.update', editTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => { editTarget.value = null; },
    });
};

// --- HAPUS ---
const canDelete = (a) => props.canManage && (a.sumber === 'manual' || a.status === 'dicabut' || !a.skhpp_id);
const destroyArchive = (archive) => {
    Swal.fire({
        icon: 'warning',
        title: 'Hapus arsip SKHPP?',
        html: `<p class="text-sm">${archive.nomor_skhpp || archive.perihal}</p><p class="text-xs text-slate-500 mt-2">Berkas PDF di arsip ini ikut dihapus.</p>`,
        showCancelButton: true,
        confirmButtonText: 'Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626',
    }).then((r) => {
        if (r.isConfirmed) router.delete(route('skhpp-archives.destroy', archive.id), { preserveScroll: true });
    });
};

// --- TAMPILAN ---
const skhppStageLabel = (a) => {
    if (a.sumber !== 'sinden') return null;
    const s = a.skhpp?.status;
    if (!a.skhpp) return a.status === 'dicabut' ? 'SKHPP dihapus' : null;
    if (s === 'approved') return 'TTE Komandan terbit';
    if (s === 'rejected') return 'Dikembalikan untuk revisi';
    return 'Menunggu TTE/TTD Komandan';
};

const statusClass = (status) => ({
    menunggu_pdf: 'bg-amber-50 text-amber-700 border-amber-200',
    tersimpan: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    dicabut: 'bg-rose-50 text-rose-700 border-rose-200',
}[status] || 'bg-slate-50 text-slate-600 border-slate-200');

const formatDate = (d) => {
    if (!d) return '-';
    const dt = new Date(d);
    return isNaN(dt) ? d : dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};
</script>

<template>
    <Head title="Arsip & Nomor SKHPP - SINDEN" />

    <AuthenticatedLayout>
        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- HEADER -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shadow-lg shadow-emerald-600/20">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-black text-slate-900 uppercase tracking-tight">Arsip & Nomor SKHPP</h1>
                        <p class="text-xs text-slate-500 font-medium">Terpisah dari Agenda & Arsip Surat Masuk/Keluar. Nomor SKHPP-D dan SKHPP-P berurutan sendiri.</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link v-if="page.props.auth.user?.is_admin || (page.props.auth.user?.features || []).includes('skhpp')" :href="route('skhpp.index')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 text-slate-700 text-xs font-black uppercase rounded-2xl hover:bg-slate-50 transition">
                        Penerbitan SKHPP
                    </Link>
                    <button type="button" @click="openRequest('D')"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black uppercase tracking-wider rounded-2xl shadow-lg shadow-emerald-600/20 transition active:scale-95 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                        Minta Nomor SKHPP Manual
                    </button>
                </div>
            </div>

            <!-- RINGKASAN NOMOR -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-for="jenis in ['D', 'P']" :key="jenis" class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-extrabold uppercase tracking-widest" :class="jenis === 'D' ? 'text-emerald-600' : 'text-indigo-600'">
                                {{ jenis === 'D' ? 'SKHPP-D · Dinas Militer & PNS' : 'SKHPP-P · Mitra Kerja / Perusahaan' }}
                            </p>
                            <p class="mt-2 text-sm text-slate-500">Nomor terakhir {{ year }}:
                                <span class="font-black text-slate-900 text-lg">{{ summary[jenis]?.last || '-' }}</span>
                            </p>
                            <p class="text-xs text-slate-500">Nomor berikutnya: <span class="font-black text-slate-800">{{ summary[jenis]?.next }}</span></p>
                        </div>
                        <button type="button" @click="openRequest(jenis)"
                            :class="jenis === 'D' ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-600' : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-600'"
                            class="px-3 py-2 rounded-xl text-[10px] font-black uppercase hover:text-white transition cursor-pointer shrink-0">
                            Minta Nomor {{ jenis }}
                        </button>
                    </div>
                    <div class="mt-4 flex gap-2 text-[10px] font-bold">
                        <span class="px-2 py-1 rounded-lg bg-amber-50 text-amber-700 border border-amber-200">{{ summary[jenis]?.menunggu || 0 }} menunggu PDF</span>
                        <span class="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200">{{ summary[jenis]?.tersimpan || 0 }} PDF tersimpan</span>
                    </div>
                </div>
            </div>

            <!-- FILTER -->
            <div class="bg-white border border-slate-200 rounded-3xl p-4 shadow-xs grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <input v-model="filterForm.search" type="text" placeholder="Cari nomor, nama, perihal..."
                    class="lg:col-span-2 w-full rounded-xl border-slate-200 text-xs focus:border-emerald-500 focus:ring-emerald-500" />
                <select v-model="filterForm.jenis" class="rounded-xl border-slate-200 text-xs">
                    <option value="">Semua Jenis</option>
                    <option value="D">SKHPP-D</option>
                    <option value="P">SKHPP-P</option>
                </select>
                <select v-model="filterForm.status" class="rounded-xl border-slate-200 text-xs">
                    <option value="">Semua Status</option>
                    <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                </select>
                <div class="grid grid-cols-2 gap-2">
                    <select v-model="filterForm.sumber" class="rounded-xl border-slate-200 text-xs">
                        <option value="">Semua Sumber</option>
                        <option value="sinden">Menu SINDEN</option>
                        <option value="manual">Manual</option>
                    </select>
                    <select v-model="filterForm.tahun" class="rounded-xl border-slate-200 text-xs">
                        <option value="">Semua Tahun</option>
                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>
            </div>

            <!-- TABEL ARSIP -->
            <div class="bg-white border border-slate-200 rounded-3xl shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="text-left px-4 py-3 font-extrabold">Nomor SKHPP</th>
                                <th class="text-left px-4 py-3 font-extrabold">Perihal / Nama</th>
                                <th class="text-left px-4 py-3 font-extrabold">Tanggal</th>
                                <th class="text-left px-4 py-3 font-extrabold">Sumber</th>
                                <th class="text-left px-4 py-3 font-extrabold">Status</th>
                                <th class="text-right px-4 py-3 font-extrabold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="a in archives.data" :key="a.id" class="hover:bg-slate-50/60 align-top">
                                <td class="px-4 py-3">
                                    <span :class="a.jenis === 'D' ? 'bg-emerald-100 text-emerald-800' : 'bg-indigo-100 text-indigo-800'" class="px-2 py-0.5 rounded-md text-[9px] font-black">SKHPP-{{ a.jenis }}</span>
                                    <p class="mt-1 font-black text-slate-900 whitespace-nowrap">{{ a.nomor_skhpp || 'Belum bernomor' }}</p>
                                    <p v-if="!a.nomor_skhpp" class="text-[10px] text-slate-400">Nomor terbit saat TTE Komandan</p>
                                </td>
                                <td class="px-4 py-3 max-w-xs">
                                    <p class="font-bold text-slate-800">{{ a.perihal }}</p>
                                    <p v-if="a.peruntukan" class="text-[10px] text-slate-500 line-clamp-2">{{ a.peruntukan }}</p>
                                    <p v-if="a.keterangan" class="text-[10px] text-slate-400 whitespace-pre-line line-clamp-2">{{ a.keterangan }}</p>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-slate-600">{{ formatDate(a.tanggal || a.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <span v-if="a.sumber === 'sinden'" class="px-2 py-0.5 rounded-md bg-blue-50 text-blue-700 text-[9px] font-black uppercase">Menu SINDEN</span>
                                    <span v-else class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[9px] font-black uppercase">Manual</span>
                                    <p class="text-[10px] text-slate-400 mt-1">{{ a.creator?.name || '-' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="statusClass(a.status)" class="px-2 py-1 rounded-lg border text-[9px] font-black uppercase whitespace-nowrap">{{ a.status_label }}</span>
                                    <p v-if="skhppStageLabel(a)" class="text-[10px] text-slate-500 mt-1">{{ skhppStageLabel(a) }}</p>
                                    <p v-if="a.uploaded_at" class="text-[10px] text-slate-400">PDF: {{ formatDate(a.uploaded_at) }}<span v-if="a.uploader"> · {{ a.uploader.name }}</span></p>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        <button v-if="a.has_file" type="button" @click="openPdf(a)" class="px-2.5 py-1.5 rounded-lg bg-emerald-600 text-white text-[9px] font-black uppercase hover:bg-emerald-700 cursor-pointer">Lihat PDF</button>
                                        <button v-if="a.sumber === 'sinden' && a.skhpp?.status === 'approved' && a.status !== 'dicabut'" type="button" :disabled="generating === a.id" @click="generatePdf(a)"
                                            class="px-2.5 py-1.5 rounded-lg bg-blue-50 text-blue-700 border border-blue-200 text-[9px] font-black uppercase hover:bg-blue-600 hover:text-white disabled:opacity-50 cursor-pointer"
                                            title="Buat ulang PDF resmi bertanda tangan elektronik dari SINDEN">
                                            {{ generating === a.id ? 'Menyimpan...' : (a.has_file ? 'Perbarui PDF TTE' : 'Simpan PDF TTE') }}
                                        </button>
                                        <button v-if="a.nomor_skhpp && a.status !== 'dicabut'" type="button" @click="openUpload(a)"
                                            class="px-2.5 py-1.5 rounded-lg bg-amber-50 text-amber-700 border border-amber-200 text-[9px] font-black uppercase hover:bg-amber-500 hover:text-white cursor-pointer">
                                            {{ a.has_file ? 'Ganti PDF' : 'Unggah PDF' }}
                                        </button>
                                        <Link v-if="a.skhpp_id" :href="route('skhpp.show', a.skhpp_id)" class="px-2.5 py-1.5 rounded-lg bg-slate-100 text-slate-600 text-[9px] font-black uppercase hover:bg-slate-200">Detail</Link>
                                        <button v-if="canEdit(a)" type="button" @click="openEdit(a)" class="px-2.5 py-1.5 rounded-lg bg-slate-100 text-slate-600 text-[9px] font-black uppercase hover:bg-slate-200 cursor-pointer">Ubah</button>
                                        <button v-if="canDelete(a)" type="button" @click="destroyArchive(a)" class="px-2.5 py-1.5 rounded-lg bg-rose-50 text-rose-700 text-[9px] font-black uppercase hover:bg-rose-600 hover:text-white cursor-pointer">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!archives.data || archives.data.length === 0">
                                <td colspan="6" class="px-4 py-12 text-center text-slate-400 font-semibold">Belum ada arsip SKHPP yang cocok dengan filter.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->
                <div v-if="archives.links && archives.links.length > 3" class="flex flex-wrap gap-1 justify-center p-4 border-t border-slate-100">
                    <template v-for="(link, i) in archives.links" :key="i">
                        <Link v-if="link.url" :href="link.url" preserve-scroll
                            :class="link.active ? 'bg-emerald-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'"
                            class="px-3 py-1.5 rounded-lg border border-slate-200 text-[11px] font-bold" v-html="link.label" />
                        <span v-else class="px-3 py-1.5 rounded-lg text-[11px] text-slate-300" v-html="link.label"></span>
                    </template>
                </div>
            </div>
        </div>

        <!-- MODAL: MINTA NOMOR SKHPP MANUAL -->
        <div v-if="showRequestModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs" @click.self="showRequestModal = false">
            <form @submit.prevent="submitRequest" class="w-full max-w-xl bg-white rounded-3xl shadow-2xl p-6 space-y-4 max-h-[92vh] overflow-y-auto">
                <div>
                    <h3 class="text-sm font-black uppercase text-slate-900">Minta Nomor SKHPP Manual</h3>
                    <p class="text-[11px] text-slate-500">Untuk SKHPP yang dibuat di luar menu SINDEN. Nomor melanjutkan urutan SKHPP-{{ requestForm.jenis }} yang sama dengan menu Penerbitan SKHPP.</p>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button v-for="j in ['D', 'P']" :key="j" type="button" @click="requestForm.jenis = j"
                        :class="requestForm.jenis === j ? (j === 'D' ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-500/20' : 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-500/20') : 'border-slate-200 hover:bg-slate-50'"
                        class="p-3 border rounded-2xl text-left cursor-pointer">
                        <p class="text-xs font-black">SKHPP-{{ j }}</p>
                        <p class="text-[10px] text-slate-500">{{ j === 'D' ? 'Dinas Militer & PNS' : 'Mitra Kerja / Perusahaan' }}</p>
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <label class="block text-[10px] font-extrabold uppercase text-slate-500">Sifat
                        <select v-model="requestForm.priority" class="mt-1 w-full rounded-xl border-slate-200 text-xs">
                            <option v-for="(label, key) in priorities" :key="key" :value="key">{{ key }} — {{ label }}</option>
                        </select>
                    </label>
                    <label class="block text-[10px] font-extrabold uppercase text-slate-500">Tanggal
                        <input v-model="requestForm.tanggal" type="date" required class="mt-1 w-full rounded-xl border-slate-200 text-xs" />
                    </label>
                </div>

                <div class="p-4 rounded-2xl bg-slate-900 text-white">
                    <p class="text-[9px] uppercase tracking-widest text-slate-400 font-bold">Nomor yang akan dibuat</p>
                    <p class="text-lg font-black tracking-wide mt-1">{{ preview.loading ? 'Memuat...' : (previewNumber || '-') }}</p>
                    <p class="text-[10px] text-slate-400 mt-1">Nomor final dikunci saat disimpan agar tidak bentrok dengan petugas lain.</p>
                </div>

                <label v-if="canManage" class="block text-[10px] font-extrabold uppercase text-slate-500">Nomor urut khusus (opsional, Admin)
                    <input v-model="requestForm.custom_nomor_urut" type="number" min="1" placeholder="Kosongkan untuk nomor berikutnya" class="mt-1 w-full rounded-xl border-slate-200 text-xs" />
                </label>

                <label class="block text-[10px] font-extrabold uppercase text-slate-500">Perihal *
                    <input v-model="requestForm.perihal" type="text" required maxlength="255" placeholder="Contoh: SKHPP a.n. ... untuk ..." class="mt-1 w-full rounded-xl border-slate-200 text-xs" />
                    <span v-if="requestForm.errors.perihal" class="text-rose-600 normal-case">{{ requestForm.errors.perihal }}</span>
                </label>
                <label class="block text-[10px] font-extrabold uppercase text-slate-500">Nama Personel / Pemohon
                    <input v-model="requestForm.nama" type="text" maxlength="255" class="mt-1 w-full rounded-xl border-slate-200 text-xs" />
                </label>
                <label class="block text-[10px] font-extrabold uppercase text-slate-500">Peruntukan
                    <textarea v-model="requestForm.peruntukan" rows="2" class="mt-1 w-full rounded-xl border-slate-200 text-xs"></textarea>
                </label>
                <label class="block text-[10px] font-extrabold uppercase text-slate-500">Keterangan
                    <textarea v-model="requestForm.keterangan" rows="2" class="mt-1 w-full rounded-xl border-slate-200 text-xs"></textarea>
                </label>
                <label class="block text-[10px] font-extrabold uppercase text-slate-500">PDF SKHPP (opsional, bisa diunggah nanti)
                    <input type="file" accept="application/pdf" @change="e => requestForm.file = e.target.files[0] || null" class="mt-1 w-full text-xs" />
                    <span v-if="requestForm.errors.file" class="text-rose-600 normal-case">{{ requestForm.errors.file }}</span>
                </label>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showRequestModal = false" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 cursor-pointer">Batal</button>
                    <button type="submit" :disabled="requestForm.processing" class="px-5 py-2 rounded-xl bg-emerald-600 text-white text-xs font-black uppercase hover:bg-emerald-700 disabled:opacity-50 cursor-pointer">
                        {{ requestForm.processing ? 'Menyimpan...' : 'Buat Nomor' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- MODAL: UNGGAH PDF -->
        <div v-if="uploadTarget" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs" @click.self="uploadTarget = null">
            <form @submit.prevent="submitUpload" class="w-full max-w-md bg-white rounded-3xl shadow-2xl p-6 space-y-4">
                <div>
                    <h3 class="text-sm font-black uppercase text-slate-900">{{ uploadTarget.has_file ? 'Ganti' : 'Unggah' }} PDF SKHPP</h3>
                    <p class="text-xs font-bold text-slate-700 mt-1">{{ uploadTarget.nomor_skhpp }}</p>
                    <p class="text-[11px] text-slate-500">{{ uploadTarget.perihal }}</p>
                </div>
                <input type="file" accept="application/pdf" required @change="e => uploadForm.file = e.target.files[0] || null" class="w-full text-xs" />
                <span v-if="uploadForm.errors.file" class="text-xs text-rose-600">{{ uploadForm.errors.file }}</span>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="uploadTarget = null" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 cursor-pointer">Batal</button>
                    <button type="submit" :disabled="uploadForm.processing || !uploadForm.file" class="px-5 py-2 rounded-xl bg-amber-500 text-white text-xs font-black uppercase hover:bg-amber-600 disabled:opacity-50 cursor-pointer">
                        {{ uploadForm.processing ? 'Mengunggah...' : 'Simpan PDF' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- MODAL: UBAH DATA -->
        <div v-if="editTarget" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs" @click.self="editTarget = null">
            <form @submit.prevent="submitEdit" class="w-full max-w-md bg-white rounded-3xl shadow-2xl p-6 space-y-3">
                <h3 class="text-sm font-black uppercase text-slate-900">Ubah Data Arsip</h3>
                <p class="text-xs font-bold text-slate-700">{{ editTarget.nomor_skhpp || 'Belum bernomor' }}</p>
                <template v-if="editTarget.sumber === 'manual'">
                    <label class="block text-[10px] font-extrabold uppercase text-slate-500">Perihal *
                        <input v-model="editForm.perihal" type="text" required class="mt-1 w-full rounded-xl border-slate-200 text-xs" />
                    </label>
                    <label class="block text-[10px] font-extrabold uppercase text-slate-500">Nama
                        <input v-model="editForm.nama" type="text" class="mt-1 w-full rounded-xl border-slate-200 text-xs" />
                    </label>
                    <label class="block text-[10px] font-extrabold uppercase text-slate-500">Peruntukan
                        <textarea v-model="editForm.peruntukan" rows="2" class="mt-1 w-full rounded-xl border-slate-200 text-xs"></textarea>
                    </label>
                </template>
                <p v-else class="text-[11px] text-slate-500">Data utama SKHPP SINDEN diubah dari menu Penerbitan SKHPP. Di sini hanya keterangan arsip.</p>
                <label class="block text-[10px] font-extrabold uppercase text-slate-500">Keterangan
                    <textarea v-model="editForm.keterangan" rows="3" class="mt-1 w-full rounded-xl border-slate-200 text-xs"></textarea>
                </label>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="editTarget = null" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 cursor-pointer">Batal</button>
                    <button type="submit" :disabled="editForm.processing" class="px-5 py-2 rounded-xl bg-slate-900 text-white text-xs font-black uppercase disabled:opacity-50 cursor-pointer">Simpan</button>
                </div>
            </form>
        </div>

        <!-- MODAL: PRATINJAU PDF -->
        <div v-if="pdfUrl" class="fixed inset-0 z-[70] flex flex-col bg-slate-950/80 p-3 sm:p-6" @click.self="pdfUrl = ''">
            <div class="flex items-center justify-between text-white mb-2">
                <p class="text-xs font-black uppercase truncate">{{ pdfTitle }}</p>
                <div class="flex gap-2">
                    <a :href="pdfUrl" target="_blank" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-[10px] font-black uppercase">Buka Tab Baru</a>
                    <button type="button" @click="pdfUrl = ''" class="px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-[10px] font-black uppercase cursor-pointer">Tutup</button>
                </div>
            </div>
            <iframe :src="pdfUrl" class="flex-1 w-full rounded-2xl bg-white"></iframe>
        </div>
    </AuthenticatedLayout>
</template>
