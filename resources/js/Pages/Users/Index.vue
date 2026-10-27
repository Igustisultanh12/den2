<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage, router, Link } from '@inertiajs/vue3';
// PERBAIKAN: Menambahkan watch, onMounted dan onUnmounted
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios'; 
import Swal from 'sweetalert2';
import html2pdf from 'html2pdf.js'; // Library Sakti untuk Cetak Tanpa RAM Server

const props = defineProps({
    users: Object, // Data pagination (10 orang)
    allUsers: Array, // Data lengkap untuk Rekap PDF
    pendingSpPersonnel: Array, // Data antrean dari SP Jaga PDF
});

const user = computed(() => usePage().props.auth.user);

// Mapping Role Satuan untuk tampilan yang lebih rapi di PDF
const roleLabels = {
    super_admin: 'SUPER ADMIN',
    superadmin: 'SUPER ADMIN',
    admin: 'ADMIN',
    personel: 'PERSONEL',
    personel_sintel: 'PERSONEL SINTEL',
    anggotasintel: 'PERSONEL SINTEL',
    komandan: 'KOMANDAN (APPROVER)',
    wadan: 'WAKIL KOMANDAN',
    pasops: 'PASOPS',
    danunit1: 'DAN UNIT I / LID',
    danunit2: 'DAN UNIT II / PAMGAL',
    danunitteknis: 'DAN UNIT TEKNIS',
    kaurmintel: 'KAUR MINTEL',
    paurset: 'PAUR SET',
    staf: 'STAF ADMINISTRASI',
};

// State Management
// --- FITUR DETAIL PERSONEL & MATRIKS HAK AKSES ---
const showDetailModal = ref(false);
const detailUser = ref(null);

const showPersonnelDetail = (u) => {
    detailUser.value = u;
    showDetailModal.value = true;
};

const printSingleTokenPdf = (userId) => {
    window.open(route('users.print-token-pdf', { ids: userId }), '_blank');
};
const showPasswordModal = ref(false);
const showEditModal = ref(false);
const showAddModal = ref(false); 
const showPreview = ref(false);
const previewImage = ref(null);
const selectedUser = ref(null);

// Form untuk verifikasi password admin (Suspend/Verifikasi)
const form = useForm({
    password: '',
});

// Form untuk Edit Data
const editForm = useForm({
    id: '',
    name: '',
    email: '',
    pangkat: '',
    nrp: '',
    phone: '',
    password: '',
    mfa_enabled: false,
});

// Form untuk Tambah Personel Baru (Otomatis)
const addForm = useForm({
    name: '',
    pangkat: '',
    nrp: '',
    email: '',
    phone: '',
    role: 'personel',
    mfa_enabled: false,
});

// Fitur Autogenerate Email Personel
const isEmailManuallyEdited = ref(false);

const autoGenerateEmail = () => {
    if (isEmailManuallyEdited.value && addForm.email) return;
    const rawNrp = (addForm.nrp || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    if (rawNrp) {
        addForm.email = `${rawNrp}@sinden.my.id`;
    } else if (addForm.name) {
        const cleanName = (addForm.name || '')
            .toLowerCase()
            .trim()
            .replace(/['`]/g, '')
            .replace(/[^a-z0-9]+/g, '.');
        if (cleanName) {
            addForm.email = `${cleanName}@sinden.my.id`;
        }
    }
};

watch(() => addForm.nrp, () => {
    autoGenerateEmail();
});

watch(() => addForm.name, () => {
    autoGenerateEmail();
});

watch(showAddModal, (newVal) => {
    if (newVal) {
        isEmailManuallyEdited.value = false;
        autoGenerateEmail();
    }
});

/**
 * FITUR BARU: LOGIKA CETAK METODE SULTAN (HTML2PDF)
 */
const formatLongDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
};

const openPreview = () => {
    const element = document.getElementById('area-rekap-personel');
    const opt = {
        margin: [0.5, 0.5, 0.5, 0.5],
        html2canvas: { scale: 2, useCORS: true, width: 1100 },
    };

    html2pdf().set(opt).from(element).outputImg().then((img) => {
        previewImage.value = img.src;
        showPreview.value = true;
    });
};

const downloadPDF = () => {
    const element = document.getElementById('area-rekap-personel');
    const opt = {
        margin:       [0.5, 0.5, 0.5, 0.5],
        filename:      `Rekap_Personel_Sinden_${new Date().getTime()}.pdf`,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, width: 1100, windowWidth: 1100 },
        jsPDF:        { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).save();
};

// --- FITUR BULK REGISTRASI PERSONEL ---
const showBulkModal = ref(false);
const isSubmittingBulk = ref(false);
const bulkRows = ref([
    { name: '', pangkat: '', nrp: '', email: '', phone: '', role: 'personel' }
]);

const loadPendingSpPersonnel = () => {
    if (props.pendingSpPersonnel && props.pendingSpPersonnel.length > 0) {
        bulkRows.value = JSON.parse(JSON.stringify(props.pendingSpPersonnel));
        showBulkModal.value = true;
    }
};

const openBulkModal = () => {
    bulkRows.value = [
        { name: '', pangkat: '', nrp: '', email: '', phone: '', role: 'personel' },
        { name: '', pangkat: '', nrp: '', email: '', phone: '', role: 'personel' }
    ];
    showBulkModal.value = true;
};

const addBulkRow = () => {
    bulkRows.value.push({ name: '', pangkat: '', nrp: '', email: '', phone: '', role: 'personel' });
};

const removeBulkRow = (index) => {
    if (bulkRows.value.length > 1) {
        bulkRows.value.splice(index, 1);
    }
};

const submitBulk = async () => {
    const invalidRow = bulkRows.value.find(r => !r.name || !r.name.trim() || !r.pangkat || !r.pangkat.trim() || !r.nrp || !r.nrp.trim());
    if (invalidRow) {
        Swal.fire('FORM BELUM LENGKAP', 'Mohon lengkapi Nama, Pangkat, dan NRP/PNS untuk setiap baris personel.', 'warning');
        return;
    }

    try {
        isSubmittingBulk.value = true;
        const res = await axios.post(route('users.store-bulk'), { users: bulkRows.value });
        if (res.data && res.data.success) {
            showBulkModal.value = false;
            
            Swal.fire({
                title: 'PENDAFTARAN MASAL BERHASIL',
                text: `${res.data.message} Dokumen Kode Verifikasi (Token) PDF akan dibuka untuk dicetak.`,
                icon: 'success',
                confirmButtonText: 'CETAK TOKEN PDF SEKARANG',
                confirmButtonColor: '#4f46e5'
            }).then(() => {
                window.open(res.data.pdf_url, '_blank');
                router.reload();
            });
        }
    } catch (err) {
        const errMsg = err.response?.data?.message || 'Gagal memproses pendaftaran masal. Cek kembali NRP/Email agar tidak ganda.';
        Swal.fire('PROSES GAGAL', errMsg, 'error');
    } finally {
        isSubmittingBulk.value = false;
    }
};

/**
 * FITUR: TAMBAH PERSONEL BARU
 */
const submitAdd = () => {
    addForm.post(route('users.store'), {
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
            isEmailManuallyEdited.value = false;
            Swal.fire({
                title: 'BERHASIL',
                text: 'Akun dibuat. Detail login dikirim via WhatsApp ke personel.',
                icon: 'success',
                confirmButtonColor: '#4f46e5'
            });
        },
        onError: (err) => {
            Swal.fire('GAGAL', 'Cek kembali data. Pastikan NRP/Email/WA belum terdaftar.', 'error');
        }
    });
};

/**
 * RESET PASSWORD: Request Token
 */
const requestToken = async (user) => {
    const result = await Swal.fire({
        title: 'REQUEST TOKEN?',
        text: `Generate token reset password untuk ${user.name}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'YA, GENERATE',
        cancelButtonText: 'BATAL'
    });

    if (result.isConfirmed) {
        Swal.fire({
            title: 'MEMPROSES...',
            text: 'Sedang men-generate token reset password...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const res = await axios.post(route('users.generate-token', user.id));
            const token = res.data?.token || res.data?.flash?.token;
            if (token) {
                Swal.fire({
                    title: 'TOKEN BERHASIL DIBUAT',
                    html: `
                        <div class="bg-indigo-50 p-6 rounded-3xl border-2 border-dashed border-indigo-200 my-4 text-center">
                            <p class="text-[11px] font-extrabold uppercase text-gray-500 mb-0.5">Personel: ${user.pangkat || ''} ${user.name}</p>
                            <p class="text-[10px] font-bold text-gray-400 mb-3">NRP/NIP: ${user.nrp || '-'}</p>
                            <div class="text-4xl font-black tracking-[0.3em] text-indigo-600 select-all cursor-pointer font-mono py-3 bg-white rounded-2xl shadow-inner border border-indigo-100">${token}</div>
                        </div>
                        <p class="text-[10px] uppercase font-bold text-gray-400">Berikan kode ini kepada personel.<br>Berlaku selama 5 menit untuk reset password.</p>
                        <div class="mt-4 flex justify-center gap-2">
                            <button type="button" id="copyTokenBtn" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-black uppercase tracking-wider transition-all shadow-md inline-flex items-center gap-1.5 cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                <span id="copyTokenText">SALIN TOKEN</span>
                            </button>
                        </div>
                    `,
                    icon: 'success',
                    confirmButtonColor: '#4f46e5',
                    confirmButtonText: 'TUTUP',
                    didOpen: () => {
                        const copyBtn = document.getElementById('copyTokenBtn');
                        const copyText = document.getElementById('copyTokenText');
                        if (copyBtn && copyText) {
                            copyBtn.addEventListener('click', () => {
                                navigator.clipboard.writeText(token).then(() => {
                                    copyText.innerText = 'BERHASIL DISALIN!';
                                    copyBtn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
                                    copyBtn.classList.add('bg-emerald-600', 'hover:bg-emerald-700');
                                    setTimeout(() => {
                                        copyText.innerText = 'SALIN TOKEN';
                                        copyBtn.classList.remove('bg-emerald-600', 'hover:bg-emerald-700');
                                        copyBtn.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
                                    }, 2500);
                                }).catch(() => {
                                    copyText.innerText = 'GAGAL SALIN';
                                });
                            });
                        }
                    }
                });
            } else {
                Swal.fire('BERHASIL', res.data?.message || 'Token berhasil dibuat.', 'success');
            }
        } catch (err) {
            console.error(err);
            const errMsg = err.response?.data?.message || 'Gagal generate token. Pastikan sistem terhubung.';
            Swal.fire('GAGAL', errMsg, 'error');
        }
    }
};

/**
 * FITUR: ADMIN UBAH PASSWORD PERSONEL LANGSUNG
 */
const openChangePasswordModal = async (user) => {
    if (!user) return;
    const { value: formValues } = await Swal.fire({
        title: 'UBAH PASSWORD PERSONEL',
        html: `
            <div class="text-left mb-3">
                <div class="bg-indigo-50/80 p-3.5 rounded-2xl border border-indigo-100 mb-4">
                    <p class="text-[11px] font-black text-indigo-950 uppercase tracking-tight">${user.pangkat || ''} ${user.name}</p>
                    <p class="text-[10px] font-bold text-indigo-600">NRP/NIP: ${user.nrp || '-'} | Email: ${user.email}</p>
                </div>
                <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Password Baru *</label>
                <input id="swal-input-password" type="password" placeholder="Minimal 6 karakter" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-3" />
                
                <label class="block text-[10px] font-black uppercase text-gray-500 mb-1">Konfirmasi Password Baru *</label>
                <input id="swal-input-confirm" type="password" placeholder="Ulangi password baru" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                
                <div class="mt-2 text-right">
                    <button type="button" id="swal-toggle-pwd" class="text-[10px] text-indigo-600 font-bold hover:underline cursor-pointer">Lihat / Sembunyikan Password</button>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'SIMPAN PASSWORD',
        cancelButtonText: 'BATAL',
        didOpen: () => {
            const pwdInput = document.getElementById('swal-input-password');
            const confirmInput = document.getElementById('swal-input-confirm');
            const toggleBtn = document.getElementById('swal-toggle-pwd');
            if (toggleBtn && pwdInput && confirmInput) {
                toggleBtn.addEventListener('click', () => {
                    const isPassword = pwdInput.type === 'password';
                    pwdInput.type = isPassword ? 'text' : 'password';
                    confirmInput.type = isPassword ? 'text' : 'password';
                    toggleBtn.innerText = isPassword ? 'Sembunyikan Password' : 'Lihat Password';
                });
            }
            if (pwdInput) pwdInput.focus();
        },
        preConfirm: () => {
            const password = document.getElementById('swal-input-password').value;
            const confirm = document.getElementById('swal-input-confirm').value;
            if (!password) {
                Swal.showValidationMessage('Password baru wajib diisi.');
                return false;
            }
            if (password.length < 6) {
                Swal.showValidationMessage('Password minimal 6 karakter.');
                return false;
            }
            if (password !== confirm) {
                Swal.showValidationMessage('Konfirmasi password tidak cocok.');
                return false;
            }
            return { password };
        }
    });

    if (formValues && formValues.password) {
        Swal.fire({
            title: 'MEMPERBARUI PASSWORD...',
            text: `Sedang mengupdate password untuk ${user.name}...`,
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const res = await axios.post(route('users.change-password', user.id), {
                password: formValues.password
            });
            Swal.fire({
                title: 'BERHASIL!',
                text: res.data?.message || `Password untuk ${user.name} berhasil diperbarui. Personel dapat login dengan password baru tersebut.`,
                icon: 'success',
                confirmButtonColor: '#4f46e5'
            });
        } catch (err) {
            console.error(err);
            const errMsg = err.response?.data?.message || err.response?.data?.errors?.password?.[0] || 'Gagal mengubah password.';
            Swal.fire('GAGAL', errMsg, 'error');
        }
    }
};

/**
 * EDIT DATA
 */
const startEdit = (user) => {
    selectedUser.value = user;
    editForm.id = user.id;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.pangkat = user.pangkat;
    editForm.nrp = user.nrp;
    editForm.phone = user.phone;
    editForm.password = '';
    editForm.mfa_enabled = Boolean(user.mfa_enabled);
    showEditModal.value = true;
};

const submitEdit = () => {
    editForm.put(route('users.update', editForm.id), {
        onSuccess: () => {
            showEditModal.value = false;
            editForm.password = '';
            Swal.fire('BERHASIL', 'Data personel telah diperbarui.', 'success');
        },
        onError: (err) => {
            const msg = Object.values(err)[0] || 'Gagal memperbarui data personel.';
            Swal.fire('GAGAL', msg, 'error');
        }
    });
};

/**
 * KONFIRMASI: Verifikasi Password Admin
 */
const startConfirmation = (user) => {
    selectedUser.value = user;
    showPasswordModal.value = true;
};

const submitConfirmation = () => {
    form.patch(route('users.toggle', selectedUser.value.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showPasswordModal.value = false;
            form.reset();
            if (detailUser.value && selectedUser.value && detailUser.value.id === selectedUser.value.id) {
                detailUser.value.is_active = !detailUser.value.is_active;
            }
            if (props.users?.data && selectedUser.value) {
                const found = props.users.data.find(u => u.id === selectedUser.value.id);
                if (found) found.is_active = !found.is_active;
            }
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2500,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'success',
                title: 'Status otoritas akun personel berhasil diperbarui.'
            });
        },
        onError: (errors) => {
            Swal.fire('GAGAL', errors.password || 'Otoritas ditolak.', 'error');
        }
    });
};

const deleteUser = (user) => {
    Swal.fire({
        title: 'HAPUS PERSONEL?',
        text: "Akun akan dihapus permanen dari sistem!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'HAPUS AKUN'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('users.destroy', user.id), {
                onSuccess: () => Swal.fire('Dihapus', 'Data telah dibersihkan.', 'success')
            });
        }
    });
};

const toggleMitraAccess = (user) => {
    if (!user) return;
    const prev = Boolean(user.can_access_mitra);
    user.can_access_mitra = !prev;
    if (detailUser.value && detailUser.value.id === user.id) {
        detailUser.value.can_access_mitra = user.can_access_mitra;
    }
    if (props.users?.data) {
        const found = props.users.data.find(u => u.id === user.id);
        if (found) found.can_access_mitra = user.can_access_mitra;
    }

    router.post(route('users.toggle-mitra-access', user.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'success',
                title: `Akses Modul Mitra: ${user.can_access_mitra ? 'DIBERIKAN (AKTIF)' : 'DINONAKTIFKAN'}`
            });
        },
        onError: () => {
            user.can_access_mitra = prev;
            if (detailUser.value && detailUser.value.id === user.id) detailUser.value.can_access_mitra = prev;
            if (props.users?.data) {
                const found = props.users.data.find(u => u.id === user.id);
                if (found) found.can_access_mitra = prev;
            }
            Swal.fire('GAGAL', 'Gagal memperbarui hak akses Modul Mitra.', 'error');
        }
    });
};

const toggleKoperasiAccess = (user) => {
    if (!user) return;
    const prev = Boolean(user.can_manage_koperasi);
    user.can_manage_koperasi = !prev;
    if (detailUser.value && detailUser.value.id === user.id) {
        detailUser.value.can_manage_koperasi = user.can_manage_koperasi;
    }
    if (props.users?.data) {
        const found = props.users.data.find(u => u.id === user.id);
        if (found) found.can_manage_koperasi = user.can_manage_koperasi;
    }

    router.post(route('users.toggle-koperasi-access', user.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'success',
                title: `Pengurus Koperasi: ${user.can_manage_koperasi ? 'AKTIF (PENGURUS)' : 'NONAKTIF'}`
            });
        },
        onError: () => {
            user.can_manage_koperasi = prev;
            if (detailUser.value && detailUser.value.id === user.id) detailUser.value.can_manage_koperasi = prev;
            if (props.users?.data) {
                const found = props.users.data.find(u => u.id === user.id);
                if (found) found.can_manage_koperasi = prev;
            }
            Swal.fire('GAGAL', 'Gagal memperbarui hak akses Pengurus Simpan Pinjam.', 'error');
        }
    });
};

const toggleMfaAccess = (user) => {
    if (!user) return;
    const prev = Boolean(user.mfa_enabled);
    user.mfa_enabled = !prev;
    if (detailUser.value && detailUser.value.id === user.id) {
        detailUser.value.mfa_enabled = user.mfa_enabled;
    }
    if (props.users?.data) {
        const found = props.users.data.find(u => u.id === user.id);
        if (found) found.mfa_enabled = user.mfa_enabled;
    }

    router.post(route('users.toggle-mfa', user.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'success',
                title: `M2F OTP WhatsApp (${user.name}): ${user.mfa_enabled ? 'AKTIF' : 'NONAKTIF'}`
            });
        },
        onError: () => {
            user.mfa_enabled = prev;
            if (detailUser.value && detailUser.value.id === user.id) detailUser.value.mfa_enabled = prev;
            if (props.users?.data) {
                const found = props.users.data.find(u => u.id === user.id);
                if (found) found.mfa_enabled = prev;
            }
            Swal.fire('GAGAL', 'Gagal memperbarui status M2F OTP WhatsApp.', 'error');
        }
    });
};

const getStatusClass = (status) => {
    return status ? 'bg-emerald-50 text-emerald-600 border-emerald-100 shadow-sm' : 'bg-rose-50 text-rose-600 border-rose-100 shadow-sm';
};

// --- AUTO REFRESH (Opsional) ---
let interval = null;
onMounted(() => {
    // interval = setInterval(() => router.reload({ only: ['users'] }), 10000);
});
onUnmounted(() => {
    if (interval) clearInterval(interval);
});
</script>

<template>
    <Head title="Otoritas Akses Personel" />

    <AuthenticatedLayout>
        <div class="space-y-6 font-sans">
            
            <!-- Page Header Card -->
            <div class="bg-white p-4 sm:p-6 lg:p-8 rounded-2xl sm:rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="font-extrabold text-lg sm:text-xl text-slate-900 uppercase tracking-tight">Otoritas Akses Personel</h2>
                    <p class="text-xs text-slate-500 font-semibold mt-0.5">Manajemen Pengguna, Peran Otoritas, & Verifikasi Akses Sistem</p>
                </div>
                
                <div class="flex flex-wrap gap-2 sm:gap-3 w-full md:w-auto">
                    <button @click="openPreview" class="px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-2xl font-extrabold text-xs uppercase shadow-xs hover:bg-slate-100 transition">
                        Pratinjau Rekap
                    </button>
                    <a :href="route('users.print-token-pdf')" target="_blank" class="px-4 py-2.5 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl font-extrabold text-xs uppercase shadow-xs hover:bg-emerald-100 transition flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <span>Cetak Token PDF</span>
                    </a>
                    <button @click="openBulkModal" class="px-4 py-2.5 bg-indigo-600 text-white rounded-2xl font-extrabold text-xs uppercase shadow-md shadow-indigo-500/20 hover:bg-indigo-700 transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                        <span>+ Tambah Banyak (Bulk)</span>
                    </button>
                    <button @click="showAddModal = true" class="px-4 py-2.5 bg-blue-600 text-white rounded-2xl font-extrabold text-xs uppercase shadow-md shadow-blue-500/20 hover:bg-blue-700 transition">
                        + Tambah Personel
                    </button>
                </div>
            </div>

            <!-- BANNER ANTREAN PERSONEL DARI SP JAGA -->
            <div v-if="pendingSpPersonnel && pendingSpPersonnel.length > 0" class="p-6 rounded-3xl bg-amber-50 border border-amber-300 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="px-3 py-1 bg-amber-400 text-slate-950 font-black text-[10px] uppercase rounded-full tracking-wider">
                            Antrean Personel SP Jaga
                        </span>
                        <span class="text-xs font-bold text-slate-800">{{ pendingSpPersonnel.length }} Personel Belum Terdaftar</span>
                    </div>
                    <p class="text-xs text-slate-600 font-medium max-w-2xl">
                        Ditemukan data nama, pangkat, & NRP dari dokumen SP Jaga Siaga Sintel yang belum ada di database. Klik tombol di samping untuk memuat seluruhnya ke antrean pendaftaran masal (Bulk) dan tinggal memasukkan nomor WhatsApp saja.
                    </p>
                </div>

                <button 
                    @click="loadPendingSpPersonnel"
                    type="button"
                    class="px-6 py-3 bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-xs uppercase tracking-wider rounded-2xl transition shadow-md shadow-amber-500/20 active:scale-[0.98] shrink-0 cursor-pointer"
                >
                    + Muat {{ pendingSpPersonnel.length }} Personel ke Bulk
                </button>
            </div>

            <!-- Table Card -->
            <div class="bg-white rounded-2xl sm:rounded-3xl border border-[#E2E8F0] shadow-xs overflow-hidden p-3 sm:p-6">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                <th class="pb-4 px-4">Personel</th>
                                <th class="pb-4 px-4 text-center">Identitas (NRP)</th>
                                <th class="pb-4 px-4 text-center">Status</th>
                                <th class="pb-4 px-4 text-right">Opsi Kelola</th>
                            </tr>
                        </thead>
                    <tbody class="divide-y divide-gray-50 text-[11px]">
                        <tr v-for="user in users.data" :key="user.id" class="group hover:bg-indigo-50/30 transition-all">
                            <td class="py-6 px-4">
                                <div class="flex flex-col leading-tight">
                                    <button @click="showPersonnelDetail(user)" class="text-left font-black text-indigo-600 hover:text-indigo-900 hover:underline cursor-pointer transition text-xs">{{ user.name }}</button>
                                    <span class="text-[9px] text-gray-400 font-bold tracking-tighter">{{ user.email }}</span>
                                    <span v-if="user.phone" class="text-[8px] text-indigo-500 font-bold mt-1">WA: {{ user.phone }}</span>
                                </div>
                            </td>
                            <td class="py-6 px-4 text-center">
                                <div class="flex flex-col leading-tight">
                                    <span class="font-black text-gray-800 uppercase italic">{{ user.pangkat || 'BELUM DIISI' }}</span>
                                    <span class="text-[10px] text-indigo-500 font-black">NRP. {{ user.nrp || '-' }}</span>
                                </div>
                            </td>
                            <td class="py-6 px-4 text-center">
                                <span :class="getStatusClass(user.is_active)" class="px-4 py-1.5 rounded-full text-[8px] font-black uppercase tracking-widest border">
                                    {{ user.is_active ? 'TERVERIFIKASI' : 'SUSPEND' }}
                                </span>
                            </td>
                            <td class="py-6 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="showPersonnelDetail(user)" class="p-2 bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white rounded-lg transition-colors shadow-xs" title="Buka Detail & Matriks Akses Personel">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </button>
                                    <button @click="startEdit(user)" class="p-2 bg-gray-50 text-gray-400 hover:text-indigo-600 rounded-lg transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button @click="toggleMitraAccess(user)" 
                                        :class="user.can_access_mitra ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-600 hover:text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-700 hover:text-white'"class="px-2.5 py-1.5 text-[8px] font-black uppercase rounded-lg transition-all shadow-xs"title="Toggle Hak Akses Modul Mitra"> Mitra: {{ user.can_access_mitra ? 'AKTIF' : 'OFF' }}
                                    </button>
                                    <button @click="toggleKoperasiAccess(user)" 
                                        :class="user.can_manage_koperasi ? 'bg-violet-100 text-violet-800 hover:bg-violet-600 hover:text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-700 hover:text-white'" class="px-2.5 py-1.5 text-[8px] font-black uppercase rounded-lg transition-all shadow-xs" title="Toggle Hak Akses Pengurus Simpan Pinjam (Koperasi)"> Koperasi: {{ user.can_manage_koperasi ? 'PENGURUS' : 'OFF' }}
                                    </button>
                                    <button @click="toggleMfaAccess(user)" 
                                        :class="user.mfa_enabled ? 'bg-amber-100 text-amber-800 hover:bg-amber-600 hover:text-white border border-amber-200' : 'bg-slate-100 text-slate-500 hover:bg-slate-700 hover:text-white border border-slate-200'" 
                                        class="px-2.5 py-1.5 text-[8px] font-black uppercase rounded-lg transition-all shadow-xs flex items-center gap-1 cursor-pointer" 
                                        title="Toggle M2F OTP WhatsApp (Verifikasi Login 2 Langkah)">
                                        <svg class="w-3 h-3" :class="user.mfa_enabled ? 'text-amber-700' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        <span>M2F WA: {{ user.mfa_enabled ? 'AKTIF' : 'OFF' }}</span>
                                    </button>
                                                                        <button v-if="!user.is_active" @click="printSingleTokenPdf(user.id)" class="px-2.5 py-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 text-[8px] font-black uppercase rounded-lg hover:bg-emerald-600 hover:text-white transition-all shadow-xs flex items-center gap-1 cursor-pointer" title="Cetak Token PDF Perorangan">
                                        <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                        <span>PDF</span>
                                    </button>
                                    <button @click="requestToken(user)" class="px-3 py-2 bg-amber-100 text-amber-700 text-[8px] font-black uppercase rounded-lg hover:bg-amber-500 hover:text-white transition-all shadow-sm" title="Generate Token Reset Password 6-Digit"> Req Token
                                    </button>
                                    <button @click="openChangePasswordModal(user)" class="px-2.5 py-2 bg-indigo-50 text-indigo-700 border border-indigo-200 text-[8px] font-black uppercase rounded-lg hover:bg-indigo-600 hover:text-white transition-all shadow-xs flex items-center gap-1 cursor-pointer" title="Ubah Password Akun Personel Secara Langsung">
                                        <svg class="w-3 h-3 text-indigo-600 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                                        <span>Ubah Pass</span>
                                    </button>
                                    <button @click="startConfirmation(user)" 
                                        :class="user.is_active ? 'border-rose-500 text-rose-500 hover:bg-rose-500 hover:text-white' : 'bg-indigo-600 text-white hover:bg-indigo-700'"class="px-5 py-2 font-black text-[9px] uppercase rounded-xl shadow-lg transition-all active:scale-95 border-2 border-transparent">
                                        {{ user.is_active ? 'SUSPEND' : 'AKTIFKAN' }}
                                    </button>
                                    <button @click="deleteUser(user)" class="p-2 text-gray-300 hover:text-rose-600 transition-colors text-lg">
                                        &times;
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-8 flex items-center justify-between px-4">
                <div class="text-[10px] font-bold text-gray-400 uppercase italic"> Showing {{ users.from }} to {{ users.to }} of {{ users.total }} Personnels
                </div>
                <div class="flex gap-1">
                    <Link v-for="(link, index) in users.links" :key="index"
                        :href="link.url || '#'"v-html="link.label"
                        :class="[
                            'px-4 py-2 rounded-xl text-[10px] font-black transition-all border',
                            link.active ? 'bg-indigo-600 text-white border-indigo-600 shadow-md' : 'bg-white text-gray-400 border-gray-100 hover:bg-gray-50',
                            !link.url ? 'opacity-30 cursor-not-allowed' : ''
                        ]"
                    />
                </div>
            </div>
        </div>
        </div>

        <div style="position: absolute; left: -9999px;">
            <div id="area-rekap-personel" class="p-10 bg-white text-black" style="width: 1050px; font-family: Arial, sans-serif;">
                <div class="inline-table uppercase mb-10 text-left text-base font-bold">
                    <p class="leading-none tracking-tight">KOMANDO ARMADA II</p>
                    <p class="leading-none tracking-tight text-center mt-1">DETASEMEN INTELIJEN</p>
                    <div class="border-b-[2px] border-black mt-2 w-full"></div>
                </div>

                <div class="text-center mb-8 uppercase text-base">
                    <h3 class="font-bold underline tracking-widest leading-none">REKAPITULASI OTORITAS AKSES PERSONEL DIGITAL</h3>
                    <p class="font-normal mt-2 text-sm">Status Data: Per {{ formatLongDate(new Date()) }}</p>
                </div>

                <table class="w-full border-collapse border-[1.5px] border-black text-[10px]">
                    <thead>
                        <tr class="bg-gray-100 uppercase font-bold text-center">
                            <th class="border border-black px-2 py-3 w-[5%]">NO</th>
                            <th class="border border-black px-3 py-3 text-left w-[25%]">NAMA LENGKAP</th>
                            <th class="border border-black px-3 py-3 text-center w-[15%]">PANGKAT</th>
                            <th class="border border-black px-3 py-3 text-center w-[15%]">NRP/NIP</th>
                            <th class="border border-black px-3 py-3 text-left w-[20%]">EMAIL / AKSES</th>
                            <th class="border border-black px-3 py-3 w-[10%]">ROLE</th>
                            <th class="border border-black px-3 py-3 w-[10%]">STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(u, index) in allUsers" :key="u.id">
                            <td class="border border-black px-2 py-2 text-center">{{ index + 1 }}</td>
                            <td class="border border-black px-3 py-2 font-bold uppercase">{{ u.name }}</td>
                            <td class="border border-black px-3 py-2 text-center uppercase">{{ u.pangkat || '-' }}</td>
                            <td class="border border-black px-3 py-2 text-center font-mono">{{ u.nrp || '-' }}</td>
                            <td class="border border-black px-3 py-2" style="text-transform: none;">{{ u.email }}</td>
                            <td class="border border-black px-3 py-2 text-center uppercase">{{ roleLabels[u.role] || u.role }}</td>
                            <td class="border border-black px-3 py-2 text-center font-bold">{{ u.is_active ? 'TERVERIFIKASI' : 'SUSPEND' }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-16 flex justify-end text-center text-sm">
                    <div class="w-72">
                        <p class="mb-20 uppercase">Surabaya, {{ formatLongDate(new Date()) }}<br>Admin Otoritas,</p>
                        <p class="font-bold underline leading-none uppercase">{{ $page.props.auth.user.name }}</p>
                        <p class="font-normal mt-1 uppercase">{{ $page.props.auth.user.pangkat || 'STAF' }}</p>
                        <p class="font-normal mt-0.5 uppercase">{{ $page.props.auth.user.nrp ? 'NRP. ' + $page.props.auth.user.nrp : '' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showPreview" class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md">
            <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-6xl w-full h-[90vh] overflow-hidden flex flex-col">
                <div class="p-6 border-b flex justify-between items-center bg-gray-50">
                    <div class="flex items-center gap-3">
                        <div class="h-8 w-8 bg-indigo-600 rounded-full flex items-center justify-center text-white text-xs font-black italic">PDF</div>
                        <h3 class="font-black text-gray-800 uppercase text-xs tracking-widest">Personnel Report Preview (Landscape)</h3>
                    </div>
                    <button @click="showPreview = false" class="text-gray-400 hover:text-red-500 font-black text-2xl">&times;</button>
                </div>
                <div class="flex-1 overflow-y-auto bg-gray-700 p-4 md:p-10 flex justify-center items-start">
                    <img :src="previewImage" class="shadow-2xl border border-gray-400 bg-white max-w-full h-auto" />
                </div>
                <div class="p-6 border-t bg-gray-50 flex justify-end gap-3">
                    <button @click="showPreview = false" class="px-6 py-2.5 text-[10px] font-black uppercase text-gray-500">Batal</button>
                    <button @click="downloadPDF(); showPreview = false" class="px-8 py-2.5 bg-indigo-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg">Download PDF Rekap</button>
                </div>
            </div>
        </div>

        <div v-if="showAddModal" class="fixed inset-0 bg-indigo-950/20 backdrop-blur-md flex items-center justify-center z-[100] p-6">
            <div class="bg-white w-full max-w-xl rounded-[2.5rem] shadow-2xl p-10 animate-in zoom-in duration-200">
                <h3 class="text-sm font-black text-indigo-950 uppercase mb-8 border-l-4 border-indigo-600 pl-4 italic">Registrasi Akun Personel Baru</h3>
                <form @submit.prevent="submitAdd" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">Nama Lengkap</label>
                            <input v-model="addForm.name" type="text" placeholder="MASUKKAN NAMA" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-black uppercase focus:ring-2 focus:ring-indigo-600" required />
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">Pangkat</label>
                            <input v-model="addForm.pangkat" type="text" placeholder="SERDA" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-black uppercase focus:ring-2 focus:ring-indigo-600" required />
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">NRP</label>
                            <input v-model="addForm.nrp" type="text" placeholder="IDENTITAS" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-black focus:ring-2 focus:ring-indigo-600" required />
                        </div>

                        <div class="col-span-2">
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">Otoritas Akses (Role)</label>
                            <select v-model="addForm.role" class="w-full bg-indigo-50 border-none rounded-2xl p-4 text-[11px] font-black uppercase focus:ring-2 focus:ring-indigo-600">
                                <option value="super_admin">SUPER ADMIN</option>
                                <option value="admin">ADMIN</option>
                                <option value="personel">PERSONEL</option>
                                <option value="personel_sintel">PERSONEL SINTEL</option>
                                <option value="komandan">KOMANDAN (APPROVER)</option>
                                <option value="wadan">WAKIL KOMANDAN</option>
                                <option value="pasops">PASOPS</option>
                                <option value="staf">STAF ADMINISTRASI</option>
                                <option value="danunit1">DAN UNIT I / LID</option>
                                <option value="danunit2">DAN UNIT II / PAMGAL</option>
                                <option value="kaurmintel">KAUR MINTEL</option>
                                <option value="paurset">PAUR SET</option>
                            </select>
                        </div>

                        <div>
                            <div class="flex items-center justify-between ml-2">
                                <label class="text-[9px] font-black text-gray-400 uppercase">Email</label>
                                <span class="text-[8px] font-extrabold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md uppercase tracking-wider">Otomatis Terisi</span>
                            </div>
                            <input 
                                v-model="addForm.email" 
                                @input="isEmailManuallyEdited = true"
                                type="email" 
                                placeholder="otomatis dari NRP/Nama" 
                                class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-bold focus:ring-2 focus:ring-indigo-600" 
                                required 
                            />
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">WhatsApp</label>
                            <input v-model="addForm.phone" type="text" placeholder="0812XXXXXXXX" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-black focus:ring-2 focus:ring-indigo-600" required />
                        </div>
                        <div class="col-span-2 pt-2 border-t border-gray-100 flex items-center justify-between">
                            <div>
                                <label class="text-[9px] font-black text-gray-700 uppercase flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    M2F OTP WhatsApp (Login 2 Langkah)
                                </label>
                                <p class="text-[8.5px] text-gray-400 font-medium">Wajibkan verifikasi OTP WhatsApp dinas saat login ke sistem</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="addForm.mfa_enabled" class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                            </label>
                        </div>
                    </div>
                    <div class="bg-indigo-50 p-4 rounded-2xl border border-indigo-100 mt-4 text-center">
                        <p class="text-[9px] text-indigo-600 font-bold uppercase leading-relaxed italic">Sistem akan otomatis mengirimkan password dan detail jabatan ke WhatsApp personel.</p>
                    </div>
                    <div class="flex gap-4 pt-4">
                        <button type="button" @click="showAddModal = false" class="flex-1 py-4 text-[9px] font-black text-gray-400 uppercase transition-all hover:text-indigo-600">Batal</button>
                        <button type="submit" :disabled="addForm.processing" class="flex-[2] py-4 bg-indigo-600 text-white rounded-2xl text-[9px] font-black uppercase shadow-xl hover:bg-indigo-700 active:scale-95 transition-all">BUAT AKUN & KIRIM WA</button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showEditModal" class="fixed inset-0 bg-indigo-950/20 backdrop-blur-md flex items-center justify-center z-[100] p-6">
            <div class="bg-white w-full max-w-md rounded-[2.5rem] shadow-2xl p-10 animate-in zoom-in duration-200">
                <h3 class="text-sm font-black text-indigo-950 uppercase mb-8 border-l-4 border-indigo-600 pl-4 italic">Update Data Personel</h3>
                <form @submit.prevent="submitEdit" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">Nama Lengkap</label>
                            <input v-model="editForm.name" type="text" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-black focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">Pangkat</label>
                            <input v-model="editForm.pangkat" type="text" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-black uppercase focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">NRP</label>
                            <input v-model="editForm.nrp" type="text" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-black focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div class="col-span-2">
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">Email</label>
                            <input v-model="editForm.email" type="email" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-bold focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div class="col-span-2">
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">WhatsApp</label>
                            <input v-model="editForm.phone" type="text" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-bold focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div class="col-span-2 pt-2 border-t border-gray-100">
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2 flex items-center gap-1.5">
                                <svg class="w-3 h-3 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                                Password Baru (Opsional - Kosongkan jika tidak diubah)
                            </label>
                            <input v-model="editForm.password" type="password" placeholder="Ketik password baru jika ingin mengubah..." class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-bold focus:ring-2 focus:ring-indigo-500 mt-1" autocomplete="new-password" />
                        </div>
                        <div class="col-span-2 pt-2 border-t border-gray-100 flex items-center justify-between">
                            <div>
                                <label class="text-[9px] font-black text-gray-700 uppercase flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    M2F OTP WhatsApp (Login 2 Langkah)
                                </label>
                                <p class="text-[8.5px] text-gray-400 font-medium">Kirim kode OTP ke nomor WhatsApp personel saat autentikasi login</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="editForm.mfa_enabled" class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                            </label>
                        </div>
                    </div>
                    <div class="flex gap-4 pt-4">
                        <button type="button" @click="showEditModal = false" class="flex-1 py-4 text-[9px] font-black text-gray-400 uppercase">Tutup</button>
                        <button type="submit" :disabled="editForm.processing" class="flex-[2] py-4 bg-gray-900 text-white rounded-2xl text-[9px] font-black uppercase shadow-xl hover:bg-indigo-600 transition-all">Update Personel</button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showPasswordModal" class="fixed inset-0 bg-indigo-950/20 backdrop-blur-md flex items-center justify-center z-[100] p-6">
            <div class="bg-white w-full max-w-sm rounded-[2.5rem] shadow-2xl border border-indigo-50 p-10 animate-in fade-in zoom-in duration-300">
                <div class="text-center mb-8">
                    <div class="h-16 w-16 bg-rose-50 text-rose-600 rounded-3xl flex items-center justify-center text-2xl mx-auto mb-4 animate-bounce"></div>
                    <h3 class="text-sm font-black text-indigo-950 uppercase tracking-[0.1em]">Otoritas Admin</h3>
                    <p class="text-[9px] text-gray-400 font-bold uppercase mt-2 italic">Konfirmasi password untuk merubah izin akses</p>
                </div>
                <div class="space-y-6">
                    <input v-model="form.password" type="password" placeholder="PASSWORD ADMIN" @keyup.enter="submitConfirmation" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[10px] font-black text-center uppercase tracking-widest focus:ring-2 focus:ring-indigo-500" autofocus />
                    <div class="flex gap-4">
                        <button @click="showPasswordModal = false" class="flex-1 py-4 text-[9px] font-black text-gray-400 uppercase">Batal</button>
                        <button @click="submitConfirmation" :disabled="form.processing" class="flex-[2] py-4 bg-indigo-600 text-white rounded-2xl text-[9px] font-black uppercase shadow-xl active:scale-95">LANJUTKAN</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- MODAL DETAIL PERSONEL & HAK AKSES FITUR -->
        <div v-if="showDetailModal && detailUser" class="fixed inset-0 bg-indigo-950/40 backdrop-blur-md flex items-center justify-center z-[100] p-4 sm:p-6 overflow-y-auto">
            <div class="bg-white w-full max-w-3xl rounded-[2.5rem] shadow-2xl p-6 sm:p-8 space-y-6 animate-in zoom-in duration-200 my-auto border border-slate-100 max-h-[92vh] flex flex-col overflow-hidden">
                
                <!-- HEADER PROFIL (FIXED) -->
                <div class="flex justify-between items-start border-b border-slate-100 pb-5 shrink-0">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 bg-indigo-600 text-white font-black text-xl rounded-2xl flex items-center justify-center shadow-md uppercase">
                            {{ detailUser.name ? detailUser.name.charAt(0) : 'P' }}
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900 font-sans tracking-wide uppercase">{{ detailUser.name }}</h3>
                            <p class="text-xs text-indigo-600 font-extrabold uppercase mt-0.5">{{ detailUser.pangkat || 'BELUM DIISI' }} / NRP. {{ detailUser.nrp || '-' }}</p>
                            <p class="text-[11px] text-slate-500 font-mono mt-0.5">{{ detailUser.email }} <span v-if="detailUser.phone" class="ml-2 font-sans font-bold text-emerald-600">WA: {{ detailUser.phone }}</span></p>
                        </div>
                    </div>
                    <button @click="showDetailModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-2xl cursor-pointer">&times;</button>
                </div>

                <!-- SCROLLABLE BODY -->
                <div class="overflow-y-auto space-y-5 pr-1 custom-scrollbar">

                    <!-- STATUS AKUN & KENDALI SUSPEND / AKTIF -->
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/80 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Status Otoritas Akun:</span>
                            <div class="flex items-center gap-2 mt-1">
                                <span :class="getStatusClass(detailUser.is_active)" class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border">
                                    {{ detailUser.is_active ? 'TERVERIFIKASI & AKTIF' : 'BELUM AKTIF / SUSPEND' }}
                                </span>
                                <span class="text-xs font-bold text-slate-700 uppercase">({{ roleLabels[detailUser.role] || detailUser.role }})</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <!-- BUTTON TOGGLE AKTIF / SUSPEND DARI DALAM MODAL -->
                            <button 
                                @click="startConfirmation(detailUser)"
                                :class="detailUser.is_active ? 'bg-rose-50 border border-rose-200 text-rose-700 hover:bg-rose-600 hover:text-white' : 'bg-indigo-600 hover:bg-indigo-700 text-white'"
                                class="px-3.5 py-2 text-xs font-black uppercase rounded-xl transition flex items-center justify-center gap-1.5 cursor-pointer flex-1 sm:flex-initial shadow-xs"
                            >
                                <svg v-if="detailUser.is_active" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                <span>{{ detailUser.is_active ? 'SUSPEND AKUN' : 'AKTIFKAN AKUN' }}</span>
                            </button>

                            <!-- BUTTON CETAK TOKEN PDF PERORANGAN -->
                            <button v-if="!detailUser.is_active" @click="printSingleTokenPdf(detailUser.id)" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black uppercase rounded-xl shadow-md shadow-emerald-500/20 transition flex items-center justify-center gap-1.5 cursor-pointer flex-1 sm:flex-initial">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                <span>Cetak Token PDF</span>
                            </button>
                        </div>
                    </div>

                    <!-- MATRIKS HAK AKSES FITUR SINDEN -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-xs font-black text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                <span>Matriks Hak Akses Fitur Sistem:</span>
                            </h4>
                            <span class="text-[10px] text-indigo-600 font-black uppercase bg-indigo-50 px-2 py-0.5 rounded-md">Klik Kartu atau Sakelar untuk Mengubah Status</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 text-xs">
                            
                            <!-- 1. PENGURUS SIMPAN PINJAM (KOPERASI) - INTERACTIVE TOGGLE CARD -->
                            <div 
                                @click="toggleKoperasiAccess(detailUser)"
                                :class="detailUser.can_manage_koperasi ? 'border-violet-500 bg-violet-50/50 shadow-sm ring-2 ring-violet-500/20' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/70'"
                                class="p-4 border rounded-2xl cursor-pointer transition-all duration-200 flex flex-col justify-between group select-none relative"
                                role="button"
                                title="Klik untuk mengaktifkan / menonaktifkan Pengurus Simpan Pinjam"
                            >
                                <div>
                                    <div class="flex items-start justify-between gap-2 mb-2">
                                        <div class="flex items-center gap-2.5">
                                            <div :class="detailUser.can_manage_koperasi ? 'bg-violet-600 text-white shadow-sm shadow-violet-600/30' : 'bg-slate-100 text-slate-500'" class="w-8 h-8 rounded-xl flex items-center justify-center font-bold shrink-0 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </div>
                                            <div>
                                                <span class="font-black text-slate-900 uppercase block text-[11px] tracking-tight">Pengurus Simpan Pinjam</span>
                                                <span class="text-[9px] text-slate-400 font-bold uppercase">Unit Koperasi SINDEN</span>
                                            </div>
                                        </div>
                                        <!-- TOGGLE SWITCH VISUAL -->
                                        <div class="flex items-center gap-1.5 shrink-0 pt-0.5">
                                            <div :class="detailUser.can_manage_koperasi ? 'bg-violet-600' : 'bg-slate-300'" class="w-11 h-6 rounded-full p-0.5 transition-colors duration-200 ease-in-out relative">
                                                <div :class="detailUser.can_manage_koperasi ? 'translate-x-5' : 'translate-x-0'" class="w-5 h-5 bg-white rounded-full shadow-md transform transition-transform duration-200 ease-in-out"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-[10.5px] text-slate-500 leading-snug">Otoritas verifikasi pinjaman prajurit, pencatatan angsuran, pembukuan kas & cetak kuitansi.</p>
                                </div>
                                <div class="mt-3.5 pt-2.5 border-t border-slate-100/80 flex items-center justify-between">
                                    <span class="text-[9px] text-slate-400 font-extrabold uppercase tracking-wider">Status Otoritas:</span>
                                    <span :class="detailUser.can_manage_koperasi ? 'bg-violet-600 text-white font-black shadow-xs shadow-violet-600/30' : 'bg-slate-100 text-slate-500 font-bold'" class="px-2.5 py-1 text-[9px] rounded-lg uppercase tracking-wider transition">
                                        {{ detailUser.can_manage_koperasi ? 'AKTIF (PENGURUS)' : 'NONAKTIF (OFF)' }}
                                    </span>
                                </div>
                            </div>

                            <!-- 2. MODUL KEUANGAN MITRA - INTERACTIVE TOGGLE CARD -->
                            <div 
                                @click="toggleMitraAccess(detailUser)"
                                :class="detailUser.can_access_mitra ? 'border-emerald-500 bg-emerald-50/50 shadow-sm ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/70'"
                                class="p-4 border rounded-2xl cursor-pointer transition-all duration-200 flex flex-col justify-between group select-none relative"
                                role="button"
                                title="Klik untuk mengaktifkan / menonaktifkan Modul Mitra"
                            >
                                <div>
                                    <div class="flex items-start justify-between gap-2 mb-2">
                                        <div class="flex items-center gap-2.5">
                                            <div :class="detailUser.can_access_mitra ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/30' : 'bg-slate-100 text-slate-500'" class="w-8 h-8 rounded-xl flex items-center justify-center font-bold shrink-0 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                            </div>
                                            <div>
                                                <span class="font-black text-slate-900 uppercase block text-[11px] tracking-tight">Modul Keuangan Mitra</span>
                                                <span class="text-[9px] text-slate-400 font-bold uppercase">Kerjasama Eksternal</span>
                                            </div>
                                        </div>
                                        <!-- TOGGLE SWITCH VISUAL -->
                                        <div class="flex items-center gap-1.5 shrink-0 pt-0.5">
                                            <div :class="detailUser.can_access_mitra ? 'bg-emerald-600' : 'bg-slate-300'" class="w-11 h-6 rounded-full p-0.5 transition-colors duration-200 ease-in-out relative">
                                                <div :class="detailUser.can_access_mitra ? 'translate-x-5' : 'translate-x-0'" class="w-5 h-5 bg-white rounded-full shadow-md transform transition-transform duration-200 ease-in-out"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-[10.5px] text-slate-500 leading-snug">Izin verifikasi & pencatatan arus pembayaran kontribusi mitra kerja satuan.</p>
                                </div>
                                <div class="mt-3.5 pt-2.5 border-t border-slate-100/80 flex items-center justify-between">
                                    <span class="text-[9px] text-slate-400 font-extrabold uppercase tracking-wider">Status Otoritas:</span>
                                    <span :class="detailUser.can_access_mitra ? 'bg-emerald-600 text-white font-black shadow-xs shadow-emerald-600/30' : 'bg-slate-100 text-slate-500 font-bold'" class="px-2.5 py-1 text-[9px] rounded-lg uppercase tracking-wider transition">
                                        {{ detailUser.can_access_mitra ? 'DIBERIKAN (AKTIF)' : 'NONAKTIF (OFF)' }}
                                    </span>
                                </div>
                            </div>

                            <!-- 3. M2F OTP WHATSAPP (LOGIN 2 LANGKAH) - INTERACTIVE TOGGLE CARD -->
                            <div 
                                @click="toggleMfaAccess(detailUser)"
                                :class="detailUser.mfa_enabled ? 'border-amber-500 bg-amber-50/50 shadow-sm ring-2 ring-amber-500/20' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/70'"
                                class="p-4 border rounded-2xl cursor-pointer transition-all duration-200 flex flex-col justify-between group select-none relative"
                                role="button"
                                title="Klik untuk mengaktifkan / menonaktifkan M2F OTP WhatsApp"
                            >
                                <div>
                                    <div class="flex items-start justify-between gap-2 mb-2">
                                        <div class="flex items-center gap-2.5">
                                            <div :class="detailUser.mfa_enabled ? 'bg-amber-600 text-white shadow-sm shadow-amber-600/30' : 'bg-slate-100 text-slate-500'" class="w-8 h-8 rounded-xl flex items-center justify-center font-bold shrink-0 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            </div>
                                            <div>
                                                <span class="font-black text-slate-900 uppercase block text-[11px] tracking-tight">M2F OTP WhatsApp</span>
                                                <span class="text-[9px] text-slate-400 font-bold uppercase">Keamanan Login 2 Langkah</span>
                                            </div>
                                        </div>
                                        <!-- TOGGLE SWITCH VISUAL -->
                                        <div class="flex items-center gap-1.5 shrink-0 pt-0.5">
                                            <div :class="detailUser.mfa_enabled ? 'bg-amber-600' : 'bg-slate-300'" class="w-11 h-6 rounded-full p-0.5 transition-colors duration-200 ease-in-out relative">
                                                <div :class="detailUser.mfa_enabled ? 'translate-x-5' : 'translate-x-0'" class="w-5 h-5 bg-white rounded-full shadow-md transform transition-transform duration-200 ease-in-out"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-[10.5px] text-slate-500 leading-snug">Wajibkan verifikasi kode OTP 6-digit via WhatsApp dinas personel saat proses login akun.</p>
                                </div>
                                <div class="mt-3.5 pt-2.5 border-t border-slate-100/80 flex items-center justify-between">
                                    <span class="text-[9px] text-slate-400 font-extrabold uppercase tracking-wider">Status Keamanan:</span>
                                    <span :class="detailUser.mfa_enabled ? 'bg-amber-600 text-white font-black shadow-xs shadow-amber-600/30' : 'bg-slate-100 text-slate-500 font-bold'" class="px-2.5 py-1 text-[9px] rounded-lg uppercase tracking-wider transition">
                                        {{ detailUser.mfa_enabled ? 'AKTIF (WA OTP WAJIB)' : 'NONAKTIF (OFF)' }}
                                    </span>
                                </div>
                            </div>

                            <!-- 6. TANDA TANGAN DIGITAL (TTE) -->
                            <div class="p-4 bg-slate-50/60 border border-slate-200 rounded-2xl flex flex-col justify-between">
                                <div>
                                    <div class="flex items-start justify-between gap-2 mb-2">
                                        <div class="flex items-center gap-2.5">
                                            <div :class="(detailUser.role === 'admin' || detailUser.role === 'komandan') ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-600'" class="w-8 h-8 rounded-xl flex items-center justify-center font-bold shrink-0">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                            </div>
                                            <div>
                                                <span class="font-black text-slate-900 uppercase block text-[11px] tracking-tight">Tanda Tangan Digital (TTE)</span>
                                                <span class="text-[9px] text-slate-400 font-bold uppercase">QR Code Kedinasan</span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-[10.5px] text-slate-500 leading-snug">Otoritas penempelan QR Code TTD kedinasan pada dokumen resmi satuan.</p>
                                </div>
                                <div class="mt-3.5 pt-2.5 border-t border-slate-200/60 flex items-center justify-between">
                                    <span class="text-[9px] text-slate-400 font-extrabold uppercase tracking-wider">Peran:</span>
                                    <span :class="(detailUser.role === 'admin' || detailUser.role === 'komandan') ? 'bg-indigo-600 text-white font-black' : 'bg-slate-200 text-slate-700 font-bold'" class="px-2.5 py-1 text-[9px] rounded-lg uppercase tracking-wider">
                                        {{ (detailUser.role === 'admin' || detailUser.role === 'komandan') ? 'APPROVER TTD' : 'PEMOHON DOKUMEN' }}
                                    </span>
                                </div>
                            </div>

                            <!-- 7. AGENDA SURAT & SKHPP -->
                            <div class="p-4 bg-slate-50/60 border border-slate-200 rounded-2xl flex flex-col justify-between">
                                <div>
                                    <div class="flex items-start justify-between gap-2 mb-2">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold shrink-0">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                            </div>
                                            <div>
                                                <span class="font-black text-slate-900 uppercase block text-[11px] tracking-tight">Agenda Surat & SKHPP</span>
                                                <span class="text-[9px] text-slate-400 font-bold uppercase">Persuratan Kedinasan</span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-[10.5px] text-slate-500 leading-snug">Akses registrasi nomor surat dinas, buku agenda dan penerbitan SKHPP.</p>
                                </div>
                                <div class="mt-3.5 pt-2.5 border-t border-slate-200/60 flex items-center justify-between">
                                    <span class="text-[9px] text-slate-400 font-extrabold uppercase tracking-wider">Fitur Standar:</span>
                                    <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 text-[9px] font-black rounded-lg uppercase">DIBERIKAN</span>
                                </div>
                            </div>

                            <!-- 8. PENYIMPANAN CLOUD & BACKUP PC -->
                            <div class="p-4 bg-slate-50/60 border border-slate-200 rounded-2xl flex flex-col justify-between">
                                <div>
                                    <div class="flex items-start justify-between gap-2 mb-2">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold shrink-0">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 00-9.78 2.096A4.001 4.001 0 003 15z"></path></svg>
                                            </div>
                                            <div>
                                                <span class="font-black text-slate-900 uppercase block text-[11px] tracking-tight">Cloud & Backup PC</span>
                                                <span class="text-[9px] text-slate-400 font-bold uppercase">Pangkalan Cadangan</span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-[10.5px] text-slate-500 leading-snug">Akses penyimpanan arsip digital publik dan pangkalan backup komputer PC.</p>
                                </div>
                                <div class="mt-3.5 pt-2.5 border-t border-slate-200/60 flex items-center justify-between">
                                    <span class="text-[9px] text-slate-400 font-extrabold uppercase tracking-wider">Alokasi:</span>
                                    <span class="px-2.5 py-1 bg-blue-100 text-blue-800 text-[9px] font-black rounded-lg uppercase">50 GB AKTIF</span>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>

                <!-- FOOTER MODAL (FIXED) -->
                <div class="flex justify-between items-center pt-3 border-t border-slate-100 shrink-0 gap-2 flex-wrap">
                    <div class="flex items-center gap-2">
                        <button @click="startEdit(detailUser)" class="px-4 py-2.5 bg-slate-100 text-slate-700 hover:bg-slate-200 font-bold text-xs uppercase rounded-xl transition flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            <span>Ubah Profil / Peran</span>
                        </button>
                        <button @click="openChangePasswordModal(detailUser)" class="px-4 py-2.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white font-bold text-xs uppercase rounded-xl transition flex items-center gap-1.5 cursor-pointer border border-indigo-200 shadow-xs" title="Ubah Password Personel Secara Langsung">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                            <span>Ubah Password</span>
                        </button>
                    </div>
                    <button @click="showDetailModal = false" class="px-6 py-2.5 bg-indigo-600 text-white font-bold text-xs uppercase rounded-xl hover:bg-indigo-700 transition cursor-pointer shadow-md shadow-indigo-500/20">
                        Tutup
                    </button>
                </div>

            </div>
        </div>
        <!-- MODAL TAMBAH PERSONEL BANYAK (BULK) -->
        <div v-if="showBulkModal" class="fixed inset-0 bg-indigo-950/40 backdrop-blur-md flex items-center justify-center z-[100] p-4 sm:p-6 overflow-y-auto">
            <div class="bg-white w-full max-w-5xl rounded-[2.5rem] shadow-2xl p-6 sm:p-8 space-y-6 animate-in zoom-in duration-200 my-auto border border-slate-100">
                <div class="flex justify-between items-center border-b border-slate-100 pb-4">
                    <div>
                        <h3 class="text-base font-black text-slate-900 uppercase">PENDAFTARAN BANYAK AKUN PERSONEL (BULK)</h3>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">Input masal data personel. Kode Verifikasi (Token Aktivasi) akan dicetak otomatis dalam format PDF Kedinasan (Tanpa Notif WA).</p>
                    </div>
                    <button @click="showBulkModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-2xl cursor-pointer">&times;</button>
                </div>

                <div class="overflow-x-auto max-h-[50vh] custom-scrollbar border border-slate-100 rounded-2xl p-2">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-100 text-slate-700 uppercase font-black">
                                <th class="p-3 w-10 text-center">NO</th>
                                <th class="p-3">NAMA LENGKAP *</th>
                                <th class="p-3 w-36">PANGKAT *</th>
                                <th class="p-3 w-32">NRP / NIP. *</th>
                                <th class="p-3 w-44">JABATAN *</th>
                                <th class="p-3">EMAIL (OPSIONAL)</th>
                                <th class="p-3 w-36">NO. WA (OPSIONAL)</th>
                                <th class="p-3 w-12 text-center">AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, idx) in bulkRows" :key="idx" class="border-b border-slate-100 hover:bg-slate-50/50">
                                <td class="p-2 text-center font-bold text-slate-400">{{ idx + 1 }}</td>
                                <td class="p-2">
                                    <input v-model="row.name" type="text" placeholder="Contoh: Erwan Junaedi" class="w-full text-xs rounded-xl border-slate-200 font-bold focus:ring-indigo-500" required />
                                </td>
                                <td class="p-2">
                                    <input v-model="row.pangkat" type="text" placeholder="PELTU TTG" class="w-full text-xs rounded-xl border-slate-200 font-bold focus:ring-indigo-500" required />
                                </td>
                                <td class="p-2">
                                    <input v-model="row.nrp" type="text" placeholder="84025" class="w-full text-xs rounded-xl border-slate-200 font-mono font-bold focus:ring-indigo-500" required />
                                </td>
                                <td class="p-2">
                                    <select v-model="row.role" class="w-full text-xs rounded-xl border-slate-200 font-bold focus:ring-indigo-500 bg-white">
                                        <option value="super_admin">SUPER ADMIN</option>
                                        <option value="admin">ADMIN</option>
                                        <option value="personel">PERSONEL</option>
                                        <option value="personel_sintel">PERSONEL SINTEL</option>
                                        <option value="komandan">KOMANDAN (APPROVER)</option>
                                        <option value="wadan">WAKIL KOMANDAN</option>
                                        <option value="pasops">PASOPS</option>
                                        <option value="staf">STAF ADMINISTRASI</option>
                                        <option value="danunit1">DAN UNIT I / LID</option>
                                        <option value="danunit2">DAN UNIT II / PAMGAL</option>
                                        <option value="kaurmintel">KAUR MINTEL</option>
                                        <option value="paurset">PAUR SET</option>
                                    </select>
                                </td>
                                <td class="p-2">
                                    <input v-model="row.email" type="email" placeholder="Otomatis jika kosong" class="w-full text-xs rounded-xl border-slate-200 font-mono focus:ring-indigo-500" />
                                </td>
                                <td class="p-2">
                                    <input v-model="row.phone" type="text" placeholder="08123456789" class="w-full text-xs rounded-xl border-slate-200 font-mono focus:ring-indigo-500" />
                                </td>
                                <td class="p-2 text-center">
                                    <button v-if="bulkRows.length > 1" @click="removeBulkRow(idx)" class="text-rose-500 hover:text-rose-700 font-bold text-lg p-1 cursor-pointer">&times;</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col sm:flex-row justify-between items-center gap-4 pt-2">
                    <button @click="addBulkRow" class="w-full sm:w-auto px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-extrabold text-xs uppercase tracking-wider flex items-center justify-center gap-1.5 transition cursor-pointer">
                        + Tambah Baris Personel
                    </button>

                    <div class="flex gap-3 w-full sm:w-auto">
                        <button @click="showBulkModal = false" class="flex-1 sm:flex-none px-5 py-2.5 bg-slate-100 text-slate-600 rounded-xl font-bold text-xs uppercase hover:bg-slate-200 transition cursor-pointer">Batal</button>
                        <button @click="submitBulk" :disabled="isSubmittingBulk" class="flex-1 sm:flex-none px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-extrabold text-xs uppercase shadow-md shadow-indigo-500/20 transition flex items-center justify-center gap-2 cursor-pointer">
                            <span v-if="isSubmittingBulk">Memproses...</span>
                            <span v-else>PROSES & CETAK TOKEN PDF</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
/* Style untuk PDF Rekap agar font mono NRP terlihat jelas */
.font-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}
</style>