<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    personels: Array,
    defaultMonth: Number,
    defaultYear: Number,
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

const initMonth = parseInt(props.defaultMonth || (new Date().getMonth() + 1), 10);
const initYear = parseInt(props.defaultYear || new Date().getFullYear(), 10);
const daysInInitMonth = new Date(initYear, initMonth, 0).getDate();
const initPrevMonth = initMonth === 1 ? 12 : initMonth - 1;
const initPrevYear = initMonth === 1 ? initYear - 1 : initYear;
const daysInInitPrevMonth = new Date(initPrevYear, initPrevMonth, 0).getDate();
const initMonthNameUpper = (monthNames[initMonth] || '').toUpperCase();

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

const initPerwiraTable = calculatePerwiraMatrix(initYear, initMonth, daysInInitMonth);
const initAnggotaDates = calculateAnggotaDates(initYear, initMonth, daysInInitMonth);

// Data Form SP Jaga
const form = useForm({
    bulan: initMonth,
    tahun: initYear,
    nomor_urut: '29',
    tmt_mulai: `${initYear}-${String(initMonth).padStart(2, '0')}-01`,
    tmt_selesai: `${initYear}-${String(initMonth).padStart(2, '0')}-${String(daysInInitMonth).padStart(2, '0')}`,
    tanggal_surat: `${initPrevYear}-${String(initPrevMonth).padStart(2, '0')}-${String(daysInInitPrevMonth).padStart(2, '0')}`,
    ttd_type: 'tte', // 'tte' atau 'manual'
    
    // Perwira Tertua (Pater / Penerima Perintah)
    perwira_tertua_user_id: '',
    perwira_tertua_nama: 'Indra Gunawan',
    perwira_tertua_pangkat_nrp: 'Kapten Laut (P) NRP 19739/P',
    perwira_tertua_jabatan: 'Dan Unit 1 Lid Den Intel Koarmada 2',
    
    // Perwira Jaga (Default 7 Baris Sesuai Format Resmi PDF)
    perwiras: [
        { user_id: '', nama: 'Bambang', pangkat_korps: 'Peltu Saa', nrp: '82068', tgl_1: initPerwiraTable[0][0], tgl_2: initPerwiraTable[0][1], tgl_3: initPerwiraTable[0][2], tgl_4: initPerwiraTable[0][3], tgl_5: initPerwiraTable[0][4], tgl_6: initPerwiraTable[0][5], tgl_7: initPerwiraTable[0][6] },
        { user_id: '', nama: 'Rizal Nurdin W.', pangkat_korps: 'Kapten Laut (P)', nrp: '20873/P', tgl_1: initPerwiraTable[1][0], tgl_2: initPerwiraTable[1][1], tgl_3: initPerwiraTable[1][2], tgl_4: initPerwiraTable[1][3], tgl_5: initPerwiraTable[1][4], tgl_6: initPerwiraTable[1][5], tgl_7: initPerwiraTable[1][6] },
        { user_id: '', nama: 'Bayu Aji K', pangkat_korps: 'Kapten Laut (P)', nrp: '22029/P', tgl_1: initPerwiraTable[2][0], tgl_2: initPerwiraTable[2][1], tgl_3: initPerwiraTable[2][2], tgl_4: initPerwiraTable[2][3], tgl_5: initPerwiraTable[2][4], tgl_6: initPerwiraTable[2][5], tgl_7: initPerwiraTable[2][6] },
        { user_id: '', nama: 'Indra Gunawan', pangkat_korps: 'Kapten Laut (P)', nrp: '19739/P', tgl_1: initPerwiraTable[3][0], tgl_2: initPerwiraTable[3][1], tgl_3: initPerwiraTable[3][2], tgl_4: initPerwiraTable[3][3], tgl_5: initPerwiraTable[3][4], tgl_6: initPerwiraTable[3][5], tgl_7: initPerwiraTable[3][6] },
        { user_id: '', nama: 'Erwan Junaidi', pangkat_korps: 'Peltu Ttg', nrp: '84025', tgl_1: initPerwiraTable[4][0], tgl_2: initPerwiraTable[4][1], tgl_3: initPerwiraTable[4][2], tgl_4: initPerwiraTable[4][3], tgl_5: initPerwiraTable[4][4], tgl_6: initPerwiraTable[4][5], tgl_7: initPerwiraTable[4][6] },
        { user_id: '', nama: "Agus Sub'chan", pangkat_korps: 'Lettu Laut (T)', nrp: '25724/P', tgl_1: initPerwiraTable[5][0], tgl_2: initPerwiraTable[5][1], tgl_3: initPerwiraTable[5][2], tgl_4: initPerwiraTable[5][3], tgl_5: initPerwiraTable[5][4], tgl_6: initPerwiraTable[5][5], tgl_7: initPerwiraTable[5][6] },
        { user_id: '', nama: 'Agus Musonif', pangkat_korps: 'Lettu Laut (P)', nrp: '26327/P', tgl_1: initPerwiraTable[6][0], tgl_2: initPerwiraTable[6][1], tgl_3: initPerwiraTable[6][2], tgl_4: initPerwiraTable[6][3], tgl_5: initPerwiraTable[6][4], tgl_6: initPerwiraTable[6][5], tgl_7: initPerwiraTable[6][6] },
    ],

    // Anggota Jaga Divisi (Default 5 Divisi Sesuai Format Resmi PDF)
    anggotas: [
        {
            divisi_no: 1,
            tanggal_list_text: initAnggotaDates[0],
            anggota_items: [
                { user_id: '', nama: 'HASAN BASRI', pangkat_korps: 'PELDA MAR', nrp_nip: '106737', role_jaga: 'BAGA' },
                { user_id: '', nama: 'ADITYA H.', pangkat_korps: 'SERMA KOM', nrp_nip: '115980', role_jaga: 'ANGGOTA' },
                { user_id: '', nama: 'ACHMAD H.', pangkat_korps: 'KOPKA LIS', nrp_nip: '89853', role_jaga: 'ANGGOTA' },
                { user_id: '', nama: 'SUNARKO, S.H', pangkat_korps: 'PENATA III/C', nrp_nip: '197308261994011001', role_jaga: 'ANGGOTA' },
            ]
        },
        {
            divisi_no: 2,
            tanggal_list_text: initAnggotaDates[1],
            anggota_items: [
                { user_id: '', nama: 'ANDIS Y.', pangkat_korps: 'SERKA EKO', nrp_nip: '114153', role_jaga: 'BAGA' },
                { user_id: '', nama: 'PUJIANTO', pangkat_korps: 'SERKA TKU', nrp_nip: '117387', role_jaga: 'ANGGOTA' },
                { user_id: '', nama: 'RINO Y', pangkat_korps: 'KOPTU TLG', nrp_nip: '113623', role_jaga: 'ANGGOTA' },
                { user_id: '', nama: 'HADIS S.', pangkat_korps: 'PENDA TK I III/B', nrp_nip: '197101311994031002', role_jaga: 'ANGGOTA' },
            ]
        },
        {
            divisi_no: 3,
            tanggal_list_text: initAnggotaDates[2],
            anggota_items: [
                { user_id: '', nama: 'HARTANTO', pangkat_korps: 'PELTU NAV', nrp_nip: '98486', role_jaga: 'BAGA' },
                { user_id: '', nama: 'DWI PURNOMO', pangkat_korps: 'SERTU TTU', nrp_nip: '105224', role_jaga: 'ANGGOTA' },
                { user_id: '', nama: 'RUMADI', pangkat_korps: 'SERTU TTU', nrp_nip: '88386', role_jaga: 'ANGGOTA' },
                { user_id: '', nama: 'EKO YANUAR', pangkat_korps: 'PENG TK I II/D', nrp_nip: '197201141998031003', role_jaga: 'ANGGOTA' },
            ]
        },
        {
            divisi_no: 4,
            tanggal_list_text: initAnggotaDates[3],
            anggota_items: [
                { user_id: '', nama: 'TRI WINDARTO', pangkat_korps: 'SERMA PDK', nrp_nip: '114222', role_jaga: 'BAGA' },
                { user_id: '', nama: 'KARIYADI', pangkat_korps: 'SERKA JAS', nrp_nip: '85822', role_jaga: 'ANGGOTA' },
                { user_id: '', nama: 'IFAN SUSANTO', pangkat_korps: 'KOPKA MES', nrp_nip: '99018', role_jaga: 'ANGGOTA' },
                { user_id: '', nama: 'SAGUS N', pangkat_korps: 'PENATA TK I III/D', nrp_nip: '19701221994021001', role_jaga: 'ANGGOTA' },
            ]
        },
        {
            divisi_no: 5,
            tanggal_list_text: initAnggotaDates[4],
            anggota_items: [
                { user_id: '', nama: 'RIBUT JOHAN P', pangkat_korps: 'SERMA KEU', nrp_nip: '112631', role_jaga: 'BAGA' },
                { user_id: '', nama: 'HENDRA S', pangkat_korps: 'SERMA KOM', nrp_nip: '114931', role_jaga: 'ANGGOTA' },
                { user_id: '', nama: 'HERI WARSITO', pangkat_korps: 'PENDA TK I III/B', nrp_nip: '197502022002122006', role_jaga: 'ANGGOTA' },
                { user_id: '', nama: 'GUNAWAN', pangkat_korps: 'PENDA III/A', nrp_nip: '197810062005011005', role_jaga: 'ANGGOTA' },
            ]
        },
    ]
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

// Auto-fill Perwira saat memilih dari database User
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

// Auto-fill Anggota saat memilih dari database User
const onAnggotaSelect = (divIdx, itemIdx, userId) => {
    const p = props.personels.find(x => x.id === userId);
    if (p) {
        form.anggotas[divIdx].anggota_items[itemIdx].nama = p.name;
        form.anggotas[divIdx].anggota_items[itemIdx].pangkat_korps = p.pangkat || '';
        form.anggotas[divIdx].anggota_items[itemIdx].nrp_nip = p.nrp || '';
    }
};

// Tambah / Hapus Perwira
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

// Tambah / Hapus Anggota di Divisi
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

    // Tanggal Surat Dikeluarkan (Akhir Bulan Sebelumnya)
    const prevMonth = m === 1 ? 12 : m - 1;
    const prevYear = m === 1 ? y - 1 : y;
    const daysInPrevMonth = new Date(prevYear, prevMonth, 0).getDate();
    form.tanggal_surat = `${prevYear}-${String(prevMonth).padStart(2, '0')}-${String(daysInPrevMonth).padStart(2, '0')}`;

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

// Fungsi Otomatis Hitung Ulang Tanggal Berdasarkan Pilihan Bulan & Tahun
const recalculateDatesForMonth = () => {
    const b = parseInt(form.bulan, 10);
    const y = parseInt(form.tahun, 10);
    if (!b || !y) return;

    isSyncingDates = true;
    const daysInMonth = new Date(y, b, 0).getDate();
    form.tmt_mulai = `${y}-${String(b).padStart(2, '0')}-01`;
    form.tmt_selesai = `${y}-${String(b).padStart(2, '0')}-${String(daysInMonth).padStart(2, '0')}`;

    generateJadwalJaga(false);

    setTimeout(() => {
        isSyncingDates = false;
    }, 50);
};

watch(() => [form.bulan, form.tahun], () => {
    if (!isSyncingDates) {
        recalculateDatesForMonth();
    }
});

// Watcher untuk sinkronisasi dua arah dari tmt_mulai ke bulan & tahun
watch(() => form.tmt_mulai, (newVal) => {
    if (isSyncingDates || !newVal) return;
    const parts = newVal.split('-');
    if (parts.length === 3) {
        const y = parseInt(parts[0], 10);
        const m = parseInt(parts[1], 10);
        if (m >= 1 && m <= 12 && (parseInt(form.bulan) !== m || parseInt(form.tahun) !== y)) {
            isSyncingDates = true;
            form.bulan = m;
            form.tahun = y;
            setTimeout(() => {
                isSyncingDates = false;
            }, 50);
        }
    }
});

const submit = () => {
    form.post(route('sp-jaga.store'), {
        onSuccess: () => {
            Swal.fire('Berhasil', 'Surat Perintah Jaga Siaga Sintel berhasil disimpan.', 'success');
        }
    });
};
</script>

<template>
    <Head title="Buat SP Jaga Baru - SINDEN" />

    <AuthenticatedLayout>
        <div class="space-y-6 max-w-6xl mx-auto font-sans pb-12">
            
            <!-- Breadcrumb & Header -->
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                        <Link :href="route('sp-jaga.index')" class="hover:text-blue-600">SP Jaga</Link>
                        <span>/</span>
                        <span class="text-slate-800 font-bold">Buat SP Jaga Baru</span>
                    </div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">Formulir Penerbitan SP Jaga Siaga</h1>
                </div>

                <Link 
                    :href="route('sp-jaga.index')"
                    class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition"
                >
                    ← Kembali
                </Link>
            </div>

            <form @submit.prevent="submit" class="space-y-8">
                
                <!-- BAGIAN 1: INFORMASI SURAT PERINTAH (HALAMAN 1) -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-6">
                    <div class="border-b border-slate-100 pb-4">
                        <h2 class="text-base font-extrabold text-slate-900">1. Header & Periode Surat Perintah</h2>
                        <p class="text-xs text-slate-500">Informasi nomor surat perintah, masa berlaku TMT dinas, dan pilihan tanda tangan.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Periode Bulan -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">Bulan Dinas</label>
                            <select v-model.number="form.bulan" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500">
                                <option v-for="m in monthsList" :key="m.id" :value="m.id">{{ m.name }}</option>
                            </select>
                        </div>

                        <!-- Tahun -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">Tahun</label>
                            <input type="number" v-model="form.tahun" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <!-- Nomor Urut Sprin -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">Nomor Urut Sprin</label>
                            <input type="text" v-model="form.nomor_urut" placeholder="Contoh: 29 atau 50a" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <!-- TMT Mulai -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">TMT Mulai</label>
                            <input type="date" v-model="form.tmt_mulai" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <!-- TMT Selesai -->
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
                                    @click="generateJadwalJaga(true)" 
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

                        <!-- Tanggal Surat Terbit -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">Tanggal Dikeluarkan di Surabaya</label>
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

                    <!-- Pilihan Jenis Tanda Tangan (TTE vs TTD Manual) -->
                    <div class="p-5 rounded-2xl bg-blue-50/60 border border-blue-200 space-y-3">
                        <label class="text-xs font-black uppercase tracking-wider text-blue-900 block">
                            Metode Pengesahan & Tanda Tangan:
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label 
                                class="p-4 rounded-2xl border flex items-start gap-3 cursor-pointer transition"
                                :class="form.ttd_type === 'tte' ? 'bg-white border-blue-600 ring-2 ring-blue-500/20 shadow-xs' : 'bg-white/50 border-slate-200'"
                            >
                                <input type="radio" v-model="form.ttd_type" value="tte" class="mt-1" />
                                <div>
                                    <span class="font-bold text-xs text-slate-900 block">TTE Digital (QR Code Resmi)</span>
                                    <span class="text-[11px] text-slate-500 leading-snug block">
                                        Draf akan dikirim ke antrean Pasops untuk diperiksa & ditandatangani secara elektronik. QR Code verifikasi akan disematkan pada PDF.
                                    </span>
                                </div>
                            </label>

                            <label 
                                class="p-4 rounded-2xl border flex items-start gap-3 cursor-pointer transition"
                                :class="form.ttd_type === 'manual' ? 'bg-white border-amber-600 ring-2 ring-amber-500/20 shadow-xs' : 'bg-white/50 border-slate-200'"
                            >
                                <input type="radio" v-model="form.ttd_type" value="manual" class="mt-1" />
                                <div>
                                    <span class="font-bold text-xs text-slate-900 block">TTD Manual (Tanda Tangan Basah)</span>
                                    <span class="text-[11px] text-slate-500 leading-snug block">
                                        Cetak SP Jaga dengan kolom tanda tangan fisik kosong. Setelah ditandatangani basah, hasil scan PDF diunggah ke sistem.
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- BAGIAN 2: DAFTAR PERWIRA JAGA SINTEL (LAMPIRAN 1) -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>
                            <h2 class="text-base font-extrabold text-slate-900">2. Lampiran 1: Daftar Nama Perwira Jaga</h2>
                            <p class="text-xs text-slate-500">Daftar perwira siaga dan jadwal rotasi tanggal dinas selama bulan berjalan. Anda juga dapat memilih Perwira Tertua (Pater) langsung dari tabel.</p>
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
                                    <th class="p-2.5 text-center w-10">Hapus</th>
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
                                            <input type="text" v-model="p.nama" placeholder="Ketik manual nama..." class="w-full p-1.5 text-xs border border-slate-200 rounded-lg" />
                                        </div>
                                    </td>
                                    <td class="p-2">
                                        <input type="text" v-model="p.pangkat_korps" placeholder="Peltu Saa" class="w-full p-1.5 text-xs border border-slate-200 rounded-lg" />
                                    </td>
                                    <td class="p-2">
                                        <input type="text" v-model="p.nrp" placeholder="82068" class="w-full p-1.5 text-xs border border-slate-200 rounded-lg" />
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
                        <p class="text-xs text-slate-500">Kelompok regu divisi jaga (1 BAGA + 3 Anggota per regu) dan jadwal tanggal bertugas.</p>
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
                                        placeholder="04, 09, 14, 19, 24, 29 JULI 2026" 
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

                            <!-- Tabel Anggota Divisi -->
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
                                                    <input type="text" v-model="item.nama" placeholder="Ketik nama..." class="w-full p-1.5 text-xs border border-slate-200 rounded-lg bg-white" />
                                                </div>
                                            </td>
                                            <td class="py-2 pr-2">
                                                <input type="text" v-model="item.pangkat_korps" placeholder="PELDA MAR" class="w-full p-1.5 text-xs border border-slate-200 rounded-lg bg-white" />
                                            </td>
                                            <td class="py-2 pr-2">
                                                <input type="text" v-model="item.nrp_nip" placeholder="106737" class="w-full p-1.5 text-xs border border-slate-200 rounded-lg bg-white" />
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
                        {{ form.processing ? 'Menyimpan...' : 'Simpan & Terbitkan SP Jaga' }}
                    </button>
                </div>

            </form>

        </div>
    </AuthenticatedLayout>
</template>