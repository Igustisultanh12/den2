<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    skhpp: Object,
});

const form = useForm({
    kategori_personel: props.skhpp.kategori_personel || 'militer',
    is_pernikahan: Boolean(props.skhpp.is_pernikahan),
    surat_pengantar: props.skhpp.surat_pengantar || '',
    nama: props.skhpp.nama || '',
    pangkat_korps_nrp: props.skhpp.pangkat_korps_nrp || '',
    nik: props.skhpp.nik || '',
    jabatan_pekerjaan: props.skhpp.jabatan_pekerjaan || '',
    tempat_lahir: props.skhpp.tempat_lahir || '',
    tanggal_lahir: props.skhpp.tanggal_lahir ? props.skhpp.tanggal_lahir.split('T')[0] : '',
    jenis_kelamin: props.skhpp.jenis_kelamin || 'Laki-laki',
    agama: props.skhpp.agama || 'Islam',
    alamat: props.skhpp.alamat || '',
    has_pengikut: Boolean(props.skhpp.has_pengikut),
    members: props.skhpp.members ? props.skhpp.members.map(m => ({
        nama: m.nama,
        pangkat_nrp_nik: m.pangkat_nrp_nik,
        jabatan: m.jabatan
    })) : [],
    peruntukan: props.skhpp.peruntukan || '',
    foto_1: null,
    foto_2: null,
});

const preview1 = ref(props.skhpp.foto_1 ? '/storage/' + props.skhpp.foto_1 : null);
const preview2 = ref(props.skhpp.foto_2 ? '/storage/' + props.skhpp.foto_2 : null);

const handleFoto1Change = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.foto_1 = file;
        preview1.value = URL.createObjectURL(file);
    }
};

const handleFoto2Change = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.foto_2 = file;
        preview2.value = URL.createObjectURL(file);
    }
};

const addMemberRow = () => {
    form.members.push({
        nama: '',
        pangkat_nrp_nik: '',
        jabatan: '',
    });
};

const removeMemberRow = (index) => {
    form.members.splice(index, 1);
};

const togglePengikut = () => {
    if (form.has_pengikut && form.members.length === 0) {
        addMemberRow();
    }
};

const submit = () => {
    form.post(route('skhpp.update', props.skhpp.id), {
        onSuccess: () => Swal.fire('BERHASIL', 'Revisi data SKHPP telah diperbarui & dikirim ulang ke TTD Komandan.', 'success'),
    });
};
</script>

<template>
    <Head :title="`Revisi SKHPP - ${skhpp.nama}`" />

    <AuthenticatedLayout>
        <div class="py-6 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            
            <!-- Header Section -->
            <div class="flex items-center justify-between bg-white p-6 rounded-3xl shadow-xs border border-slate-100">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-amber-50 text-amber-600 rounded-2xl">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-black text-slate-900 uppercase tracking-tight">Revisi Data SKHPP Operator</h1>
                        <p class="text-xs text-slate-500 font-medium">Perbarui data yang salah lalu ajukan ulang ke Komandan</p>
                    </div>
                </div>

                <Link :href="route('skhpp.index')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold uppercase rounded-xl transition-all">
                     Batal / Kembali
                </Link>
            </div>

            <!-- Alert Catatan Revisi dari Komandan -->
            <div v-if="skhpp.catatan_revisi" class="p-5 bg-rose-50 border border-rose-200 rounded-3xl space-y-1">
                <div class="text-xs font-black uppercase text-rose-700 tracking-wider flex items-center gap-2">
                    <span> Catatan Revisi dari Komandan:</span>
                </div>
                <p class="text-xs font-semibold text-rose-900 leading-relaxed pl-6">
                    "{{ skhpp.catatan_revisi }}"
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                
                <!-- Opsi Kategori & Pengajuan -->
                <div class="bg-white p-6 rounded-3xl shadow-xs border border-slate-100 space-y-6">
                    <h2 class="text-xs font-black uppercase tracking-wider text-emerald-600 border-b pb-2">1. Jenis Permohonan & Kategori</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Kategori Personel & Peruntukan</label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <button type="button" @click="form.kategori_personel = 'militer'"
                                    :class="form.kategori_personel === 'militer' ? 'bg-emerald-600 text-white shadow-md' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"class="py-3.5 px-4 rounded-2xl text-xs font-black uppercase transition-all flex flex-col items-center gap-1">
                                    <span> Militer TNI AL</span>
                                    <span class="text-[9px] opacity-80">(Format Dinas SKHPP-D)</span>
                                </button>
                                <button type="button" @click="form.kategori_personel = 'sipil_dinas'"
                                    :class="form.kategori_personel === 'sipil_dinas' ? 'bg-emerald-600 text-white shadow-md' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"class="py-3.5 px-4 rounded-2xl text-xs font-black uppercase transition-all flex flex-col items-center gap-1">
                                    <span> PNS / Sipil Dinas</span>
                                    <span class="text-[9px] opacity-80">(Format Dinas SKHPP-D)</span>
                                </button>
                                <button type="button" @click="form.kategori_personel = 'perusahaan'"
                                    :class="form.kategori_personel === 'perusahaan' ? 'bg-blue-600 text-white shadow-md' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"class="py-3.5 px-4 rounded-2xl text-xs font-black uppercase transition-all flex flex-col items-center gap-1">
                                    <span> Perusahaan / Mitra Kerja</span>
                                    <span class="text-[9px] opacity-80">(Format Perusahaan SKHPP-P)</span>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Peruntukan Khusus Pernikahan?</label>
                            <div class="grid grid-cols-2 gap-3">
                                <button type="button" @click="form.is_pernikahan = false"
                                    :class="!form.is_pernikahan ? 'bg-blue-600 text-white shadow-md' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"class="py-3 px-4 rounded-2xl text-xs font-black uppercase transition-all"> Kedinasan / General
                                </button>
                                <button type="button" @click="form.is_pernikahan = true"
                                    :class="form.is_pernikahan ? 'bg-purple-600 text-white shadow-md' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"class="py-3 px-4 rounded-2xl text-xs font-black uppercase transition-all"> Pengajuan Nikah
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Point 1c: Surat Pengantar -->
                <div class="bg-white p-6 rounded-3xl shadow-xs border border-slate-100 space-y-4">
                    <h2 class="text-xs font-black uppercase tracking-wider text-emerald-600 border-b pb-2">2. Dasar Surat Pengantar (Point 1c)</h2>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1"> Isi Surat Pengantar / Permohonan Security Clearance *
                        </label>
                        <textarea v-model="form.surat_pengantar" rows="3" required
                            placeholder="Contoh: Surat Karumkital Dr. Oepomo Koarmada 2 No. R/19/III/2026 tanggal 30 Maret 2026, tentang permohonan Security Clearance."class="w-full text-xs font-medium px-4 py-3 rounded-2xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                    </div>
                </div>

                <!-- Point 2: Data Personel Utama -->
                <div class="bg-white p-6 rounded-3xl shadow-xs border border-slate-100 space-y-4">
                    <h2 class="text-xs font-black uppercase tracking-wider text-emerald-600 border-b pb-2">3. Data Penelitian Personel Utama (Point 2)</h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Nama Lengkap & Gelar *</label>
                            <input type="text" v-model="form.nama" required placeholder="Contoh: dr. Lilis Haryani atau Sardijono, S.H."class="w-full text-xs font-semibold px-4 py-2.5 rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                                {{ form.kategori_personel === 'militer' ? 'Pangkat / Korps / NRP' : 'Pangkat / Golongan / NIP / Korps (Opsional PNS)' }}
                            </label>
                            <input type="text" v-model="form.pangkat_korps_nrp" placeholder="Contoh: Kapten Laut (K/W) NRP 22608/P atau PNS PENATA III/C NIP. 1973..."class="w-full text-xs font-semibold px-4 py-2.5 rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">NIK (Nomor Induk Kependudukan / Pelajar / Mahasiswa)</label>
                            <input type="text" v-model="form.nik" placeholder="Contoh: 3515081205640009"class="w-full text-xs font-semibold px-4 py-2.5 rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Jabatan / Pekerjaan *</label>
                            <input type="text" v-model="form.jabatan_pekerjaan" required placeholder="Contoh: Ka/PS BP Tg. Sadari Diskes Koarmada 2 atau Pimpinan CV Afnalia Jaya"class="w-full text-xs font-semibold px-4 py-2.5 rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Tempat Lahir *</label>
                            <input type="text" v-model="form.tempat_lahir" required placeholder="Contoh: Medan / Surabaya"class="w-full text-xs font-semibold px-4 py-2.5 rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Tanggal Lahir *</label>
                            <input type="date" v-model="form.tanggal_lahir" required
                                class="w-full text-xs font-semibold px-4 py-2.5 rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Jenis Kelamin *</label>
                            <select v-model="form.jenis_kelamin" required class="w-full text-xs font-semibold px-4 py-2.5 rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Agama *</label>
                            <input type="text" v-model="form.agama" required placeholder="Islam / Kristen / Katolik / Hindu / Buddha"class="w-full text-xs font-semibold px-4 py-2.5 rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" />
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Alamat Rumah Lengkap *</label>
                            <textarea v-model="form.alamat" rows="2" required placeholder="Contoh: Jl. Petukangan 62 Ampel Surabaya."class="w-full text-xs font-medium px-4 py-2.5 rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Point 2h: Anggota Pengikut -->
                <div class="bg-white p-6 rounded-3xl shadow-xs border border-slate-100 space-y-4">
                    <div class="flex items-center justify-between border-b pb-2">
                        <h2 class="text-xs font-black uppercase tracking-wider text-emerald-600">4. Anggota Pengikut / Rombongan (Point 2h)</h2>

                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" v-model="form.has_pengikut" @change="togglePengikut" class="w-4 h-4 text-emerald-600 rounded-md focus:ring-0" />
                            <span class="text-xs font-bold uppercase text-slate-700">Centang Jika Ada Pengikut (> 1 Orang)</span>
                        </label>
                    </div>

                    <div v-if="form.has_pengikut" class="space-y-4 pt-2">
                        <div v-for="(member, idx) in form.members" :key="idx" class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-3">
                            <div class="flex items-center justify-between text-xs font-bold uppercase text-slate-600">
                                <span>Anggota Pengikut #{{ idx + 1 }}</span>
                                <button type="button" @click="removeMemberRow(idx)" class="text-rose-600 hover:underline text-[10px]">Hapus Baris</button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <input type="text" v-model="member.nama" required placeholder="Nama Lengkap"class="w-full text-xs px-3 py-2 rounded-xl border-slate-300 focus:ring-emerald-500" />
                                </div>
                                <div>
                                    <input type="text" v-model="member.pangkat_nrp_nik" required placeholder="NRP / NIK"class="w-full text-xs px-3 py-2 rounded-xl border-slate-300 focus:ring-emerald-500" />
                                </div>
                                <div>
                                    <input type="text" v-model="member.jabatan" required placeholder="Jabatan / Pekerjaan"class="w-full text-xs px-3 py-2 rounded-xl border-slate-300 focus:ring-emerald-500" />
                                </div>
                            </div>
                        </div>

                        <button type="button" @click="addMemberRow" class="px-4 py-2 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-xs font-bold uppercase rounded-xl transition-all">
                            + Tambah Anggota Pengikut
                        </button>
                    </div>
                </div>

                <!-- Point 4: Peruntukan SKHPP -->
                <div class="bg-white p-6 rounded-3xl shadow-xs border border-slate-100 space-y-4">
                    <h2 class="text-xs font-black uppercase tracking-wider text-emerald-600 border-b pb-2">5. Peruntukan / Maksud Pemberian SKHPP (Point 4)</h2>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Maksud & Peruntukan *</label>
                        <textarea v-model="form.peruntukan" rows="3" required
                            placeholder="Contoh: Dalam rangka sebagai persyaratan mengikuti program pendidikan..."class="w-full text-xs font-medium px-4 py-3 rounded-2xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                    </div>
                </div>

                <!-- Unggahan Foto Pas Foto -->
                <div class="bg-white p-6 rounded-3xl shadow-xs border border-slate-100 space-y-4">
                    <h2 class="text-xs font-black uppercase tracking-wider text-emerald-600 border-b pb-2">6. Unggahan Pas Foto (Opsional Jika Tidak Diubah)</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                                {{ form.is_pernikahan ? 'Foto 1: Calon Suami' : 'Pas Foto Utama Personel (3x4)' }}
                            </label>
                            <input type="file" @change="handleFoto1Change" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100" />

                            <div v-if="preview1" class="mt-3">
                                <img :src="preview1" class="w-32 h-40 object-cover rounded-xl border border-slate-200 shadow-md" />
                            </div>
                        </div>

                        <div v-if="form.is_pernikahan">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2"> Foto 2: Calon Istri
                            </label>
                            <input type="file" @change="handleFoto2Change" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100" />

                            <div v-if="preview2" class="mt-3">
                                <img :src="preview2" class="w-32 h-40 object-cover rounded-xl border border-slate-200 shadow-md" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end gap-4 pt-4">
                    <Link :href="route('skhpp.index')" class="px-6 py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold uppercase rounded-2xl transition-all"> Batal
                    </Link>
                    <button type="submit" :disabled="form.processing"class="px-8 py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black uppercase tracking-wider rounded-2xl shadow-lg shadow-emerald-600/20 transition-all active:scale-95 disabled:opacity-50"> Simpan Perubahan & Ajukan Ulang Ke Komandan
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
