<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    spJaga: Object,
    personels: Array,
});

const monthNames = [
    '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

const monthsList = [
    { id: 1, name: 'Januari' },
    { id: 2, name: 'Februari' },
    { id: 3, name: 'Maret' },
    { id: 4, name: 'April' },
    { id: 5, name: 'Mei' },
    { id: 6, name: 'Juni' },
    { id: 7, name: 'Juli' },
    { id: 8, name: 'Agustus' },
    { id: 9, name: 'September' },
    { id: 10, name: 'Oktober' },
    { id: 11, name: 'November' },
    { id: 12, name: 'Desember' },
];

const parseExactDate = (dateVal) => {
    if (!dateVal) return '';
    const str = String(dateVal);
    if (str.includes('T')) {
        const d = new Date(str);
        if (!isNaN(d.getTime())) {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        }
    }
    return str.substring(0, 10);
};

const initialTmtMulai = props.spJaga.tmt_mulai_formatted 
    ? String(props.spJaga.tmt_mulai_formatted).substring(0, 10) 
    : parseExactDate(props.spJaga.tmt_mulai);

const initialTmtSelesai = props.spJaga.tmt_selesai_formatted 
    ? String(props.spJaga.tmt_selesai_formatted).substring(0, 10) 
    : parseExactDate(props.spJaga.tmt_selesai);

const initialTanggalSurat = props.spJaga.tanggal_surat_formatted 
    ? String(props.spJaga.tanggal_surat_formatted).substring(0, 10) 
    : parseExactDate(props.spJaga.tanggal_surat);

const initialBulan = props.spJaga.bulan ? parseInt(props.spJaga.bulan, 10) : (initialTmtMulai ? parseInt(initialTmtMulai.substring(5, 7), 10) : 10);
const initialTahun = props.spJaga.tahun ? parseInt(props.spJaga.tahun, 10) : (initialTmtMulai ? parseInt(initialTmtMulai.substring(0, 4), 10) : 2026);

function safeParseArray(val) {
    if (Array.isArray(val)) return val;
    if (typeof val === 'string') {
        try {
            const parsed = JSON.parse(val);
            if (Array.isArray(parsed)) return parsed;
        } catch (e) {
            return [];
        }
    }
    return [];
}

const form = useForm({
    bulan: initialBulan,
    tahun: initialTahun,
    nomor_urut: props.spJaga.nomor_urut !== null && props.spJaga.nomor_urut !== undefined ? String(props.spJaga.nomor_urut) : '29',
    tmt_mulai: initialTmtMulai,
    tmt_selesai: initialTmtSelesai,
    tanggal_surat: initialTanggalSurat,
    ttd_type: props.spJaga.ttd_type || 'tte',
    
    // Perwira Tertua (Pater / Penerima Perintah)
    perwira_tertua_user_id: props.spJaga.perwira_tertua_user_id || '',
    perwira_tertua_nama: props.spJaga.perwira_tertua_nama || props.spJaga.perwiras?.[0]?.nama || 'Indra Gunawan',
    perwira_tertua_pangkat_nrp: props.spJaga.perwira_tertua_pangkat_nrp || (props.spJaga.perwiras?.[0] ? `${props.spJaga.perwiras[0].pangkat_korps} NRP ${props.spJaga.perwiras[0].nrp}` : 'Kapten Laut (P) NRP 19739/P'),
    perwira_tertua_jabatan: props.spJaga.perwira_tertua_jabatan || 'Dan Unit 1 Lid Den Intel Koarmada 2',
    
    perwiras: props.spJaga.perwiras?.length > 0 ? props.spJaga.perwiras.map(p => ({
        user_id: p.user_id || '',
        nama: p.nama,
        pangkat_korps: p.pangkat_korps,
        nrp: p.nrp,
        tgl_1: p.tgl_1 || '-',
        tgl_2: p.tgl_2 || '-',
        tgl_3: p.tgl_3 || '-',
        tgl_4: p.tgl_4 || '-',
        tgl_5: p.tgl_5 || '-',
        tgl_6: p.tgl_6 || '-',
        tgl_7: p.tgl_7 || '-',
    })) : [],

    anggotas: props.spJaga.anggotas?.length > 0 ? props.spJaga.anggotas.map(a => ({
        divisi_no: a.divisi_no,
        tanggal_list_text: a.tanggal_list_text || '',
        anggota_items: safeParseArray(a.anggota_items)
    })) : []
});

// Menetapkan baris perwira jaga sebagai Perwira Tertua
const setAsPerwiraTertua = (idx) => {
    const p = form.perwiras[idx];
    if (!p) return;
    form.perwira_tertua_user_id = p.user_id || '';
    form.perwira_tertua_nama = p.nama || '';
    const pangkat = p.pangkat_korps ? p.pangkat_korps.trim() : '';
    const nrp = p.nrp ? `NRP ${p.nrp.replace(/^NRP\s*/i, '').trim()}` : '';
    form.perwira_tertua_pangkat_nrp = [pangkat, nrp].filter(Boolean).join(' ');
    if (!form.perwira_tertua_jabatan) {
        form.perwira_tertua_jabatan = 'Dan Unit 1 Lid Den Intel Koarmada 2';
    }
};

// Mengecek apakah baris tertentu merupakan Perwira Tertua yang aktif
const isRowPerwiraTertua = (p, idx) => {
    if (form.perwira_tertua_user_id && p.user_id && form.perwira_tertua_user_id == p.user_id) {
        return true;
    }
    if (form.perwira_tertua_nama && p.nama && form.perwira_tertua_nama.toLowerCase().trim() === p.nama.toLowerCase().trim()) {
        return true;
    }
    return false;
};

// Memilih perwira tertua dari dropdown cepat
const onSelectPerwiraTertuaFromDropdown = (val) => {
    if (!val) return;
    // Cek di baris perwiras
    const perwiraRow = form.perwiras.find(p => p.nama === val || (p.user_id && p.user_id == val));
    if (perwiraRow) {
        form.perwira_tertua_user_id = perwiraRow.user_id || '';
        form.perwira_tertua_nama = perwiraRow.nama || '';
        const pangkat = perwiraRow.pangkat_korps ? perwiraRow.pangkat_korps.trim() : '';
        const nrp = perwiraRow.nrp ? `NRP ${perwiraRow.nrp.replace(/^NRP\s*/i, '').trim()}` : '';
        form.perwira_tertua_pangkat_nrp = [pangkat, nrp].filter(Boolean).join(' ');
        if (!form.perwira_tertua_jabatan) {
            form.perwira_tertua_jabatan = 'Dan Unit 1 Lid Den Intel Koarmada 2';
        }
        return;
    }
    // Cek di database personels
    const person = props.personels.find(u => u.id == val || u.name === val);
    if (person) {
        form.perwira_tertua_user_id = person.id;
        form.perwira_tertua_nama = person.name;
        const pangkat = person.pangkat ? person.pangkat.trim() : '';
        const nrp = person.nrp ? `NRP ${person.nrp.replace(/^NRP\s*/i, '').trim()}` : '';
        form.perwira_tertua_pangkat_nrp = [pangkat, nrp].filter(Boolean).join(' ');
        if (!form.perwira_tertua_jabatan) {
            form.perwira_tertua_jabatan = 'Dan Unit 1 Lid Den Intel Koarmada 2';
        }
    }
};

const onPerwiraSelect = (idx, userId) => {
    const p = props.personels.find(x => x.id === userId);
    if (p) {
        form.perwiras[idx].nama = p.name;
        form.perwiras[idx].pangkat_korps = p.pangkat || '';
        form.perwiras[idx].nrp = p.nrp || '';
        if (isRowPerwiraTertua(form.perwiras[idx], idx)) {
            setAsPerwiraTertua(idx);
        }
    }
};

const onAnggotaSelect = (divIdx, itemIdx, userId) => {
    const p = props.personels.find(x => x.id === userId);
    if (p) {
        form.anggotas[divIdx].anggota_items[itemIdx].nama = p.name;
        form.anggotas[divIdx].anggota_items[itemIdx].pangkat_korps = p.pangkat || '';
        form.anggotas[divIdx].anggota_items[itemIdx].nrp_nip = p.nrp || '';
    }
};

const addPerwira = () => {
    form.perwiras.push({
        user_id: '',
        nama: '',
        pangkat_korps: '',
        nrp: '',
        tgl_1: '-',
        tgl_2: '-',
        tgl_3: '-',
        tgl_4: '-',
        tgl_5: '-',
        tgl_6: '-',
        tgl_7: '-',
    });
};

const removePerwira = (idx) => {
    if (form.perwiras.length > 1) {
        form.perwiras.splice(idx, 1);
    }
};

const addAnggotaToDivisi = (divIdx) => {
    form.anggotas[divIdx].anggota_items.push({
        user_id: '',
        nama: '',
        pangkat_korps: '',
        nrp_nip: '',
        role_jaga: 'ANGGOTA'
    });
};

const removeAnggotaFromDivisi = (divIdx, itemIdx) => {
    if (form.anggotas[divIdx].anggota_items.length > 1) {
        form.anggotas[divIdx].anggota_items.splice(itemIdx, 1);
    }
};


// Definisi Pola Rotasi Matriks Perwira Jaga (Mega-siklus 49 hari / 8 siklus kolom)
// Berdasarkan perbandingan resmi jadwal jaga Sintel Koarmada 2
const cycleDefs = [
    { skip: null, rows: [0, 1, 2, 3, 4, 5, 6] },
    { skip: 0,    rows: [1, 2, 3, 4, 5, 6] },
    { skip: 1,    rows: [0, 2, 3, 4, 5, 6] },
    { skip: 2,    rows: [0, 1, 3, 4, 5, 6] },
    { skip: 3,    rows: [0, 1, 2, 4, 5, 6] },
    { skip: 4,    rows: [0, 1, 2, 3, 5, 6] },
    { skip: 5,    rows: [0, 1, 2, 3, 4, 6] },
    { skip: 6,    rows: [0, 1, 2, 3, 4, 5] },
];

const mod = (n, m) => ((n % m) + m) % m;

const getDaysDiff = (y, m, d, refY, refM, refD) => {
    const utc1 = Date.UTC(y, m - 1, d);
    const utc2 = Date.UTC(refY, refM - 1, refD);
    return Math.floor((utc1 - utc2) / (1000 * 60 * 60 * 24));
};

const calculatePerwiraMatrix = (year, month, endDay) => {
    const monthDaysInfo = [];
    for (let d = 1; d <= endDay; d++) {
        const diff = getDaysDiff(year, month, d, 2026, 8, 5);
        const megaId = Math.floor(diff / 49);
        const remD = mod(diff, 49);

        let cIdx = 0;
        let row = 0;
        let skip = null;
        let remAcc = remD;
        for (let i = 0; i < cycleDefs.length; i++) {
            if (remAcc < cycleDefs[i].rows.length) {
                cIdx = i;
                row = cycleDefs[i].rows[remAcc];
                skip = cycleDefs[i].skip;
                break;
            }
            remAcc -= cycleDefs[i].rows.length;
        }
        const globalCycleId = megaId * 8 + cIdx;
        monthDaysInfo.push({
            day: d,
            globalCycleId,
            cIdx,
            row,
            skip,
        });
    }

    const cyclesInMonth = [];
    for (const info of monthDaysInfo) {
        if (cyclesInMonth.length === 0 || cyclesInMonth[cyclesInMonth.length - 1].globalCycleId !== info.globalCycleId) {
            cyclesInMonth.push({
                globalCycleId: info.globalCycleId,
                cIdx: info.cIdx,
                skip: info.skip,
                days: {},
            });
        }
        cyclesInMonth[cyclesInMonth.length - 1].days[info.row] = String(info.day).padStart(2, '0');
    }

    const table = Array.from({ length: 7 }, () => Array(7).fill('-'));
    for (let colIdx = 0; colIdx < cyclesInMonth.length && colIdx < 7; colIdx++) {
        const cyc = cyclesInMonth[colIdx];
        for (let r = 0; r < 7; r++) {
            if (cyc.days[r] !== undefined) {
                table[r][colIdx] = cyc.days[r];
            } else {
                table[r][colIdx] = '-';
            }
        }
    }
    return table;
};

const calculateAnggotaDates = (year, month, endDay) => {
    const mName = (monthNames[month] || '').toUpperCase();
    const divDates = Array.from({ length: 5 }, () => []);

    for (let d = 1; d <= endDay; d++) {
        const diff = getDaysDiff(year, month, d, 2026, 8, 1);
        const divIdx = mod(3 + diff, 5);
        divDates[divIdx].push(String(d).padStart(2, '0'));
    }

    return divDates.map(dates => `${dates.join(', ')} ${mName} ${year}`);
};

const showCol7 = ref(false);
const hasCol7 = computed(() => {
    return showCol7.value || form.perwiras.some(p => p.tgl_7 && p.tgl_7.trim() !== '' && p.tgl_7.trim() !== '-');
});

let isSyncingDates = false;

// Fungsi Otomatis Hitung Ulang Tanggal Jaga Perwira & Anggota (Sesuai Perbandingan Siklus Resmi)
const generateJadwalJaga = (showNotification = true) => {
    let y = parseInt(form.tahun, 10);
    let m = parseInt(form.bulan, 10);
    
    // Tentukan hari terakhir jaga berdasarkan tmt_selesai atau jumlah hari dalam bulan
    let endDay = 30;
    if (form.tmt_selesai) {
        const parts = form.tmt_selesai.split('-');
        if (parts.length === 3) {
            y = parseInt(parts[0], 10) || y;
            m = parseInt(parts[1], 10) || m;
            endDay = parseInt(parts[2], 10) || 30;
        }
    } else {
        endDay = new Date(y, m, 0).getDate();
        form.tmt_selesai = `${y}-${String(m).padStart(2, '0')}-${String(endDay).padStart(2, '0')}`;
    }

    if (!form.tmt_mulai) {
        form.tmt_mulai = `${y}-${String(m).padStart(2, '0')}-01`;
    }

    // Tanggal Surat Dikeluarkan (hanya set jika masih kosong)
    if (!form.tanggal_surat) {
        const prevMonth = m === 1 ? 12 : m - 1;
        const prevYear = m === 1 ? y - 1 : y;
        const daysInPrevMonth = new Date(prevYear, prevMonth, 0).getDate();
        form.tanggal_surat = `${prevYear}-${String(prevMonth).padStart(2, '0')}-${String(daysInPrevMonth).padStart(2, '0')}`;
    }

    const mName = (monthNames[m] || '').toUpperCase();

    // 1. Generate Tanggal Perwira Jaga (Matriks Rotasi Kontinu Antar-Bulan)
    if (form.perwiras && form.perwiras.length >= 7) {
        const perwiraMatrix = calculatePerwiraMatrix(y, m, endDay);
        for (let r = 0; r < 7; r++) {
            for (let colIdx = 1; colIdx <= 7; colIdx++) {
                form.perwiras[r][`tgl_${colIdx}`] = perwiraMatrix[r][colIdx - 1];
            }
        }
        for (let i = 7; i < form.perwiras.length; i++) {
            for (let colIdx = 1; colIdx <= 7; colIdx++) {
                form.perwiras[i][`tgl_${colIdx}`] = '-';
            }
        }
    }

    // 2. Generate Tanggal Anggota Jaga 5 Divisi (Rotasi Kontinu Antar-Bulan)
    if (form.anggotas && form.anggotas.length >= 5) {
        const anggotaDates = calculateAnggotaDates(y, m, endDay);
        form.anggotas.forEach((div, idx) => {
            if (idx < 5) {
                div.tanggal_list_text = anggotaDates[idx];
            }
        });
    }

    if (showNotification) {
        Swal.fire({
            title: 'Jadwal Jaga Berhasil Di-generate',
            text: `Tanggal jaga perwira dan anggota telah dihitung otomatis untuk periode 1 s.d. ${endDay} ${mName} ${y}.`,
            icon: 'success',
            confirmButtonColor: '#2563eb',
            timer: 2000,
            showConfirmButton: false,
        });
    }
};

// Konfirmasi sebelum menghitung ulang jadwal jaga agar data sebelumnya tidak hilang tanpa sengaja
const confirmAndGenerateJadwalJaga = () => {
    Swal.fire({
        title: 'Hitung Ulang Jadwal Jaga?',
        text: 'Tanggal dinas jaga perwira dan anggota akan dihitung ulang secara otomatis berdasarkan periode TMT. Data tanggal dinas yang ada saat ini akan diperbarui.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hitung Ulang',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#64748b',
    }).then((result) => {
        if (result.isConfirmed) {
            generateJadwalJaga(true);
        }
    });
};

const submit = () => {
    form.put(route('sp-jaga.update', props.spJaga.id), {
        onSuccess: () => {
            Swal.fire('Berhasil', 'Perubahan jadwal SP Jaga berhasil disimpan.', 'success');
        }
    });
};
</script>

<template>
    <Head title="Koreksi SP Jaga - SINDEN" />

    <AuthenticatedLayout>
        <div class="space-y-6 max-w-6xl mx-auto font-sans pb-12">
            
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                        <Link :href="route('sp-jaga.index')" class="hover:text-blue-600">SP Jaga</Link>
                        <span>/</span>
                        <span class="text-slate-800 font-bold">Koreksi & Perbarui Jadwal</span>
                    </div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">Koreksi Surat Perintah Jaga</h1>
                </div>

                <Link 
                    :href="route('sp-jaga.index')"
                    class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition"
                >
                    ← Kembali
                </Link>
            </div>

            <form @submit.prevent="submit" class="space-y-8">
                
                <!-- BAGIAN 1: INFORMASI SURAT PERINTAH -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-6">
                    <div class="border-b border-slate-100 pb-4">
                        <h2 class="text-base font-extrabold text-slate-900">1. Header & Periode Surat Perintah</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">Bulan Dinas</label>
                            <select v-model.number="form.bulan" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500">
                                <option v-for="m in monthsList" :key="m.id" :value="m.id">{{ m.name }}</option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">Tahun</label>
                            <input type="number" v-model="form.tahun" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">Nomor Urut Sprin</label>
                            <input type="text" v-model="form.nomor_urut" placeholder="Contoh: 50 atau 50a" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">TMT Mulai</label>
                            <input type="date" v-model="form.tmt_mulai" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">TMT Selesai</label>
                            <div class="flex items-center gap-2">
                                <input 
                                    type="date" 
                                    v-model="form.tmt_selesai" 
                                    class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500" 
                                />
                                <button 
                                    type="button" 
                                    @click="confirmAndGenerateJadwalJaga" 
                                    class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 active:scale-95 text-white font-extrabold text-xs rounded-xl shadow-sm transition flex items-center gap-1.5 shrink-0 cursor-pointer"
                                    title="Generate otomatis tanggal jaga perwira dan anggota sesuai pola rotasi dinas"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    <span>Generate</span>
                                </button>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">Tanggal Dikeluarkan</label>
                            <input type="date" v-model="form.tanggal_surat" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500" />
                        </div>
                    </div>

                    <!-- Dedicated Card: Pemilihan Perwira Tertua (Pater / Penerima Perintah) -->
                    <div class="p-5 sm:p-6 rounded-2xl bg-amber-50/70 border border-amber-200 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-amber-200/80 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="p-1.5 bg-amber-500 text-white rounded-lg shadow-xs text-sm">🎖️</span>
                                <div>
                                    <h3 class="text-xs font-black uppercase tracking-wider text-amber-950">Perwira Tertua (Pater / Penerima Perintah)</h3>
                                    <p class="text-[11px] text-amber-800">Pejabat perwira tertua yang ditunjuk menerima surat perintah di Diktum Kepada.</p>
                                </div>
                            </div>
                            <div class="text-[11px] font-bold text-amber-900 bg-amber-200/60 px-2.5 py-1 rounded-lg">
                                Diktum: Kepada
                            </div>
                        </div>

                        <div class="space-y-3">
                            <!-- Quick Selector Dropdown -->
                            <div>
                                <label class="text-[11px] font-bold text-amber-900 block mb-1">
                                    ⚡ Pilih Cepat dari Perwira Jaga / Seluruh Personel:
                                </label>
                                <select 
                                    @change="onSelectPerwiraTertuaFromDropdown($event.target.value)"
                                    class="w-full p-2.5 text-xs border border-amber-300 rounded-xl bg-white font-medium text-slate-800 focus:ring-2 focus:ring-amber-500 shadow-xs"
                                >
                                    <option value="">-- Pilih dari Daftar Perwira / Personel --</option>
                                    <optgroup label="📋 Perwira Jaga (Lampiran 1)">
                                        <option 
                                            v-for="(p, idx) in form.perwiras.filter(x => x.nama)" 
                                            :key="'p-' + idx" 
                                            :value="p.user_id || p.nama"
                                        >
                                            {{ p.pangkat_korps }} {{ p.nama }} (NRP {{ p.nrp || '-' }})
                                        </option>
                                    </optgroup>
                                    <optgroup label="👥 Seluruh Personel (Database)">
                                        <option v-for="user in personels" :key="'u-' + user.id" :value="user.id">
                                            {{ user.pangkat }} {{ user.name }} (NRP {{ user.nrp || '-' }})
                                        </option>
                                    </optgroup>
                                </select>
                            </div>

                            <!-- 3 Field Rincian Perwira Tertua -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-1">
                                <div class="space-y-1">
                                    <label class="text-[11px] font-bold text-slate-700 block">Nama Lengkap Perwira Tertua</label>
                                    <input 
                                        type="text" 
                                        v-model="form.perwira_tertua_nama" 
                                        placeholder="Contoh: Indra Gunawan"
                                        class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-white font-bold text-slate-900 focus:ring-2 focus:ring-amber-500" 
                                    />
                                </div>

                                <div class="space-y-1">
                                    <label class="text-[11px] font-bold text-slate-700 block">Pangkat, Korps & NRP</label>
                                    <input 
                                        type="text" 
                                        v-model="form.perwira_tertua_pangkat_nrp" 
                                        placeholder="Contoh: Kapten Laut (P) NRP 19739/P"
                                        class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-white text-slate-900 focus:ring-2 focus:ring-amber-500" 
                                    />
                                </div>

                                <div class="space-y-1">
                                    <label class="text-[11px] font-bold text-slate-700 block">Jabatan Dinas</label>
                                    <input 
                                        type="text" 
                                        v-model="form.perwira_tertua_jabatan" 
                                        placeholder="Contoh: Dan Unit 1 Lid Den Intel Koarmada 2"
                                        class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-white text-slate-900 focus:ring-2 focus:ring-amber-500" 
                                    />
                                </div>
                            </div>

                            <!-- Preview Diktum Kepada Text -->
                            <div class="p-2.5 rounded-xl bg-amber-100/60 border border-amber-300/50 text-[11px] text-amber-950 flex items-start gap-2">
                                <span class="font-black text-amber-700 uppercase tracking-wider shrink-0">Preview Teks Kepada:</span>
                                <span class="italic font-serif">
                                    "{{ form.perwira_tertua_pangkat_nrp || 'Kapten Laut (P) NRP 19739/P' }} {{ form.perwira_tertua_nama || 'Indra Gunawan' }}, {{ form.perwira_tertua_jabatan || 'Dan Unit 1 Lid Den Intel Koarmada 2' }}, beserta ... orang sesuai lampiran."
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BAGIAN 2: DAFTAR PERWIRA JAGA (LAMPIRAN 1) -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>
                            <h2 class="text-base font-extrabold text-slate-900">2. Lampiran 1: Daftar Nama Perwira Jaga</h2>
                            <p class="text-xs text-slate-500">Perbarui perwira jaga dan jadwal tanggal dinas. Anda juga dapat memilih Perwira Tertua (Pater) langsung dari tabel.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button 
                                type="button" 
                                @click="showCol7 = !showCol7" 
                                class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition cursor-pointer"
                            >
                                {{ hasCol7 ? 'Sembunyikan Kolom 7' : '+ Kolom 7 (Opsional)' }}
                            </button>
                            <button 
                                @click="addPerwira" 
                                type="button"
                                class="px-3.5 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-100 font-bold text-xs rounded-xl transition cursor-pointer"
                            >
                                + Tambah Baris Perwira
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-extrabold uppercase text-slate-500">
                                    <th class="p-2.5 text-center w-10">No</th>
                                    <th class="p-2.5 w-60">Nama Perwira</th>
                                    <th class="p-2.5 w-36">Pangkat, Korps</th>
                                    <th class="p-2.5 w-28">NRP</th>
                                    <th class="p-2.5 text-center w-28">Pater / Tertua</th>
                                    <th class="p-2.5 text-center" :colspan="hasCol7 ? 7 : 6">Tanggal Dinas Jaga</th>
                                    <th class="p-2.5 text-center w-12">Hapus</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr 
                                    v-for="(p, idx) in form.perwiras" 
                                    :key="idx" 
                                    class="transition"
                                    :class="isRowPerwiraTertua(p, idx) ? 'bg-amber-50/60 ring-1 ring-amber-300' : 'hover:bg-slate-50/50'"
                                >
                                    <td class="p-2 text-center font-bold text-slate-500">{{ idx + 1 }}.</td>
                                    <td class="p-2">
                                        <div class="space-y-1">
                                            <select 
                                                v-model="p.user_id" 
                                                @change="onPerwiraSelect(idx, p.user_id)"
                                                class="w-full p-1.5 text-xs border border-slate-200 rounded-lg bg-slate-50"
                                            >
                                                <option value="">-- Pilih dari Personel --</option>
                                                <option v-for="user in personels" :key="user.id" :value="user.id">{{ user.pangkat }} {{ user.name }}</option>
                                            </select>
                                            <input type="text" v-model="p.nama" placeholder="Ketik nama..." class="w-full p-1.5 text-xs border border-slate-200 rounded-lg" />
                                        </div>
                                    </td>
                                    <td class="p-2">
                                        <input type="text" v-model="p.pangkat_korps" class="w-full p-1.5 text-xs border border-slate-200 rounded-lg" />
                                    </td>
                                    <td class="p-2">
                                        <input type="text" v-model="p.nrp" class="w-full p-1.5 text-xs border border-slate-200 rounded-lg" />
                                    </td>
                                    <td class="p-2 text-center whitespace-nowrap">
                                        <span 
                                            v-if="isRowPerwiraTertua(p, idx)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-400 text-slate-950 font-black text-[10px] rounded-lg shadow-xs ring-1 ring-amber-500"
                                        >
                                            ⭐ Pater (Tertua)
                                        </span>
                                        <button
                                            v-else
                                            type="button"
                                            @click="setAsPerwiraTertua(idx)"
                                            class="px-2.5 py-1 bg-slate-100 hover:bg-amber-100 text-slate-600 hover:text-amber-800 text-[10px] font-bold rounded-lg transition cursor-pointer"
                                            title="Pilih perwira ini sebagai Perwira Tertua"
                                        >
                                            ⭐ Pilih Pater
                                        </button>
                                    </td>
                                    <!-- Kolom Tanggal Dinas Jaga (Dinamis Sesuai Kebutuhan Bulan) -->
                                    <td class="p-1 w-12"><input type="text" v-model="p.tgl_1" class="w-full p-1.5 text-xs text-center border border-slate-200 rounded-lg" /></td>
                                    <td class="p-1 w-12"><input type="text" v-model="p.tgl_2" class="w-full p-1.5 text-xs text-center border border-slate-200 rounded-lg" /></td>
                                    <td class="p-1 w-12"><input type="text" v-model="p.tgl_3" class="w-full p-1.5 text-xs text-center border border-slate-200 rounded-lg" /></td>
                                    <td class="p-1 w-12"><input type="text" v-model="p.tgl_4" class="w-full p-1.5 text-xs text-center border border-slate-200 rounded-lg" /></td>
                                    <td class="p-1 w-12"><input type="text" v-model="p.tgl_5" class="w-full p-1.5 text-xs text-center border border-slate-200 rounded-lg" /></td>
                                    <td class="p-1 w-12"><input type="text" v-model="p.tgl_6" class="w-full p-1.5 text-xs text-center border border-slate-200 rounded-lg" /></td>
                                    <td v-if="hasCol7" class="p-1 w-12"><input type="text" v-model="p.tgl_7" class="w-full p-1.5 text-xs text-center border border-slate-200 rounded-lg" /></td>
                                    <td class="p-2 text-center">
                                        <button 
                                            @click="removePerwira(idx)" 
                                            type="button" 
                                            class="text-red-500 hover:text-red-700 font-bold p-1 cursor-pointer"
                                            title="Hapus Baris"
                                        >
                                            x
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- BAGIAN 3: DAFTAR ANGGOTA JAGA DIVISI (LAMPIRAN 2) -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-6">
                    <div class="border-b border-slate-100 pb-4">
                        <h2 class="text-base font-extrabold text-slate-900">3. Lampiran 2: Daftar Nama Anggota Jaga (5 Kelompok Divisi)</h2>
                    </div>

                    <div class="space-y-6">
                        <div 
                            v-for="(divisi, divIdx) in form.anggotas" 
                            :key="divIdx"
                            class="p-5 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-4"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 pb-3">
                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 bg-slate-800 text-white font-black text-xs rounded-lg">
                                        Divisi {{ divisi.divisi_no }}
                                    </span>
                                    <span class="text-xs font-bold text-slate-700">Jadwal Tanggal Dinas:</span>
                                </div>
                                <div class="flex-1 max-w-md">
                                    <input 
                                        type="text" 
                                        v-model="divisi.tanggal_list_text" 
                                        class="w-full p-2 text-xs border border-slate-300 rounded-xl bg-white font-bold text-blue-700"
                                    />
                                </div>
                                <button 
                                    @click="addAnggotaToDivisi(divIdx)" 
                                    type="button"
                                    class="px-3 py-1 bg-blue-600 text-white font-bold text-xs rounded-lg hover:bg-blue-700 transition"
                                >
                                    + Tambah Personel
                                </button>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-xs text-left">
                                    <thead>
                                        <tr class="text-[10px] font-extrabold uppercase text-slate-400 border-b border-slate-200">
                                            <th class="py-2 w-64">Nama Lengkap</th>
                                            <th class="py-2 w-44">Pangkat / Korps</th>
                                            <th class="py-2 w-36">NRP / NIP</th>
                                            <th class="py-2 w-28 text-center">Peran Jaga</th>
                                            <th class="py-2 w-10 text-center">Hapus</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="(item, itemIdx) in divisi.anggota_items" :key="itemIdx">
                                            <td class="py-2 pr-2">
                                                <div class="space-y-1">
                                                    <select 
                                                        v-model="item.user_id" 
                                                        @change="onAnggotaSelect(divIdx, itemIdx, item.user_id)"
                                                        class="w-full p-1.5 text-xs border border-slate-200 rounded-lg bg-white"
                                                    >
                                                        <option value="">-- Pilih dari Personel --</option>
                                                        <option v-for="user in personels" :key="user.id" :value="user.id">{{ user.pangkat }} {{ user.name }}</option>
                                                    </select>
                                                    <input type="text" v-model="item.nama" class="w-full p-1.5 text-xs border border-slate-200 rounded-lg bg-white" />
                                                </div>
                                            </td>
                                            <td class="py-2 pr-2">
                                                <input type="text" v-model="item.pangkat_korps" class="w-full p-1.5 text-xs border border-slate-200 rounded-lg bg-white" />
                                            </td>
                                            <td class="py-2 pr-2">
                                                <input type="text" v-model="item.nrp_nip" class="w-full p-1.5 text-xs border border-slate-200 rounded-lg bg-white" />
                                            </td>
                                            <td class="py-2 pr-2 text-center">
                                                <select v-model="item.role_jaga" class="p-1.5 text-xs font-bold border border-slate-200 rounded-lg bg-white">
                                                    <option value="BAGA">BAGA</option>
                                                    <option value="ANGGOTA">ANGGOTA</option>
                                                </select>
                                            </td>
                                            <td class="py-2 text-center">
                                                <button 
                                                    @click="removeAnggotaFromDivisi(divIdx, itemIdx)" 
                                                    type="button" 
                                                    class="text-red-500 hover:text-red-700 font-bold p-1"
                                                >
                                                    x
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SUBMIT BAR -->
                <div class="flex items-center justify-end gap-4 bg-white p-6 rounded-3xl border border-slate-200 shadow-xs">
                    <Link 
                        :href="route('sp-jaga.index')"
                        class="px-6 py-3 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-2xl transition"
                    >
                        Batal
                    </Link>

                    <button 
                        type="submit" 
                        :disabled="form.processing"
                        class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-black text-xs uppercase tracking-wider rounded-2xl transition shadow-lg shadow-blue-500/20 active:scale-[0.98] cursor-pointer"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan Jadwal' }}
                    </button>
                </div>

            </form>

        </div>
    </AuthenticatedLayout>
</template>