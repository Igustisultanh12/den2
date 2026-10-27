<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    skhpp: Object,
    verificationUrl: String,
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const isCommander = computed(() => user.value.role === 'admin' || user.value.role === 'komandan' || user.value.name === 'I Gusti Sultan H.A, A.Md.Kom');

// QR Code URL generator (Using Google Chart / QRServer API)
const qrApiUrl = computed(() => {
    const url = props.verificationUrl || window.location.href;
    return `https://api.qrserver.com/v1/create-qr-code/?size=150x150&margin=5&data=${encodeURIComponent(url)}`;
});

const isExporting = ref(false);

const downloadPdf = () => {
    window.open(route('skhpp.export-pdf', props.skhpp.id), '_blank');
};

const approveSkhpp = () => {
    const defaultSeq = (props.skhpp.nomor_urut || 1);

    Swal.fire({
        title: 'OTORISASI TTD KOMANDAN',
        html: `
            <div class="text-left text-xs space-y-3 font-sans">
                <p class="text-slate-600">Nomor SKHPP akan diterbitkan & tersinkronisasi otomatis ke <b>Buku Agenda Surat SINDEN (Letter Logs)</b>:</p>
                
                <div>
                    <label class="block font-bold uppercase mb-1 text-slate-700">Nomor Urut SKHPP (Bisa diisi manual jika ada arsip terlewat)</label>
                    <input id="swal-nomor-urut-show" type="number" value="${defaultSeq}" class="w-full text-xs font-bold p-2.5 border rounded-xl" placeholder="Masukkan nomor urut (contoh: 171)" />
                </div>

                <div>
                    <label class="block font-bold uppercase mb-1 text-slate-700">Derajat Kecepatan / Prioritas (Logika Penyamaran Kode SKHPP)</label>
                    <select id="swal-priority-show" class="w-full text-xs font-bold p-2.5 border rounded-xl">
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
            const seq = document.getElementById('swal-nomor-urut-show').value;
            const prio = document.getElementById('swal-priority-show').value;
            return { custom_nomor_urut: seq, priority: prio };
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            router.post(route('skhpp.approve', props.skhpp.id), result.value, {
                onSuccess: () => Swal.fire('SUKSES', 'SKHPP Resmi disetujui & ditandatangani Komandan.', 'success')
            });
        }
    });
};

const formatDateIndo = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
};
</script>

<template>
    <Head :title="`Detail SKHPP - ${skhpp.nama}`" />

    <AuthenticatedLayout>
        <div class="py-6 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            
            <!-- Top Control Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-3xl shadow-xs border border-slate-100">
                <div class="flex items-center gap-3">
                    <Link :href="route('skhpp.index')" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition-all">
                         Halaman Utama
                    </Link>
                    <div>
                        <h1 class="text-base font-black text-slate-900 uppercase">Dokumen SKHPP {{ skhpp.nama }}</h1>
                        <p class="text-[10px] text-slate-500 font-bold uppercase">Status: 
                            <span :class="skhpp.status === 'approved' ? 'text-emerald-600' : 'text-amber-600'">
                                {{ skhpp.status === 'approved' ? 'RESMI TERBIT (TTD DIGITAL)' : 'PENDING TTD KOMANDAN' }}
                            </span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button v-if="isCommander && skhpp.status === 'pending'" @click="approveSkhpp"class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black uppercase tracking-wider rounded-xl shadow-md transition-all"> TTD Komandan Sekarang
                    </button>

                    <button @click="downloadPdf" :disabled="isExporting"class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-black uppercase tracking-wider rounded-xl shadow-md transition-all flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        <span>{{ isExporting ? 'Mengeksport PDF...' : 'Unduh Dokumen PDF' }}</span>
                    </button>
                </div>
            </div>

            <!-- Main Printable Document Container -->
            <div class="flex justify-center">
                <div id="area-skhpp-cetak" class="bg-white text-black p-10 font-sans leading-relaxed shadow-2xl border border-slate-200 text-[12pt]" style="width: 670px; min-height: 950px; box-sizing: border-box; font-family: Arial, Helvetica, sans-serif;">
                    
                    <!-- Page 1: Main SKHPP Document -->
                    <div class="skhpp-page">
                        
                        <!-- Kop Header -->
                        <div class="mb-5 w-[380px] text-center">
                            <div class="font-normal uppercase text-[12pt] whitespace-nowrap">KOMANDO ARMADA II</div>
                            <div class="font-normal uppercase text-[12pt] whitespace-nowrap">DETASEMEN INTELIJEN</div>
                            <div class="border-b-2 border-black w-full mt-0.5"></div>
                        </div>

                        <!-- Judul Surat -->
                        <div class="text-center my-6">
                            <div class="font-normal uppercase text-[12pt]">SURAT KETERANGAN HASIL PENELITIAN PERSONEL(SKHPP)</div>
                            <div v-if="skhpp.kategori_personel === 'perusahaan'" class="font-normal uppercase text-[12pt]">MITRA KERJA TNI ANGKATAN LAUT</div>
                            <div class="font-normal text-[12pt] mt-1">
                                <span v-if="skhpp.nomor_skhpp">Nomor : {{ skhpp.nomor_skhpp }}</span>
                                <span v-else>Nomor : R/ &nbsp;&nbsp;&nbsp;&nbsp;{{ skhpp.nomor_urut || '        ' }}&nbsp;&nbsp;&nbsp;&nbsp; /SKHPP/{{ skhpp.bulan_romawi || 'VIII' }}/{{ skhpp.tahun || '2026' }}</span>
                            </div>
                        </div>

                        <!-- Poin Rincian SKHPP -->
                        <div class="text-[12pt] space-y-3 font-normal" style="text-align: justify;">
                            
                            <!-- Poin 1: Dasar -->
                            <div class="flex items-start">
                                <span class="w-8 font-normal shrink-0">1.</span>
                                <div class="flex-1">
                                    <div class="font-normal">Dasar :</div>
                                    <div class="space-y-1.5 mt-1">
                                        <div class="flex items-start">
                                            <span class="w-6 shrink-0">a.</span>
                                            <div class="flex-1">Peraturan Kasal Nomor Perkasal/50/XII/2007 tanggal 04 Desember 2007 tentang Petunjuk Pelaksanaan Penelitian Personel di lingkungan TNI AL;</div>
                                        </div>
                                        <div class="flex items-start">
                                            <span class="w-6 shrink-0">b.</span>
                                            <div class="flex-1">Prosedur Tetap Nomor Protap/01/VIII/2024 tanggal 26 Agustus 2024 tentang Pengurusan Surat Keterangan Hasil Penelitian Personel (SKHPP) di Lingkungan Tentara Nasional Indonesia Angkatan Laut; dan</div>
                                        </div>
                                        <div class="flex items-start">
                                            <span class="w-6 shrink-0">c.</span>
                                            <div class="flex-1">{{ skhpp.surat_pengantar }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Poin 2: Data Personel -->
                            <div class="flex items-start pt-1">
                                <span class="w-8 font-normal shrink-0">2.</span>
                                <div class="flex-1">
                                    <div>Dengan ini menerangkan bahwa hasil penelitian terhadap :</div>
                                    
                                    <!-- Militer / PNS dengan Pangkat/NRP/NIP -->
                                    <table v-if="skhpp.pangkat_korps_nrp" class="w-full mt-2 text-[12pt] pl-[45px]" style="line-height: 1.5;">
                                        <tr>
                                            <td class="w-6 font-normal align-top">a.</td>
                                            <td class="w-36 font-normal align-top">Nama</td>
                                            <td class="w-3 align-top">:</td>
                                            <td class="font-normal align-top">{{ skhpp.nama }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal align-top">b.</td>
                                            <td class="font-normal align-top">Pangkat/Korp/NRP</td>
                                            <td class="align-top">:</td>
                                            <td class="align-top font-normal">{{ skhpp.pangkat_korps_nrp }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal align-top">c.</td>
                                            <td class="font-normal align-top">Jabatan</td>
                                            <td class="align-top">:</td>
                                            <td class="align-top font-normal">{{ skhpp.jabatan_pekerjaan }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal align-top">d.</td>
                                            <td class="font-normal align-top">Tempat/Tgl. lahir</td>
                                            <td class="align-top">:</td>
                                            <td class="align-top font-normal">{{ skhpp.tempat_lahir }}, {{ formatDateIndo(skhpp.tanggal_lahir) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal align-top">e.</td>
                                            <td class="font-normal align-top">Jenis kelamin</td>
                                            <td class="align-top">:</td>
                                            <td class="align-top font-normal">{{ skhpp.jenis_kelamin }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal align-top">f.</td>
                                            <td class="font-normal align-top">Agama</td>
                                            <td class="align-top">:</td>
                                            <td class="align-top font-normal">{{ skhpp.agama }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal align-top">g.</td>
                                            <td class="font-normal align-top">Alamat rumah</td>
                                            <td class="align-top">:</td>
                                            <td class="align-top font-normal">{{ skhpp.alamat }}</td>
                                        </tr>
                                    </table>

                                    <!-- Sipil / Pelajar / Mahasiswa / Perusahaan dengan NIK -->
                                    <table v-else class="w-full mt-2 text-[12pt] pl-[45px]" style="line-height: 1.5;">
                                        <tr>
                                            <td class="w-6 font-normal align-top">a.</td>
                                            <td class="w-36 font-normal align-top">Nama</td>
                                            <td class="w-3 align-top">:</td>
                                            <td class="font-normal align-top">{{ skhpp.nama }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal align-top">b.</td>
                                            <td class="font-normal align-top">NIK</td>
                                            <td class="align-top">:</td>
                                            <td class="align-top font-normal">{{ skhpp.nik || '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal align-top">c.</td>
                                            <td class="font-normal align-top">Tempat/Tgl. lahir</td>
                                            <td class="align-top">:</td>
                                            <td class="align-top font-normal">{{ skhpp.tempat_lahir }}, {{ formatDateIndo(skhpp.tanggal_lahir) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal align-top">d.</td>
                                            <td class="font-normal align-top">Jenis kelamin</td>
                                            <td class="align-top">:</td>
                                            <td class="align-top font-normal">{{ skhpp.jenis_kelamin }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal align-top">e.</td>
                                            <td class="font-normal align-top">Agama</td>
                                            <td class="align-top">:</td>
                                            <td class="align-top font-normal">{{ skhpp.agama }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal align-top">f.</td>
                                            <td class="font-normal align-top">Pekerjaan</td>
                                            <td class="align-top">:</td>
                                            <td class="align-top font-normal">{{ skhpp.jabatan_pekerjaan }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal align-top">g.</td>
                                            <td class="font-normal align-top">Alamat rumah</td>
                                            <td class="align-top">:</td>
                                            <td class="align-top font-normal">{{ skhpp.alamat }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Poin 2: Hasil (Nomor 2 Sesuai SISFOPERS) -->
                            <div class="flex items-start pt-1">
                                <span class="w-8 font-normal shrink-0">2.</span>
                                <div class="flex-1"> Hasil Penelitian Personel <span class="font-bold">Memenuhi Syarat</span>
                                </div>
                            </div>

                            <!-- Poin 3: Peruntukan -->
                            <div class="flex items-start pt-1">
                                <span class="w-8 font-normal shrink-0">3.</span>
                                <div class="flex-1"> SKHPP ini diberikan {{ skhpp.peruntukan }}.
                                </div>
                            </div>

                            <!-- Poin 4: Penutup -->
                            <div class="flex items-start pt-1">
                                <span class="w-8 font-normal shrink-0">4.</span>
                                <div class="flex-1"> Apabila kemudian terdapat kekeliruan, SKHPP ini akan dicabut dan diadakan pembetulan seperlunya.
                                </div>
                            </div>
                        </div>

                        <!-- Pas Foto 4x6 (Mepet TTD Komandan & Suami-Istri Nempel) & Signature Block Komandan -->
                        <div class="mt-8 flex justify-between items-start text-[12pt]">
                            
                            <!-- Ruang Kosong Left -->
                            <div class="w-[15%]"></div>

                            <!-- Pas Foto Section 4x6 (Nempel Berhimpitan Tanpa Border) -->
                            <div class="flex items-center gap-0 pr-1 shrink-0">
                                <div v-if="skhpp.foto_1" class="text-center">
                                    <img :src="'/storage/' + skhpp.foto_1" class="w-[4cm] h-[6cm] object-cover border-none inline-block align-top" />
                                    <div v-if="skhpp.is_pernikahan" class="text-[9px] font-normal mt-1 uppercase">Suami</div>
                                </div>
                                <div v-if="skhpp.is_pernikahan && skhpp.foto_2" class="text-center">
                                    <img :src="'/storage/' + skhpp.foto_2" class="w-[4cm] h-[6cm] object-cover border-none inline-block align-top" />
                                    <div class="text-[9px] font-normal mt-1 uppercase">Istri</div>
                                </div>
                            </div>

                            <!-- TTD Block Komandan & QR Code -->
                            <div class="w-[330px]">
                                <div class="text-left">Dikeluarkan di Surabaya</div>
                                <div class="border-b border-black pb-0.5 mb-1 flex justify-between items-center text-[12pt]">
                                    <span>pada tanggal</span>
                                    <span>{{ formatDateIndo(skhpp.tanggal_skhpp || skhpp.approved_at || skhpp.created_at) }}</span>
                                </div>
                                <div class="font-normal text-center whitespace-nowrap mt-1 leading-snug"> Komandan Detasemen Intelijen Koarmada 2,
                                </div>

                                <!-- QR Code Digital Signature -->
                                <div class="my-2 py-1 text-center">
                                    <div v-if="skhpp.status === 'approved'" class="flex items-center justify-center gap-2">
                                        <img :src="qrApiUrl" class="w-[85px] h-[85px] border border-slate-300 p-0.5 rounded-sm" />
                                        <div class="text-[8px] leading-tight font-sans text-slate-600 text-left">
                                            <div class="font-bold text-emerald-700"> TERVERIFIKASI TTD</div>
                                            <div>Detasemen Intelijen V</div>
                                            <div class="text-[7px] text-slate-400 mt-0.5 truncate max-w-[120px]">{{ skhpp.verification_code }}</div>
                                        </div>
                                    </div>
                                    <div v-else class="h-16 flex items-center justify-center border border-dashed border-slate-300 text-[10px] text-slate-400 font-sans italic">
                                        [ PENDING TTD KOMANDAN ]
                                    </div>
                                </div>

                                <div class="font-normal text-center whitespace-nowrap">Hari Bagio Wijayanto, M.Tr.Opsla.</div>
                                <div class="font-normal text-center whitespace-nowrap">Kolonel Laut (E) NRP 16085/P</div>
                            </div>
                        </div>

                        <!-- Footer Kepada -->
                        <div class="mt-8 text-[12pt] font-normal">
                            <div>Kepada :</div>
                            <div class="font-normal border-b border-black inline-block pb-0.5 whitespace-nowrap">Yth. Asintel Pangkoarmada 2</div>
                        </div>
                    </div>

                    <!-- Page 2: Lampiran SKHPP (If members exist > 0) -->
                    <template v-if="skhpp.has_pengikut && skhpp.members && skhpp.members.length > 0">
                        
                        <div class="html2pdf__page-break" style="page-break-before: always; height: 1px;"></div>

                        <div class="skhpp-page pt-6">
                            
                            <!-- Header Lampiran (Kop Kiri & Detail Lampiran Kanan) -->
                            <div class="flex justify-between items-start mb-6">
                                <div class="w-[380px] text-center">
                                    <div class="text-[12pt] font-normal uppercase leading-tight whitespace-nowrap">KOMANDO ARMADA II</div>
                                    <div class="text-[12pt] font-normal uppercase leading-tight whitespace-nowrap">DETASEMEN INTELIJEN</div>
                                    <div class="border-b-2 border-black w-full mt-0.5"></div>
                                </div>
                                <div class="text-right text-[11pt] font-normal" style="line-height: 1.3;">
                                    <div>Lampiran SKHPP Den Intel Koarmada 2</div>
                                    <div class="border-b border-black inline-block pb-0.5">
                                        <span v-if="skhpp.nomor_skhpp">Nomor {{ skhpp.nomor_skhpp }}</span>
                                        <span v-else>Nomor SKHPP/ <span class="px-2">{{ skhpp.nomor_urut || '   ' }}</span> /{{ skhpp.bulan_romawi || 'VIII' }}/{{ skhpp.tahun || '2026' }}</span><br> Tanggal <span class="px-3">{{ formatDateIndo(skhpp.tanggal_skhpp || skhpp.approved_at || skhpp.created_at) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Judul Lampiran -->
                            <div class="text-center my-6">
                                <div class="text-[12pt] font-normal uppercase tracking-wider"> DAFTAR NAMA-NAMA ANGGOTA PENGIKUT
                                </div>
                            </div>

                            <!-- Tabel Anggota Pengikut -->
                            <table class="w-full border-collapse border border-black text-[11pt] my-6 font-normal">
                                <thead>
                                    <tr class="font-normal uppercase border-b border-black text-center bg-slate-50">
                                        <th class="border border-black p-2 w-10">NO</th>
                                        <th class="border border-black p-2">NAMA</th>
                                        <th class="border border-black p-2">NIK / NRP / NIP</th>
                                        <th class="border border-black p-2">JABATAN</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(m, idx) in skhpp.members" :key="idx" class="border-b border-black">
                                        <td class="border border-black p-2 text-center font-normal">{{ idx + 1 }}.</td>
                                        <td class="border border-black p-2 font-normal">{{ m.nama }}</td>
                                        <td class="border border-black p-2 text-center">{{ m.pangkat_nrp_nik }}</td>
                                        <td class="border border-black p-2">{{ m.jabatan }}</td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- TTD Komandan Lampiran -->
                            <div class="mt-8 flex justify-end text-[12pt]">
                                <div class="w-[330px] text-center">
                                    <div class="font-normal text-center whitespace-nowrap leading-snug"> Komandan Detasemen Intelijen Koarmada 2,
                                    </div>

                                    <div class="my-2 py-1 text-center">
                                        <div v-if="skhpp.status === 'approved'" class="flex items-center justify-center gap-2">
                                            <img :src="qrApiUrl" class="w-[85px] h-[85px] border border-slate-300 p-0.5 rounded-sm" />
                                        </div>
                                        <div v-else class="h-16 flex items-center justify-center border border-dashed border-slate-300 text-[10px] text-slate-400 font-sans italic">
                                            [ PENDING TTD KOMANDAN ]
                                        </div>
                                    </div>

                                    <div class="font-normal text-center whitespace-nowrap">Hari Bagio Wijayanto, M.Tr.Opsla.</div>
                                    <div class="font-normal text-center whitespace-nowrap">Kolonel Laut (E) NRP 16085/P</div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@media print {
    @page {
        size: 215mm 330mm portrait;
        margin: 1cm 1.5cm;
    }
}
</style>
