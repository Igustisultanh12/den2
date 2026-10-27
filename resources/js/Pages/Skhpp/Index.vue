<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AuditTrailModal from '@/Components/AuditTrailModal.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    skhpps: Object,
    filters: Object,
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => Boolean(user.value.is_admin));
const isCommander = computed(() => Boolean(user.value.is_admin) || user.value.role === 'komandan' || user.value.name === 'I Gusti Sultan H.A, A.Md.Kom');

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'all');
const kategoriFilter = ref(props.filters?.kategori || 'all');

const showAuditModal = ref(false);
const auditTarget = ref('');

const openAuditTrail = (target) => {
    auditTarget.value = target;
    showAuditModal.value = true;
};

const applyFilter = () => {
    router.get(route('skhpp.index'), {
        search: search.value,
        status: statusFilter.value,
        kategori: kategoriFilter.value,
    }, { preserveState: true, replace: true });
};

const resetFilter = () => {
    search.value = '';
    statusFilter.value = 'all';
    kategoriFilter.value = 'all';
    applyFilter();
};

const approveSkhpp = (skhpp) => {
    const defaultSeq = (skhpp.nomor_urut || '');
    const katCode = (skhpp.kategori_personel === 'perusahaan') ? 'SKHPP-P (Mitra Kerja/Perusahaan)' : 'SKHPP-D (Dinas Militer & PNS)';

    Swal.fire({
        title: 'OTORISASI TTD KOMANDAN',
        html: `
            <div class="text-left text-xs space-y-3 font-sans">
                <div class="bg-blue-50 p-2.5 rounded-xl border border-blue-100">
                    <p class="font-black text-blue-600 text-[10px] uppercase mb-0.5">${katCode}</p>
                    <p class="font-bold text-slate-800">${skhpp.nama}</p>
                </div>
                
                <div>
                    <label class="block font-bold uppercase mb-1 text-slate-700 text-[10px]">Nomor Urut ${skhpp.kategori_personel === 'perusahaan' ? 'SKHPP-P' : 'SKHPP-D'} (Isi untuk Loncati Nomor)</label>
                    <input id="swal-nomor-urut" type="number" value="${defaultSeq}" class="w-full text-xs font-bold p-2.5 border rounded-xl" placeholder="Kosongkan untuk otomatis urutan selanjutnya (cth: ${skhpp.kategori_personel === 'perusahaan' ? '15' : '180'})" />
                    <span class="text-[9px] text-slate-400 mt-1 block">*Penomoran SKHPP-P dan SKHPP-D terpisah & independen.</span>
                </div>

                <div>
                    <label class="block font-bold uppercase mb-1 text-slate-700 text-[10px]">Derajat Kecepatan / Prioritas Surat</label>
                    <select id="swal-priority" class="w-full text-xs font-bold p-2.5 border rounded-xl">
                        <option value="R" selected>R (RAHASIA)  Format: R / [NOMOR] / SKHPP / [BULAN] / [TAHUN]</option>
                        <option value="B">B (BIASA)  Format: B / [NOMOR] / SKHPP / [BULAN] / [TAHUN]</option>
                        <option value="K">K (KILAT)  Format: K / [NOMOR] / SKHPP / [BULAN] / [TAHUN]</option>
                    </select>
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'YA, SETUJU & TTD',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#059669',
        preConfirm: () => {
            const seq = document.getElementById('swal-nomor-urut').value;
            const prio = document.getElementById('swal-priority').value;
            return { custom_nomor_urut: seq, priority: prio };
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            router.post(route('skhpp.approve', skhpp.id), result.value, {
                onSuccess: () => Swal.fire('SUKSES', 'SKHPP Resmi disetujui & ditandatangani Komandan.', 'success')
            });
        }
    });
};

const changeNomorSkhpp = (skhpp) => {
    const katCode = (skhpp.kategori_personel === 'perusahaan') ? 'SKHPP-P' : 'SKHPP-D';
    Swal.fire({
        title: `ATUR NOMOR URUT ${katCode}`,
        html: `
            <div class="text-left text-xs space-y-3 font-sans">
                <p class="text-slate-600">Atur atau ubah nomor urut khusus untuk <b>${skhpp.nama}</b> (${katCode}):</p>
                <div>
                    <label class="block font-bold uppercase mb-1 text-slate-700 text-[10px]">Nomor Urut Baru (${katCode})</label>
                    <input id="swal-new-seq" type="number" value="${skhpp.nomor_urut || ''}" class="w-full text-xs font-bold p-2.5 border rounded-xl" placeholder="Ketik nomor urut (contoh: ${skhpp.kategori_personel === 'perusahaan' ? '15' : '180'})" />
                    <span class="text-[9px] text-slate-400 mt-1 block">*Nomor urut ${katCode} berjalan independen tanpa mempengaruhi kategori lain.</span>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Simpan Nomor',
        confirmButtonColor: '#2563eb',
        preConfirm: () => {
            return document.getElementById('swal-new-seq').value;
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            router.post(route('skhpp.update-number', skhpp.id), {
                custom_nomor_urut: result.value
            }, {
                onSuccess: () => Swal.fire('BERHASIL DISIMPAN', `Nomor urut ${katCode} berhasil diatur ke ${result.value} tanpa memberikan TTD.`, 'success')
            });
        }
    });
};

const reSubmitTte = (skhpp) => {
    const katCode = (skhpp.kategori_personel === 'perusahaan') ? 'SKHPP-P' : 'SKHPP-D';
    Swal.fire({
        title: 'AJUKAN TTE ULANG?',
        html: `
            <div class="text-left text-xs space-y-2 font-sans">
                <p class="text-slate-600">Ajukan ulang permohonan SKHPP atas nama <b>${skhpp.nama}</b> (${katCode}) ke Komandan untuk otorisasi TTE Tanda Tangan Digital.</p>
                <p class="text-emerald-600 font-bold text-[10px]"> Status akan direset ke Pending TTD Komandan & notifikasi WA dikirim.</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'YA, AJUKAN TTE ULANG',
        confirmButtonColor: '#2563eb',
        cancelButtonText: 'BATAL'
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('skhpp.resubmit-tte', skhpp.id), {}, {
                onSuccess: () => Swal.fire('BERHASIL DIAJUKAN', `Permohonan SKHPP ${skhpp.nama} berhasil diajukan ulang ke TTD Komandan.`, 'success')
            });
        }
    });
};

const rejectSkhpp = (skhpp) => {
    Swal.fire({
        title: 'TOLAK / MINTA REVISI',
        text: 'Masukkan alasan penolakan atau catatan revisi agar operator dapat mengedit data:',
        input: 'textarea',
        inputPlaceholder: 'Contoh: Data peruntukan kurang spesifik atau NRP salah...',
        showCancelButton: true,
        confirmButtonText: 'Kirim Penolakan',
        confirmButtonColor: '#dc2626',
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            router.post(route('skhpp.reject', skhpp.id), {
                catatan_revisi: result.value
            }, {
                onSuccess: () => Swal.fire('DIKEMBALIKAN', 'Permohonan SKHPP dikembalikan ke operator untuk direvisi.', 'warning')
            });
        }
    });
};

const deleteSkhpp = (skhpp) => {
    Swal.fire({
        title: 'Hapus SKHPP?',
        text: `Data permohonan SKHPP atas nama ${skhpp.nama} akan dihapus permanen.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Hapus',
        confirmButtonColor: '#dc2626',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('skhpp.destroy', skhpp.id), {
                onSuccess: () => Swal.fire('TERHAPUS', 'Data berhasil dihapus.', 'success')
            });
        }
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
};
</script>

<template>
    <Head title="Penerbitan SKHPP - SINDEN" />

    <AuthenticatedLayout>
        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-3xl shadow-xs border border-slate-100">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-emerald-50 text-emerald-600 rounded-2xl">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-black text-slate-900 uppercase tracking-tight">Penerbitan SKHPP</h1>
                        <p class="text-xs text-slate-500 font-medium">Surat Keterangan Hasil Penelitian Personel (Militer & Sipil Mitra) SINDEN</p>
                    </div>
                </div>

                <Link :href="route('skhpp.create')"class="inline-flex items-center gap-2 px-5 py-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black uppercase tracking-wider rounded-2xl shadow-lg shadow-emerald-600/20 transition-all active:scale-95">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                    <span>Terbitkan SKHPP Baru</span>
                </Link>
            </div>

            <!-- Filter & Search Bar -->
            <div class="bg-white p-5 rounded-3xl shadow-xs border border-slate-100 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Cari Personel / NIK / Nomor</label>
                    <input type="text" v-model="search" @keyup.enter="applyFilter" placeholder="Ketik nama / NIK / NRP..."class="w-full text-xs font-semibold px-4 py-2.5 rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" />
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Kategori Personel</label>
                    <select v-model="kategoriFilter" @change="applyFilter" class="w-full text-xs font-semibold px-4 py-2.5 rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="all">Semua Kategori</option>
                        <option value="militer">Militer TNI AL</option>
                        <option value="sipil">Sipil / Mitra Kerja</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Status TTD Komandan</label>
                    <select v-model="statusFilter" @change="applyFilter" class="w-full text-xs font-semibold px-4 py-2.5 rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="all">Semua Status</option>
                        <option value="pending">Pending TTD Komandan</option>
                        <option value="approved">Resmi Disetujui (Terbit)</option>
                        <option value="rejected">Ditolak / Revisi</option>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button @click="applyFilter" class="flex-1 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold uppercase rounded-xl transition-all"> Filter
                    </button>
                    <button @click="resetFilter" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold uppercase rounded-xl transition-all"> Reset
                    </button>
                </div>
            </div>

            <!-- Table Container -->
            <div class="bg-white rounded-3xl shadow-xs border border-slate-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-400">
                                <th class="py-4 px-6">No. SKHPP & Tanggal</th>
                                <th class="py-4 px-6">Identitas Personel Utama</th>
                                <th class="py-4 px-6">Jenis & Peruntukan</th>
                                <th class="py-4 px-6">Anggota Pengikut</th>
                                <th class="py-4 px-6 text-center">Status TTD</th>
                                <th class="py-4 px-6 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-medium text-slate-700">
                            <tr v-for="skhpp in skhpps.data" :key="skhpp.id" class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-4 px-6">
                                    <div class="font-bold text-slate-900">
                                        {{ skhpp.nomor_skhpp || 'Draft (Pending Nomor)' }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5"> Diajukan: {{ formatDate(skhpp.submitted_at || skhpp.created_at) }}
                                    </div>
                                </td>

                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <img v-if="skhpp.foto_1" :src="'/storage/' + skhpp.foto_1" class="w-10 h-12 object-cover rounded-lg border border-slate-200 shadow-xs" />
                                        <div>
                                            <div class="font-bold text-slate-900">{{ skhpp.nama }}</div>
                                            <div class="text-[10px] text-blue-600 font-semibold uppercase">
                                                {{ skhpp.kategori_personel === 'militer' ? (skhpp.pangkat_korps_nrp || 'MILITER') : ('NIK: ' + (skhpp.nik || '-')) }}
                                            </div>
                                            <div class="text-[10px] text-slate-400">{{ skhpp.jabatan_pekerjaan }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-4 px-6">
                                    <span :class="skhpp.is_pernikahan ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'" class="px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-wider">
                                        {{ skhpp.is_pernikahan ? 'Pengajuan Nikah' : 'Kedinasan / General' }}
                                    </span>
                                    <div class="text-[10px] text-slate-500 mt-1 line-clamp-2 max-w-xs" :title="skhpp.peruntukan">
                                        {{ skhpp.peruntukan }}
                                    </div>
                                </td>

                                <td class="py-4 px-6">
                                    <span v-if="skhpp.has_pengikut && skhpp.members?.length" class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 text-amber-700 rounded-lg text-[10px] font-bold border border-amber-200/60">
                                         {{ skhpp.members.length }} Anggota (Ada Lampiran)
                                    </span>
                                    <span v-else class="text-slate-400 text-[10px]"> Perorangan (Tanpa Pengikut)
                                    </span>
                                </td>

                                <td class="py-4 px-6 text-center">
                                    <span v-if="skhpp.status === 'approved'" class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-[9px] font-black uppercase tracking-wider inline-flex items-center gap-1"> TERBIT (TTD QR)
                                    </span>
                                    <span v-else-if="skhpp.status === 'rejected'" class="px-3 py-1 bg-rose-100 text-rose-700 rounded-full text-[9px] font-black uppercase tracking-wider block"> REVISI / DITOLAK
                                    </span>
                                    <span v-else class="px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-[9px] font-black uppercase tracking-wider"> PENDING TTD
                                    </span>
                                    
                                    <div v-if="skhpp.catatan_revisi" class="text-[9px] text-rose-600 font-semibold mt-1 italic max-w-xs truncate" :title="skhpp.catatan_revisi"> Revisi: "{{ skhpp.catatan_revisi }}"
                                    </div>
                                </td>

                                <td class="py-4 px-6 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button @click="openAuditTrail(skhpp.nomor_skhpp || skhpp.nama)" class="px-2.5 py-1.5 bg-slate-100 text-slate-700 hover:bg-slate-700 hover:text-white rounded-xl text-[10px] font-bold uppercase transition-all shadow-xs flex items-center gap-1" title="Jejak Audit & Riwayat Dokumen">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            Audit
                                        </button>

                                        <a :href="route('skhpp.export-pdf', skhpp.id)" target="_blank" class="px-3 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white rounded-xl text-[10px] font-bold uppercase transition-all shadow-xs flex items-center gap-1" title="Unduh PDF DomPDF"> PDF
                                        </a>

                                        <Link :href="route('skhpp.show', skhpp.id)" class="px-3 py-1.5 bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white rounded-xl text-[10px] font-bold uppercase transition-all shadow-xs" title="Pratinjau Dokumen"> Detail
                                        </Link>

                                        <!-- Tombol Edit & Revisi untuk Operator jika Ditolak/Pending -->
                                        <Link v-if="skhpp.status === 'rejected' || skhpp.status === 'pending'" :href="route('skhpp.edit', skhpp.id)"class="px-3 py-1.5 bg-amber-50 text-amber-700 hover:bg-amber-500 hover:text-white rounded-xl text-[10px] font-bold uppercase transition-all shadow-xs"title="Edit Data & Ajukan Ulang"> Edit Data
                                        </Link>

                                        <!-- Tombol Khusus Admin untuk Atur Nomor & Ajukan TTE Ulang -->
                                        <template v-if="isAdmin">
                                            <button @click="changeNomorSkhpp(skhpp)"class="px-2.5 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white rounded-xl text-[10px] font-bold uppercase transition-all shadow-xs"title="Atur / Loncati Nomor Urut SKHPP (Khusus Admin)"> Atur Nomor
                                            </button>
                                            <button @click="reSubmitTte(skhpp)"class="px-2.5 py-1.5 bg-cyan-50 text-cyan-700 hover:bg-cyan-600 hover:text-white rounded-xl text-[10px] font-bold uppercase transition-all shadow-xs flex items-center gap-1"title="Ajukan Ulang TTE ke Komandan (Khusus Admin)"> Ajukan TTE Ulang
                                            </button>
                                        </template>

                                        <template v-if="isCommander && skhpp.status !== 'approved'">
                                            <button @click="approveSkhpp(skhpp)"class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-[10px] font-bold uppercase transition-all shadow-xs"title="Setujui & TTD QR Komandan"> TTD Komandan
                                            </button>
                                            <button @click="rejectSkhpp(skhpp)"class="px-3 py-1.5 bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white rounded-xl text-[10px] font-bold uppercase transition-all shadow-xs"title="Tolak Permohonan"> Tolak
                                            </button>
                                        </template>

                                        <button @click="deleteSkhpp(skhpp)"class="p-1.5 bg-slate-100 text-slate-400 hover:text-rose-600 rounded-xl transition-all"title="Hapus SKHPP">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!skhpps.data || skhpps.data.length === 0">
                                <td colspan="6" class="py-12 text-center text-slate-400 text-xs font-bold"> Belum ada permohonan penerbitan SKHPP terdaftar.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <AuditTrailModal :show="showAuditModal" :target="auditTarget" title="Jejak Audit SKHPP" @close="showAuditModal = false" />
    </AuthenticatedLayout>
</template>
