<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, Head, router, Link, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import html2pdf from 'html2pdf.js';

const props = defineProps({
    categories: Array,
    sub_categories: Array, // Data dari Manajemen Kategori (Model LetterSubCategory)
    logs: Object,
    priorities: Object
});

const user = computed(() => usePage().props.auth.user);

// State untuk Popup Pratinjau
const showPreview = ref(false);
const previewImage = ref(null);

// State untuk Filter Laporan
const filterCategoryId = ref('');
const search = ref('');
const startDate = ref(''); 
const endDate = ref('');   

const getTodayLocalDate = () => {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const form = useForm({
    priority: 'B',
    category_id: '',
    sub_category_id: '', 
    subject: '',
    recipient: '', 
    date: getTodayLocalDate(),
    sequence: '', // PENTING: Sudah mendukung String/Huruf
});

const isUploading = ref(false);
const activeLogId = ref(null);
const fileInput = ref(null);
const isCalculating = ref(false);

const toRoman = (month) => {
    return ["I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X", "XI", "XII"][month - 1];
};

// Filter Sub-Kategori berdasarkan Category_id yang dipilih di Form Booking
const filteredSubCategories = computed(() => {
    if (!form.category_id || !props.sub_categories) return [];
    return props.sub_categories.filter(sub => sub.category_id == form.category_id);
});

// LOGIKA PENOMORAN: Menampilkan preview nomor secara real-time
const previewNumber = computed(() => {
    if (!form.category_id || !form.date) return 'Pilih Kategori...';
    
    const category = props.categories.find(c => c.id === form.category_id);
    if (!category) return '...';
    
    const dateObj = new Date(form.date);
    const romanMonth = toRoman(dateObj.getMonth() + 1);
    const formattedSeq = String(form.sequence || '0');
    
    const cleanCode = category.code.split('-')[0];
    
    if (cleanCode === 'R' || cleanCode === 'Sprin') {
        return `${cleanCode} / ${formattedSeq} / ${romanMonth} / ${dateObj.getFullYear()}`;
    }
    
    return `${form.priority} / ${formattedSeq} / ${cleanCode} / ${romanMonth} / ${dateObj.getFullYear()}`;
});

// Logika Filter Laporan (DIKALIBRASI: Menggunakan perbandingan == dan menyisir logs.data)
const filteredForReport = computed(() => {
    const rawData = props.logs?.data || [];
    return rawData.filter(log => {
        // Filter Kategori (Gunakan == agar string vs int tetap sinkron)
        const matchesCategory = !filterCategoryId.value || log.category_id == filterCategoryId.value;
        
        // Filter Pencarian
        const matchesSearch = !search.value || 
            log.subject?.toLowerCase().includes(search.value.toLowerCase()) ||
            log.recipient?.toLowerCase().includes(search.value.toLowerCase()) ||
            log.full_number?.toLowerCase().includes(search.value.toLowerCase());

        // Filter Tanggal
        let matchesDate = true;
        const logDateOnly = log.date ? (typeof log.date === 'string' ? log.date.split(' ')[0] : new Date(log.date).toISOString().split('T')[0]) : '';
        
        if (startDate.value) matchesDate = matchesDate && logDateOnly >= startDate.value;
        if (endDate.value) matchesDate = matchesDate && logDateOnly <= endDate.value;

        return matchesCategory && matchesSearch && matchesDate;
    });
});

const selectedCategoryName = computed(() => {
    if (!filterCategoryId.value) return 'SEMUA KATEGORI';
    const cat = props.categories.find(c => c.id == filterCategoryId.value);
    return cat ? cat.name.toUpperCase() : 'SEMUA KATEGORI';
});

const totalData = computed(() => filteredForReport.value.length);
const totalTerarsip = computed(() => filteredForReport.value.filter(l => l.is_archived).length);

// Fungsi Helper untuk membersihkan tanggal ISO di pratinjau
const formatCleanDate = (dateStr) => {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('id-ID', { year: 'numeric', month: '2-digit', day: '2-digit' });
};

// FUNGSI PRATINJAU (Membangun gambar untuk popup)
const openPreview = () => {
    const element = document.getElementById('area-laporan-cetak');
    const opt = {
        margin: [0.5, 0.5, 0.5, 0.5],
        html2canvas: { scale: 2, useCORS: true, width: 800 },
    };

    html2pdf().set(opt).from(element).outputImg().then((img) => {
        previewImage.value = img.src;
        showPreview.value = true;
    });
};

// FUNGSI DOWNLOAD ASLI (DARI SERVER BLADE)
const downloadPDF = () => {
    const url = route('letter-logs.pdf', {
        category_id: filterCategoryId.value,
        start_date: startDate.value,
        end_date: endDate.value
    });
    window.open(url, '_blank');
};

const formatLongDate = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
};

watch(() => form.category_id, async (newId) => {
    form.sub_category_id = ''; 
    if (newId) {
        isCalculating.value = true;
        try {
            const response = await axios.get(route('letter-logs.next-number', { category_id: newId }));
            form.sequence = String(response.data.sequence); 
        } finally {
            isCalculating.value = false;
        }
    }
});

watch(() => form.sub_category_id, (newSubId) => {
    if (newSubId) {
        const sub = props.sub_categories.find(s => s.id === newSubId);
        if (sub && !form.subject.startsWith(sub.name.toUpperCase())) {
            form.subject = `${sub.name.toUpperCase()}: ${form.subject}`;
        }
    }
});

const submit = () => {
    form.post(route('letter-logs.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('subject', 'recipient', 'sub_category_id', 'sequence');
            alert('Nomor surat berhasil di-booking!');
        }
    });
};

const triggerUpload = (id) => {
    activeLogId.value = id;
    fileInput.value.click(); 
};

const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (!file || file.type !== 'application/pdf') return alert('Pilih file PDF');
    router.post(route('letters.store-direct'), {
        letter_log_id: activeLogId.value,
        file: file
    }, {
        onBefore: () => isUploading.value = true,
        onFinish: () => { isUploading.value = false; event.target.value = ''; },
        onSuccess: () => alert('Berhasil diarsipkan!')
    });
};
</script>

<template>
    <Head title="Penomoran Surat" />
    <AuthenticatedLayout>
        <div class="space-y-6 font-sans print:hidden">
            
            <!-- Page Header Card -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="font-extrabold text-xl text-slate-900 uppercase tracking-tight">Penomoran Agenda Surat</h2>
                    <p class="text-xs text-slate-500 font-semibold mt-0.5">Sistem Pengagendaan & Booking Nomor Surat SINDEN</p>
                </div>
                
                <div class="flex flex-wrap items-center gap-3 w-full md:w-auto bg-slate-50 p-3 rounded-2xl border border-slate-200">
                    <div class="flex flex-col">
                        <label class="text-[9px] font-extrabold text-slate-400 ml-1 mb-1 uppercase">Kategori</label>
                        <select v-model="filterCategoryId" class="bg-white border-slate-200 text-xs font-extrabold uppercase tracking-wider rounded-xl py-2 px-3 focus:ring-blue-500">
                            <option value="">Semua Kategori</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                    </div>

                    <div class="flex flex-col">
                        <label class="text-[9px] font-extrabold text-slate-400 ml-1 mb-1 uppercase">Dari</label>
                        <input type="date" v-model="startDate" class="bg-white border-slate-200 text-xs font-bold rounded-xl py-2 px-3 focus:ring-blue-500">
                    </div>

                    <div class="flex flex-col">
                        <label class="text-[9px] font-extrabold text-slate-400 ml-1 mb-1 uppercase">Sampai</label>
                        <input type="date" v-model="endDate" class="bg-white border-slate-200 text-xs font-bold rounded-xl py-2 px-3 focus:ring-blue-500">
                    </div>

                    <div class="flex flex-wrap items-center h-full gap-2 mt-4 md:mt-0">
                        <button @click="openPreview" class="px-4 py-2.5 bg-blue-50 text-blue-600 rounded-xl text-xs font-extrabold uppercase tracking-wider hover:bg-blue-600 hover:text-white transition"> Pratinjau
                        </button>
                        <button @click="downloadPDF" class="px-4 py-2.5 bg-slate-900 text-white rounded-xl text-xs font-extrabold uppercase tracking-wider shadow-sm hover:bg-blue-600 transition"> Unduh PDF
                        </button>
                    </div>
                </div>
            </div>

            <input type="file" ref="fileInput" class="hidden" accept="application/pdf" @change="handleFileUpload">
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Form Booking Nomor Card -->
                <div class="space-y-6">
                    <div class="bg-white p-4 sm:p-6 lg:p-8 rounded-2xl sm:rounded-3xl shadow-xs border border-[#E2E8F0]">
                        <h3 class="font-extrabold text-slate-900 uppercase text-xs tracking-wider mb-6 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-blue-600"></span> Booking Nomor Baru
                        </h3>
                        <form @submit.prevent="submit" class="space-y-4">
                            <div>
                                <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider ms-1">Sifat Surat</label>
                                <select v-model="form.priority" class="w-full mt-1 rounded-2xl border-slate-200 bg-slate-50 py-3 text-xs font-bold focus:ring-blue-500 focus:border-blue-600">
                                    <option v-for="(label, code) in priorities" :key="code" :value="code">{{ code }} - {{ label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider ms-1">Kategori Surat</label>
                                <select v-model="form.category_id" class="w-full mt-1 rounded-2xl border-slate-200 bg-slate-50 py-3 text-xs font-bold focus:ring-blue-500 focus:border-blue-600">
                                    <option value="" disabled>Pilih Kategori...</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                </select>
                            </div>

                            <div v-if="filteredSubCategories.length > 0">
                                <label class="text-[10px] font-extrabold text-blue-600 uppercase ms-1 animate-pulse">Pilih Jenis Surat</label>
                                <select v-model="form.sub_category_id" class="w-full mt-1 rounded-2xl border-blue-200 bg-blue-50/50 py-3 text-xs font-bold focus:ring-blue-500 focus:border-blue-600">
                                    <option value="">-- Pilih Jenis --</option>
                                    <option v-for="sub in filteredSubCategories" :key="sub.id" :value="sub.id">{{ sub.name }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider ms-1">Nomor Urut (Huruf diperbolehkan)</label>
                                <input type="text" v-model="form.sequence" class="w-full mt-1 rounded-2xl border-slate-200 bg-slate-50 py-3 text-xs font-black uppercase px-4 focus:ring-blue-500 focus:border-blue-600" placeholder="Contoh: 14a" required />
                            </div>

                            <div>
                                <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider ms-1">Perihal</label>
                                <textarea v-model="form.subject" placeholder="..." class="w-full mt-1 rounded-2xl border-slate-200 bg-slate-50 h-20 text-xs font-medium px-4 py-2.5 focus:ring-blue-500 focus:border-blue-600"></textarea>
                            </div>
                            <div>
                                <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider ms-1">Tujuan / Alamat</label>
                                <textarea v-model="form.recipient" placeholder="..." class="w-full mt-1 rounded-2xl border-slate-200 bg-slate-50 h-24 text-xs font-medium px-4 py-2.5 focus:ring-blue-500 focus:border-blue-600"></textarea>
                            </div>
                            
                            <div class="p-4 bg-blue-50/60 rounded-2xl border border-blue-100">
                                <p class="text-[9px] font-extrabold text-blue-600 uppercase mb-1">Radar Preview Nomor:</p>
                                <div class="font-mono text-blue-800 text-sm font-black tracking-tight">
                                    {{ isCalculating ? 'Calculating...' : previewNumber }}
                                </div>
                            </div>

                            <button :disabled="form.processing || !form.category_id" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3.5 rounded-2xl font-extrabold uppercase text-xs tracking-wider shadow-md shadow-blue-500/20 active:scale-95 disabled:opacity-50 transition"> Booking Nomor Sekarang
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Table Log Agenda Card -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-3xl shadow-xs border border-[#E2E8F0] overflow-hidden">
                        <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center bg-slate-50/50 gap-4">
                            <h3 class="font-extrabold text-slate-700 uppercase text-xs tracking-wider">Log Penomoran Terakhir</h3>
                            <input v-model="search" type="text" placeholder="Cari nomor/perihal..." class="rounded-2xl border-slate-200 bg-white text-xs font-bold px-5 py-2.5 w-full md:w-64 focus:ring-blue-500 focus:border-blue-600">
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-gray-50/80 uppercase text-[9px] font-black text-gray-400">
                                    <tr>
                                        <th class="px-8 py-5 tracking-widest border-b border-gray-100">Detail Surat</th>
                                        <th class="px-8 py-5 text-right tracking-widest border-b border-gray-100">Status/Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <tr v-for="log in filteredForReport" :key="log.id" class="hover:bg-indigo-50/10 transition group">
                                        <td class="px-8 py-5">
                                            <div class="font-mono text-[13px] font-black text-indigo-900 mb-1 italic">{{ log.full_number }}</div>
                                            <div class="text-[10px] text-gray-500 font-medium mb-1">Subjek: "{{ log.subject }}"</div>
                                            <div class="text-[9px] text-indigo-500 font-black uppercase">Kepada: {{ log.recipient || '-' }}</div>
                                        </td>
                                        <td class="px-8 py-5 text-right">
                                            <button v-if="!log.is_archived" @click="triggerUpload(log.id)" class="bg-white border border-indigo-200 text-indigo-600 px-4 py-2 rounded-xl text-[9px] font-black uppercase hover:bg-indigo-600 hover:text-white transition-all">Upload PDF</button>
                                            <span v-else class="text-emerald-500 font-black text-[10px] uppercase italic"> Terarsip</span>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredForReport.length === 0">
                                        <td colspan="2" class="px-8 py-10 text-center text-gray-400 font-bold italic uppercase text-xs">Data tidak ditemukan pada periode ini.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="position: absolute; left: -9999px;">
            <div id="area-laporan-cetak" class="p-10 bg-white text-black" style="width: 790px; font-family: 'Times New Roman', serif;">
                
                <div style="text-align: left; margin-bottom: 30px;">
                    <div style="display: inline-block; text-align: center;">
                        <p style="margin: 0; font-size: 14px; font-weight: bold; line-height: 1.2; text-transform: uppercase;">KOMANDO ARMADA II</p>
                        <p style="margin: 0; font-size: 14px; font-weight: bold; line-height: 1.2; text-transform: uppercase;">DETASEMEN INTELIJEN</p>
                        <div style="margin-top: 5px; border-top: 1px solid #000; border-bottom: 3.5px solid #000; height: 2px; width: 320px; margin-left: auto; margin-right: auto;"></div>
                    </div>
                </div>

                <div class="text-center mb-8 uppercase">
                    <h3 class="font-bold underline tracking-widest text-base">LAPORAN REKAPITULASI PENOMORAN SURAT</h3>
                    <p class="font-bold mt-2 text-xs">KATEGORI: {{ selectedCategoryName }}</p>
                    <p class="font-normal mt-1 text-sm">Periode: {{ startDate ? formatLongDate(startDate) : 'Awal' }} s/d {{ endDate ? formatLongDate(endDate) : 'Sekarang' }}</p>
                </div>

                <table class="w-full table-fixed border-collapse border-[1.5px] border-black text-[9px]" style="table-layout: fixed; width: 100%;">
                    <thead>
                        <tr class="bg-gray-100 uppercase font-bold text-center">
                            <th class="border border-black px-1 py-3" style="width: 30px;">NO</th>
                            <th class="border border-black px-2 py-3 text-left" style="width: 140px;">NOMOR SURAT</th>
                            <th class="border border-black px-2 py-3 text-left" style="width: 130px;">ALAMAT TUJUAN</th>
                            <th class="border border-black px-2 py-3 text-left">PERIHAL</th>
                            <th class="border border-black px-1 py-3" style="width: 75px;">TANGGAL</th>
                            <th class="border border-black px-1 py-3" style="width: 75px;">STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(log, index) in filteredForReport" :key="log.id">
                            <td class="border border-black px-1 py-2 text-center" style="vertical-align: top;">{{ index + 1 }}</td>
                            <td class="border border-black px-2 py-2 font-bold uppercase" style="vertical-align: top; word-break: break-all; white-space: normal;">{{ log.full_number }}</td>
                            <td class="border border-black px-2 py-2 uppercase" style="vertical-align: top; word-wrap: break-word; white-space: normal;">{{ log.recipient || '-' }}</td>
                            <td class="border border-black px-2 py-2 italic" style="vertical-align: top; word-wrap: break-word; white-space: normal; line-height: 1.3;">"{{ log.subject }}"</td>
                            <td class="border border-black px-1 py-2 text-center" style="vertical-align: top;">
                                {{ formatCleanDate(log.date) }}
                            </td>
                            <td class="border border-black px-1 py-2 text-center font-bold" style="vertical-align: top;">{{ log.is_archived ? 'TERARSIP' : 'PENDING' }}</td>
                        </tr>
                        <tr v-if="filteredForReport.length === 0">
                            <td colspan="6" class="border border-black px-3 py-10 text-center font-bold italic text-gray-400">TIDAK ADA DATA UNTUK PERIODE TERPILIH.</td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-6 text-sm font-bold uppercase border-l-[4px] border-black pl-4 py-2 bg-gray-50">
                    <p>TOTAL DATA DISARING: {{ totalData }} BERKAS</p>
                    <p>TOTAL BERKAS TERARSIP: {{ totalTerarsip }} BERKAS</p>
                </div>
                
                <div class="mt-16 flex justify-end">
                    <div class="w-80 text-left text-sm"> 
                        <p class="mb-1">Dicetak melalui Sinden di Surabaya</p>
                        <p class="mb-4">Pada tanggal {{ formatLongDate(new Date()) }}</p>
                        <div class="text-center">
                            <p class="font-bold">a.n. Komandan Detasemen Intelijen</p>
                            <p class="mb-20 font-bold">Petugas Administrasi,</p>
                            <p class="font-bold underline uppercase">{{ user.name }}</p>
                            <p class="font-normal">{{ user.pangkat || 'Letnan Dua' }}</p>
                            <p class="font-normal">NRP. {{ user.nrp || '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showPreview" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md">
            <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-5xl w-full h-[90vh] overflow-hidden flex flex-col">
                <div class="p-6 border-b flex justify-between items-center bg-gray-50">
                    <div class="flex items-center gap-3">
                        <div class="h-8 w-8 bg-indigo-600 rounded-full flex items-center justify-center text-white text-xs font-black italic">PDF</div>
                        <h3 class="font-black text-gray-800 uppercase text-xs tracking-widest">Digital Report Preview</h3>
                    </div>
                    <button @click="showPreview = false" class="text-gray-400 hover:text-red-500 font-black text-2xl">&times;</button>
                </div>
                <div class="flex-1 overflow-y-auto bg-gray-700 p-10 flex justify-center items-start">
                    <img :src="previewImage" class="shadow-2xl border bg-white max-w-full md:max-w-[800px] h-auto" />
                </div>
                <div class="p-6 border-t bg-gray-50 flex justify-end gap-3 font-bold">
                    <button @click="showPreview = false" class="px-6 py-2.5 text-[10px] uppercase text-gray-500">Batal</button>
                    <button @click="downloadPDF(); showPreview = false" class="px-8 py-2.5 bg-indigo-600 text-white rounded-xl text-[10px] uppercase tracking-widest shadow-lg active:scale-95 transition-all">Download Sekarang</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
#area-laporan-cetak {
    word-break: break-word !important;
}

#area-laporan-cetak table {
    border-collapse: collapse !important;
    width: 100% !important;
}

#area-laporan-cetak td {
    overflow-wrap: break-word !important;
    word-wrap: break-word !important;
    white-space: normal !important;
}
</style>