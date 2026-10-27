<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    submissions: Object,
    stats: Object,
    sintelStats: Object,
    sintelUsers: Array,
    otherUsers: Array,
    filters: Object,
    stages: Array,
});

const page = usePage();
const isAdmin = computed(() => Boolean(page.props.auth.user.is_admin));

const searchQuery = ref(props.filters?.search || '');
const selectedStage = ref(props.filters?.stage || 'all');
const selectedStatus = ref(props.filters?.status || 'all');
const selectedPengambilan = ref(props.filters?.pengambilan || 'all');
const selectedKategori = ref(props.filters?.kategori || 'all');
const selectedSort = ref(props.filters?.sort || 'terbaru');

const handleFilter = () => {
    router.get(route('sc-submissions.index'), {
        search: searchQuery.value,
        stage: selectedStage.value,
        status: selectedStatus.value,
        pengambilan: selectedPengambilan.value,
        kategori: selectedKategori.value,
        sort: selectedSort.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const setKategoriTab = (kat) => {
    selectedKategori.value = kat;
    handleFilter();
};

const resetFilter = () => {
    searchQuery.value = '';
    selectedStage.value = 'all';
    selectedStatus.value = 'all';
    selectedPengambilan.value = 'all';
    selectedKategori.value = 'all';
    selectedSort.value = 'terbaru';
    handleFilter();
};

const getExportPdfUrl = () => {
    const params = new URLSearchParams();
    if (searchQuery.value) params.append('search', searchQuery.value);
    if (selectedStage.value && selectedStage.value !== 'all') params.append('stage', selectedStage.value);
    if (selectedStatus.value && selectedStatus.value !== 'all') params.append('status', selectedStatus.value);
    if (selectedPengambilan.value && selectedPengambilan.value !== 'all') params.append('pengambilan', selectedPengambilan.value);
    if (selectedKategori.value && selectedKategori.value !== 'all') params.append('kategori', selectedKategori.value);
    if (selectedSort.value) params.append('sort', selectedSort.value);
    const qs = params.toString();
    return route('sc-submissions.pdf') + (qs ? '?' + qs : '');
};

// Modal Pencatatan Pengambilan SC
const isTakenModalOpen = ref(false);
const isSubmittingTaken = ref(false);
const activeSubmissionForTaken = ref(null);
const takenForm = ref({
    is_taken: 1,
    taken_at: '',
    tanggal_sc: '',
    nomor_sc: '',
    taken_by: '',
    catatan: '',
});

const openTakenModal = (sub) => {
    activeSubmissionForTaken.value = sub;
    isSubmittingTaken.value = false;

    const todayDate = new Date().toISOString().split('T')[0];
    const nowLocal = new Date();
    const localIso = new Date(nowLocal.getTime() - (nowLocal.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);

    takenForm.value = {
        is_taken: sub.is_taken ? 1 : 1,
        taken_at: sub.taken_at ? new Date(sub.taken_at).toISOString().slice(0, 16) : localIso,
        tanggal_sc: sub.tanggal_sc ? sub.tanggal_sc.slice(0, 10) : todayDate,
        nomor_sc: sub.nomor_sc || '',
        taken_by: sub.taken_by || sub.nama,
        catatan: '',
    };
    isTakenModalOpen.value = true;
};

const submitToggleTaken = () => {
    if (!activeSubmissionForTaken.value || isSubmittingTaken.value) return;
    isSubmittingTaken.value = true;

    router.post(route('sc-submissions.toggle-taken', activeSubmissionForTaken.value.id), takenForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            isTakenModalOpen.value = false;
            Swal.fire({
                title: 'Status Pengambilan Diperbarui',
                text: 'Catatan pengambilan berkas SC berhasil disimpan.',
                icon: 'success',
                confirmButtonColor: '#2563eb',
            });
        },
        onError: (err) => {
            Swal.fire({
                title: 'Gagal Memperbarui',
                text: Object.values(err)[0] || 'Terjadi gangguan saat menyimpan status pengambilan.',
                icon: 'error',
                confirmButtonColor: '#dc2626',
            });
        },
        onFinish: () => {
            isSubmittingTaken.value = false;
        }
    });
};

// Sinkronisasi SKHPP Terbit Otomatis
const isSyncingSkhpp = ref(false);
const syncSkhpp = () => {
    Swal.fire({
        title: 'Konfirmasi Sinkronisasi',
        text: 'Sinkronkan seluruh dokumen SKHPP yang telah disahkan Komandan Denintel (TTE) ke dalam daftar Pengajuan SC? Berkas yang disinkronkan akan langsung berada pada Tahap 5 (SKHPP Terbit).',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Sinkronkan!',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            isSyncingSkhpp.value = true;
            router.post(route('sc-submissions.sync-skhpp'), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        title: 'Sinkronisasi Berhasil',
                        text: 'Seluruh berkas SKHPP yang telah terbit berhasil disinkronkan.',
                        icon: 'success',
                        confirmButtonColor: '#2563eb',
                    });
                },
                onError: (err) => {
                    Swal.fire({
                        title: 'Gagal Sinkronisasi',
                        text: Object.values(err)[0] || 'Terjadi gangguan saat sinkronisasi berkas.',
                        icon: 'error',
                        confirmButtonColor: '#dc2626',
                    });
                },
                onFinish: () => {
                    isSyncingSkhpp.value = false;
                }
            });
        }
    });
};

// State & Logika Modal Kirim Notifikasi ke Staf Intel
const isNotifyModalOpen = ref(false);
const isSubmittingNotification = ref(false);
const notifyRecipientType = ref('user'); // 'user' | 'custom'
const selectedSintelUserId = ref('');
const notifyScope = ref('stage_6_9'); // 'stage_6_9' | 'stage_5_9'
const notifyCustomPangkat = ref('');
const notifyCustomNama = ref('');
const notifyCustomNrp = ref('');
const notifyCustomPhone = ref('');
const notifyCatatanTambahan = ref('');

const sintelUsersList = computed(() => props.sintelUsers || []);
const otherUsersList = computed(() => props.otherUsers || []);

const currentNotifyStats = computed(() => {
    if (notifyScope.value === 'stage_5_9') {
        return props.sintelStats?.stage_5_9 || { dinas: 0, perusahaan: 0, total: 0 };
    }
    return props.sintelStats?.stage_6_9 || { dinas: 0, perusahaan: 0, total: 0 };
});

const selectedUserObj = computed(() => {
    if (!selectedSintelUserId.value) return null;
    return [...sintelUsersList.value, ...otherUsersList.value].find(u => String(u.id) === String(selectedSintelUserId.value)) || null;
});

const onSelectUser = () => {
    if (selectedUserObj.value) {
        notifyCustomPangkat.value = selectedUserObj.value.pangkat || '';
        notifyCustomNama.value = selectedUserObj.value.name || '';
        notifyCustomNrp.value = selectedUserObj.value.nrp || '';
        notifyCustomPhone.value = selectedUserObj.value.phone || '';
    }
};

const formattedRecipient = computed(() => {
    const p = (notifyCustomPangkat.value || '').trim();
    const n = (notifyCustomNama.value || '').trim();
    const nrp = (notifyCustomNrp.value || '').trim();
    
    const parts = [];
    if (p) parts.push(p);
    if (n) parts.push(n);
    if (nrp) parts.push(`NRP ${nrp}`);
    
    if (parts.length === 0) return 'Yth. Personel Staf Intelijen';
    return `Yth. ${parts.join(' ')}`;
});

const generatedPreviewMessage = computed(() => {
    const stats = currentNotifyStats.value;
    const recipient = formattedRecipient.value;
    const trackingUrl = typeof window !== 'undefined' ? (window.location.origin + '/sc-submissions') : 'https://sinden2.my.id/sc-submissions';
    
    let msg = `*PEMBERITAHUAN BERKAS SECURITY CLEARANCE (SC)*\n*DETASEMEN INTELIJEN Koarmada 2*\n\n${recipient}\n\nDisampaikan informasi bahwa saat ini terdapat berkas pengajuan Security Clearance (SC) yang perlu ditindaklanjuti dan diperbarui oleh Staf Intelijen:\n\n- Berkas Dinas: ${stats.dinas} berkas\n- Berkas Perusahaan: ${stats.perusahaan} berkas\n- Total Berkas: ${stats.total} berkas\n\n`;
    
    if (notifyCatatanTambahan.value && notifyCatatanTambahan.value.trim()) {
        msg += `Catatan: ${notifyCatatanTambahan.value.trim()}\n\n`;
    }
    
    msg += `Mohon perkenan untuk melakukan pembaruan berkas melalui tautan sistem SINDEN:\n${trackingUrl}\n*(Silakan login menggunakan NRP dan Password Anda)*\n\nDemikian pemberitahuan ini disampaikan. Terima kasih.`;
    return msg;
});

const openNotifyModal = () => {
    if (sintelUsersList.value.length > 0 && !selectedSintelUserId.value) {
        selectedSintelUserId.value = sintelUsersList.value[0].id;
        onSelectUser();
    } else if (selectedUserObj.value) {
        onSelectUser();
    }
    isNotifyModalOpen.value = true;
};

const submitSendNotification = () => {
    if (isSubmittingNotification.value) return;
    
    const phone = (notifyCustomPhone.value || '').trim();
    const nama = (notifyCustomNama.value || '').trim();
    
    if (!nama) {
        Swal.fire({
            title: 'Nama Penerima Wajib Diisi',
            text: 'Silakan pilih personel atau masukkan nama penerima notifikasi.',
            icon: 'warning',
            confirmButtonColor: '#4f46e5',
        });
        return;
    }
    
    if (!phone) {
        Swal.fire({
            title: 'Nomor WhatsApp Wajib Diisi',
            text: 'Penerima belum memiliki nomor telepon WhatsApp. Silakan isi nomor WhatsApp tujuan.',
            icon: 'warning',
            confirmButtonColor: '#4f46e5',
        });
        return;
    }
    
    isSubmittingNotification.value = true;
    router.post(route('sc-submissions.notify-sintel'), {
        user_id: notifyRecipientType.value === 'user' ? selectedSintelUserId.value : null,
        pangkat: notifyCustomPangkat.value,
        nama: notifyCustomNama.value,
        nrp: notifyCustomNrp.value,
        phone: phone,
        scope: notifyScope.value,
        catatan_tambahan: notifyCatatanTambahan.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isNotifyModalOpen.value = false;
            Swal.fire({
                title: 'Notifikasi Terkirim',
                text: `Notifikasi rekap pengajuan berkas SC berhasil dikirim ke WhatsApp ${formattedRecipient.value} (${phone}).`,
                icon: 'success',
                confirmButtonColor: '#4f46e5',
            });
        },
        onError: (err) => {
            Swal.fire({
                title: 'Gagal Mengirim',
                text: Object.values(err)[0] || 'Terjadi kesalahan saat mengirim notifikasi ke server WhatsApp.',
                icon: 'error',
                confirmButtonColor: '#dc2626',
            });
        },
        onFinish: () => {
            isSubmittingNotification.value = false;
        }
    });
};

// Modal Tambah Pengajuan Baru
const isCreateModalOpen = ref(false);
const isSubmittingCreate = ref(false);
const createFileInputRef = ref(null);
const createForm = ref({
    kategori_sc: 'dinas',
    nama: '',
    pangkat_korps: '',
    identifier_type: 'nrp',
    identifier_number: '',
    kesatuan: '',
    jabatan: '',
    phone: '',
    keperluan: '',
    current_stage: 1,
    status: 'proses',
    catatan_petugas: '',
    nomor_surat_rh: '',
    nomor_skhpp: '',
    nomor_sc: '',
    file_skhpp: null,
});

const resetCreateForm = () => {
    createForm.value = {
        kategori_sc: 'dinas',
        nama: '',
        pangkat_korps: '',
        identifier_type: 'nrp',
        identifier_number: '',
        kesatuan: '',
        jabatan: '',
        phone: '',
        keperluan: '',
        current_stage: 1,
        status: 'proses',
        catatan_petugas: '',
        nomor_surat_rh: '',
        nomor_skhpp: '',
        nomor_sc: '',
        file_skhpp: null,
    };
    if (createFileInputRef.value) {
        createFileInputRef.value.value = '';
    }
    isSubmittingCreate.value = false;
};

const openCreateModal = () => {
    resetCreateForm();
    isCreateModalOpen.value = true;
};

const closeCreateModal = () => {
    isCreateModalOpen.value = false;
    resetCreateForm();
};

const handleCreateFileSkhpp = (e) => {
    createForm.value.file_skhpp = e.target.files[0] || null;
};

const submitCreate = () => {
    if (isSubmittingCreate.value) return;
    isSubmittingCreate.value = true;

    router.post(route('sc-submissions.store'), createForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            isCreateModalOpen.value = false;
            resetCreateForm();
            Swal.fire({
                title: 'Berhasil Didaftarkan',
                text: 'Pengajuan Security Clearance baru berhasil dicatat ke sistem.',
                icon: 'success',
                confirmButtonColor: '#2563eb',
            });
        },
        onError: (err) => {
            Swal.fire({
                title: 'Gagal Mendaftarkan',
                text: Object.values(err)[0] || 'Gagal mendaftarkan berkas pengajuan.',
                icon: 'error',
                confirmButtonColor: '#dc2626',
            });
        },
        onFinish: () => {
            isSubmittingCreate.value = false;
        }
    });
};

// Modal Pembaruan Tahapan Cepat (Quick Stage Update)
const isUpdateStageModalOpen = ref(false);
const isSubmittingStage = ref(false);
const activeSubmissionForStage = ref(null);
const stageForm = ref({
    stage: 1,
    notes: '',
    status: 'proses',
    nomor_skhpp: '',
    nomor_sc: '',
    file_skhpp: null,
    file_sc_preview: null,
});

const openUpdateStageModal = (sub) => {
    activeSubmissionForStage.value = sub;
    isSubmittingStage.value = false;
    stageForm.value = {
        stage: sub.current_stage,
        notes: '',
        status: sub.status || 'proses',
        nomor_skhpp: sub.nomor_skhpp || '',
        nomor_sc: sub.nomor_sc || '',
        file_skhpp: null,
        file_sc_preview: null,
    };
    isUpdateStageModalOpen.value = true;
};

const handleStageFileSkhpp = (e) => {
    stageForm.value.file_skhpp = e.target.files[0] || null;
};

const handleStageFileScPreview = (e) => {
    stageForm.value.file_sc_preview = e.target.files[0] || null;
};

const advanceToNextStage = () => {
    if (stageForm.value.stage < 10) {
        stageForm.value.stage++;
        if (stageForm.value.stage === 10) {
            stageForm.value.status = 'selesai';
        }
    }
};

const submitUpdateStage = () => {
    if (!activeSubmissionForStage.value || isSubmittingStage.value) return;
    isSubmittingStage.value = true;

    router.post(route('sc-submissions.update-stage', activeSubmissionForStage.value.id), stageForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            isUpdateStageModalOpen.value = false;
            Swal.fire({
                title: 'Tahapan Diperbarui',
                text: 'Perubahan tahapan berkas SC berhasil disimpan.',
                icon: 'success',
                confirmButtonColor: '#2563eb',
            });
        },
        onError: (err) => {
            Swal.fire({
                title: 'Gagal Memperbarui Tahapan',
                text: Object.values(err)[0] || 'Terjadi kesalahan saat memperbarui tahapan.',
                icon: 'error',
                confirmButtonColor: '#dc2626',
            });
        },
        onFinish: () => {
            isSubmittingStage.value = false;
        }
    });
};

// Modal Petinjau Hasil SC (Untuk Petugas / Admin)
const isPreviewModalOpen = ref(false);
const activeSubmissionForPreview = ref(null);
const previewPdfUrl = computed(() => {
    if (!activeSubmissionForPreview.value || !activeSubmissionForPreview.value.is_sc_preview_available) return null;
    return route('tracking-sc.preview-pdf', activeSubmissionForPreview.value.tracking_code) + '#toolbar=0&navpanes=0&scrollbar=1';
});

const openPreviewModal = (sub) => {
    activeSubmissionForPreview.value = sub;
    isPreviewModalOpen.value = true;
};

const closePreviewModal = () => {
    isPreviewModalOpen.value = false;
    activeSubmissionForPreview.value = null;
};

// Pengamanan Anti-Download: blokir shortcut Ctrl+S, Ctrl+P, F12
const handleKeydown = (e) => {
    if (!isPreviewModalOpen.value) return;
    if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S' || e.key === 'p' || e.key === 'P')) {
        e.preventDefault();
        Swal.fire({
            title: 'Peringatan Kedinasan',
            text: 'Dokumen ini berstatus petinjau sementara dan tidak diizinkan untuk diunduh maupun dicetak.',
            icon: 'warning',
            confirmButtonColor: '#2563eb',
            confirmButtonText: 'Siap, Dimengerti',
        });
    }
    if (e.key === 'PrintScreen') {
        Swal.fire({
            title: 'Peringatan Kedinasan',
            text: 'Fitur tangkapan layar dibatasi untuk berkas petinjau intelijen.',
            icon: 'warning',
            confirmButtonColor: '#2563eb',
            confirmButtonText: 'Siap, Dimengerti',
        });
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});

// Modal Detail / Riwayat Logs
const isLogsModalOpen = ref(false);
const activeSubmissionForLogs = ref(null);

const openLogsModal = (sub) => {
    activeSubmissionForLogs.value = sub;
    isLogsModalOpen.value = true;
};

// Modal Edit Detail Pemohon
const isEditModalOpen = ref(false);
const isSubmittingEdit = ref(false);
const editForm = ref({});

const openEditModal = (sub) => {
    isSubmittingEdit.value = false;
    editForm.value = {
        id: sub.id,
        kategori_sc: sub.kategori_sc || 'dinas',
        nama: sub.nama,
        pangkat_korps: sub.pangkat_korps || '',
        identifier_type: sub.identifier_type,
        identifier_number: sub.identifier_number,
        kesatuan: sub.kesatuan || '',
        jabatan: sub.jabatan || '',
        phone: sub.phone || '',
        keperluan: sub.keperluan || '',
        status: sub.status,
        nomor_surat_rh: sub.nomor_surat_rh || '',
        nomor_skhpp: sub.nomor_skhpp || '',
        nomor_sc: sub.nomor_sc || '',
    };
    isEditModalOpen.value = true;
};

const submitEdit = () => {
    if (isSubmittingEdit.value) return;
    isSubmittingEdit.value = true;

    router.put(route('sc-submissions.update', editForm.value.id), editForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            isEditModalOpen.value = false;
            Swal.fire({
                title: 'Perubahan Disimpan',
                text: 'Data pemohon SC berhasil diperbarui.',
                icon: 'success',
                confirmButtonColor: '#2563eb',
            });
        },
        onError: (err) => {
            Swal.fire({
                title: 'Gagal Mengubah Data',
                text: Object.values(err)[0] || 'Terjadi kesalahan saat menyimpan perubahan.',
                icon: 'error',
                confirmButtonColor: '#dc2626',
            });
        },
        onFinish: () => {
            isSubmittingEdit.value = false;
        }
    });
};

// Hapus Berkas dengan SweetAlert2
const deleteSubmission = (sub) => {
    Swal.fire({
        title: 'Konfirmasi Penghapusan',
        html: `<p class="text-sm text-slate-600">Yakin ingin menghapus seluruh data pengajuan SC atas nama <b class="text-slate-900">${sub.nama}</b> (<span class="font-mono font-bold text-blue-600">${sub.tracking_code}</span>)?</p><p class="text-xs text-rose-500 mt-2 font-semibold">Tindakan ini akan menghapus berkas PDF terkait dari server dan tidak dapat dibatalkan.</p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Hapus Berkas!',
        cancelButtonText: 'Batal',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('sc-submissions.destroy', sub.id), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        title: 'Berhasil Dihapus',
                        text: `Data pengajuan SC atas nama ${sub.nama} telah berhasil dihapus.`,
                        icon: 'success',
                        confirmButtonColor: '#2563eb',
                    });
                },
                onError: (err) => {
                    Swal.fire({
                        title: 'Gagal Menghapus',
                        text: Object.values(err)[0] || 'Gagal menghapus data pengajuan.',
                        icon: 'error',
                        confirmButtonColor: '#dc2626',
                    });
                }
            });
        }
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
};

const formatDateTime = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }) + ' WIB';
};
</script>

<template>
    <Head title="Manajemen Pengajuan Security Clearance (SC) - SINDEN" />

    <AuthenticatedLayout>
        <div class="space-y-5 font-sans">
            
            <!-- Hero Header Banner -->
            <div class="bg-white p-5 sm:p-6 rounded-3xl border border-[#E2E8F0] shadow-xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 bg-blue-50 text-blue-600 font-extrabold text-[10px] uppercase rounded-full tracking-wider border border-blue-100">Layanan Dokumen Intelijen</span>
                            <span class="text-slate-400 text-xs font-semibold">10 Tahapan Operasional SC</span>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                            Manajemen Pengajuan Security Clearance (SC)
                        </h1>
                        <p class="text-xs text-slate-500 font-medium leading-relaxed max-w-3xl">
                            Pengelolaan alur berkas SC terpadu mulai dari Pengisian RH, Verifikasi Denintel, Integrasi SKHPP TTE/Basah, Proses Sintel, Petinjau Softfile (2x24 Jam), hingga Penerbitan Dokumen di Mako Koarmada 2.
                        </p>
                    </div>

                    <!-- Tombol Utama Pendaftaran Berkas -->
                    <div class="self-start sm:self-center shrink-0">
                        <button 
                            @click="openCreateModal"
                            class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-xs uppercase tracking-wider transition shadow-md shadow-blue-500/20 flex items-center gap-2 cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                            </svg>
                            <span>Daftarkan Berkas SC</span>
                        </button>
                    </div>
                </div>

                <!-- Bilah Aksi & Utilitas Terpadu -->
                <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Halaman Publik Tanpa Login -->
                        <a 
                            :href="route('tracking-sc.index')" 
                            target="_blank"
                            class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs uppercase tracking-wider transition flex items-center gap-1.5 border border-slate-200/80"
                            title="Buka halaman tracking publik tanpa login"
                        >
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            <span>Halaman Publik</span>
                        </a>

                        <!-- Tombol Cetak Agenda SC (PDF) -->
                        <a 
                            :href="getExportPdfUrl()" 
                            target="_blank"
                            class="px-3.5 py-2 bg-red-700 hover:bg-red-800 text-white rounded-xl font-bold text-xs uppercase tracking-wider transition shadow-sm flex items-center gap-1.5 cursor-pointer"
                            title="Cetak Buku Agenda Pengambilan SC format PDF dinas"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Cetak Agenda SC (PDF)</span>
                        </a>

                        <!-- Tombol Sinkronisasi SKHPP Terbit -->
                        <button 
                            @click="syncSkhpp"
                            :disabled="isSyncingSkhpp"
                            class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs uppercase tracking-wider transition shadow-sm flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                            title="Tarik & sinkronkan data SKHPP yang telah disahkan Komandan Denintel (TTE)"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span>{{ isSyncingSkhpp ? 'Menyinkronkan...' : 'Sinkronkan SKHPP Terbit' }}</span>
                        </button>

                        <!-- Tombol Kirim Notifikasi ke Staf Intel -->
                        <button 
                            @click="openNotifyModal"
                            class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs uppercase tracking-wider transition shadow-sm flex items-center gap-1.5 cursor-pointer"
                            title="Kirim notifikasi rekap pengajuan berkas SC ke WhatsApp Staf Intel"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            <span>Kirim Notifikasi</span>
                        </button>
                    </div>

                    <div class="text-[11px] text-slate-400 font-semibold tracking-wide hidden sm:block">
                        Detasemen Intelijen Koarmada 2
                    </div>
                </div>
            </div>

            <!-- Kartu Statistik Alur Berkas -->
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2.5 sm:gap-3">
                <div class="bg-white p-3.5 sm:p-4 rounded-2xl border border-[#E2E8F0] shadow-xs space-y-1">
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-slate-400 block truncate">Total Berkas</span>
                    <h3 class="text-xl font-black text-slate-900">{{ stats.total || 0 }}</h3>
                    <p class="text-[10px] text-slate-500 font-medium truncate">Seluruh Pengajuan</p>
                </div>

                <div class="bg-white p-3.5 sm:p-4 rounded-2xl border border-[#E2E8F0] shadow-xs space-y-1">
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-blue-500 block truncate">Proses</span>
                    <h3 class="text-xl font-black text-blue-600">{{ stats.in_progress || 0 }}</h3>
                    <p class="text-[10px] text-slate-500 font-medium truncate">Tahapan Berjalan</p>
                </div>

                <div class="bg-white p-3.5 sm:p-4 rounded-2xl border border-[#E2E8F0] shadow-xs space-y-1">
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-amber-500 block truncate">Di Denintel</span>
                    <h3 class="text-xl font-black text-amber-600">{{ stats.at_denintel || 0 }}</h3>
                    <p class="text-[10px] text-slate-500 font-medium truncate">Tahap 1 s/d 5</p>
                </div>

                <div class="bg-white p-3.5 sm:p-4 rounded-2xl border border-[#E2E8F0] shadow-xs space-y-1">
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-indigo-500 block truncate">Di Sintel</span>
                    <h3 class="text-xl font-black text-indigo-600">{{ stats.at_sintel || 0 }}</h3>
                    <p class="text-[10px] text-slate-500 font-medium truncate">Tahap 6 s/d 9</p>
                </div>

                <div class="bg-white p-3.5 sm:p-4 rounded-2xl border border-[#E2E8F0] shadow-xs space-y-1">
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-emerald-500 block truncate">SC Selesai</span>
                    <h3 class="text-xl font-black text-emerald-600">{{ stats.completed || 0 }}</h3>
                    <p class="text-[10px] text-slate-500 font-medium truncate">Tahap 10 Terbit</p>
                </div>

                <div class="bg-teal-50/40 p-3.5 sm:p-4 rounded-2xl border border-teal-200/80 shadow-xs space-y-1">
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-teal-700 block truncate">Sudah Diambil</span>
                    <h3 class="text-xl font-black text-teal-700">{{ stats.sudah_diambil || 0 }}</h3>
                    <p class="text-[10px] text-teal-600/80 font-medium truncate">Telah Diserahkan</p>
                </div>

                <div class="bg-rose-50/40 p-3.5 sm:p-4 rounded-2xl border border-rose-200/80 shadow-xs space-y-1">
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-rose-600 block truncate">Belum Diambil</span>
                    <h3 class="text-xl font-black text-rose-600">{{ stats.belum_diambil || 0 }}</h3>
                    <p class="text-[10px] text-rose-600/80 font-medium truncate">Menunggu Personel</p>
                </div>
            </div>

            <!-- Filter & Pencarian Bar -->
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-[#E2E8F0] shadow-xs flex flex-col lg:flex-row gap-3 items-stretch lg:items-center justify-between">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2 flex-1">
                    <!-- Pencarian NIK / NIP / NRP / Nama -->
                    <div class="relative sm:col-span-2 lg:col-span-1">
                        <input 
                            type="text" 
                            v-model="searchQuery"
                            @keyup.enter="handleFilter"
                            placeholder="Cari NIK / NIP / NRP atau Nama Pemohon, No. SC..." 
                            class="w-full pl-9 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:bg-white focus:ring-2 focus:ring-blue-500"
                        />
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>

                    <!-- Filter Kategori SC: Dinas (SKHPP-D) vs Perusahaan (SKHPP-P) -->
                    <select 
                        v-model="selectedKategori" 
                        @change="handleFilter"
                        class="text-xs font-semibold py-2.5 px-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white"
                    >
                        <option value="all">Semua Kategori SC</option>
                        <option value="dinas">SC Dinas (SKHPP-D)</option>
                        <option value="perusahaan">SC Perusahaan (SKHPP-P)</option>
                    </select>

                    <!-- Filter Tahapan -->
                    <select 
                        v-model="selectedStage" 
                        @change="handleFilter"
                        class="text-xs font-semibold py-2.5 px-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white"
                    >
                        <option value="all">Semua Tahapan (1-10)</option>
                        <option v-for="stg in stages" :key="stg.id" :value="stg.id">
                            Tahap {{ stg.id }}: {{ stg.title }}
                        </option>
                    </select>

                    <!-- Filter Pengambilan SC -->
                    <select 
                        v-model="selectedPengambilan" 
                        @change="handleFilter"
                        class="text-xs font-semibold py-2.5 px-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white"
                    >
                        <option value="all">Semua Pengambilan</option>
                        <option value="belum_diambil">Belum Diambil</option>
                        <option value="sudah_diambil">Sudah Diambil</option>
                    </select>

                    <!-- Urutkan / Sort (Default: Terbaru ke Terlama) -->
                    <select 
                        v-model="selectedSort" 
                        @change="handleFilter"
                        class="text-xs font-semibold py-2.5 px-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white"
                    >
                        <option value="terbaru">Urutan: Terbaru (Terbaru di Atas)</option>
                        <option value="terlama">Urutan: Terlama (Terlama di Atas)</option>
                        <option value="nama_asc">Nama (A - Z)</option>
                        <option value="nama_desc">Nama (Z - A)</option>
                        <option value="nomor_sc">Nomor SC</option>
                        <option value="tanggal_sc">Tanggal SC</option>
                        <option value="tanggal_diambil">Tanggal Diambil</option>
                    </select>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <button 
                        @click="handleFilter"
                        class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-extrabold uppercase tracking-wider transition cursor-pointer"
                    >
                        Terapkan
                    </button>
                    <button 
                        @click="resetFilter"
                        class="px-3 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-extrabold transition cursor-pointer"
                        title="Reset Filter"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- Segment Tabs Kategori SC (SC Dinas SKHPP-D vs SC Perusahaan SKHPP-P) -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-1.5 p-1 bg-slate-100/90 rounded-2xl border border-slate-200/80">
                    <button 
                        type="button"
                        @click="setKategoriTab('all')"
                        :class="[
                            selectedKategori === 'all' 
                                ? 'bg-white text-slate-900 shadow-xs font-black' 
                                : 'text-slate-600 hover:text-slate-900 font-bold hover:bg-white/60'
                        ]"
                        class="px-3.5 py-2 rounded-xl text-xs uppercase tracking-wider transition-all flex items-center gap-2 cursor-pointer"
                    >
                        <span>Semua Pengajuan SC</span>
                        <span 
                            :class="selectedKategori === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-200 text-slate-700'"
                            class="px-2 py-0.5 rounded-full text-[10px] font-mono font-extrabold"
                        >
                            {{ stats.total || 0 }}
                        </span>
                    </button>

                    <button 
                        type="button"
                        @click="setKategoriTab('dinas')"
                        :class="[
                            selectedKategori === 'dinas' 
                                ? 'bg-blue-600 text-white shadow-xs font-black' 
                                : 'text-slate-600 hover:text-slate-900 font-bold hover:bg-white/60'
                        ]"
                        class="px-3.5 py-2 rounded-xl text-xs uppercase tracking-wider transition-all flex items-center gap-2 cursor-pointer"
                    >
                        <span class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            SC Dinas (SKHPP-D)
                        </span>
                        <span 
                            :class="selectedKategori === 'dinas' ? 'bg-white text-blue-700' : 'bg-slate-200 text-slate-700'"
                            class="px-2 py-0.5 rounded-full text-[10px] font-mono font-extrabold"
                        >
                            {{ stats.total_dinas || 0 }}
                        </span>
                    </button>

                    <button 
                        type="button"
                        @click="setKategoriTab('perusahaan')"
                        :class="[
                            selectedKategori === 'perusahaan' 
                                ? 'bg-indigo-600 text-white shadow-xs font-black' 
                                : 'text-slate-600 hover:text-slate-900 font-bold hover:bg-white/60'
                        ]"
                        class="px-3.5 py-2 rounded-xl text-xs uppercase tracking-wider transition-all flex items-center gap-2 cursor-pointer"
                    >
                        <span class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            SC Perusahaan (SKHPP-P)
                        </span>
                        <span 
                            :class="selectedKategori === 'perusahaan' ? 'bg-white text-indigo-700' : 'bg-slate-200 text-slate-700'"
                            class="px-2 py-0.5 rounded-full text-[10px] font-mono font-extrabold"
                        >
                            {{ stats.total_perusahaan || 0 }}
                        </span>
                    </button>
                </div>

                <div class="text-[11px] font-bold text-slate-500 hidden sm:block">
                    Menampilkan: 
                    <span class="text-slate-900 font-black">
                        {{ selectedKategori === 'dinas' ? 'SC Dinas (SKHPP-D) - Personel Militer/PNS' : (selectedKategori === 'perusahaan' ? 'SC Perusahaan (SKHPP-P) - Rekanan/Swasta' : 'Semua Kategori Berkas SC') }}
                    </span>
                </div>
            </div>

            <!-- Tabel Daftar Berkas Pengajuan SC -->
            <div class="bg-white rounded-3xl border border-[#E2E8F0] shadow-xs overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <h3 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider">
                            Daftar Berkas Pengajuan Security Clearance
                        </h3>
                        <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[10px] font-extrabold">
                            {{ selectedKategori === 'dinas' ? 'SKHPP-D' : (selectedKategori === 'perusahaan' ? 'SKHPP-P' : 'Dinas & Perusahaan') }}
                        </span>
                    </div>
                    <span class="text-xs font-mono font-bold text-slate-400">
                        Total: {{ submissions.total || 0 }} Berkas
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[940px] text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-100 text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">
                                <th class="p-3.5 pl-5 w-[210px] sticky left-0 bg-slate-50/95 backdrop-blur-xs z-10 shadow-[6px_0_10px_-4px_rgba(0,0,0,0.06)]">Kode / Pemohon / No. SC</th>
                                <th class="p-3.5 w-[130px]">Kesatuan & Keperluan</th>
                                <th class="p-3.5 w-[160px]">Tahapan Terkini (1-10)</th>
                                <th class="p-3.5 w-[125px]">Integrasi Dokumen</th>
                                <th class="p-3.5 w-[85px] text-center">Status SC</th>
                                <th class="p-3.5 w-[120px]">Pengambilan</th>
                                <th class="p-3.5 w-[80px]">Tanggal</th>
                                <th class="p-3.5 pr-5 text-right sticky right-0 bg-slate-50/95 backdrop-blur-xs z-10 shadow-[-6px_0_10px_-4px_rgba(0,0,0,0.06)] min-w-[135px]">Aksi Kedinasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                            <tr v-for="sub in submissions.data" :key="sub.id" class="hover:bg-slate-50/70 transition">
                                <!-- Kode, Kategori & Pemohon (Sticky Left agar identitas selalu terlihat) -->
                                <td class="p-3.5 pl-5 sticky left-0 bg-white/95 backdrop-blur-xs z-10 shadow-[6px_0_10px_-4px_rgba(0,0,0,0.06)]">
                                    <div class="flex flex-wrap items-center gap-1.5 mb-1">
                                        <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 bg-slate-100 text-slate-700 rounded-md">
                                            {{ sub.tracking_code }}
                                        </span>
                                        <span v-if="sub.kategori_sc === 'perusahaan'" class="text-[9px] font-black px-1.5 py-0.5 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-md" title="Security Clearance Perusahaan / Rekanan Swasta">
                                            SC-P (Perusahaan)
                                        </span>
                                        <span v-else class="text-[9px] font-black px-1.5 py-0.5 bg-blue-50 text-blue-700 border border-blue-200 rounded-md" title="Security Clearance Dinas Militer TNI AL / PNS">
                                            SC-D (Dinas)
                                        </span>
                                    </div>
                                    <div v-if="sub.nomor_sc" class="font-mono text-[9px] font-extrabold px-1.5 py-0.5 bg-blue-50 text-blue-800 border border-blue-200 rounded-md inline-block mb-1">
                                        No. SC: {{ sub.nomor_sc }}
                                    </div>
                                    <div class="font-extrabold text-slate-900 text-xs truncate max-w-[200px]" :title="sub.nama">
                                        {{ sub.nama }}
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-mono truncate max-w-[200px]">
                                        {{ sub.pangkat_korps ? sub.pangkat_korps + ' - ' : '' }}{{ sub.identifier_type.toUpperCase() }}. {{ sub.identifier_number }}
                                    </div>
                                    <div v-if="sub.tanggal_sc" class="text-[9px] text-slate-400 font-mono mt-0.5">
                                        Tgl SC: {{ sub.tanggal_sc_formatted || formatDate(sub.tanggal_sc) }}
                                    </div>
                                </td>

                                <!-- Kesatuan & Keperluan -->
                                <td class="p-3.5">
                                    <span class="font-bold text-slate-800 text-[11px] block truncate max-w-[130px]" :title="sub.kesatuan">{{ sub.kesatuan || '-' }}</span>
                                    <span class="text-[10px] text-slate-500 block truncate max-w-[130px]" :title="sub.keperluan">{{ sub.keperluan || 'Kedinasan' }}</span>
                                </td>

                                <!-- Tahapan Terkini -->
                                <td class="p-3.5">
                                    <div class="space-y-1 max-w-[165px]">
                                        <div class="flex items-center justify-between text-[10px]">
                                            <span class="font-extrabold truncate pr-1" :class="sub.current_stage === 10 ? 'text-emerald-700' : 'text-blue-700'" :title="`Tahap ${sub.current_stage}: ${sub.stage_title}`">
                                                Tahap {{ sub.current_stage }}: {{ sub.stage_title }}
                                            </span>
                                            <span class="font-mono font-bold text-slate-400 shrink-0">{{ sub.current_stage }}/10</span>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                            <div 
                                                :style="{ width: sub.progress_percentage + '%' }"
                                                :class="sub.current_stage === 10 ? 'bg-emerald-500' : 'bg-blue-600'"
                                                class="h-full rounded-full transition-all duration-300"
                                            ></div>
                                        </div>
                                        <span v-if="sub.catatan_petugas" class="text-[9px] text-slate-400 italic truncate block max-w-[160px]" :title="sub.catatan_petugas">
                                            "{{ sub.catatan_petugas }}"
                                        </span>
                                    </div>
                                </td>

                                <!-- Integrasi & Dokumen Terlampir -->
                                <td class="p-3.5 space-y-1">
                                    <!-- Status SKHPP -->
                                    <div class="flex items-center gap-1">
                                        <span class="text-[9px] text-slate-400 font-bold uppercase shrink-0">SKHPP:</span>
                                        <span v-if="sub.skhpp_id" class="px-1.5 py-0.5 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-700 font-extrabold text-[9px] flex items-center gap-1 truncate">
                                            <svg class="w-2.5 h-2.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                            TTE Otomatis
                                        </span>
                                        <a v-else-if="sub.file_skhpp_url" :href="sub.file_skhpp_url" target="_blank" class="px-1.5 py-0.5 rounded-md bg-blue-50 border border-blue-200 text-blue-700 font-extrabold text-[9px] hover:underline flex items-center gap-1 truncate">
                                            <svg class="w-2.5 h-2.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            TTD Basah
                                        </a>
                                        <span v-else class="text-[9px] text-slate-400 font-medium">Belum Ada</span>
                                    </div>

                                    <!-- Status Petinjau SC Sintel -->
                                    <div class="flex items-center gap-1">
                                        <span class="text-[9px] text-slate-400 font-bold uppercase shrink-0">Sintel:</span>
                                        <button 
                                            v-if="sub.is_sc_preview_available"
                                            @click="openPreviewModal(sub)"
                                            class="px-1.5 py-0.5 rounded-md bg-indigo-50 border border-indigo-200 text-indigo-700 font-extrabold text-[9px] hover:bg-indigo-600 hover:text-white transition flex items-center gap-1 cursor-pointer truncate"
                                            title="Lihat Petinjau Dokumen SC Sintel"
                                        >
                                            <svg class="w-2.5 h-2.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            Petinjau ({{ sub.sc_preview_remaining_hours }}j)
                                        </button>
                                        <span v-else-if="sub.sc_preview_expired_at" class="text-[9px] text-rose-500 font-bold">
                                            Kadaluarsa
                                        </span>
                                        <span v-else class="text-[9px] text-slate-400 font-medium">-</span>
                                    </div>
                                </td>

                                <!-- Status SC -->
                                <td class="p-3.5 text-center">
                                    <span :class="{
                                        'bg-emerald-100 text-emerald-800': sub.status === 'selesai' || sub.current_stage === 10,
                                        'bg-blue-100 text-blue-800': sub.status === 'proses' && sub.current_stage < 10,
                                        'bg-amber-100 text-amber-800': sub.status === 'perbaikan',
                                        'bg-rose-100 text-rose-800': sub.status === 'ditolak',
                                    }" class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider inline-block">
                                        {{ (sub.status || 'proses').toUpperCase() }}
                                    </span>
                                </td>

                                <!-- Status Pengambilan -->
                                <td class="p-3.5">
                                    <div v-if="sub.is_taken" class="space-y-1">
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-teal-100 text-teal-800 inline-flex items-center gap-1">
                                            <svg class="w-2.5 h-2.5 text-teal-700" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            Sudah Diambil
                                        </span>
                                        <div class="text-[10px] text-slate-700 font-mono font-bold">
                                            {{ sub.taken_at_formatted || formatDate(sub.taken_at) }}
                                        </div>
                                        <div v-if="sub.taken_by" class="text-[9px] text-slate-500 truncate max-w-[110px]" :title="sub.taken_by">
                                            Oleh: {{ sub.taken_by }}
                                        </div>
                                        <button 
                                            @click="openTakenModal(sub)"
                                            class="text-[9px] text-blue-600 hover:underline font-bold block cursor-pointer"
                                        >
                                            Ubah
                                        </button>
                                    </div>
                                    <div v-else class="space-y-1">
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-slate-100 text-slate-600 inline-block">
                                            Belum Diambil
                                        </span>
                                        <button 
                                            @click="openTakenModal(sub)"
                                            class="px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 rounded-lg text-[9px] font-extrabold uppercase tracking-wider transition cursor-pointer flex items-center gap-1"
                                            title="Catat pengambilan SC oleh pemohon"
                                        >
                                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            Tandai Diambil
                                        </button>
                                    </div>
                                </td>

                                <!-- Tanggal Berkas -->
                                <td class="p-3.5 font-mono text-slate-500 text-[10px]">
                                    {{ formatDate(sub.created_at) }}
                                </td>

                                <!-- Tombol Aksi (Sticky Right agar selalu terlihat) -->
                                <td class="p-3.5 pr-5 text-right sticky right-0 bg-white/95 backdrop-blur-xs z-10 shadow-[-6px_0_10px_-4px_rgba(0,0,0,0.06)]">
                                    <div class="flex items-center justify-end gap-1">
                                        <!-- Update Tahapan Cepat -->
                                        <button 
                                            @click="openUpdateStageModal(sub)"
                                            class="px-2 py-1.5 bg-blue-50 hover:bg-blue-600 hover:text-white text-blue-700 border border-blue-200 rounded-lg text-[10px] font-extrabold uppercase tracking-wider transition cursor-pointer"
                                            title="Perbarui tahapan berkas"
                                        >
                                            Update
                                        </button>

                                        <!-- Catat Pengambilan -->
                                        <button 
                                            @click="openTakenModal(sub)"
                                            class="p-1.5 bg-teal-50 hover:bg-teal-600 hover:text-white text-teal-700 border border-teal-200 rounded-lg transition cursor-pointer"
                                            title="Pencatatan status pengambilan SC"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>

                                        <!-- Riwayat Log -->
                                        <button 
                                            @click="openLogsModal(sub)"
                                            class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg transition cursor-pointer"
                                            title="Lihat riwayat linimasa berkas"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>

                                        <!-- Edit Data -->
                                        <button 
                                            @click="openEditModal(sub)"
                                            class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg transition cursor-pointer"
                                            title="Edit data pemohon"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                        </button>

                                        <!-- Hapus (Admin Only) -->
                                        <button 
                                            v-if="isAdmin"
                                            @click="deleteSubmission(sub)"
                                            class="p-1.5 bg-rose-50 hover:bg-rose-600 hover:text-white text-rose-600 rounded-lg transition cursor-pointer"
                                            title="Hapus berkas"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!submissions.data || submissions.data.length === 0">
                                <td colspan="8" class="p-12 text-center text-xs text-slate-400 font-semibold italic">
                                    Belum ada berkas pengajuan Security Clearance yang terdaftar.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Links -->
                <div v-if="submissions.links && submissions.links.length > 3" class="p-4 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500 font-medium">
                        Menampilkan {{ submissions.from || 0 }} - {{ submissions.to || 0 }} dari {{ submissions.total || 0 }} berkas
                    </span>
                    <div class="flex items-center gap-1">
                        <Link 
                            v-for="(lnk, lIdx) in submissions.links" 
                            :key="lIdx"
                            :href="lnk.url || '#'"
                            :class="[
                                lnk.active ? 'bg-blue-600 text-white font-black' : 'bg-slate-50 text-slate-700 hover:bg-slate-100',
                                !lnk.url ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'
                            ]"
                            class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs transition"
                            v-html="lnk.label"
                        ></Link>
                    </div>
                </div>
            </div>

        </div>

        <!-- MODAL DAFTARKAN PENGAJUAN SC BARU -->
        <Teleport to="body">
            <div v-if="isCreateModalOpen" class="fixed inset-0 z-[160] bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto animate-in fade-in duration-150">
                <div class="bg-white w-full max-w-xl rounded-3xl shadow-2xl border border-slate-100 overflow-hidden my-auto animate-in zoom-in-95 duration-150">
                    <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-blue-600 text-white flex items-center justify-center font-black shadow-xs">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                                </svg>
                            </div>
                            <div>
                                <span class="text-[10px] font-black uppercase text-blue-600 tracking-wider block">Registrasi Berkas Baru</span>
                                <h3 class="text-base font-extrabold text-slate-900">Pendaftaran Pengajuan SC</h3>
                            </div>
                        </div>
                        <button @click="closeCreateModal" class="w-8 h-8 rounded-xl bg-slate-200/70 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition cursor-pointer">
                            &times;
                        </button>
                    </div>

                    <form @submit.prevent="submitCreate" class="p-5 sm:p-6 space-y-4 text-xs font-semibold">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Kategori SC (Dinas vs Perusahaan) -->
                            <div class="space-y-1 sm:col-span-2">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Kategori Security Clearance *</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <label 
                                        :class="createForm.kategori_sc === 'dinas' ? 'bg-blue-50 border-blue-500 text-blue-800 ring-2 ring-blue-500/20' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'"
                                        class="p-2.5 rounded-xl border flex items-center gap-2.5 cursor-pointer transition"
                                    >
                                        <input type="radio" v-model="createForm.kategori_sc" value="dinas" class="text-blue-600 focus:ring-blue-500" />
                                        <div>
                                            <div class="font-extrabold text-xs">SC Dinas (SKHPP-D)</div>
                                            <div class="text-[9px] text-slate-400">Militer TNI AL & ASN / PNS</div>
                                        </div>
                                    </label>
                                    <label 
                                        :class="createForm.kategori_sc === 'perusahaan' ? 'bg-indigo-50 border-indigo-500 text-indigo-800 ring-2 ring-indigo-500/20' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'"
                                        class="p-2.5 rounded-xl border flex items-center gap-2.5 cursor-pointer transition"
                                    >
                                        <input type="radio" v-model="createForm.kategori_sc" value="perusahaan" class="text-indigo-600 focus:ring-indigo-500" />
                                        <div>
                                            <div class="font-extrabold text-xs">SC Perusahaan (SKHPP-P)</div>
                                            <div class="text-[9px] text-slate-400">Mitra Kerja, Rekanan & Swasta</div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Nama Lengkap -->
                            <div class="space-y-1 sm:col-span-2">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Nama Lengkap Pemohon *</label>
                                <input 
                                    type="text" 
                                    v-model="createForm.nama" 
                                    required 
                                    placeholder="Contoh: Raden Surya Wicaksana"
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <!-- Pangkat / Korps -->
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Pangkat & Korps</label>
                                <input 
                                    type="text" 
                                    v-model="createForm.pangkat_korps" 
                                    placeholder="Contoh: Kapten Laut (E), Serka Kom, PNS III/b"
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <!-- Tipe Identitas -->
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Jenis Nomor Identitas *</label>
                                <select 
                                    v-model="createForm.identifier_type"
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="nrp">NRP (Militer)</option>
                                    <option value="nip">NIP (PNS/ASN)</option>
                                    <option value="nik">NIK (KTP)</option>
                                    <option value="nim">NIM (Mahasiswa / Siswa Magang)</option>
                                </select>
                            </div>

                            <!-- Nomor Identitas -->
                            <div class="space-y-1 sm:col-span-2">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Nomor NRP / NIP / NIK / NIM (Kunci Pelacakan) *</label>
                                <input 
                                    type="text" 
                                    v-model="createForm.identifier_number" 
                                    required 
                                    placeholder="Masukkan nomor identitas untuk tracking publik..."
                                    class="w-full text-xs font-mono font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                                />
                                <span class="text-[10px] text-slate-400 block px-1">*Nomor ini digunakan pemohon untuk mengecek status berkas di portal publik tanpa login.</span>
                            </div>

                            <!-- Kesatuan -->
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Satuan / Kesatuan</label>
                                <input 
                                    type="text" 
                                    v-model="createForm.kesatuan" 
                                    placeholder="Contoh: Denintel Koarmada 2, KRI ..., dsb."
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <!-- Jabatan -->
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Jabatan</label>
                                <input 
                                    type="text" 
                                    v-model="createForm.jabatan" 
                                    placeholder="Contoh: Paur Lidik, Ba Intel, dll."
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <!-- No. WhatsApp / HP -->
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Nomor WhatsApp / HP</label>
                                <input 
                                    type="text" 
                                    v-model="createForm.phone" 
                                    placeholder="Contoh: 081234567890"
                                    class="w-full text-xs font-mono font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <!-- Keperluan -->
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Keperluan Pengajuan SC</label>
                                <input 
                                    type="text" 
                                    v-model="createForm.keperluan" 
                                    placeholder="Contoh: Satgas Luar Negeri, Dikreg Seskoal, UKP, dsb."
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <!-- Tahapan Awal -->
                            <div class="space-y-1 sm:col-span-2">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahapan Awal Berkas Saat Ini</label>
                                <select 
                                    v-model="createForm.current_stage"
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                                >
                                    <option v-for="stg in stages" :key="stg.id" :value="stg.id">
                                        Tahap {{ stg.id }}: {{ stg.title }} ({{ stg.category }})
                                    </option>
                                </select>
                            </div>

                            <!-- Input Nomor SKHPP (Muncul otomatis jika memilih Tahap 5 ke atas) -->
                            <div v-if="Number(createForm.current_stage) >= 5" class="space-y-1 sm:col-span-2 p-3.5 bg-blue-50/70 border border-blue-200 rounded-2xl animate-in fade-in slide-in-from-top-2 duration-200">
                                <label class="text-[10px] font-black uppercase tracking-wider text-blue-900 flex items-center justify-between">
                                    <span>Nomor SKHPP {{ createForm.kategori_sc === 'perusahaan' ? 'Perusahaan (SKHPP-P)' : 'Dinas (SKHPP-D)' }}</span>
                                    <span class="text-[9px] font-mono font-extrabold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-md">Tahap 5: SKHPP Terbit</span>
                                </label>
                                <input 
                                    type="text" 
                                    v-model="createForm.nomor_skhpp" 
                                    :placeholder="createForm.kategori_sc === 'perusahaan' ? 'Contoh: B/SKHPP-P/05/IX/2026/Denintel' : 'Contoh: B/SKHPP-D/18/IX/2026/Denintel'"
                                    class="w-full text-xs font-mono font-bold p-2.5 border border-blue-200 rounded-xl bg-white focus:ring-2 focus:ring-blue-500 text-slate-900"
                                />
                                <span class="text-[10px] text-slate-500 block px-0.5">
                                    *Masukkan nomor resmi SKHPP yang telah disahkan oleh Komandan Detasemen Intelijen.
                                </span>
                            </div>

                            <!-- Input Nomor SC (Muncul otomatis jika memilih Tahap 8 ke atas) -->
                            <div v-if="Number(createForm.current_stage) >= 8" class="space-y-1 sm:col-span-2 p-3.5 bg-indigo-50/70 border border-indigo-200 rounded-2xl animate-in fade-in slide-in-from-top-2 duration-200">
                                <label class="text-[10px] font-black uppercase tracking-wider text-indigo-950 flex items-center justify-between">
                                    <span>Nomor Naskah Security Clearance (SC) Resmi</span>
                                    <span class="text-[9px] font-mono font-extrabold bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-md">Tahap 8+: Sintel</span>
                                </label>
                                <input 
                                    type="text" 
                                    v-model="createForm.nomor_sc" 
                                    placeholder="Contoh: SC/456/IX/2026/Sintel"
                                    class="w-full text-xs font-mono font-bold p-2.5 border border-indigo-200 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500 text-slate-900"
                                />
                            </div>

                            <!-- Upload Berkas SKHPP TTD Basah (Opsional) -->
                            <div class="space-y-1 sm:col-span-2">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Unggah PDF SKHPP Tanda Tangan Basah (Opsional)</label>
                                <input 
                                    ref="createFileInputRef"
                                    type="file" 
                                    accept="application/pdf"
                                    @change="handleCreateFileSkhpp"
                                    class="w-full text-xs font-medium p-2.5 border border-slate-200 rounded-xl bg-slate-50 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-black file:bg-blue-600 file:text-white hover:file:bg-blue-700"
                                />
                                <span class="text-[10px] text-slate-400 block px-1">*Format PDF, maksimal 10MB. Digunakan bila berkas SKHPP telah ditandatangani basah manual oleh Komandan.</span>
                            </div>

                            <!-- Catatan Awal Petugas -->
                            <div class="space-y-1 sm:col-span-2">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Catatan Awal Petugas</label>
                                <textarea 
                                    v-model="createForm.catatan_petugas"
                                    rows="2"
                                    placeholder="Keterangan awal penyerahan berkas atau catatan operasional..."
                                    class="w-full text-xs font-medium p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                                ></textarea>
                            </div>
                        </div>

                        <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                            <button 
                                type="button" 
                                @click="closeCreateModal" 
                                class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-extrabold text-xs uppercase tracking-wider transition cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="isSubmittingCreate"
                                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-extrabold text-xs uppercase tracking-wider shadow-md shadow-blue-600/20 transition cursor-pointer disabled:opacity-50"
                            >
                                {{ isSubmittingCreate ? 'Menyimpan...' : 'Daftarkan Pengajuan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- MODAL PEMBARUAN TAHAPAN CEPAT (QUICK STAGE UPDATE) -->
        <Teleport to="body">
            <div v-if="isUpdateStageModalOpen && activeSubmissionForStage" class="fixed inset-0 z-[160] bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto animate-in fade-in duration-150">
                <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl border border-slate-100 overflow-hidden my-auto animate-in zoom-in-95 duration-150">
                    <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
                        <div>
                            <span class="text-[10px] font-black uppercase text-blue-600 tracking-wider block">Pembaruan Tahapan Berkas</span>
                            <h3 class="text-base font-extrabold text-slate-900">
                                {{ activeSubmissionForStage.nama }}
                            </h3>
                            <span class="text-[10px] font-mono text-slate-400 block">{{ activeSubmissionForStage.tracking_code }} - {{ activeSubmissionForStage.identifier_number }}</span>
                        </div>
                        <button @click="isUpdateStageModalOpen = false" class="w-8 h-8 rounded-xl bg-slate-200/70 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition cursor-pointer">
                            &times;
                        </button>
                    </div>

                    <form @submit.prevent="submitUpdateStage" class="p-5 sm:p-6 space-y-4 text-xs font-semibold">
                        <!-- Quick Advance Button -->
                        <div v-if="stageForm.stage < 10" class="p-3 bg-blue-50 border border-blue-200/70 rounded-2xl flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-extrabold text-blue-600 uppercase block">Lompat Cepat:</span>
                                <span class="text-xs font-extrabold text-blue-900">Maju ke Tahap {{ stageForm.stage + 1 }}: {{ stages[stageForm.stage]?.title }}</span>
                            </div>
                            <button 
                                type="button" 
                                @click="advanceToNextStage"
                                class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-extrabold text-[10px] uppercase tracking-wider transition cursor-pointer shadow-xs"
                            >
                                +1 Maju Tahap
                            </button>
                        </div>

                        <!-- Pilih Tahapan Berkas -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Pilih Tahapan Saat Ini (1-10) *</label>
                            <select 
                                v-model="stageForm.stage"
                                class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                            >
                                <option v-for="stg in stages" :key="stg.id" :value="stg.id">
                                    Tahap {{ stg.id }}: {{ stg.title }} ({{ stg.category }})
                                </option>
                            </select>
                        </div>

                        <!-- Status Berkas -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Status Operasional Berkas</label>
                            <div class="grid grid-cols-3 gap-2">
                                <label :class="stageForm.status === 'proses' ? 'bg-blue-600 text-white border-blue-600' : 'bg-slate-50 text-slate-700 border-slate-200'" class="p-2 rounded-xl border text-center font-bold text-xs cursor-pointer transition">
                                    <input type="radio" v-model="stageForm.status" value="proses" class="hidden" />
                                    Proses
                                </label>
                                <label :class="stageForm.status === 'selesai' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-slate-50 text-slate-700 border-slate-200'" class="p-2 rounded-xl border text-center font-bold text-xs cursor-pointer transition">
                                    <input type="radio" v-model="stageForm.status" value="selesai" class="hidden" />
                                    Selesai
                                </label>
                                <label :class="stageForm.status === 'perbaikan' ? 'bg-amber-600 text-white border-amber-600' : 'bg-slate-50 text-slate-700 border-slate-200'" class="p-2 rounded-xl border text-center font-bold text-xs cursor-pointer transition">
                                    <input type="radio" v-model="stageForm.status" value="perbaikan" class="hidden" />
                                    Perbaikan
                                </label>
                            </div>
                        </div>

                        <!-- Input Nomor SKHPP & File PDF Basah jika tahap >= 5 -->
                        <div v-if="stageForm.stage >= 5" class="p-3.5 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-700">Nomor SKHPP (Denintel)</label>
                                <input 
                                    type="text" 
                                    v-model="stageForm.nomor_skhpp" 
                                    placeholder="Contoh: SKHPP/123/IX/2026"
                                    class="w-full text-xs font-bold p-2.5 border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-700">Unggah / Ganti PDF SKHPP (Tanda Tangan Basah)</label>
                                <input 
                                    type="file" 
                                    accept="application/pdf"
                                    @change="handleStageFileSkhpp"
                                    class="w-full text-xs font-medium p-2 border border-slate-200 rounded-xl bg-white file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-black file:bg-blue-600 file:text-white hover:file:bg-blue-700"
                                />
                                <div v-if="activeSubmissionForStage.file_skhpp_url" class="pt-1 flex items-center justify-between">
                                    <span class="text-[10px] text-slate-500">Berkas SKHPP Basah telah tersimpan:</span>
                                    <a :href="activeSubmissionForStage.file_skhpp_url" target="_blank" class="text-[10px] font-bold text-blue-600 hover:underline flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        Buka PDF SKHPP
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Input Nomor SC & Upload Softfile Hasil SC Sintel jika tahap >= 8 -->
                        <div v-if="stageForm.stage >= 8" class="p-3.5 bg-indigo-50/70 border border-indigo-200 rounded-2xl space-y-3">
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-indigo-950">Nomor Naskah Security Clearance (SC)</label>
                                <input 
                                    type="text" 
                                    v-model="stageForm.nomor_sc" 
                                    placeholder="Contoh: SC/456/IX/2026/Sintel"
                                    class="w-full text-xs font-bold p-2.5 border border-indigo-200 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>

                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <label class="text-[10px] font-black uppercase tracking-wider text-indigo-950">Softfile PDF Hasil SC (Petinjau Sintel)</label>
                                    <span class="text-[9px] font-mono text-indigo-600 font-bold bg-indigo-100 px-2 py-0.5 rounded-full">Aktif 2x24 Jam</span>
                                </div>
                                <p class="text-[10px] text-indigo-800 font-medium leading-relaxed">
                                    Softfile ini akan tampil pada portal pelacakan publik dalam mode petinjau terproteksi watermark <strong>(PETINJAU)</strong> tanpa fitur unduh, dan otomatis terhapus dalam kurun waktu 48 jam.
                                </p>
                                <input 
                                    type="file" 
                                    accept="application/pdf"
                                    @change="handleStageFileScPreview"
                                    class="w-full text-xs font-medium p-2 border border-indigo-200 rounded-xl bg-white file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-black file:bg-indigo-600 file:text-white hover:file:bg-indigo-700"
                                />

                                <div v-if="activeSubmissionForStage.is_sc_preview_available" class="pt-1 flex items-center justify-between">
                                    <span class="text-[10px] text-indigo-700 font-bold">Status: Petinjau Aktif</span>
                                    <span class="text-[10px] font-mono font-bold text-amber-600">Sisa: {{ activeSubmissionForStage.sc_preview_remaining_hours }} Jam</span>
                                </div>
                                <div v-else-if="activeSubmissionForStage.sc_preview_expired_at" class="text-[10px] text-rose-600 font-bold">
                                    Petinjau sebelumnya telah kadaluarsa & terhapus otomatis.
                                </div>
                            </div>
                        </div>

                        <!-- Catatan Petugas untuk Tahap Ini -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Catatan Pembaruan (Terlihat oleh Pemohon)</label>
                            <textarea 
                                v-model="stageForm.notes" 
                                rows="3"
                                placeholder="Tuliskan keterangan detail posisi berkas untuk informasi personel..."
                                class="w-full text-xs font-medium p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                            ></textarea>
                        </div>

                        <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                            <button 
                                type="button" 
                                @click="isUpdateStageModalOpen = false" 
                                class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-extrabold text-xs uppercase tracking-wider transition cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="isSubmittingStage"
                                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-extrabold text-xs uppercase tracking-wider shadow-md shadow-blue-600/20 transition cursor-pointer disabled:opacity-50"
                            >
                                {{ isSubmittingStage ? 'Menyimpan...' : 'Simpan Tahapan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- MODAL PETINJAU HASIL SC (OFFICER VIEWER) -->
        <Teleport to="body">
            <div 
                v-if="isPreviewModalOpen && activeSubmissionForPreview" 
                class="fixed inset-0 z-[200] bg-slate-950/90 backdrop-blur-md flex flex-col p-2 sm:p-6 overflow-hidden animate-in fade-in duration-200"
                @contextmenu.prevent
            >
                <div class="bg-slate-900 border border-slate-800 rounded-3xl flex-1 flex flex-col overflow-hidden shadow-2xl relative">
                    
                    <!-- Header Modal Viewer -->
                    <div class="px-5 py-4 border-b border-slate-800 bg-slate-950/90 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-black">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-black uppercase text-indigo-400 tracking-wider">PETINJAU RESMI KEDINASAN</span>
                                    <span class="px-2 py-0.5 bg-rose-500/20 text-rose-300 border border-rose-500/30 rounded-full text-[9px] font-black uppercase">
                                        PROTEKSI ANTI-DOWNLOAD
                                    </span>
                                </div>
                                <h3 class="text-sm sm:text-base font-extrabold text-white">
                                    Petinjau SC: {{ activeSubmissionForPreview?.nama }} ({{ activeSubmissionForPreview?.tracking_code }})
                                </h3>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="text-[11px] font-mono font-bold text-amber-400 bg-amber-950/60 border border-amber-800/60 px-3 py-1.5 rounded-xl">
                                Sisa Masa Aktif: {{ activeSubmissionForPreview?.sc_preview_remaining_hours }} Jam
                            </span>
                            <button 
                                @click="closePreviewModal" 
                                class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-extrabold uppercase tracking-wider transition cursor-pointer border border-slate-700 flex items-center gap-1.5"
                            >
                                <span>Tutup</span>
                                <span class="text-base leading-none">&times;</span>
                            </button>
                        </div>
                    </div>

                    <!-- PDF Viewer Container With Watermark Overlay -->
                    <div class="flex-1 relative bg-slate-950 overflow-hidden" @contextmenu.prevent>
                        <iframe 
                            v-if="previewPdfUrl"
                            :src="previewPdfUrl"
                            class="w-full h-full border-0 relative z-10"
                            title="Petinjau Berkas SC"
                        ></iframe>

                        <!-- OVERLAY WATERMARK BESAR "(PETINJAU)" -->
                        <div class="absolute inset-0 pointer-events-none select-none z-20 flex flex-col justify-around items-center overflow-hidden p-6">
                            <div class="w-full flex justify-around items-center transform -rotate-25 opacity-30 select-none">
                                <span class="text-4xl sm:text-6xl font-black text-red-500 tracking-widest font-mono">
                                    (PETINJAU)
                                </span>
                                <span class="text-4xl sm:text-6xl font-black text-red-500 tracking-widest font-mono hidden md:inline">
                                    (PETINJAU)
                                </span>
                            </div>

                            <div class="w-full flex justify-around items-center transform -rotate-25 opacity-35 select-none">
                                <span class="text-5xl sm:text-7xl font-black text-red-600 tracking-widest font-mono">
                                    (PETINJAU)
                                </span>
                                <span class="text-5xl sm:text-7xl font-black text-red-600 tracking-widest font-mono hidden md:inline">
                                    (PETINJAU)
                                </span>
                            </div>

                            <div class="w-full flex justify-around items-center transform -rotate-25 opacity-30 select-none">
                                <span class="text-4xl sm:text-6xl font-black text-red-500 tracking-widest font-mono">
                                    (PETINJAU)
                                </span>
                                <span class="text-4xl sm:text-6xl font-black text-red-500 tracking-widest font-mono hidden md:inline">
                                    (PETINJAU)
                                </span>
                            </div>

                            <div class="bg-red-950/80 border border-red-600/60 text-red-300 px-6 py-2 rounded-full text-xs font-black uppercase tracking-widest shadow-2xl backdrop-blur-xs">
                                DOKUMEN PETINJAU RESMI - TIDAK DAPAT DIUNDUH - OTOMATIS TERHAPUS DALAM 2X24 JAM
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </Teleport>

        <!-- MODAL RIWAYAT LINIMASA LOGS -->
        <Teleport to="body">
            <div v-if="isLogsModalOpen && activeSubmissionForLogs" class="fixed inset-0 z-[160] bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto animate-in fade-in duration-150">
                <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl border border-slate-100 overflow-hidden my-auto animate-in zoom-in-95 duration-150">
                    <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
                        <div>
                            <span class="text-[10px] font-black uppercase text-blue-600 tracking-wider block">Riwayat Alur Berkas</span>
                            <h3 class="text-base font-extrabold text-slate-900">
                                {{ activeSubmissionForLogs.nama }}
                            </h3>
                            <span class="text-[10px] font-mono text-slate-400 block">{{ activeSubmissionForLogs.tracking_code }}</span>
                        </div>
                        <button @click="isLogsModalOpen = false" class="w-8 h-8 rounded-xl bg-slate-200/70 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition cursor-pointer">
                            &times;
                        </button>
                    </div>

                    <div class="p-5 sm:p-6 space-y-4 text-xs">
                        <div v-if="activeSubmissionForLogs.logs && activeSubmissionForLogs.logs.length > 0" class="space-y-3 max-h-96 overflow-y-auto pr-1">
                            <div 
                                v-for="(lg, lgIdx) in activeSubmissionForLogs.logs" 
                                :key="lg.id"
                                class="p-3.5 bg-slate-50 border border-slate-200/70 rounded-2xl space-y-1"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="font-extrabold text-blue-700">Tahap {{ lg.stage }}: {{ lg.stage_title }}</span>
                                    <span class="font-mono text-[10px] text-slate-400">{{ formatDateTime(lg.created_at) }}</span>
                                </div>
                                <p class="text-slate-600 font-medium leading-relaxed">{{ lg.notes || '-' }}</p>
                                <div class="text-[10px] text-slate-400 font-bold pt-1 border-t border-slate-200/60">
                                    Petugas: {{ lg.user_name || 'Petugas Kedinasan' }}
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-slate-400 font-medium italic">
                            Belum ada riwayat pembaruan yang tercatat.
                        </div>

                        <div class="pt-2 text-right">
                            <button 
                                type="button" 
                                @click="isLogsModalOpen = false" 
                                class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-extrabold text-xs uppercase tracking-wider transition cursor-pointer"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- MODAL EDIT DATA PEMOHON -->
        <Teleport to="body">
            <div v-if="isEditModalOpen" class="fixed inset-0 z-[160] bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto animate-in fade-in duration-150">
                <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl border border-slate-100 overflow-hidden my-auto animate-in zoom-in-95 duration-150">
                    <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
                        <div>
                            <span class="text-[10px] font-black uppercase text-blue-600 tracking-wider block">Koreksi Data Berkas</span>
                            <h3 class="text-base font-extrabold text-slate-900">Edit Data Pemohon</h3>
                        </div>
                        <button @click="isEditModalOpen = false" class="w-8 h-8 rounded-xl bg-slate-200/70 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition cursor-pointer">
                            &times;
                        </button>
                    </div>

                    <form @submit.prevent="submitEdit" class="p-5 sm:p-6 space-y-4 text-xs font-semibold">
                        <!-- Kategori SC (Dinas vs Perusahaan) -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Kategori Security Clearance *</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label 
                                    :class="editForm.kategori_sc === 'dinas' ? 'bg-blue-50 border-blue-500 text-blue-800 ring-2 ring-blue-500/20' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'"
                                    class="p-2.5 rounded-xl border flex items-center gap-2 cursor-pointer transition"
                                >
                                    <input type="radio" v-model="editForm.kategori_sc" value="dinas" class="text-blue-600 focus:ring-blue-500" />
                                    <div>
                                        <div class="font-extrabold text-xs">SC Dinas (SKHPP-D)</div>
                                        <div class="text-[9px] text-slate-400">Militer & ASN</div>
                                    </div>
                                </label>
                                <label 
                                    :class="editForm.kategori_sc === 'perusahaan' ? 'bg-indigo-50 border-indigo-500 text-indigo-800 ring-2 ring-indigo-500/20' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'"
                                    class="p-2.5 rounded-xl border flex items-center gap-2 cursor-pointer transition"
                                >
                                    <input type="radio" v-model="editForm.kategori_sc" value="perusahaan" class="text-indigo-600 focus:ring-indigo-500" />
                                    <div>
                                        <div class="font-extrabold text-xs">SC Perusahaan (SKHPP-P)</div>
                                        <div class="text-[9px] text-slate-400">Mitra & Swasta</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Nama Lengkap *</label>
                            <input 
                                type="text" 
                                v-model="editForm.nama" 
                                required
                                class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white"
                            />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Pangkat & Korps</label>
                                <input 
                                    type="text" 
                                    v-model="editForm.pangkat_korps" 
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Jenis Identitas *</label>
                                <select 
                                    v-model="editForm.identifier_type"
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white"
                                >
                                    <option value="nrp">NRP (Militer)</option>
                                    <option value="nip">NIP (PNS/ASN)</option>
                                    <option value="nik">NIK (KTP)</option>
                                    <option value="nim">NIM (Mahasiswa / Siswa Magang)</option>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Nomor Identitas *</label>
                                <input 
                                    type="text" 
                                    v-model="editForm.identifier_number" 
                                    required
                                    class="w-full text-xs font-mono font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Satuan / Kesatuan</label>
                                <input 
                                    type="text" 
                                    v-model="editForm.kesatuan" 
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">No. WhatsApp / HP</label>
                                <input 
                                    type="text" 
                                    v-model="editForm.phone" 
                                    class="w-full text-xs font-mono font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white"
                                />
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Keperluan SC</label>
                            <input 
                                type="text" 
                                v-model="editForm.keperluan" 
                                class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Nomor SKHPP</label>
                                <input 
                                    type="text" 
                                    v-model="editForm.nomor_skhpp" 
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Nomor SC Resmi</label>
                                <input 
                                    type="text" 
                                    v-model="editForm.nomor_sc" 
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white"
                                />
                            </div>
                        </div>

                        <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                            <button 
                                type="button" 
                                @click="isEditModalOpen = false" 
                                class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-extrabold text-xs uppercase tracking-wider transition cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="isSubmittingEdit"
                                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-extrabold text-xs uppercase tracking-wider shadow-md shadow-blue-600/20 transition cursor-pointer disabled:opacity-50"
                            >
                                {{ isSubmittingEdit ? 'Menyimpan...' : 'Simpan Perubahan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- MODAL PENCATATAN PENGAMBILAN SC -->
        <Teleport to="body">
            <div v-if="isTakenModalOpen && activeSubmissionForTaken" class="fixed inset-0 z-[160] bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto animate-in fade-in duration-150">
                <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl border border-slate-100 overflow-hidden my-auto animate-in zoom-in-95 duration-150">
                    <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-teal-600 text-white flex items-center justify-center font-black shadow-xs">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <span class="text-[10px] font-black uppercase text-teal-600 tracking-wider block">Agenda Pengambilan Berkas</span>
                                <h3 class="text-base font-extrabold text-slate-900">
                                    {{ activeSubmissionForTaken.nama }}
                                </h3>
                                <span class="text-[10px] font-mono text-slate-400 block">{{ activeSubmissionForTaken.tracking_code }} - {{ activeSubmissionForTaken.identifier_number }}</span>
                            </div>
                        </div>
                        <button @click="isTakenModalOpen = false" class="w-8 h-8 rounded-xl bg-slate-200/70 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition cursor-pointer">
                            &times;
                        </button>
                    </div>

                    <form @submit.prevent="submitToggleTaken" class="p-5 sm:p-6 space-y-4 text-xs font-semibold">
                        <!-- Pilihan Status Pengambilan: Sudah Diambil / Belum Diambil -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Status Pengambilan Berkas *</label>
                            <div class="grid grid-cols-2 gap-3">
                                <label 
                                    :class="takenForm.is_taken ? 'bg-teal-600 text-white border-teal-600 shadow-sm' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                                    class="p-3 rounded-2xl border text-center font-black text-xs cursor-pointer transition flex items-center justify-center gap-2"
                                >
                                    <input type="radio" :value="1" v-model.number="takenForm.is_taken" class="hidden" />
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    <span>Sudah Diambil</span>
                                </label>
                                <label 
                                    :class="!takenForm.is_taken ? 'bg-rose-600 text-white border-rose-600 shadow-sm' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                                    class="p-3 rounded-2xl border text-center font-black text-xs cursor-pointer transition flex items-center justify-center gap-2"
                                >
                                    <input type="radio" :value="0" v-model.number="takenForm.is_taken" class="hidden" />
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    <span>Belum Diambil</span>
                                </label>
                            </div>
                        </div>

                        <!-- Data Nomor SC & Tanggal SC -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3.5 bg-slate-50 border border-slate-200 rounded-2xl">
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-700">Nomor Naskah SC</label>
                                <input 
                                    type="text" 
                                    v-model="takenForm.nomor_sc" 
                                    placeholder="Contoh: SC/123/IX/2026/Sintel"
                                    class="w-full text-xs font-bold p-2.5 border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-teal-500"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-700">Tanggal Terbit SC</label>
                                <input 
                                    type="date" 
                                    v-model="takenForm.tanggal_sc" 
                                    class="w-full text-xs font-bold p-2.5 border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-teal-500"
                                />
                            </div>
                        </div>

                        <!-- Form Detail Pengambilan (Hanya tampil jika Sudah Diambil) -->
                        <div v-if="takenForm.is_taken" class="space-y-3 p-3.5 bg-teal-50/60 border border-teal-200 rounded-2xl">
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-teal-950">Tanggal & Waktu Diambil *</label>
                                <input 
                                    type="datetime-local" 
                                    v-model="takenForm.taken_at" 
                                    required
                                    class="w-full text-xs font-mono font-bold p-2.5 border border-teal-200 rounded-xl bg-white focus:ring-2 focus:ring-teal-500"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-teal-950">Nama Pengambil Berkas *</label>
                                <input 
                                    type="text" 
                                    v-model="takenForm.taken_by" 
                                    required
                                    placeholder="Nama pemohon atau personel yang mewakili..."
                                    class="w-full text-xs font-bold p-2.5 border border-teal-200 rounded-xl bg-white focus:ring-2 focus:ring-teal-500"
                                />
                                <span class="text-[10px] text-teal-700/80 block">Default: Nama pemohon. Dapat disesuaikan bila diwakilkan rekan satuan.</span>
                            </div>
                        </div>

                        <!-- Catatan Tambahan -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Keterangan / Catatan Tambahan</label>
                            <textarea 
                                v-model="takenForm.catatan" 
                                rows="2"
                                placeholder="Keterangan dinas penyerahan berkas fisik..."
                                class="w-full text-xs font-medium p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-teal-500"
                            ></textarea>
                        </div>

                        <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                            <button 
                                type="button" 
                                @click="isTakenModalOpen = false" 
                                class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-extrabold text-xs uppercase tracking-wider transition cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="isSubmittingTaken"
                                class="px-6 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl font-extrabold text-xs uppercase tracking-wider shadow-md shadow-teal-600/20 transition cursor-pointer disabled:opacity-50 flex items-center gap-2"
                            >
                                <span>{{ isSubmittingTaken ? 'Menyimpan...' : 'Simpan Status Pengambilan' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- MODAL: Kirim Notifikasi ke Staf Intel -->
        <Teleport to="body">
            <div 
                v-if="isNotifyModalOpen" 
                class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
                @click.self="isNotifyModalOpen = false"
            >
                <div class="bg-white rounded-3xl max-w-2xl w-full shadow-2xl border border-slate-100 overflow-hidden my-8 animate-in fade-in zoom-in duration-200">
                    <!-- Header Modal -->
                    <div class="p-5 sm:p-6 bg-gradient-to-r from-indigo-700 to-indigo-900 text-white flex items-center justify-between">
                        <div class="space-y-1">
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-indigo-500/30 text-indigo-100 text-[10px] font-black uppercase tracking-wider">
                                <span>WhatsApp Gateway Dinas</span>
                            </div>
                            <h3 class="text-lg font-black tracking-tight">Kirim Notifikasi ke Staf Intel</h3>
                            <p class="text-xs text-indigo-100/90 font-medium">Kirimkan rekapitulasi pengajuan berkas SC yang perlu ditindaklanjuti ke WhatsApp Staf Intel.</p>
                        </div>
                        <button 
                            @click="isNotifyModalOpen = false" 
                            class="p-2 text-indigo-200 hover:text-white hover:bg-white/10 rounded-xl transition cursor-pointer"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Form Isi -->
                    <form @submit.prevent="submitSendNotification" class="p-5 sm:p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                        <!-- Mode Penerima: Dari Daftar Personel vs Input Manual -->
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Pilihan Tujuan Penerima</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button 
                                    type="button"
                                    @click="notifyRecipientType = 'user'"
                                    :class="notifyRecipientType === 'user' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                                    class="py-2 px-3 rounded-xl font-bold text-xs uppercase tracking-wider transition text-center cursor-pointer"
                                >
                                    Pilih dari Daftar Personel
                                </button>
                                <button 
                                    type="button"
                                    @click="notifyRecipientType = 'custom'"
                                    :class="notifyRecipientType === 'custom' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                                    class="py-2 px-3 rounded-xl font-bold text-xs uppercase tracking-wider transition text-center cursor-pointer"
                                >
                                    Input Manual / Nomor Lain
                                </button>
                            </div>
                        </div>

                        <!-- Dropdown Pilih Personel jika mode 'user' -->
                        <div v-if="notifyRecipientType === 'user'" class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-700">Pilih Personel Staf Intel *</label>
                            <select 
                                v-model="selectedSintelUserId"
                                @change="onSelectUser"
                                class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="" disabled>-- Pilih Personel Tujuan --</option>
                                <optgroup label="Staf Intelijen (Sintel)">
                                    <option v-for="user in sintelUsersList" :key="'sintel-' + user.id" :value="user.id">
                                        {{ user.pangkat ? user.pangkat + ' ' : '' }}{{ user.name }} {{ user.nrp ? '(NRP ' + user.nrp + ')' : '' }} {{ user.phone ? ' - ' + user.phone : ' [Belum ada No. HP]' }}
                                    </option>
                                </optgroup>
                                <optgroup v-if="otherUsersList.length > 0" label="Personel Lainnya">
                                    <option v-for="user in otherUsersList" :key="'other-' + user.id" :value="user.id">
                                        {{ user.pangkat ? user.pangkat + ' ' : '' }}{{ user.name }} {{ user.nrp ? '(NRP ' + user.nrp + ')' : '' }} - {{ user.phone }}
                                    </option>
                                </optgroup>
                            </select>
                        </div>

                        <!-- Form Detail Penerima (Auto-filled atau Manual) -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 p-3.5 bg-slate-50 border border-slate-200 rounded-2xl">
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-600">Pangkat Penerima</label>
                                <input 
                                    type="text" 
                                    v-model="notifyCustomPangkat" 
                                    placeholder="Contoh: Peltu Saa / Kapten"
                                    class="w-full text-xs font-medium p-2.5 border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-600">Nama Penerima *</label>
                                <input 
                                    type="text" 
                                    v-model="notifyCustomNama" 
                                    required
                                    placeholder="Nama personel..."
                                    class="w-full text-xs font-bold p-2.5 border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-600">NRP Penerima</label>
                                <input 
                                    type="text" 
                                    v-model="notifyCustomNrp" 
                                    placeholder="Contoh: 82068"
                                    class="w-full text-xs font-mono font-medium p-2.5 border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>
                            <div class="sm:col-span-3 space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-700 flex items-center justify-between">
                                    <span>Nomor WhatsApp Penerima *</span>
                                    <span v-if="!notifyCustomPhone" class="text-rose-600 font-bold">Wajib diisi</span>
                                </label>
                                <input 
                                    type="text" 
                                    v-model="notifyCustomPhone" 
                                    required
                                    placeholder="Contoh: 08123456789 atau 628123456789"
                                    class="w-full text-xs font-mono font-bold p-2.5 border rounded-xl bg-white focus:ring-2 focus:ring-indigo-500"
                                    :class="!notifyCustomPhone ? 'border-amber-300 ring-2 ring-amber-200' : 'border-slate-200'"
                                />
                            </div>
                        </div>

                        <!-- Pilihan Cakupan Berkas yang Perlu Diupdate -->
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Cakupan Berkas yang Perlu Di-update</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <label 
                                    :class="notifyScope === 'stage_6_9' ? 'border-indigo-600 bg-indigo-50/70 text-indigo-950 font-bold' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                                    class="p-3 rounded-2xl border cursor-pointer transition flex items-start gap-2.5"
                                >
                                    <input type="radio" value="stage_6_9" v-model="notifyScope" class="mt-0.5 text-indigo-600 focus:ring-indigo-500" />
                                    <div class="text-xs">
                                        <div class="font-black text-slate-900">Tahap 6 s/d 9 (Di Staf Intel)</div>
                                        <div class="text-[10px] text-slate-500">Berkas yang saat ini aktif diproses Staf Intel.</div>
                                    </div>
                                </label>
                                <label 
                                    :class="notifyScope === 'stage_5_9' ? 'border-indigo-600 bg-indigo-50/70 text-indigo-950 font-bold' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                                    class="p-3 rounded-2xl border cursor-pointer transition flex items-start gap-2.5"
                                >
                                    <input type="radio" value="stage_5_9" v-model="notifyScope" class="mt-0.5 text-indigo-600 focus:ring-indigo-500" />
                                    <div class="text-xs">
                                        <div class="font-black text-slate-900">Tahap 5 s/d 9 (Termasuk SKHPP Terbit)</div>
                                        <div class="text-[10px] text-slate-500">Termasuk berkas SKHPP terbit yang siap beralih ke Sintel.</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Ringkasan Jumlah Pengajuan yang Harus Di-update -->
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Jumlah Pengajuan yang Harus Di-update</label>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="p-3 rounded-2xl bg-blue-50 border border-blue-200 text-center">
                                    <span class="text-[9px] font-black uppercase tracking-wider text-blue-800 block">Berkas Dinas</span>
                                    <span class="text-xl font-black text-blue-900 block mt-0.5">{{ currentNotifyStats.dinas }}</span>
                                    <span class="text-[9px] text-blue-600 font-medium">Pengajuan</span>
                                </div>
                                <div class="p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-center">
                                    <span class="text-[9px] font-black uppercase tracking-wider text-emerald-800 block">Berkas Perusahaan</span>
                                    <span class="text-xl font-black text-emerald-900 block mt-0.5">{{ currentNotifyStats.perusahaan }}</span>
                                    <span class="text-[9px] text-emerald-600 font-medium">Pengajuan</span>
                                </div>
                                <div class="p-3 rounded-2xl bg-slate-900 border border-slate-800 text-center text-white">
                                    <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block">Total Berkas</span>
                                    <span class="text-xl font-black text-amber-400 block mt-0.5">{{ currentNotifyStats.total }}</span>
                                    <span class="text-[9px] text-slate-400 font-medium">Menunggu Tindak Lanjut</span>
                                </div>
                            </div>
                        </div>

                        <!-- Catatan Tambahan (Opsional) -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Catatan Tambahan (Opsional)</label>
                            <textarea 
                                v-model="notifyCatatanTambahan" 
                                rows="2"
                                placeholder="Tambahkan pesan instruksi atau keterangan dinas..."
                                class="w-full text-xs font-medium p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500"
                            ></textarea>
                        </div>

                        <!-- Pratinjau Pesan WhatsApp -->
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500 flex items-center justify-between">
                                <span>Pratinjau Pesan WhatsApp</span>
                                <span class="text-indigo-600 font-bold">Otomatis Terformat</span>
                            </label>
                            <div class="p-3.5 bg-slate-900 text-emerald-400 font-mono text-[11px] leading-relaxed rounded-2xl border border-slate-800 whitespace-pre-line shadow-inner max-h-48 overflow-y-auto">
                                {{ generatedPreviewMessage }}
                            </div>
                        </div>

                        <!-- Tombol Aksi -->
                        <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                            <button 
                                type="button" 
                                @click="isNotifyModalOpen = false" 
                                class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-extrabold text-xs uppercase tracking-wider transition cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="isSubmittingNotification || !notifyCustomPhone"
                                class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-extrabold text-xs uppercase tracking-wider shadow-md shadow-indigo-600/20 transition cursor-pointer disabled:opacity-50 flex items-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                </svg>
                                <span>{{ isSubmittingNotification ? 'Mengirim Notifikasi...' : 'Kirim Notifikasi WhatsApp' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style>
@media print {
  /* DOKUMEN 100% BLANK KOSONG SAAT PRINT */
  html, body {
    background: #ffffff !important;
    color: transparent !important;
    height: 100% !important;
    width: 100% !important;
    overflow: hidden !important;
  }
  
  body * {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
  }
}
</style>
