<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    myAccount: Object,
    myActiveLoan: Object,
    myLatestRejectedLoan: Object,
    myLoanHistory: Array,
    mySavingsTransactions: Array,
    isPengurus: Boolean,
});

// Modal States
const showApplyModal = ref(false);
const showScheduleModal = ref(false);

// Form Pengajuan Pinjaman Mandiri (Baku Tenor 10 Bulan)
const applyForm = useForm({
    loan_type: 'reguler',
    amount_requested: 1000000,
    duration_months: 10,
    purpose: '',
    document: null,
});

// Kalkulator Cicilan Realtime (Otomatis Tenor 10 Bulan)
const calculatedMonthlyInstallment = computed(() => {
    const amount = Number(applyForm.amount_requested) || 0;
    return Math.round(amount / 10);
});

const submitApplyLoan = () => {
    applyForm.post(route('simpan-pinjam.apply-loan'), {
        onSuccess: () => {
            showApplyModal.value = false;
            applyForm.reset();
            Swal.fire({
                title: 'BERHASIL DIAJUKAN',
                text: 'Pengajuan pinjaman Anda telah diterima dan akan diverifikasi oleh pengurus koperasi.',
                icon: 'success',
                confirmButtonColor: '#059669',
            });
        },
        onError: (err) => {
            Swal.fire('GAGAL', Object.values(err)[0] || 'Periksa kembali formulir pengajuan Anda.', 'error');
        }
    });
};

// Format Helpers
const formatRupiah = (val) => {
    return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

// Progress Pelunasan Pinjaman Aktif
const loanRepaymentProgress = computed(() => {
    if (!props.myActiveLoan) return 0;
    const total = Number(props.myActiveLoan.total_loan_amount) || 1;
    const paid = Number(props.myActiveLoan.total_paid) || 0;
    return Math.min(100, Math.round((paid / total) * 100));
});
</script>

<template>
    <Head title="Simpan Pinjam - SINDEN" />

    <AuthenticatedLayout>
        <div class="space-y-6 sm:space-y-8 font-sans max-w-7xl mx-auto pb-12">
            
            <!-- Banner Khusus Pengurus / Admin -->
            <div v-if="isPengurus" class="p-4 rounded-2xl bg-gradient-to-r from-violet-600 to-indigo-700 text-white shadow-md flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <div>
                        <div class="text-xs font-black uppercase tracking-wider text-violet-200">Akses Pengurus Aktif</div>
                        <div class="text-sm font-bold">Anda memiliki hak akses untuk mengelola verifikasi dan pembukuan Koperasi.</div>
                    </div>
                </div>
                <Link 
                    :href="route('simpan-pinjam.kelola')"
                    class="px-5 py-2.5 bg-white text-violet-900 hover:bg-violet-50 font-black text-xs uppercase tracking-wider rounded-xl transition shadow-sm shrink-0"
                >
                    Buka Menu Kelola Koperasi &rarr;
                </Link>
            </div>

            <!-- Header Halaman Portal Mandiri -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 font-extrabold text-[10px] uppercase rounded-full tracking-wider border border-emerald-200">
                            Portal Koperasi Personel
                        </span>
                        <span class="text-slate-400 text-xs font-semibold">Mandiri & Transparan</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                        Simpan Pinjam Personel
                    </h1>
                    <p class="text-xs text-slate-500 font-medium mt-1">
                        Layanan keuangan dan kesejahteraan prajurit Detasemen Intelijen Koarmada 2.
                    </p>
                </div>

                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <button 
                        @click="showApplyModal = true"
                        class="w-full sm:w-auto px-6 py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl font-black text-xs uppercase tracking-wider transition shadow-md shadow-emerald-600/20 flex items-center justify-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                        <span>Ajukan Pinjaman Baru</span>
                    </button>
                </div>
            </div>

            <!-- Grid 1: Virtual Member Card & Ringkasan Simpanan -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Virtual Member Card -->
                <div class="relative overflow-hidden rounded-3xl p-6 sm:p-7 text-white shadow-xl bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-950 border border-slate-700/50 flex flex-col justify-between min-h-[220px]">
                    <div class="absolute -right-12 -top-12 w-48 h-48 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>
                    <div class="flex justify-between items-start z-10">
                        <div>
                            <h3 class="text-sm font-black tracking-wide text-white">UNIT SIMPAN PINJAM</h3>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[9px] font-black uppercase bg-emerald-400/20 text-emerald-300 border border-emerald-400/30">
                            ANGGOTA AKTIF
                        </span>
                    </div>

                    <div class="z-10 my-4">
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Nomor Rekening Anggota</span>
                        <div class="text-lg font-mono font-black tracking-widest text-emerald-300">
                            {{ myAccount?.member_number || 'KOP-00000' }}
                        </div>
                    </div>

                    <div class="flex justify-between items-end z-10 border-t border-slate-700/60 pt-3">
                        <div>
                            <div class="text-xs font-black uppercase tracking-wide text-white">{{ $page.props.auth.user.name }}</div>
                            <div class="text-[10px] text-slate-300 font-mono">{{ $page.props.auth.user.pangkat }} - NRP {{ $page.props.auth.user.nrp }}</div>
                        </div>
                        <div class="text-right">
                            <span class="text-[9px] uppercase text-slate-400 block">Total Simpanan</span>
                            <span class="text-sm font-black text-emerald-400">{{ formatRupiah(myAccount?.total_simpanan) }}</span>
                        </div>
                    </div>
                </div>

                <!-- 3 Kartu Rincian Tabungan -->
                <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Simpanan Pokok</span>
                            <div class="text-xl font-black text-slate-900">{{ formatRupiah(myAccount?.balance_simpanan_pokok) }}</div>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-3">Simpanan awal keanggotaan koperasi kedinasan.</p>
                    </div>

                    <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Simpanan Wajib</span>
                            <div class="text-xl font-black text-emerald-600">{{ formatRupiah(myAccount?.balance_simpanan_wajib) }}</div>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-3">Iuran rutin bulanan per anggota via dinas.</p>
                    </div>

                    <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Simpanan Sukarela</span>
                            <div class="text-xl font-black text-indigo-600">{{ formatRupiah(myAccount?.balance_simpanan_sukarela) }}</div>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-3">Tabungan bebas yang dapat diambil sewaktu-waktu.</p>
                    </div>
                </div>
            </div>

            <!-- Grid 2: Status Pinjaman Berjalan & Kartu Angsuran -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-100 pb-4">
                    <div>
                        <h3 class="text-base font-black text-slate-900">Pinjaman Aktif Anda</h3>
                        <p class="text-xs text-slate-500">Pemantauan sisa hutang pokok, angsuran bulanan, dan jadwal jatuh tempo tanggal 1.</p>
                    </div>
                    <button 
                        v-if="myActiveLoan && myActiveLoan.installments?.length > 0"
                        @click="showScheduleModal = true"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-extrabold text-xs uppercase rounded-xl transition flex items-center gap-1.5"
                    >
                        <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        <span>Lihat Kartu Angsuran 10 Bulan</span>
                    </button>
                </div>

                <!-- Jika Ada Pinjaman Aktif -->
                <div v-if="myActiveLoan" class="space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 p-5 rounded-2xl bg-slate-50 border border-slate-200">
                        <div>
                            <span class="text-[10px] font-black uppercase text-slate-400 block">Kode Pinjaman</span>
                            <span class="text-sm font-mono font-black text-indigo-700">{{ myActiveLoan.loan_code }}</span>
                            <div class="mt-1">
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase"
                                    :class="{
                                        'bg-amber-100 text-amber-800': myActiveLoan.status === 'pending',
                                        'bg-emerald-100 text-emerald-800': myActiveLoan.status === 'active',
                                        'bg-blue-100 text-blue-800': myActiveLoan.status === 'approved',
                                    }">
                                    {{ myActiveLoan.status === 'pending' ? 'Menunggu Persetujuan' : 'Aktif Berjalan' }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <span class="text-[10px] font-black uppercase text-slate-400 block">Plafon Pinjaman</span>
                            <span class="text-base font-black text-slate-900">{{ formatRupiah(myActiveLoan.amount_approved || myActiveLoan.amount_requested) }}</span>
                            <div class="text-[10px] text-slate-500">Tenor: {{ myActiveLoan.duration_months }} Bulan</div>
                        </div>

                        <div>
                            <span class="text-[10px] font-black uppercase text-slate-400 block">Cicilan / Bulan (Tgl 1)</span>
                            <span class="text-base font-black text-emerald-600">{{ formatRupiah(myActiveLoan.monthly_installment) }}</span>
                            <div class="text-[10px] text-slate-500 font-medium">Bunga 0% (Bagi 10 Rata)</div>
                        </div>

                        <div>
                            <span class="text-[10px] font-black uppercase text-slate-400 block">Sisa Pokok Hutang</span>
                            <span class="text-base font-black text-rose-600">{{ formatRupiah(myActiveLoan.remaining_amount) }}</span>
                            <div class="text-[10px] text-slate-500">Terbayar: {{ formatRupiah(myActiveLoan.total_paid) }}</div>
                        </div>
                    </div>

                    <!-- Progress Bar Pelunasan -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-xs font-extrabold">
                            <span class="text-slate-600 uppercase">Progress Pelunasan Pinjaman</span>
                            <span class="text-emerald-700 font-black">{{ loanRepaymentProgress }}% Selesai</span>
                        </div>
                        <div class="w-full h-3.5 bg-slate-100 rounded-full overflow-hidden p-0.5 border border-slate-200">
                            <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full transition-all duration-500" :style="{ width: loanRepaymentProgress + '%' }"></div>
                        </div>
                    </div>
                </div>

                <!-- Jika Ada Pengajuan Terakhir yang Ditolak -->
                <div v-else-if="myLatestRejectedLoan" class="p-6 bg-rose-50/70 border border-rose-200 rounded-3xl space-y-4">
                    <div class="flex flex-col sm:flex-row items-start justify-between gap-4">
                        <div class="flex items-start gap-3.5">
                            <div class="w-10 h-10 rounded-2xl bg-rose-600 text-white flex items-center justify-center font-black shrink-0 shadow-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-md bg-rose-100 text-rose-800 text-[10px] font-black uppercase tracking-wider">Pengajuan Ditolak</span>
                                    <span class="text-xs font-mono font-bold text-slate-500">{{ myLatestRejectedLoan.loan_code }}</span>
                                </div>
                                <h4 class="text-base font-black text-rose-950 mt-1">Permohonan Pinjaman Belum Dapat Disetujui</h4>
                                <p class="text-xs text-rose-800 mt-0.5">
                                    Pengajuan sebesar <b>{{ formatRupiah(myLatestRejectedLoan.amount_requested) }}</b> (Tenor {{ myLatestRejectedLoan.duration_months }} Bulan) ditolak oleh Pengurus Koperasi pada {{ formatDate(myLatestRejectedLoan.updated_at) }}.
                                </p>
                            </div>
                        </div>
                        <button 
                            @click="showApplyModal = true"
                            class="w-full sm:w-auto px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-black text-xs uppercase tracking-wider transition shadow-sm shrink-0"
                        >
                            + Ajukan Permohonan Baru
                        </button>
                    </div>

                    <!-- Catatan Alasan Penolakan -->
                    <div class="p-4 bg-white rounded-2xl border border-rose-200/80 text-xs space-y-1">
                        <span class="text-[10px] font-black uppercase text-slate-400 block tracking-wider">Catatan / Alasan Penolakan Resmi Pengurus:</span>
                        <p class="font-bold text-slate-800 italic">"{{ myLatestRejectedLoan.rejection_reason || 'Tidak memenuhi kriteria evaluasi pinjaman koperasi.' }}"</p>
                    </div>

                    <div class="text-[11px] text-rose-700/80 flex items-center justify-between border-t border-rose-200/60 pt-2">
                        <span>Pemberitahuan resmi juga telah diterbitkan pada lonceng notifikasi aplikasi dan pesan WhatsApp kedinasan Anda.</span>
                    </div>
                </div>

                <!-- Jika Tidak Ada Pinjaman Aktif & Tidak Ada Penolakan Baru -->
                <div v-else class="text-center py-10 px-4 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                    <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h4 class="font-black text-slate-900 text-sm">Rekening Bersih Bebas Tanggungan</h4>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1 mb-4">
                        Anda saat ini tidak memiliki pinjaman aktif berjalan. Anda dapat mengajukan pinjaman dengan skema cicilan otomatis 10 bulan.
                    </p>
                    <button 
                        @click="showApplyModal = true"
                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase tracking-wider rounded-xl transition shadow-md shadow-emerald-600/20"
                    >
                        + Buat Pengajuan Pinjaman
                    </button>
                </div>
            </div>

            <!-- Grid 3: Riwayat Mutasi Tabungan Pribadi -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-4">
                <h3 class="text-base font-black text-slate-900">Buku Mutasi Tabungan Pribadi</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="text-[10px] font-extrabold uppercase text-slate-400 border-b border-slate-100">
                                <th class="py-3 px-3">Tanggal</th>
                                <th class="py-3 px-3">Kode Transaksi</th>
                                <th class="py-3 px-3">Jenis Simpanan</th>
                                <th class="py-3 px-3">Tipe</th>
                                <th class="py-3 px-3 text-right">Nominal</th>
                                <th class="py-3 px-3 text-right">Saldo Akhir</th>
                                <th class="py-3 px-3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="tx in mySavingsTransactions" :key="tx.id" class="hover:bg-slate-50 transition">
                                <td class="py-3 px-3 font-mono">{{ formatDate(tx.created_at) }}</td>
                                <td class="py-3 px-3 font-mono font-bold text-slate-700">{{ tx.transaction_code }}</td>
                                <td class="py-3 px-3 font-bold uppercase">{{ tx.saving_type }}</td>
                                <td class="py-3 px-3">
                                    <span :class="tx.type === 'deposit' ? 'text-emerald-600 bg-emerald-50' : 'text-rose-600 bg-rose-50'" class="px-2 py-0.5 rounded-full font-black text-[10px] uppercase">
                                        {{ tx.type === 'deposit' ? 'SETOR' : 'TARIK' }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-right font-black text-slate-900">{{ formatRupiah(tx.amount) }}</td>
                                <td class="py-3 px-3 text-right font-bold text-emerald-700">{{ formatRupiah(tx.balance_after) }}</td>
                                <td class="py-3 px-3 text-slate-500">{{ tx.notes || '-' }}</td>
                            </tr>
                            <tr v-if="!mySavingsTransactions || mySavingsTransactions.length === 0">
                                <td colspan="7" class="py-8 text-center text-slate-400">Belum ada riwayat mutasi simpanan tercatat.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Grid 4: Riwayat Pengajuan Pinjaman Saya -->
            <div v-if="myLoanHistory && myLoanHistory.length > 0" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <h3 class="text-base font-black text-slate-900">Riwayat Pengajuan & Pinjaman Anda</h3>
                        <p class="text-xs text-slate-500">Seluruh riwayat permohonan pinjaman yang pernah Anda ajukan ke Koperasi SINDEN.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="text-[10px] font-extrabold uppercase text-slate-400 border-b border-slate-100">
                                <th class="py-3 px-3">Tanggal</th>
                                <th class="py-3 px-3">Kode Pinjaman</th>
                                <th class="py-3 px-3 text-right">Nominal Diajukan</th>
                                <th class="py-3 px-3 text-center">Tenor</th>
                                <th class="py-3 px-3 text-center">Status</th>
                                <th class="py-3 px-3">Catatan / Alasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="lh in myLoanHistory" :key="lh.id" class="hover:bg-slate-50 transition">
                                <td class="py-3 px-3 font-mono">{{ formatDate(lh.created_at) }}</td>
                                <td class="py-3 px-3 font-mono font-bold text-slate-800">{{ lh.loan_code }}</td>
                                <td class="py-3 px-3 text-right font-black text-slate-900">{{ formatRupiah(lh.amount_requested) }}</td>
                                <td class="py-3 px-3 text-center font-bold">{{ lh.duration_months }} Bln</td>
                                <td class="py-3 px-3 text-center">
                                    <span :class="{
                                        'bg-amber-100 text-amber-800': lh.status === 'pending',
                                        'bg-emerald-100 text-emerald-800': lh.status === 'active',
                                        'bg-rose-100 text-rose-800': lh.status === 'rejected',
                                        'bg-blue-100 text-blue-800': lh.status === 'paid_off',
                                    }" class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase">
                                        {{ lh.status === 'rejected' ? 'DITOLAK' : (lh.status === 'pending' ? 'MENUNGGU' : (lh.status === 'active' ? 'AKTIF' : 'LUNAS')) }}
                                    </span>
                                </td>
                                <td class="py-3 px-3">
                                    <span v-if="lh.status === 'rejected'" class="text-rose-600 font-bold italic">
                                        {{ lh.rejection_reason || 'Pengajuan ditolak pengurus' }}
                                    </span>
                                    <span v-else class="text-slate-500">
                                        {{ lh.purpose }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- ===================================================================== -->
        <!-- MODAL: FORMULIR PENGAJUAN PINJAMAN MANDIRI (ANTI-TERPOTONG / SCROLL)   -->
        <!-- ===================================================================== -->
        <div v-if="showApplyModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 overflow-y-auto">
            <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden my-auto animate-in zoom-in-95 duration-150 border border-slate-100">
                
                <!-- Fixed Modal Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0 bg-slate-50/70">
                    <div>
                        <span class="text-[10px] font-black uppercase text-emerald-600 tracking-wider">Formulir Mandiri</span>
                        <h3 class="text-base sm:text-lg font-black text-slate-900">Pengajuan Pinjaman Koperasi</h3>
                    </div>
                    <button @click="showApplyModal = false" class="w-8 h-8 rounded-xl bg-slate-200/60 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition">
                        &times;
                    </button>
                </div>

                <!-- Scrollable Form Body -->
                <form id="applyLoanForm" @submit.prevent="submitApplyLoan" class="p-6 overflow-y-auto space-y-4 flex-1 text-xs">
                    <!-- Kategori Pinjaman -->
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Kategori Pinjaman</label>
                        <select v-model="applyForm.loan_type" class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 font-bold focus:ring-2 focus:ring-emerald-500">
                            <option value="reguler">Pinjaman Reguler (Kesejahteraan Umum)</option>
                            <option value="darurat">Pinjaman Darurat (Kesehatan / Medis)</option>
                            <option value="barang">Pinjaman Pengadaan Barang / Elektronik</option>
                            <option value="pendidikan">Pinjaman Biaya Pendidikan Anak</option>
                        </select>
                    </div>

                    <!-- Nominal Pinjaman -->
                    <div class="space-y-1">
                        <div class="flex justify-between items-center">
                            <label class="font-bold text-slate-700 block">Nominal Pinjaman Yang Diajukan</label>
                            <span class="font-black text-emerald-600 text-sm">{{ formatRupiah(applyForm.amount_requested) }}</span>
                        </div>
                        <input 
                            v-model.number="applyForm.amount_requested"
                            type="number"
                            step="100000"
                            min="500000"
                            max="50000000"
                            class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 font-black text-slate-900 focus:ring-2 focus:ring-emerald-500"
                            required
                        />
                    </div>

                    <!-- Pilihan Tenor Otomatis 10 Bulan -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="font-bold text-slate-700 block">Jangka Waktu Angsuran (Tenor)</label>
                            <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black uppercase tracking-wider">Otomatis 10 Bulan</span>
                        </div>
                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-2xl flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-black text-sm shadow-xs">
                                    10
                                </div>
                                <div>
                                    <div class="font-extrabold text-xs text-slate-900">10 Bulan (Standar Koperasi SINDEN)</div>
                                    <div class="text-[11px] text-slate-500">Cicilan pokok dibagi rata 10 kali tanpa bunga tambahan</div>
                                </div>
                            </div>
                            <span class="text-xs font-black text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-xl">
                                Pokok / 10
                            </span>
                        </div>
                    </div>

                    <!-- Kalkulator Simulasi Cicilan Interaktif -->
                    <div class="p-4 rounded-2xl bg-gradient-to-br from-emerald-50 via-teal-50 to-emerald-100/40 border border-emerald-200 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] uppercase font-black text-emerald-800 block tracking-wider">Cicilan Rutin / Bulan</span>
                            <span class="text-[11px] text-emerald-700 font-medium">Jatuh tempo setiap tanggal 1 setiap bulannya</span>
                        </div>
                        <div class="text-right">
                            <div class="text-xl font-black text-emerald-900">
                                {{ formatRupiah(calculatedMonthlyInstallment) }}
                            </div>
                            <span class="text-[10px] text-emerald-800 font-extrabold bg-emerald-200/60 px-2 py-0.5 rounded-md inline-block mt-0.5">
                                x 10 Bulan (Lunas)
                            </span>
                        </div>
                    </div>

                    <!-- Keperluan Pinjaman -->
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Alasan & Keperluan Pinjaman</label>
                        <textarea 
                            v-model="applyForm.purpose"
                            rows="2"
                            placeholder="Contoh: Biaya renovasi tempat tinggal / biaya sekolah anak..."
                            class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-500"
                            required
                        ></textarea>
                    </div>

                    <!-- Upload Berkas (Opsional) -->
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Lampiran Pendukung (Opsional)</label>
                        <input 
                            @change="applyForm.document = $event.target.files[0]"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            class="w-full text-xs file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer"
                        />
                    </div>
                </form>

                <!-- Fixed Modal Sticky Footer -->
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/80 flex gap-3 shrink-0">
                    <button type="button" @click="showApplyModal = false" class="flex-1 py-3 bg-white hover:bg-slate-100 text-slate-700 font-bold rounded-2xl border border-slate-200 transition uppercase text-xs">
                        Batal
                    </button>
                    <button form="applyLoanForm" type="submit" :disabled="applyForm.processing" class="flex-[2] py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-2xl transition uppercase text-xs shadow-md shadow-emerald-600/30">
                        {{ applyForm.processing ? 'Memproses...' : 'Kirim Pengajuan Pinjaman' }}
                    </button>
                </div>

            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- MODAL: KARTU JADWAL ANGSURAN PRIBADI (ANTI-TERPOTONG / SCROLL)        -->
        <!-- ===================================================================== -->
        <div v-if="showScheduleModal && myActiveLoan" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 overflow-y-auto">
            <div class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden my-auto border border-slate-100">
                
                <!-- Fixed Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0 bg-slate-50/70">
                    <div>
                        <span class="text-[10px] font-black uppercase text-indigo-600 tracking-wider">Kartu Angsuran 10 Bulan</span>
                        <h3 class="text-base font-black text-slate-900">Jadwal Cicilan Pinjaman {{ myActiveLoan.loan_code }}</h3>
                    </div>
                    <button @click="showScheduleModal = false" class="w-8 h-8 rounded-xl bg-slate-200/60 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition">
                        &times;
                    </button>
                </div>

                <!-- Scrollable Body Table -->
                <div class="p-6 overflow-y-auto space-y-4 flex-1 text-xs">
                    <div class="p-4 rounded-2xl bg-indigo-50/70 border border-indigo-200 grid grid-cols-3 gap-3 text-center">
                        <div>
                            <span class="text-[9px] font-bold text-indigo-700 uppercase block">Total Plafon</span>
                            <span class="text-sm font-black text-indigo-950">{{ formatRupiah(myActiveLoan.total_loan_amount) }}</span>
                        </div>
                        <div>
                            <span class="text-[9px] font-bold text-indigo-700 uppercase block">Total Terbayar</span>
                            <span class="text-sm font-black text-emerald-700">{{ formatRupiah(myActiveLoan.total_paid) }}</span>
                        </div>
                        <div>
                            <span class="text-[9px] font-bold text-indigo-700 uppercase block">Sisa Hutang Pokok</span>
                            <span class="text-sm font-black text-rose-700">{{ formatRupiah(myActiveLoan.remaining_amount) }}</span>
                        </div>
                    </div>

                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="text-[10px] font-extrabold uppercase text-slate-400 border-b border-slate-100">
                                <th class="py-2.5 px-3 text-center">Bulan</th>
                                <th class="py-2.5 px-3">Jatuh Tempo</th>
                                <th class="py-2.5 px-3 text-right">Tagihan</th>
                                <th class="py-2.5 px-3 text-right">Dibayar</th>
                                <th class="py-2.5 px-3 text-center">Status</th>
                                <th class="py-2.5 px-3 text-center">Slip</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="inst in myActiveLoan.installments" :key="inst.id">
                                <td class="py-2.5 px-3 text-center font-bold">#{{ inst.installment_no }}</td>
                                <td class="py-2.5 px-3 font-mono font-bold text-slate-800">{{ formatDate(inst.due_date) }}</td>
                                <td class="py-2.5 px-3 text-right font-black text-slate-900">{{ formatRupiah(inst.amount_due) }}</td>
                                <td class="py-2.5 px-3 text-right font-black text-emerald-700">{{ formatRupiah(inst.amount_paid) }}</td>
                                <td class="py-2.5 px-3 text-center">
                                    <span :class="inst.status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'" class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase">
                                        {{ inst.status === 'paid' ? 'LUNAS' : 'BELUM' }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <a v-if="inst.status === 'paid'" :href="route('simpan-pinjam.receipt', inst.id)" target="_blank" class="text-[10px] font-black text-emerald-700 underline">
                                        Kuitansi
                                    </a>
                                    <span v-else class="text-slate-300">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Fixed Footer -->
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/80 flex justify-end shrink-0">
                    <button type="button" @click="showScheduleModal = false" class="px-6 py-2.5 bg-slate-900 text-white font-black rounded-xl text-xs uppercase transition">
                        Tutup
                    </button>
                </div>

            </div>
        </div>

    </AuthenticatedLayout>
</template>
