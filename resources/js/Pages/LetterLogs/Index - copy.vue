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

// State untuk Filter Laporan (PERBAIKAN: Tambah filter kategori dan tanggal periode)
const filterCategoryId = ref('');
const search = ref('');
const startDate = ref(''); // Filter Tanggal Mulai
const endDate = ref('');   // Filter Tanggal Selesai

const form = useForm({
    priority: 'B',
    category_id: '',
    sub_category_id: '', // Menampung pilihan dari Manajemen Kategori
    subject: '',
    recipient: '', 
    date: new Date().toISOString().substr(0, 10),
    sequence: '',
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

// LOGIKA PENOMORAN: Menangani Suffix Kode (P/D) dan Format Khusus R/Sprin
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

// PERBAIKAN: Logika Filter Laporan (Sangat Akurat untuk Filter Kategori & Periode Tanggal)
const filteredForReport = computed(() => {
    if (!props.logs?.data) return [];
    return props.logs.data.filter(log => {
        // 1. Filter Kategori
        const matchesCategory = !filterCategoryId.value || log.category_id == filterCategoryId.value;

        // 2. Filter Pencarian Teks
        const matchesSearch = !search.value || 
            log.subject?.toLowerCase().includes(search.value.toLowerCase()) ||
            log.recipient?.toLowerCase().includes(search.value.toLowerCase()) ||
            log.full_number?.toLowerCase().includes(search.value.toLowerCase());

        // 3. PERBAIKAN VITAL: Filter Range Tanggal (Menggunakan perbandingan String YYYY-MM-DD agar presisi)
        let matchesDate = true;
        
        // Ambil bagian tanggal saja (YYYY-MM-DD) dari log.date
        const logDateOnly = log.date ? log.date.split(' ')[0] : '';
        
        if (startDate.value) {
            matchesDate = matchesDate && logDateOnly >= startDate.value;
        }
        if (endDate.value) {
            matchesDate = matchesDate && logDateOnly <= endDate.value;
        }

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

const downloadPDF = () => {
    const element = document.getElementById('area-laporan-cetak');
    const fileNameSuffix = filterCategoryId.value ? selectedCategoryName.value.replace(/\s+/g, '_') : 'SEMUA';
    const opt = {
        margin:       [0.5, 0.5, 0.5, 0.5],
        filename:      `LAPORAN_${fileNameSuffix}_${new Date().getTime()}.pdf`,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, width: 800, windowWidth: 800 },
        jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
};

const formatLongDate = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
};

watch(() => form.category_id, async (newId) => {
    form.sub_category_id = ''; // Reset saat kategori ganti
    if (newId) {
        isCalculating.value = true;
        try {
            const response = await axios.get(route('letter-logs.next-number', { category_id: newId }));
            form.sequence = response.data.sequence;
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
            form.reset('subject', 'recipient', 'sub_category_id');
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
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 print:hidden">
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">Penomoran Surat</h2>
                
                <div class="flex flex-wrap items-center gap-2 w-full md:w-auto bg-gray-50 p-2 rounded-2xl border border-gray-100">
                    <div class="flex flex-col">
                        <label class="text-[8px] font-black text-gray-400 ml-1 mb-1">KATEGORI</label>
                        <select v-model="filterCategoryId" class="bg-white border-gray-200 text-[10px] font-black uppercase tracking-widest rounded-xl focus:ring-indigo-500 py-2">
                            <option value="">Semua Kategori</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                    </div>

                    <div class="flex flex-col">
                        <label class="text-[8px] font-black text-gray-400 ml-1 mb-1">DARI TANGGAL</label>
                        <input type="date" v-model="startDate" class="bg-white border-gray-200 text-[10px] font-bold rounded-xl focus:ring-indigo-500 py-2">
                    </div>

                    <div class="flex flex-col">
                        <label class="text-[8px] font-black text-gray-400 ml-1 mb-1">SAMPAI TANGGAL</label>
                        <input type="date" v-model="endDate" class="bg-white border-gray-200 text-[10px] font-bold rounded-xl focus:ring-indigo-500 py-2">
                    </div>

                    <div class="flex items-end h-full gap-2 mt-3 md:mt-0">
                        <button @click="openPreview" class="px-5 py-2.5 bg-indigo-100 text-indigo-700 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-sm hover:bg-indigo-200 transition-all"> Pratinjau
                        </button>
                        <button @click="downloadPDF" class="px-5 py-2.5 bg-gray-900 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg hover:bg-indigo-600 transition-all"> Unduh PDF
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-8 px-4 md:px-0 print:hidden">
            <input type="file" ref="fileInput" class="hidden" accept="application/pdf" @change="handleFileUpload">
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="space-y-6">
                    <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100">
                        <h3 class="font-black text-indigo-900 uppercase text-xs tracking-widest mb-6">Booking Nomor</h3>
                        <form @submit.prevent="submit" class="space-y-4">
                            <div>
                                <label class="text-[9px] font-black text-gray-400 uppercase ml-1">Sifat Surat</label>
                                <select v-model="form.priority" class="w-full mt-1 rounded-2xl border-gray-100 bg-gray-50 h-12 text-xs font-bold focus:ring-indigo-500">
                                    <option v-for="(label, code) in priorities" :key="code" :value="code">{{ code }} - {{ label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-gray-400 uppercase ml-1">Kategori Surat</label>
                                <select v-model="form.category_id" class="w-full mt-1 rounded-2xl border-gray-100 bg-gray-50 h-12 text-xs font-bold focus:ring-indigo-500">
                                    <option value="" disabled>Pilih Kategori...</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                </select>
                            </div>

                            <div v-if="filteredSubCategories.length > 0">
                                <label class="text-[9px] font-black text-indigo-400 uppercase ml-1 animate-pulse">Pilih Jenis Surat (Sub-Kategori)</label>
                                <select v-model="form.sub_category_id" class="w-full mt-1 rounded-2xl border-indigo-100 bg-indigo-50/30 h-12 text-xs font-bold focus:ring-indigo-500">
                                    <option value="">-- Pilih Jenis --</option>
                                    <option v-for="sub in filteredSubCategories" :key="sub.id" :value="sub.id">
                                        {{ sub.name }} ({{ sub.sub_code || sub.code }})
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="text-[9px] font-black text-gray-400 uppercase ml-1">Perihal</label>
                                <textarea v-model="form.subject" placeholder="Isi perihal surat..." class="w-full mt-1 rounded-2xl border-gray-100 bg-gray-50 h-20 text-xs font-medium italic focus:ring-indigo-500"></textarea>
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-gray-400 uppercase ml-1">Tujuan / Alamat</label>
                                <textarea v-model="form.recipient" placeholder="Contoh: Danlantamal V Surabaya..." class="w-full mt-1 rounded-2xl border-gray-100 bg-gray-50 h-24 text-xs font-medium focus:ring-indigo-500"></textarea>
                            </div>
                            
                            <div class="p-5 bg-indigo-50 rounded-2xl border border-indigo-100">
                                <p class="text-[9px] font-black text-indigo-400 uppercase mb-1">Pratinjau Nomor:</p>
                                <div class="font-mono text-indigo-700 text-sm font-black tracking-tighter">
                                    {{ isCalculating ? 'Sedang Menghitung...' : previewNumber }}
                                </div>
                            </div>

                            <button :disabled="form.processing || !form.category_id" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-4 rounded-2xl font-black uppercase text-[10px] tracking-widest transition-all shadow-lg active:scale-95 disabled:opacity-50 text-center"> Booking Nomor Sekarang
                            </button>
                        </form>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-100 overflow-hidden">
                        <div class="p-6 md:p-8 border-b border-gray-50 flex flex-col md:flex-row justify-between items-center bg-gray-50/30 gap-4">
                            <h3 class="font-black text-gray-400 uppercase text-[10px] tracking-widest">Log Penomoran Terakhir</h3>
                            <input v-model="search" type="text" placeholder="Cari nomor/perihal..." class="rounded-full border-gray-100 bg-white text-[10px] px-6 py-2.5 w-full md:w-64 focus:ring-indigo-500">
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-gray-50/80 uppercase">
                                    <tr>
                                        <th class="px-8 py-5 text-[9px] font-black text-gray-400 tracking-widest border-b border-gray-100">Detail Surat</th>
                                        <th class="px-8 py-5 text-[9px] font-black text-gray-400 text-right tracking-widest border-b border-gray-100">Status/Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <tr v-for="log in filteredForReport" :key="log.id" class="hover:bg-indigo-50/10 transition group">
                                        <td class="px-8 py-5 leading-tight">
                                            <div class="font-mono text-[13px] font-black text-indigo-900 mb-1 italic">{{ log.full_number }}</div>
                                            <div class="text-[10px] text-gray-500 font-medium mb-1">Subjek: "{{ log.subject }}"</div>
                                            <div class="text-[9px] text-indigo-500 font-black uppercase tracking-tighter">Kepada: {{ log.recipient || '-' }}</div>
                                            <div class="text-[8px] text-gray-300 font-bold uppercase mt-1">Dibuat: {{ log.date }}</div>
                                        </td>
                                        <td class="px-8 py-5 text-right">
                                            <button v-if="!log.is_archived" @click="triggerUpload(log.id)" class="bg-white border border-indigo-200 text-indigo-600 px-4 py-2 rounded-xl text-[9px] font-black uppercase hover:bg-indigo-600 hover:text-white transition-all">Upload PDF</button>
                                            <div v-else class="flex flex-col items-end">
                                                <span class="text-emerald-500 font-black text-[10px] uppercase italic"> Terarsip</span>
                                                <span class="text-[8px] text-gray-400 font-bold uppercase mt-1 tracking-widest">Akses Digital Aktif</span>
                                            </div>
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
            <div id="area-laporan-cetak" class="p-10 bg-white text-black" style="width: 790px; font-family: Arial, sans-serif;">
                <div class="inline-table uppercase mb-10 text-left text-base font-bold">
                    <p class="leading-none tracking-tight">KOMANDO ARMADA II</p>
                    <p class="leading-none tracking-tight text-center mt-1">DETASEMEN INTELIJEN</p>
                    <div class="border-b-[2px] border-black mt-2 w-full"></div>
                </div>

                <div class="text-center mb-8 uppercase text-base">
                    <h3 class="font-bold underline tracking-widest leading-none">LAPORAN REKAPITULASI PENOMORAN SURAT</h3>
                    <p class="font-bold mt-2 text-xs">KATEGORI: {{ selectedCategoryName }}</p>
                    <p class="font-normal mt-1 text-sm">Periode: {{ startDate ? formatLongDate(startDate) : 'Awal' }} s/d {{ endDate ? formatLongDate(endDate) : 'Sekarang' }}</p>
                </div>

                <p class="text-sm mb-4 italic font-bold">1. Daftar rincian penomoran surat yang telah terbit melalui sistem digital:</p>

                <table class="w-full border-collapse border-[1.5px] border-black text-[9px]">
                    <thead>
                        <tr class="bg-gray-100 uppercase font-bold text-center">
                            <th class="border border-black px-2 py-3 w-[5%]">NO</th>
                            <th class="border border-black px-3 py-3 text-left w-[25%]">NOMOR SURAT</th>
                            <th class="border border-black px-3 py-3 text-left w-[20%]">ALAMAT TUJUAN</th>
                            <th class="border border-black px-3 py-3 text-left w-[25%]">PERIHAL</th>
                            <th class="border border-black px-3 py-3 w-[12%]">TANGGAL</th>
                            <th class="border border-black px-3 py-3 w-[13%]">STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(log, index) in filteredForReport" :key="log.id">
                            <td class="border border-black px-2 py-2 text-center">{{ index + 1 }}</td>
                            <td class="border border-black px-3 py-2 font-bold uppercase">{{ log.full_number }}</td>
                            <td class="border border-black px-3 py-2 uppercase leading-tight">{{ log.recipient || '-' }}</td>
                            <td class="border border-black px-3 py-2 italic leading-tight">"{{ log.subject }}"</td>
                            <td class="border border-black px-3 py-2 text-center">{{ log.date }}</td>
                            <td class="border border-black px-3 py-2 text-center font-bold">{{ log.is_archived ? 'TERARSIP' : 'PENDING' }}</td>
                        </tr>
                        <tr v-if="filteredForReport.length === 0">
                            <td colspan="6" class="border border-black px-3 py-10 text-center font-bold italic text-gray-400">Tidak ada data untuk periode terpilih.</td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-6 text-sm font-bold uppercase border-l-[3px] border-black pl-4 py-2 bg-gray-50">
                    <p>TOTAL DATA DISARING: {{ totalData }} BERKAS</p>
                    <p>TOTAL BERKAS TERARSIP: {{ totalTerarsip }} BERKAS</p>
                </div>
                
                    <div class="mt-16 flex justify-end text-sm">
                    <div class="w-80 text-left"> <p class="mb-1"><br> Dicetak melalui Sinden di Surabaya <br>Pada tanggal  {{ formatLongDate(new Date()) }}</p>
                        
                        <div class="inline-block text-center">
                            
                            <div class="border-t border-black w-full mb-2"></div>
                            
                            <p class="mb-20"> a.n. Komandan Detasemen Intelijen<br> Petugas Administrasi,
                            </p>
                            
                            <p class="font-bold underline leading-none">{{ user.name }}</p>
                            <p class="font-normal mt-1">{{ user.pangkat || 'Pangkat Belum Diisi' }}</p>
                            <p class="font-normal mt-0.5">{{ user.nrp ? 'NRP. ' + user.nrp : 'NRP Belum Diisi' }}</p>
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
                        <h3 class="font-black text-gray-800 uppercase text-xs tracking-widest">Digital Report Preview ({{ selectedCategoryName }})</h3>
                    </div>
                    <button @click="showPreview = false" class="text-gray-400 hover:text-red-500 font-black text-2xl">&times;</button>
                </div>
                
                <div class="flex-1 overflow-y-auto bg-gray-700 p-4 md:p-10 flex justify-center items-start">
                    <img :src="previewImage" class="shadow-2xl border border-gray-400 bg-white max-w-full md:max-w-[800px] h-auto" />
                </div>

                <div class="p-6 border-t bg-gray-50 flex justify-end gap-3">
                    <button @click="showPreview = false" class="px-6 py-2.5 text-[10px] font-black uppercase text-gray-500">Batal</button>
                    <button @click="downloadPDF(); showPreview = false" class="px-8 py-2.5 bg-indigo-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg">Download Sekarang</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
#area-laporan-cetak table {
    border-spacing: 0;
}


</style>