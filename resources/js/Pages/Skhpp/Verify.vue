<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
  skhpp: Object,
  settings: Object
});

const page = usePage();
const pageSettings = computed(() => props.settings || page.props.settings || {});
const appName = computed(() => pageSettings.value.agency_name || pageSettings.value.app_name || 'SINDEN');

const configuredLogo = computed(() => {
  const logo = pageSettings.value.agency_logo || pageSettings.value.logo || pageSettings.value.logo_tni;
  if (!logo) return '/images/logo.png';
  return (logo.startsWith('http') || logo.startsWith('/storage') || logo.startsWith('/images')) ? logo : '/storage/' + logo;
});

const loginBg = computed(() => {
  const bg = pageSettings.value.login_background;
  if (!bg) return null;
  return (bg.startsWith('http') || bg.startsWith('/storage')) ? bg : '/storage/' + bg;
});

const formatDateIndo = (dateStr) => {
  if (!dateStr) return '-';
  return new Date(dateStr).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};
</script>

<template>
  <Head :title="`Verifikasi Legalitas SKHPP - ${skhpp?.nama || 'SINDEN'}`" />

  <div 
    class="min-h-screen flex text-slate-800 font-sans bg-cover bg-center relative transition-all duration-300 bg-slate-950"
    :style="loginBg ? { backgroundImage: `url(${loginBg})` } : { backgroundColor: '#020617' }"
  >
    <!-- Dark overlay when background image is present -->
    <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-[3px] z-0"></div>

    <!-- Main Wrapper -->
    <div class="relative z-10 w-full min-h-screen flex flex-col lg:flex-row">
      
      <!-- Bagian Kiri: Logo & Informasi Aplikasi (Desktop) -->
      <div class="hidden lg:flex lg:w-1/2 flex-col justify-between p-12 lg:p-16 text-white z-10">
        <div></div>
        
        <!-- Live Preview Logo -->
        <div class="my-auto max-w-lg space-y-6 flex flex-col items-center text-center mx-auto">
          <div class="flex justify-center animate-float-slow">
            <img v-if="configuredLogo" :src="configuredLogo" class="h-36 lg:h-44 object-contain drop-shadow-[0_10px_25px_rgba(0,0,0,0.6)]" alt="Logo SINDEN" />
            <div v-else class="w-32 h-32 rounded-3xl bg-blue-600/30 backdrop-blur-md border border-blue-500/40 flex items-center justify-center shadow-2xl">
              <span class="font-black text-white text-5xl">S</span>
            </div>
          </div>
          
          <div class="space-y-3">
            <h2 class="text-3xl font-extrabold tracking-tight uppercase text-white drop-shadow-md">
              {{ appName }} DETASEMEN INTELIJEN
            </h2>
            <p class="text-xs leading-relaxed max-w-md mx-auto text-slate-300 font-medium">
              Portal Otentikasi & Verifikasi Keabsahan Dokumen Resmi Surat Keterangan Hasil Penelitian Personel (SKHPP) KOMANDO ARMADA II.
            </p>
          </div>
        </div>

        <p class="text-xs text-slate-400 text-center font-medium">
          <AppCopyright />
        </p>
      </div>

      <!-- Bagian Kanan: Card Form Verifikasi Dokumen Publik -->
      <div class="w-full lg:w-1/2 flex items-center justify-center p-4 sm:p-8 z-10 my-auto">
        <div 
          class="w-full max-w-[620px] p-6 sm:p-8 rounded-2xl shadow-2xl transition-all duration-300 space-y-5 bg-slate-900/90 backdrop-blur-md border border-white/10 text-white animate-float-card"
        >
          <!-- Header logo di mobile -->
          <div class="lg:hidden flex flex-col items-center justify-center gap-2 mb-2 text-center animate-float-slow">
            <img v-if="configuredLogo" :src="configuredLogo" class="h-16 object-contain drop-shadow-md" />
            <h1 class="text-sm font-black uppercase text-white tracking-wider">{{ appName }} LEGALITAS DIGITAL</h1>
          </div>

          <!-- KONDISI 1: DOKUMEN VALID & TERDAFTAR -->
          <div v-if="skhpp" class="space-y-5">
            
            <!-- BANNER STATUS SAH -->
            <div class="p-4 rounded-2xl text-center space-y-1.5 border shadow-xs bg-emerald-500/20 border-emerald-400/40 text-emerald-200">
              <div class="w-12 h-12 bg-emerald-500 text-white rounded-full flex items-center justify-center mx-auto shadow-md shadow-emerald-500/30">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 stroke-current" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </div>
              <h3 class="text-base font-extrabold tracking-tight uppercase text-emerald-300">DOKUMEN RESMI TERVERIFIKASI & SAH</h3>
              <p class="text-xs leading-relaxed opacity-90">
                Surat Keterangan Hasil Penelitian Personel Ini Sah Ditandatangani Komandan Detasemen Intelijen Koarmada 2 secara Digital.
              </p>
            </div>

            <!-- DETAIL HASIL VERIFIKASI -->
            <div class="space-y-3.5">
              <div class="flex items-center justify-between border-b border-white/10 pb-2.5">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-300">
                  Manifes Otentikasi (Kode Unik)
                </span>
                <span class="font-mono text-xs font-bold px-2.5 py-0.5 rounded border bg-white/10 border-white/20 text-orange-300">
                  {{ skhpp.verification_code }}
                </span>
              </div>

              <!-- Grid 2 Kolom Menyamping -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                
                <!-- 1: Judul Dokumen -->
                <div class="p-3 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Judul Dokumen Resmi</span>
                  <p class="text-xs font-bold text-white uppercase leading-snug">{{ skhpp.document_title || 'SKHPP DENINTEL Koarmada 2' }}</p>
                  <p v-if="skhpp.kategori_personel === 'perusahaan'" class="text-[9px] font-bold text-blue-400 uppercase">MITRA KERJA TNI AL</p>
                </div>

                <!-- 2: Nomor Surat Resmi -->
                <div class="p-3 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Nomor Surat Resmi</span>
                  <p class="text-xs font-mono font-bold text-orange-400 truncate" :title="skhpp.nomor_skhpp">{{ skhpp.nomor_skhpp || 'Draft (Proses TTD)' }}</p>
                </div>

                <!-- 3: Nama Personel -->
                <div class="p-3 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Nama Personel (Subjek)</span>
                  <p class="text-xs font-bold text-emerald-300 truncate" :title="skhpp.nama">{{ skhpp.nama }}</p>
                </div>

                <!-- 4: Identitas NRP / NIK -->
                <div class="p-3 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span v-if="skhpp.pangkat_korps_nrp" class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pangkat / Korps / NRP</span>
                  <span v-else-if="skhpp.nik" class="text-[10px] font-bold uppercase tracking-wider text-slate-400">NIK Identitas</span>
                  <span v-else class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Kategori Personel</span>

                  <p v-if="skhpp.pangkat_korps_nrp" class="text-xs font-mono font-bold text-blue-300 truncate" :title="skhpp.pangkat_korps_nrp">{{ skhpp.pangkat_korps_nrp }}</p>
                  <p v-else-if="skhpp.nik" class="text-xs font-mono font-bold text-white truncate">{{ skhpp.nik }}</p>
                  <p v-else class="text-xs font-bold text-white uppercase">{{ skhpp.kategori_personel ? skhpp.kategori_personel.toUpperCase() : 'SIPIL' }}</p>
                </div>

                <!-- 5: Jabatan / Pekerjaan -->
                <div class="p-3 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Jabatan / Pekerjaan</span>
                  <p class="text-xs font-semibold text-slate-200 truncate" :title="skhpp.jabatan_pekerjaan">{{ skhpp.jabatan_pekerjaan }}</p>
                </div>

                <!-- 6: Tanggal Pengesahan -->
                <div class="p-3 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tanggal Pengesahan</span>
                  <p class="text-xs font-semibold text-slate-200">{{ formatDateIndo(skhpp.tanggal_skhpp || skhpp.approved_at) }}</p>
                </div>

                <!-- 7: Peruntukan (Span 2 Full Width) -->
                <div class="sm:col-span-2 p-3 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Maksud & Peruntukan</span>
                  <p class="text-xs font-medium text-slate-300 leading-relaxed">{{ skhpp.peruntukan }}</p>
                </div>

                <!-- 8: Yang Bertanda Tangan (Span 2 Full Width) DENGAN ICON SHIELD SVG -->
                <div class="sm:col-span-2 p-3 rounded-xl border border-white/10 bg-white/5 flex items-center justify-between gap-3">
                  <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Pejabat Penandatangan (TTE)</span>
                    <p class="text-xs font-bold text-white">{{ skhpp.signer_name || 'Hari Bagio Wijayanto, M.Tr.Opsla.' }}</p>
                    <p class="text-[10px] text-slate-300">{{ skhpp.signer_title || 'Komandan Detasemen Intelijen Koarmada 2 - Kolonel Laut (E) NRP 16085/P' }}</p>
                  </div>
                  <div class="text-emerald-400 shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 stroke-current" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                  </div>
                </div>

              </div>

              <!-- CATATAN JAMINAN KEASLIAN DENGAN ICON SHIELD SVG -->
              <div class="p-3.5 rounded-xl text-xs leading-relaxed border border-blue-400/30 bg-blue-500/10 text-blue-200 space-y-1">
                <div class="flex items-center gap-1.5">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 stroke-current text-blue-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                  </svg>
                  <strong class="font-bold text-[11px] text-blue-300">Catatan Jaminan Keaslian:</strong>
                </div>
                <p class="text-[10px] opacity-90 leading-relaxed">
                  Jika data pada dokumen fisik / lembar cetak berbeda dengan data di atas, maka dokumen tersebut dinyatakan <strong>TIDAK SAH / PALSU</strong>.
                </p>
              </div>
            </div>

          </div>

          <!-- KONDISI 2: KODE DOKUMEN TIDAK DITEMUKAN / TIDAK VALID -->
          <div v-else class="space-y-4">
            <div class="p-6 rounded-2xl text-center space-y-3 border bg-red-500/20 border-red-400/40 text-red-200">
              <div class="w-12 h-12 bg-red-500 text-white rounded-full flex items-center justify-center mx-auto shadow-md shadow-red-500/30">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 stroke-current" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </div>
              <h3 class="text-lg font-extrabold tracking-tight uppercase text-red-300">DOKUMEN TIDAK TERDAFTAR</h3>
              <p class="text-xs leading-relaxed">
                Kode verifikasi tidak ditemukan pada basis data resmi SINDEN Detasemen Intelijen Koarmada 2.
              </p>
            </div>
          </div>

          <div class="pt-2 border-t border-white/10">
            <Link :href="route('login')" class="w-full text-center text-xs font-bold text-blue-400 hover:text-blue-300 hover:underline block cursor-pointer">
              &larr; Kembali ke Portal SINDEN
            </Link>
          </div>

        </div>
      </div>
    </div>
  </div>
</template>

<style>
html, body {
  background-color: #020617 !important;
  margin: 0;
  padding: 0;
  min-height: 100vh;
}

@keyframes floatSlow {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-10px); }
}

@keyframes floatCard {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-6px); }
}

.animate-float-slow {
  animation: floatSlow 6s ease-in-out infinite;
}

.animate-float-card {
  animation: floatCard 5s ease-in-out infinite;
}
</style>