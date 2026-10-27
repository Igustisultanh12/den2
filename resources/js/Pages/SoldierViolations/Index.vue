<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import html2pdf from 'html2pdf.js'; 

const props = defineProps({
    violations: Object,
    filters: Object,
    stats: Object,
    auth: Object 
});

// State untuk Kontrol Modal
const showCreateModal = ref(false); 
const showUpdateModal = ref(false); 
const showPreviewModal = ref(false); 
const showDocumentPreviewModal = ref(false); // Modal untuk Iframe Pratinjau Berkas Dokumen
const showDetailModal = ref(false); // MODAL BARU: Rincian Lengkap Kasus & Berkas Personel
const selectedCase = ref(null);
const selectedDetailCase = ref(null);
const isPrinting = ref(false);
const printData = ref(null);
const currentPreviewUrl = ref(''); // Menyimpan URL berkas aktif yang sedang ditinjau

// Form Input Baru
const form = useForm({
    nama: '',
    nrp: '',
    pangkat: '',
    jabatan: '',
    satuan: '',
    kasus: '',
    tmt: '',
    perkembangan_kasus: '',
    status: 'PROSES',
    lampiran: [], 
    putusan: [],  
});

// Form Update Perkembangan
const updateForm = useForm({
    _method: 'PUT',
    catatan_baru: '',
    status: '',
    lampiran: [],
    putusan: [],
});

const search = ref(props.filters.search || '');

const openUpdateModal = (item) => {
    selectedCase.value = item;
    updateForm.status = item.status;
    updateForm.catatan_baru = ''; 
    updateForm.lampiran = [];
    updateForm.putusan = [];
    showUpdateModal.value = true;
};

// Fungsi Membuka Modal Rincian Kasus Lengkap saat Nama Personel Diklik
const openDetailModal = (item) => {
    selectedDetailCase.value = item;
    showDetailModal.value = true;
};

// Fungsi Membuka Peninjau Laporan PDF (Cetak Form)
const openPreview = (item) => {
    printData.value = item;
    showPreviewModal.value = true;
};

// Fungsi Membuka Iframe Peninjau Berkas Fisik Langsung (.docx, .pdf, .png, dll)
const viewFileDirect = (filePath) => {
    const ext = filePath.split('.').pop().toLowerCase();
    if (['docx', 'doc', 'pdf', 'jpg', 'jpeg', 'png'].includes(ext)) {
        currentPreviewUrl.value = `/soldier-violations/preview-file?file_path=${encodeURIComponent(filePath)}`;
        showDocumentPreviewModal.value = true;
    } else {
        window.open('/storage/' + filePath, '_blank');
    }
};

// Fungsi Download PDF dari Peninjau
const downloadPDF = () => {
    isPrinting.value = true;
    const element = document.getElementById('area-cetak-cases');
    const opt = {
        margin: [0.3, 0.3],
        filename: `LAP_KASUS_${printData.value.nrp}_${printData.value.name.replace(/\s+/g, '_')}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    
    html2pdf().set(opt).from(element).save().then(() => {
        isPrinting.value = false;
        showPreviewModal.value = false;
    });
};

const submit = () => {
    form.post(route('soldier-violations.store'), {
        forceFormData: true, 
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
            alert('Lapor! Data kasus dan paket berkas awal berhasil diamankan.');
        },
        onError: (errors) => {
            console.error(errors);
            alert('Gagal menyimpan data kasus. Periksa kembali form berkas lampiran Anda.');
        }
    });
};

const submitUpdate = () => {
    updateForm.post(route('soldier-violations.update', selectedCase.value.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showUpdateModal.value = false;
            updateForm.reset({ catatan_baru: '', lampiran: [], putusan: [] });
            alert('Lapor! Update perkembangan dan berkas telah disinkronkan.');
        },
        onError: (errors) => {
            console.error(errors);
            alert('Gagal memperbarui data. Periksa kembali jaringan atau rute server Anda.');
        }
    });
};

// Hapus Riwayat Update Tertentu
const deleteHistoryItem = (updateId) => {
    if (confirm('Lapor! Hapus baris riwayat perkembangan ini beserta file fisiknya secara permanen?')) {
        router.post(route('soldier-violations.delete-update', selectedCase.value.id), {
            _method: 'DELETE',
            update_id: updateId
        }, { 
            preserveScroll: true,
            onSuccess: () => {
                const updated = props.violations.data.find(v => v.id === selectedCase.value.id);
                if (updated) selectedCase.value = updated;
                if (selectedDetailCase.value && selectedDetailCase.value.id === selectedCase.value.id) {
                    selectedDetailCase.value = updated;
                }
            }
        });
    }
};

// Bersihkan/Hapus Seluruh Data Kasus
const deleteCase = (id) => {
    if (confirm('PERINGATAN! Seluruh data dan seluruh riwayat berkas kasus ini akan dimusnahkan secara total. Lanjutkan?')) {
        router.delete(route('soldier-violations.destroy', id), {
            onSuccess: () => alert('Lapor! Seluruh data telah dimusnahkan dari server.')
        });
    }
};

const statusColor = (status) => {
    const colors = {
        'PROSES': 'bg-red-100 text-red-700 border-red-200',
        'SIDANG': 'bg-amber-100 text-amber-700 border-amber-200',
        'SELESAI': 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'DINAS_KEMBALI': 'bg-blue-100 text-blue-700 border-blue-200',
        'PDTH': 'bg-gray-800 text-white border-gray-900',
    };
    return colors[status] || 'bg-gray-100 text-gray-700';
};

const parseFiles = (jsonString) => {
    try {
        return jsonString ? JSON.parse(jsonString) : [];
    } catch (e) {
        return [];
    }
};

// Ambil Nama File dari Jalur Folder/Path Storage
const getFileName = (path) => {
    return path ? path.split('/').pop().substring(11) : 'Dokumen';
};
</script>

<template>
    <Head title="Pelanggaran Prajurit" />
    <AuthenticatedLayout>
        <div class="space-y-6 font-sans">
            
            <!-- Page Header Card -->
            <div class="bg-white p-4 sm:p-6 lg:p-8 rounded-2xl sm:rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="font-extrabold text-lg sm:text-xl text-slate-900 uppercase tracking-tight">Data Pelanggaran Prajurit</h2>
                    <p class="text-xs text-slate-500 font-semibold mt-0.5">Monitoring Berkas & Perkembangan Kasus Personel</p>
                </div>

                <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                    <button @click="showCreateModal = true" class="flex-1 md:flex-none justify-center bg-blue-600 hover:bg-blue-700 text-white px-4 sm:px-5 py-2.5 sm:py-3 rounded-2xl text-xs font-extrabold uppercase shadow-sm transition tracking-wider flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4" />
                        </svg> Input Kasus Baru
                    </button>
                    <div class="flex gap-4 text-right border-l border-slate-100 pl-3">
                        <div>
                            <p class="text-[9px] font-extrabold text-slate-400 uppercase tracking-wider">Total Kasus</p>
                            <p class="text-lg sm:text-xl font-black text-blue-600">{{ stats.total }}</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-extrabold text-slate-400 uppercase tracking-wider">Dalam Proses</p>
                            <p class="text-lg sm:text-xl font-black text-rose-600">{{ stats.proses }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="bg-white rounded-2xl sm:rounded-3xl shadow-xs border border-[#E2E8F0] overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                    <input v-model="search" type="text" placeholder="Cari NRP atau Nama Prajurit..." class="rounded-2xl border-slate-200 bg-white text-xs font-bold px-5 py-2.5 w-full md:w-96 focus:ring-blue-500 focus:border-blue-600">
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] font-extrabold tracking-wider border-b border-slate-100">
                            <tr>
                                <th class="px-6 py-4">Personel</th>
                                <th class="px-6 py-4">Kasus & TMT</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4 text-center">Riwayat Berkas (Per Update)</th>
                                <th class="px-6 py-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="item in violations.data" :key="item.id" class="hover:bg-indigo-50/10 transition group">
                                <td class="px-6 py-5">
                                    <button type="button" @click="openDetailModal(item)" class="text-left group/btn focus:outline-none block w-full">
                                        <div class="font-black text-indigo-900 text-sm uppercase group-hover/btn:text-blue-600 transition">
                                            {{ item.name }}
                                        </div>
                                        <div class="text-[10px] text-slate-500 font-bold uppercase tracking-tighter mt-0.5">{{ item.rank }} / {{ item.nrp }}</div>
                                        <div class="text-[9px] text-indigo-600 font-black uppercase mt-0.5">{{ item.unit }}</div>
                                    </button>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="text-[11px] font-medium text-gray-700 line-clamp-2 w-48 italic">"{{ item.case_description }}"</div>
                                    <div class="text-[9px] font-black text-gray-400 mt-1 uppercase text-left">TMT: {{ item.incident_date?.split('T')[0] }}</div>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <span :class="statusColor(item.status)" class="px-3 py-1 rounded-full text-[9px] font-black border uppercase">
                                        {{ item.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="flex flex-col gap-2">
                                        <div v-for="(paket, idx) in parseFiles(item.lampiran_berkas)" :key="idx" class="p-2 bg-gray-50 rounded-lg border border-gray-100 flex flex-col gap-1">
                                            <div class="flex justify-between items-center border-b pb-1 mb-1">
                                                <span class="text-[7px] font-black text-indigo-600 uppercase">UPDATE: {{ paket.tanggal }}</span>
                                            </div>
                                            <p class="text-[9px] text-gray-700 font-semibold mb-1">"{{ paket.catatan }}"</p>
                                            
                                            <div class="flex flex-wrap gap-1" v-if="paket.file_paths && paket.file_paths.length">
                                                <button type="button" v-for="(path, pIdx) in paket.file_paths" :key="pIdx" @click="viewFileDirect(path)" class="px-2 py-0.5 bg-red-600 text-white rounded text-[7px] font-black hover:bg-red-700 transition-all truncate max-w-[120px]" :title="getFileName(path)">
                                                     {{ getFileName(path) }}
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="flex flex-col gap-1 mt-1" v-if="item.dokumen_putusan">
                                            <button type="button" v-for="(pPath, pIdx) in parseFiles(item.dokumen_putusan)" :key="pIdx" @click="viewFileDirect(pPath)" class="w-full px-2 py-1 bg-gray-900 text-white rounded text-[8px] font-black hover:bg-indigo-600 transition-all uppercase text-center truncate"> PUTUSAN AKHIR {{ pIdx + 1 }}
                                            </button>
                                        </div>
                                        
                                        <span v-if="parseFiles(item.lampiran_berkas).length === 0 && !item.dokumen_putusan" class="text-[10px] text-gray-300 italic text-center">No File</span>
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-right flex justify-end gap-2">
                                    <button @click="openDetailModal(item)" title="Lihat Rincian Lengkap" class="p-2.5 bg-blue-50 text-blue-600 rounded-xl hover:bg-blue-600 hover:text-white transition-all border border-blue-100 shadow-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                    <button @click="openPreview(item)" title="Pratinjau Cetak PDF" class="p-2.5 bg-gray-50 text-gray-400 rounded-xl hover:bg-indigo-600 hover:text-white transition-all border border-gray-100 shadow-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                    </button>
                                    <button @click="openUpdateModal(item)" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-[9px] font-black uppercase shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition-all"> Update
                                    </button>
                                    <button @click="deleteCase(item.id)" title="Bersihkan Data Kasus" class="p-2.5 bg-red-50 text-red-500 rounded-xl hover:bg-red-600 hover:text-white transition-all border border-red-100">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL DETAIL LENGKAP KASUS PERSONEL -->
        <div v-if="showDetailModal" class="fixed inset-0 z-[140] flex items-center justify-center p-4 bg-black/70 backdrop-blur-md text-left">
            <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-4xl w-full max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in duration-200">
                <!-- Header Modal -->
                <div class="p-6 sm:p-8 bg-slate-900 text-white flex justify-between items-start">
                    <div class="flex items-start gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-blue-600/30 border border-blue-400/40 flex items-center justify-center text-blue-300 font-black text-xl shadow-inner uppercase shrink-0">
                            {{ selectedDetailCase?.name?.charAt(0) }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-extrabold text-base sm:text-lg text-white uppercase tracking-tight">{{ selectedDetailCase?.name }}</h3>
                                <span :class="statusColor(selectedDetailCase?.status)" class="px-3 py-0.5 rounded-full text-[9px] font-black border uppercase">
                                    {{ selectedDetailCase?.status }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-300 font-bold mt-1 uppercase">{{ selectedDetailCase?.rank }} / NRP. {{ selectedDetailCase?.nrp }}</p>
                            <p class="text-[10px] text-blue-300 font-extrabold mt-0.5 uppercase">JABATAN: {{ selectedDetailCase?.position }} | SATKER: {{ selectedDetailCase?.unit }}</p>
                        </div>
                    </div>
                    <button @click="showDetailModal = false" class="text-slate-400 hover:text-white font-black text-2xl p-1 leading-none">&times;</button>
                </div>

                <!-- Body Detail Content -->
                <div class="p-6 sm:p-8 overflow-y-auto flex-1 space-y-6">
                    
                    <!-- Uraian Kasus & TMT -->
                    <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200">
                        <div class="flex justify-between items-center mb-2 flex-wrap gap-2">
                            <h4 class="text-[11px] font-black text-slate-700 uppercase tracking-wider flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg> Deskripsi &amp; Pasal Pelanggaran Kasus
                            </h4>
                            <span class="text-[10px] font-bold text-slate-600 uppercase bg-white px-3 py-1 rounded-lg border border-slate-200">
                                TMT Kejadian: {{ selectedDetailCase?.incident_date?.split('T')[0] }}
                            </span>
                        </div>
                        <p class="text-xs font-medium text-slate-800 whitespace-pre-line leading-relaxed italic bg-white p-4 rounded-xl border border-slate-100">
                            "{{ selectedDetailCase?.case_description }}"
                        </p>
                    </div>

                    <!-- Timeline Perkembangan & Lampiran Berkas Per Update -->
                    <div>
                        <h4 class="text-[11px] font-black text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg> Riwayat Kronologi Perkembangan &amp; Berkas Lampiran
                        </h4>

                        <div v-if="parseFiles(selectedDetailCase?.lampiran_berkas).length" class="space-y-4">
                            <div v-for="(paket, idx) in parseFiles(selectedDetailCase?.lampiran_berkas)" :key="idx" class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs relative">
                                <div class="flex justify-between items-center border-b border-slate-100 pb-2 mb-3">
                                    <span class="text-[10px] font-black text-indigo-700 uppercase flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-indigo-600"></span> UPDATE: {{ paket.tanggal }}
                                    </span>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase">Tahap {{ parseFiles(selectedDetailCase?.lampiran_berkas).length - idx }}</span>
                                </div>

                                <p class="text-xs text-slate-800 font-semibold mb-4 leading-relaxed bg-slate-50 p-3.5 rounded-xl border border-slate-100">
                                    "{{ paket.catatan }}"
                                </p>

                                <!-- List File Lampiran -->
                                <div v-if="paket.file_paths && paket.file_paths.length" class="space-y-2">
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Berkas Lampiran Tahap Ini:</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <div v-for="(fPath, fIdx) in paket.file_paths" :key="fIdx" class="flex items-center justify-between p-3 bg-red-50/70 border border-red-200/80 rounded-xl hover:bg-red-50 transition">
                                            <div class="flex items-center gap-2 min-w-0 pr-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                </svg>
                                                <span class="text-xs font-extrabold text-slate-800 truncate" :title="getFileName(fPath)">{{ getFileName(fPath) }}</span>
                                            </div>
                                            <div class="flex items-center gap-1.5 shrink-0">
                                                <button @click="viewFileDirect(fPath)" class="px-2.5 py-1 bg-red-600 hover:bg-red-700 text-white text-[9px] font-black rounded-lg uppercase tracking-wider transition shadow-xs">
                                                    Lihat
                                                </button>
                                                <a :href="'/storage/' + fPath" download class="p-1 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg transition" title="Unduh Berkas">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                    </svg>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="text-center p-8 bg-slate-50 rounded-2xl text-slate-400 text-xs italic">
                            Belum ada riwayat perkembangan berkas tercatat.
                        </div>
                    </div>

                    <!-- Dokumen Putusan Akhir -->
                    <div v-if="selectedDetailCase?.dokumen_putusan && parseFiles(selectedDetailCase?.dokumen_putusan).length">
                        <h4 class="text-[11px] font-black text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg> Dokumen Putusan Akhir
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div v-for="(pPath, pIdx) in parseFiles(selectedDetailCase?.dokumen_putusan)" :key="pIdx" class="flex items-center justify-between p-4 bg-slate-900 text-white rounded-2xl shadow-sm">
                                <div class="flex items-center gap-3 min-w-0 pr-2">
                                    <span class="px-2 py-0.5 bg-emerald-500 text-slate-950 font-black text-[9px] rounded uppercase">PUTUSAN {{ pIdx + 1 }}</span>
                                    <span class="text-xs font-extrabold truncate" :title="getFileName(pPath)">{{ getFileName(pPath) }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button @click="viewFileDirect(pPath)" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-[9px] font-black rounded-xl uppercase transition shadow-xs">
                                        Lihat
                                    </button>
                                    <a :href="'/storage/' + pPath" download class="p-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl transition" title="Unduh Putusan">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Footer Action Modal -->
                <div class="p-5 border-t bg-slate-50 flex flex-wrap justify-between items-center gap-3">
                    <div class="flex gap-2">
                        <button @click="openPreview(selectedDetailCase)" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-[10px] font-black uppercase rounded-xl transition flex items-center gap-1.5 shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg> Cetak PDF Ringkasan
                        </button>
                        <button @click="showDetailModal = false; openUpdateModal(selectedDetailCase)" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black uppercase rounded-xl transition flex items-center gap-1.5 shadow-sm">
                            + Update Perkembangan
                        </button>
                    </div>

                    <button @click="showDetailModal = false" class="px-6 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-[10px] font-black uppercase rounded-xl transition">
                        Tutup Rincian
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL FORM INPUT KASUS BARU -->
        <div v-if="showCreateModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col animate-in fade-in zoom-in duration-200 text-left">
                <div class="p-8 border-b bg-gray-50 flex justify-between items-center text-left">
                    <h3 class="font-black text-gray-800 uppercase text-sm tracking-widest">Registrasi Pelanggaran Personel</h3>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-red-500 text-2xl font-black">&times;</button>
                </div>
                
                <form @submit.prevent="submit" class="flex-1 overflow-y-auto p-8 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-left">
                        <input v-model="form.nama" type="text" placeholder="Nama Lengkap" class="rounded-2xl border-gray-100 bg-gray-50 text-xs font-bold h-12 uppercase" required>
                        <input v-model="form.nrp" type="text" placeholder="NRP" class="rounded-2xl border-gray-100 bg-gray-50 text-xs font-bold h-12 uppercase" required>
                        <input v-model="form.pangkat" type="text" placeholder="Pangkat" class="rounded-2xl border-gray-100 bg-gray-50 text-xs font-bold h-12 uppercase" required>
                        <input v-model="form.satuan" type="text" placeholder="Satuan Kerja (Satker)" class="rounded-2xl border-gray-100 bg-gray-50 text-xs font-bold h-12 uppercase" required>
                    </div>
                    <input v-model="form.jabatan" type="text" placeholder="Jabatan Saat Ini" class="w-full rounded-2xl border-gray-100 bg-gray-50 text-xs font-bold h-12 uppercase" required>
                    <textarea v-model="form.kasus" placeholder="Keterangan Kasus / Pasal yang Dilanggar..." class="w-full rounded-2xl border-gray-100 bg-gray-50 text-xs font-medium h-24" required></textarea>
                    
                    <div class="grid grid-cols-2 gap-4 text-left">
                        <div>
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2 block">TMT Kejadian</label>
                            <input v-model="form.tmt" type="date" class="w-full mt-1 rounded-2xl border-gray-100 bg-gray-50 text-xs font-bold h-12" required>
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2 block">Status Kasus Awal</label>
                            <select v-model="form.status" class="w-full mt-1 rounded-2xl border-gray-100 bg-gray-50 text-xs font-bold h-12 text-red-600">
                                <option value="PROSES">PROSES</option>
                                <option value="SIDANG">SIDANG</option>
                            </select>
                        </div>
                    </div>

                    <textarea v-model="form.perkembangan_kasus" placeholder="Catatan perkembangan awal..." class="w-full rounded-2xl border-gray-100 bg-gray-50 text-xs font-medium h-20"></textarea>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-red-50/50 p-6 rounded-[2rem] border border-red-100">
                        <div>
                            <label class="text-[10px] font-black text-red-600 uppercase mb-2 block">Lampiran Berkas Awal (Multi-File / Max 150MB Total)</label>
                            <input type="file" @change="form.lampiran = Array.from($event.target.files)" class="text-[10px] file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-black file:bg-red-600 file:text-white" multiple>
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-gray-700 uppercase mb-2 block text-wrap">Dokumen Putusan (Opsional - Multi)</label>
                            <input type="file" @change="form.putusan = Array.from($event.target.files)" class="text-[10px] file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-black file:bg-gray-800 file:text-white" multiple>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" @click="showCreateModal = false" class="px-8 py-3 text-[10px] font-black uppercase text-gray-400 hover:text-gray-600">Batal</button>
                        <button type="submit" :disabled="form.processing" class="bg-red-600 text-white px-10 py-3 rounded-2xl font-black uppercase text-[10px] tracking-widest shadow-lg shadow-red-100 hover:bg-red-700 transition-all disabled:opacity-50">
                            {{ form.processing ? 'Mengunci Kasus...' : 'Simpan Data Kasus' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL FORM UPDATE PERKEMBANGAN -->
        <div v-if="showUpdateModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm text-left">
            <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="p-6 border-b flex justify-between items-center bg-gray-50 text-left">
                    <div>
                        <h3 class="font-black text-gray-800 uppercase text-xs tracking-widest">Update Kronologi & Berkas Kasus</h3>
                        <p class="text-[10px] text-indigo-600 font-bold uppercase mt-1">{{ selectedCase?.name }} ({{ selectedCase?.nrp }})</p>
                    </div>
                    <button @click="showUpdateModal = false" class="text-gray-400 hover:text-red-500 font-black text-2xl">&times;</button>
                </div>
                
                <div class="p-8 overflow-y-auto flex-1 space-y-6">
                    <div class="space-y-3">
                        <label class="text-[9px] font-black text-gray-400 uppercase ml-2 block">Daftar Riwayat & Berkas Paket:</label>
                        <div v-for="(paket, idx) in parseFiles(selectedCase?.lampiran_berkas)" :key="idx" class="flex flex-col gap-2 bg-gray-50 p-4 rounded-2xl border border-gray-100 group transition-all hover:border-red-200">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-[8px] font-black text-indigo-600 uppercase block mb-1">{{ paket.tanggal }}</span>
                                    <p class="text-[10px] text-gray-600 font-semibold italic">"{{ paket.catatan }}"</p>
                                </div>
                                <button type="button" @click="deleteHistoryItem(paket.id)" class="p-2 text-gray-300 hover:text-red-600 transition-colors" title="Hapus Riwayat Ini">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                            
                            <div class="flex flex-wrap gap-1" v-if="paket.file_paths && paket.file_paths.length">
                                <button type="button" v-for="(fPath, fIdx) in paket.file_paths" :key="fIdx" @click="viewFileDirect(fPath)" class="bg-red-50 text-red-600 px-2 py-0.5 rounded text-[8px] border border-red-200 font-bold truncate max-w-[150px]">
                                     {{ getFileName(fPath) }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <form @submit.prevent="submitUpdate" class="pt-6 border-t space-y-4">
                        <div>
                            <label class="text-[9px] font-black text-indigo-600 uppercase ml-2 block">Catatan Perkembangan Baru</label>
                            <textarea v-model="updateForm.catatan_baru" placeholder="Tulis perkembangan terbaru di sini..." class="w-full mt-1 rounded-2xl border-gray-100 bg-gray-50 text-xs font-medium h-24 focus:ring-indigo-500" required></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-[9px] font-black text-indigo-600 uppercase ml-2 block">Status Terbaru</label>
                                <select v-model="updateForm.status" class="w-full mt-1 rounded-2xl border-gray-100 bg-gray-50 text-xs font-bold h-12">
                                    <option value="PROSES">PROSES</option>
                                    <option value="SIDANG">SIDANG</option>
                                    <option value="SELESAI">SELESAI</option>
                                    <option value="DINAS_KEMBALI">DINAS KEMBALI</option>
                                    <option value="PDTH">PDTH</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="p-4 bg-red-50/50 rounded-2xl border border-red-100 space-y-2">
                                <p class="text-[9px] font-black text-red-600 uppercase tracking-widest">Tambah Berkas Paket (Multi):</p>
                                <input type="file" @change="updateForm.lampiran = Array.from($event.target.files)" class="text-[9px] w-full file:bg-red-600 file:text-white file:rounded-full file:border-0 file:px-3 file:py-1" multiple>
                            </div>
                            <div class="p-4 bg-indigo-50/50 rounded-2xl border border-indigo-100 space-y-2">
                                <p class="text-[9px] font-black text-indigo-400 uppercase tracking-widest">Update Putusan Akhir (Multi):</p>
                                <input type="file" @change="updateForm.putusan = Array.from($event.target.files)" class="text-[9px] w-full file:bg-gray-800 file:text-white file:rounded-full file:border-0 file:px-3 file:py-1" multiple>
                            </div>
                        </div>
                        <button type="submit" :disabled="updateForm.processing" class="w-full py-3 bg-indigo-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg hover:bg-indigo-700 transition-all disabled:opacity-50">
                            {{ updateForm.processing ? 'Menyinkronkan...' : 'Simpan Perkembangan' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL PRATINJAU CETAK PDF -->
        <div v-if="showPreviewModal" class="fixed inset-0 z-[150] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md">
            <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-[90vw] w-[1150px] max-h-[90vh] flex flex-col overflow-hidden text-left">
                <div class="p-6 border-b flex justify-between items-center bg-gray-50">
                    <h3 class="font-black text-gray-800 uppercase text-xs tracking-widest">Pratinjau Laporan Riwayat Pelanggaran</h3>
                    <div class="flex gap-2">
                        <button @click="downloadPDF" :disabled="isPrinting" class="px-6 py-2 bg-indigo-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-700 transition-all flex items-center gap-2 shadow-lg disabled:opacity-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            {{ isPrinting ? 'Mencetak...' : 'Download PDF' }}
                        </button>
                        <button @click="showPreviewModal = false" class="px-4 py-2 bg-gray-200 text-gray-600 rounded-xl text-[10px] font-black uppercase hover:bg-red-500 hover:text-white transition-all">Tutup</button>
                    </div>
                </div>
                
                <div class="flex-1 overflow-auto p-12 bg-gray-200/50 flex justify-center text-left">
                    <div class="shadow-2xl origin-top scale-[0.8] md:scale-[0.9] lg:scale-100 text-left">
                        <div id="area-cetak-cases" class="p-10 bg-white text-black text-left" style="width: 1080px; font-family: Arial, sans-serif; min-height: 700px;">
                            <div class="uppercase mb-6 text-left text-[11px] font-bold border-b-2 border-black pb-1 w-fit">
                                <p>KOMANDO ARMADA II</p>
                                <p class="text-center">DETASEMEN INTELIJEN</p>
                            </div>

                            <div class="text-center mb-8 uppercase text-left">
                                <h3 class="font-bold underline text-xl text-left">DAFTAR RIWAYAT DAN PERKEMBANGAN KASUS PERSONEL</h3>
                                <p class="text-[11px] mt-1 italic font-normal text-center">Nomor: R/RINGKAS-KASUS/{{ new Date().getFullYear() }}</p>
                            </div>

                            <table class="w-full border-collapse border-[1.5px] border-black text-[11px] text-left">
                                <thead>
                                    <tr class="bg-gray-100 text-center font-bold">
                                        <th class="border border-black p-3 w-[18%] uppercase">Identitas Personel</th>
                                        <th class="border border-black p-3 w-[24%] uppercase">Uraian Pelanggaran</th>
                                        <th class="border border-black p-3 w-[43%] uppercase">Kronologi Perkembangan</th>
                                        <th class="border border-black p-3 w-[15%] uppercase">Status Akhir</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="border border-black p-4 align-top uppercase leading-relaxed text-left">
                                            <div class="mb-3 text-left">
                                                <span class="font-bold underline text-[9px]">Nama / NRP:</span><br>
                                                <span class="font-black">{{ printData?.name }} / {{ printData?.nrp }}</span>
                                            </div>
                                            <div class="mb-3 text-left">
                                                <span class="font-bold underline text-[9px]">Pangkat / Jabatan:</span><br>
                                                {{ printData?.rank }} / {{ printData?.position }}
                                            </div>
                                            <div class="text-left">
                                                <span class="font-bold underline text-[9px]">Satuan Kerja:</span><br>
                                                {{ printData?.unit }}
                                            </div>
                                        </td>
                                        <td class="border border-black p-4 align-top italic leading-relaxed text-justify">
                                            "{{ printData?.case_description }}"
                                            <div class="mt-6 not-italic font-bold border-t border-black pt-2 uppercase text-[9px] text-left"> TMT Kejadian: {{ printData?.incident_date?.split('T')[0] }}
                                            </div>
                                        </td>
                                        <td class="border border-black p-0 align-top text-left">
                                            <table class="w-full border-collapse text-left">
                                                <tr v-for="(paket, idx) in parseFiles(printData?.lampiran_berkas)" :key="idx" class="border-b border-gray-200 last:border-0">
                                                    <td class="p-2 font-mono text-[9px] leading-relaxed text-left">
                                                        <div class="font-bold text-indigo-600 text-left">[{{ paket.tanggal }}]</div>
                                                        <div class="text-gray-800 text-left">{{ paket.catatan }}</div>
                                                        <div v-if="paket.file_paths && paket.file_paths.length" class="text-[7px] text-red-500 font-bold mt-1 uppercase italic text-left">
                                                            * {{ paket.file_paths.length }} BERKAS AMAN DALAM SISTEM
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                        <td class="border border-black p-4 align-top text-center">
                                            <div class="text-[9px] font-bold underline mb-1 uppercase text-gray-400">Tindak Lanjut:</div>
                                            <div class="font-black text-xs uppercase">{{ printData?.status }}</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="mt-16 flex justify-end text-center text-[12px] text-left">
                                <div class="w-72">
                                    <p class="mb-20 uppercase text-left">Surabaya, {{ new Date().toLocaleDateString('id-ID', {day:'numeric', month:'long', year:'numeric'}) }}<br>A.n. Komandan Detasemen Intelijen<br>Petugas Administrasi,</p>
                                    <p class="font-bold underline leading-none uppercase text-left">{{ props.auth.user.name }}</p>
                                    <p class="mt-1 uppercase text-left">{{ props.auth.user.pangkat || 'PANGKAT' }} NRP. {{ props.auth.user.nrp || '-------' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL PRATINJAU IFRAME BERKAS DOKUMEN -->
        <div v-if="showDocumentPreviewModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/75 backdrop-blur-md">
            <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-5xl w-full h-[85vh] flex flex-col overflow-hidden animate-in fade-in duration-150">
                <div class="p-5 border-b bg-gray-900 text-white flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <h4 class="font-black text-[11px] uppercase tracking-widest text-slate-200">Pratinjau Berkas Dokumen Intelijen (Si Sinden Engine)</h4>
                    </div>
                    <button @click="showDocumentPreviewModal = false; currentPreviewUrl = ''" class="px-4 py-1.5 bg-red-600 hover:bg-red-700 text-white font-black rounded-xl text-[10px] uppercase tracking-wider transition-all">Tutup</button>
                </div>
                <div class="flex-1 bg-slate-700 p-2">
                    <iframe :src="currentPreviewUrl" class="w-full h-full rounded-2xl border-0 bg-white" allow="autoplay"></iframe>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;  
    overflow: hidden;
}

#area-cetak-cases {
    background-color: white !important;
}

#area-cetak-cases table td {
    word-wrap: break-word;
    word-break: break-word;
}
.animate-in {
    animation: fadeIn 0.2s ease-out forwards;
}
@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.98); }
    to { opacity: 1; transform: scale(1); }
}
</style>