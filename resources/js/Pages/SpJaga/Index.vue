<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    mySchedule: Object,
    spJagas: Object,
    pendingSignatures: Array,
    isPasops: Boolean,
    isAdmin: Boolean,
});

const activeTab = ref(props.isPasops && props.pendingSignatures?.length > 0 ? 'pending' : (props.mySchedule ? 'my_schedule' : 'all'));

// Modal Upload TTD Basah Manual
const isUploadModalOpen = ref(false);
const selectedSpForUpload = ref(null);
const uploadForm = useForm({
    signed_file: null,
});

const openUploadModal = (sp) => {
    selectedSpForUpload.value = sp;
    uploadForm.reset();
    isUploadModalOpen.value = true;
};

const handleFileUpload = (e) => {
    uploadForm.signed_file = e.target.files[0];
};

const submitUploadManual = () => {
    if (!uploadForm.signed_file) {
        Swal.fire('Peringatan', 'Silakan pilih berkas PDF hasil scan yang telah bertanda tangan fisik.', 'warning');
        return;
    }

    uploadForm.post(route('sp-jaga.upload-manual-signed', selectedSpForUpload.value.id), {
        onSuccess: () => {
            isUploadModalOpen.value = false;
            Swal.fire('Berhasil', 'Berkas PDF bertanda tangan basah berhasil diunggah & notifikasi WhatsApp terkirim ke seluruh personel.', 'success');
        },
        onError: () => {
            Swal.fire('Gagal', 'Terjadi kesalahan saat mengunggah berkas.', 'error');
        }
    });
};

// Aksi TTE Pasops
const approveTte = (sp) => {
    Swal.fire({
        title: 'Konfirmasi TTE Digital',
        html: `<p class="text-xs text-slate-600">Apakah Anda yakin menyetujui & menandatangani Surat Perintah Jaga <b>${sp.nomor_sprin}</b> secara elektronik (TTE QR Code)?<br><br>Setelah disahkan, notifikasi jadwal jaga akan langsung dikirimkan ke WhatsApp seluruh personel.</p>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Setujui & TTE',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#64748b',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('sp-jaga.approve-tte', sp.id), {}, {
                onSuccess: () => {
                    Swal.fire('Sukses', 'SP Jaga berhasil disahkan & notifikasi WhatsApp telah dikirimkan.', 'success');
                }
            });
        }
    });
};

const deleteSp = (sp) => {
    Swal.fire({
        title: 'Hapus SP Jaga?',
        text: `Surat Perintah ${sp.nomor_sprin} akan dihapus secara permanen.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('sp-jaga.destroy', sp.id));
        }
    });
};

const formatMonthName = (bulan, tahun) => {
    const months = [
        '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    return `${months[bulan]} ${tahun}`;
};
</script>

<template>
    <Head title="Surat Perintah (SP) Jaga - SINDEN" />

    <AuthenticatedLayout>
        <div class="space-y-6 font-sans">
            
            <!-- Header Halaman -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="px-3 py-1 bg-blue-50 text-blue-700 font-extrabold text-[10px] uppercase rounded-full tracking-wider">
                            Dinas Jaga Siaga Sintel
                        </span>
                        <span class="text-slate-400 text-xs font-semibold">&bull; Detasemen Intelijen</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                        Surat Perintah Jaga Siaga Sintel
                    </h1>
                    <p class="text-xs text-slate-500 font-medium mt-1 max-w-2xl">
                        Penerbitan jadwal dinas jaga siaga perwira & anggota, pengesahan TTE / TTD Manual, pencetakan PDF 3 halaman resmi, dan notifikasi WhatsApp otomatis.
                    </p>
                </div>

                <div class="flex items-center gap-3 shrink-0" v-if="isAdmin">
                    <Link 
                        :href="route('sp-jaga.create')"
                        class="px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs uppercase tracking-wider rounded-2xl transition shadow-md shadow-blue-500/20 active:scale-[0.98]"
                    >
                        + Buat SP Jaga Baru
                    </Link>
                </div>
            </div>

            <!-- Tab Navigation Header -->
            <div class="flex items-center gap-2 border-b border-slate-200 overflow-x-auto pb-1">
                <!-- Tab 1: Jadwal Jaga Saya -->
                <button 
                    @click="activeTab = 'my_schedule'"
                    type="button"
                    class="px-4 py-2.5 rounded-xl font-extrabold text-xs uppercase tracking-wider transition cursor-pointer flex items-center gap-2"
                    :class="activeTab === 'my_schedule' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                >
                    <span>Jadwal Jaga Saya</span>
                </button>

                <!-- Tab 2: Semua SP Jaga -->
                <button 
                    @click="activeTab = 'all'"
                    type="button"
                    class="px-4 py-2.5 rounded-xl font-extrabold text-xs uppercase tracking-wider transition cursor-pointer flex items-center gap-2"
                    :class="activeTab === 'all' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                >
                    <span>Daftar SP Jaga</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="activeTab === 'all' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'">
                        {{ spJagas?.total || 0 }}
                    </span>
                </button>

                <!-- Tab 3: Antrean Persetujuan Pasops (Khusus Pasops / Admin) -->
                <button 
                    v-if="isPasops || isAdmin"
                    @click="activeTab = 'pending'"
                    type="button"
                    class="px-4 py-2.5 rounded-xl font-extrabold text-xs uppercase tracking-wider transition cursor-pointer flex items-center gap-2"
                    :class="activeTab === 'pending' ? 'bg-amber-500 text-slate-950 font-black shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                >
                    <span>Antrean TTE Pasops</span>
                    <span v-if="pendingSignatures?.length > 0" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-red-500 text-white animate-pulse">
                        {{ pendingSignatures.length }}
                    </span>
                </button>
            </div>

            <!-- ================================================================= -->
            <!-- KONTEN TAB 1: JADWAL JAGA SAYA                                    -->
            <!-- ================================================================= -->
            <div v-if="activeTab === 'my_schedule'" class="space-y-6">
                <div v-if="mySchedule" class="bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 text-white p-6 sm:p-8 rounded-3xl border border-blue-500/30 shadow-xl space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-white/10 pb-6">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="px-3 py-1 bg-amber-400 text-slate-950 font-black text-[10px] uppercase rounded-full tracking-wider">
                                    Jadwal Dinas Aktif
                                </span>
                                <span class="text-xs text-slate-300 font-mono">{{ mySchedule.nomor_sprin }}</span>
                            </div>
                            <h2 class="text-2xl font-black tracking-tight text-white">
                                Periode: {{ formatMonthName(mySchedule.bulan, mySchedule.tahun) }}
                            </h2>
                        </div>

                        <a 
                            :href="mySchedule.pdf_url" 
                            target="_blank"
                            class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl font-black text-xs uppercase tracking-wider bg-amber-400 hover:bg-amber-500 text-slate-950 transition shadow-lg shadow-amber-400/20 active:scale-[0.98]"
                        >
                            <span>Unduh PDF Resmi 3 Halaman</span>
                        </a>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Peran Jaga -->
                        <div class="p-5 rounded-2xl bg-white/5 border border-white/10 space-y-1.5">
                            <span class="text-[10px] font-extrabold uppercase tracking-widest text-amber-400 block">Posisi / Jabatan Jaga</span>
                            <h3 class="text-lg font-black text-white">{{ mySchedule.peran }}</h3>
                            <p class="text-xs text-slate-400 font-medium">{{ mySchedule.pangkat_korps }}</p>
                        </div>

                        <!-- Tanggal Dinas Jaga -->
                        <div class="p-5 rounded-2xl bg-white/5 border border-white/10 space-y-1.5 md:col-span-2">
                            <span class="text-[10px] font-extrabold uppercase tracking-widest text-amber-400 block">Tanggal Dinas Jaga Anda</span>
                            <div class="flex flex-wrap gap-2 pt-1">
                                <span 
                                    v-for="tgl in mySchedule.tanggal_jaga.split(',')" 
                                    :key="tgl"
                                    class="px-3.5 py-1.5 rounded-xl bg-blue-500/20 border border-blue-400/40 text-amber-300 font-extrabold text-sm tracking-wide"
                                >
                                    Tgl {{ tgl.trim() }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-400 pt-2 font-medium">
                                Lokasi Dinas: Kantor Sintel / Kantor Tim Intel Koarmada 2 (Jl. Stasiun Benteng Ujung Surabaya).
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else class="bg-white p-12 rounded-3xl border border-slate-200 text-center space-y-3">
                    <h3 class="text-base font-bold text-slate-800">Tidak Ada Jadwal Jaga Aktif</h3>
                    <p class="text-xs text-slate-500 max-w-md mx-auto leading-relaxed">
                        Nama Anda saat ini belum tercantum pada jadwal jaga siaga yang telah terbit. Silakan cek tab <b>Daftar SP Jaga</b> untuk melihat dokumen lengkap.
                    </p>
                </div>
            </div>

            <!-- ================================================================= -->
            <!-- KONTEN TAB 2: DAFTAR SEMUA SP JAGA                                -->
            <!-- ================================================================= -->
            <div v-if="activeTab === 'all'" class="space-y-4">
                <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-xs">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-700">
                            <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="px-6 py-4">Nomor Sprin</th>
                                    <th class="px-6 py-4">Periode Bulan</th>
                                    <th class="px-6 py-4">Perwira Tertua</th>
                                    <th class="px-6 py-4 text-center">Tipe TTD</th>
                                    <th class="px-6 py-4 text-center">Status</th>
                                    <th class="px-6 py-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="sp in spJagas.data" :key="sp.id" class="hover:bg-slate-50/80 transition">
                                    <td class="px-6 py-4 font-bold text-slate-900">
                                        {{ sp.nomor_sprin }}
                                        <span class="block text-[10px] text-slate-400 font-normal">
                                            {{ sp.tanggal_surat ? new Date(sp.tanggal_surat).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-bold text-slate-800">
                                        {{ formatMonthName(sp.bulan, sp.tahun) }}
                                        <span class="block text-[10px] text-slate-400 font-normal">
                                            Total {{ sp.total_personel_count }} Personel
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-slate-900 block">{{ sp.perwira_tertua_nama }}</span>
                                        <span class="text-[10px] text-slate-500">{{ sp.perwira_tertua_pangkat_nrp }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span 
                                            class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider"
                                            :class="sp.ttd_type === 'tte' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-amber-50 text-amber-700 border border-amber-200'"
                                        >
                                            {{ sp.ttd_type === 'tte' ? 'TTE Digital' : 'TTD Basah' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span 
                                            class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider"
                                            :class="{
                                                'bg-emerald-50 text-emerald-700 border border-emerald-200': sp.status === 'published',
                                                'bg-amber-50 text-amber-700 border border-amber-200': sp.status === 'pending_signature',
                                                'bg-slate-100 text-slate-600 border border-slate-200': sp.status === 'draft',
                                            }"
                                        >
                                            {{ sp.status === 'published' ? 'Terbit' : (sp.status === 'pending_signature' ? 'Menunggu TTE' : 'Draf') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right space-x-2">
                                        <!-- Unduh PDF -->
                                        <a 
                                            :href="route('sp-jaga.download-pdf', sp.id)" 
                                            target="_blank"
                                            class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs inline-flex items-center gap-1 transition"
                                            title="Unduh PDF Resmi"
                                        >
                                            <span>Unduh PDF</span>
                                        </a>

                                        <!-- Upload Scan TTD Basah (Khusus Manual & Admin) -->
                                        <button 
                                            v-if="isAdmin && sp.ttd_type === 'manual'"
                                            @click="openUploadModal(sp)"
                                            class="px-3 py-1.5 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 font-bold text-xs inline-flex items-center gap-1 transition cursor-pointer"
                                            title="Upload Scan PDF Bertanda Tangan Basah"
                                        >
                                            <span>Upload Scan</span>
                                        </button>

                                        <!-- Edit / Koreksi -->
                                        <Link 
                                            v-if="isAdmin || isPasops"
                                            :href="route('sp-jaga.edit', sp.id)"
                                            class="px-3 py-1.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold text-xs inline-flex items-center gap-1 transition"
                                            title="Koreksi Jadwal"
                                        >
                                            <span>Edit</span>
                                        </Link>

                                        <!-- Hapus (Admin) -->
                                        <button 
                                            v-if="isAdmin"
                                            @click="deleteSp(sp)"
                                            class="px-2.5 py-1.5 rounded-xl bg-red-50 hover:bg-red-100 text-red-600 font-bold text-xs transition cursor-pointer"
                                            title="Hapus SP Jaga"
                                        >
                                            <span>Hapus</span>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!spJagas.data || spJagas.data.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center text-slate-400 text-xs">
                                        Belum ada dokumen Surat Perintah Jaga yang diterbitkan.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ================================================================= -->
            <!-- KONTEN TAB 3: ANTREAN PERSETUJUAN PASOPS                          -->
            <!-- ================================================================= -->
            <div v-if="activeTab === 'pending'" class="space-y-4">
                <div v-if="pendingSignatures && pendingSignatures.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div 
                        v-for="sp in pendingSignatures" 
                        :key="sp.id"
                        class="p-6 rounded-3xl border border-amber-300 bg-amber-50/40 shadow-sm space-y-4"
                    >
                        <div class="flex items-start justify-between">
                            <div class="space-y-1">
                                <span class="px-2.5 py-0.5 bg-amber-500 text-slate-950 font-black text-[9px] uppercase rounded-md tracking-wider">
                                    Memerlukan TTE Pasops
                                </span>
                                <h3 class="text-lg font-black text-slate-900">{{ sp.nomor_sprin }}</h3>
                                <p class="text-xs text-slate-600 font-medium">
                                    Periode: {{ formatMonthName(sp.bulan, sp.tahun) }} (Total {{ sp.total_personel_count }} Personel)
                                </p>
                            </div>
                        </div>

                        <div class="text-xs text-slate-600 space-y-1 bg-white p-3.5 rounded-2xl border border-amber-200">
                            <p><b>Perwira Tertua:</b> {{ sp.perwira_tertua_nama }} ({{ sp.perwira_tertua_pangkat_nrp }})</p>
                            <p><b>Operator Pembuat:</b> {{ sp.creator?.name || 'Admin' }}</p>
                        </div>

                        <div class="flex items-center justify-between gap-3 pt-2">
                            <div class="flex items-center gap-2">
                                <a 
                                    :href="route('sp-jaga.download-pdf', sp.id)" 
                                    target="_blank"
                                    class="px-3.5 py-2 rounded-xl bg-white border border-slate-300 text-slate-800 font-bold text-xs hover:bg-slate-50 transition"
                                >
                                    Pratinjau PDF
                                </a>
                                <Link 
                                    :href="route('sp-jaga.edit', sp.id)"
                                    class="px-3.5 py-2 rounded-xl bg-white border border-blue-200 text-blue-700 font-bold text-xs hover:bg-blue-50 transition"
                                >
                                    Koreksi Jadwal
                                </Link>
                            </div>

                            <button 
                                v-if="isPasops || isAdmin"
                                @click="approveTte(sp)"
                                type="button"
                                class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs uppercase tracking-wider transition shadow-md shadow-blue-500/20 cursor-pointer"
                            >
                                Setujui & TTE
                            </button>
                        </div>
                    </div>
                </div>

                <div v-else class="bg-white p-12 rounded-3xl border border-slate-200 text-center space-y-2">
                    <h3 class="text-base font-bold text-slate-800">Tidak Ada Antrean TTE</h3>
                    <p class="text-xs text-slate-500">Seluruh berkas Surat Perintah Jaga telah disahkan dan berstatus terbit.</p>
                </div>
            </div>

            <!-- ================================================================= -->
            <!-- MODAL UPLOAD SCAN PDF BERTANDA TANGAN BASAH (MANUAL)              -->
            <!-- ================================================================= -->
            <div v-if="isUploadModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs" @click="isUploadModalOpen = false"></div>
                
                <div class="relative w-full max-w-md bg-white rounded-3xl p-6 sm:p-8 shadow-2xl border border-slate-100 space-y-6 z-10">
                    <div class="space-y-1 border-b border-slate-100 pb-4">
                        <h3 class="text-lg font-extrabold text-slate-900">Upload Hasil Scan TTD Basah</h3>
                        <p class="text-xs text-slate-500">
                            {{ selectedSpForUpload?.nomor_sprin }} - {{ formatMonthName(selectedSpForUpload?.bulan, selectedSpForUpload?.tahun) }}
                        </p>
                    </div>

                    <form @submit.prevent="submitUploadManual" class="space-y-4">
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">Pilih Berkas PDF Scan Fisik (Max 20MB)</label>
                            <input 
                                type="file" 
                                accept="application/pdf"
                                @change="handleFileUpload"
                                required
                                class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 file:mr-4 file:py-1.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-extrabold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer"
                            />
                        </div>

                        <div class="p-3.5 rounded-2xl bg-amber-50 border border-amber-200 text-[11px] text-amber-800 leading-relaxed font-medium">
                            Setelah file diunggah, status berkas akan berubah menjadi <b>Terbit (Published)</b> dan sistem akan secara otomatis mengirim notifikasi jadwal jaga ke WhatsApp seluruh personel terkait.
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button 
                                @click="isUploadModalOpen = false" 
                                type="button" 
                                class="px-4 py-2.5 text-xs font-bold text-slate-500 hover:bg-slate-100 rounded-xl transition cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="uploadForm.processing"
                                class="px-6 py-2.5 text-xs font-black uppercase tracking-wider bg-blue-600 hover:bg-blue-700 text-white rounded-xl transition shadow-md shadow-blue-500/20 cursor-pointer"
                            >
                                {{ uploadForm.processing ? 'Mengunggah...' : 'Unggah & Terbitkan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
