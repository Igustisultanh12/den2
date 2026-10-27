<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
  doc: Object,
  verify_code: String,
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
  <Head :title="`Verifikasi Legalitas TTE Berkas Dinas - ${doc?.document_title || doc?.subject || 'SINDEN'}`" />

  <div 
    class="min-h-screen flex text-slate-800 font-sans bg-cover bg-center relative transition-all duration-300 bg-slate-950"
    :style="loginBg ? { backgroundImage: `url(${loginBg})` } : { backgroundColor: '#020617' }"
  >
    <!-- Dark overlay when background image is present -->
    <div class="absolute inset-0 bg-slate-950/85 backdrop-blur-[3px] z-0"></div>

    <!-- Main Wrapper -->
    <div class="relative z-10 w-full min-h-screen flex flex-col lg:flex-row">
      
      <!-- Bagian Kiri: Logo & Informasi Aplikasi Kedinasan (Desktop) -->
      <div class="hidden lg:flex lg:w-1/2 flex-col justify-between p-12 lg:p-16 text-white z-10">
        <div></div>
        
        <!-- Live Preview Logo dengan Efek Kedinasan Mentul-Mentul -->
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
              Portal Otentikasi & Verifikasi Keabsahan Tanda Tangan Elektronik (TTE) Berkas Naskah Dinas Komando Armada II.
            </p>
          </div>
        </div>

        <p class="text-xs text-slate-400 text-center font-medium">
          <AppCopyright />
        </p>
      </div>

      <!-- Bagian Kanan: Card Form Verifikasi Dokumen Naskah Dinas Publik -->
      <div class="w-full lg:w-1/2 flex items-center justify-center p-4 sm:p-8 z-10 my-auto">
        <div 
          class="w-full max-w-[640px] p-6 sm:p-8 rounded-2xl shadow-2xl transition-all duration-300 space-y-5 bg-slate-900/90 backdrop-blur-md border border-white/10 text-white animate-float-card"
        >
          <!-- Header logo di mobile -->
          <div class="lg:hidden flex flex-col items-center justify-center gap-2 mb-2 text-center animate-float-slow">
            <img v-if="configuredLogo" :src="configuredLogo" class="h-16 object-contain drop-shadow-md" />
            <h1 class="text-sm font-black uppercase text-white tracking-wider">{{ appName }} VERIFIKASI TTE BERKAS DINAS</h1>
          </div>

          <!-- KONDISI 1: BERKAS DINAS RESMI TERVERIFIKASI & SAH -->
          <div v-if="doc && doc.is_valid" class="space-y-5">
            
            <!-- BANNER STATUS SAH DENGAN ICON SVG CENTANG HIJAU MANDIRI -->
            <div class="p-4 rounded-2xl text-center space-y-1.5 border shadow-xs bg-emerald-500/20 border-emerald-400/40 text-emerald-200">
              <div class="w-12 h-12 bg-emerald-500 text-white rounded-full flex items-center justify-center mx-auto shadow-md shadow-emerald-500/30">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 stroke-current" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </div>
              <h3 class="text-base font-extrabold tracking-tight uppercase text-emerald-300">TTE BERKAS DINAS TERVERIFIKASI & SAH</h3>
              <p class="text-xs leading-relaxed opacity-90">
                Dokumen Naskah Dinas Ini Sah Ditandatangani oleh Komandan Detasemen Intelijen Koarmada 2 secara Digital (TTE).
              </p>
            </div>

            <!-- DETAIL HASIL VERIFIKASI -->
            <div class="space-y-3.5">
              <!-- Manifes Kode Unik Berkas (Bukan NRP) -->
              <div class="flex items-center justify-between border-b border-white/10 pb-2.5">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-300">
                  Kode Unik Berkas Dinas
                </span>
                <span class="font-mono text-xs font-bold px-2.5 py-0.5 rounded border bg-blue-500/20 border-blue-400/30 text-cyan-300">
                  {{ doc.verification_code }}
                </span>
              </div>

              <!-- Grid 2 Kolom Menyamping -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                
                <!-- 1: Judul Dokumen -->
                <div class="p-3 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Judul / Perihal Naskah</span>
                  <p class="text-xs font-bold text-white uppercase leading-snug truncate" :title="doc.document_title || doc.subject">
                    {{ doc.document_title || doc.subject }}
                  </p>
                  <p class="text-[9px] font-bold text-cyan-400 uppercase">DOKUMEN RESMI DINAS</p>
                </div>

                <!-- 2: Nomor Registrasi Surat -->
                <div class="p-3 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Nomor Registrasi Surat</span>
                  <p class="text-xs font-mono font-bold text-orange-400 truncate" :title="doc.letter_number">
                    {{ doc.letter_number }}
                  </p>
                </div>

                <!-- 3: Nama Pengaju / Pembuat -->
                <div class="p-3 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Nama Personel (Pengaju)</span>
                  <p class="text-xs font-bold text-emerald-300 truncate" :title="doc.nama">
                    {{ doc.nama }}
                  </p>
                </div>

                <!-- 4: Identitas Pangkat / NRP (Real Database) -->
                <div class="p-3 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pangkat & NRP Personel</span>
                  <p class="text-xs font-mono font-bold text-blue-300 truncate" :title="doc.pangkat_korps_nrp">
                    {{ doc.pangkat_korps_nrp }}
                  </p>
                </div>

                <!-- 5: Jabatan Personel -->
                <div class="p-3 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Jabatan / Satuan</span>
                  <p class="text-xs font-semibold text-slate-200 truncate" :title="doc.jabatan_pekerjaan">
                    {{ doc.jabatan_pekerjaan }}
                  </p>
                </div>

                <!-- 6: Waktu Pengesahan TTE -->
                <div class="p-3 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Waktu Pengesahan TTE</span>
                  <p class="text-xs font-semibold text-slate-200">
                    {{ formatDateIndo(doc.tanggal_dokumen) }}
                  </p>
                </div>

                <!-- 7: Peruntukan / Keterangan Berkas -->
                <div class="sm:col-span-2 p-3 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Perihal / Keterangan Berkas</span>
                  <p class="text-xs font-medium text-slate-300 leading-relaxed">
                    {{ doc.peruntukan || doc.subject }}
                  </p>
                </div>

                <!-- 8: Pejabat Penandatangan TTE Komandan DENGAN ICON SHIELD SVG -->
                <div class="sm:col-span-2 p-3 rounded-xl border border-white/10 bg-white/5 flex items-center justify-between gap-3">
                  <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Pejabat Penandatangan (TTE)</span>
                    <p class="text-xs font-bold text-white">{{ doc.signer_name }}</p>
                    <p class="text-[10px] text-slate-300">{{ doc.signer_title }}</p>
                  </div>
                  <div class="text-emerald-400 shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 stroke-current" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                  </div>
                </div>

                <!-- 9: SHA-256 Hash Kriptografi -->
                <div class="sm:col-span-2 p-2.5 rounded-xl border border-white/10 bg-black/40 space-y-0.5 font-mono text-[9px]">
                  <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block">Digital Certificate Hash (SHA-256):</span>
                  <p class="text-slate-300 break-all">{{ doc.sha256_hash }}</p>
                </div>

              </div>

              <!-- Jaminan Keaslian -->
              <div class="p-3.5 rounded-xl text-xs leading-relaxed border border-cyan-400/30 bg-cyan-500/10 text-cyan-200 space-y-1">
                <div class="flex items-center gap-1.5">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 stroke-current text-cyan-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                  </svg>
                  <strong class="font-bold text-[11px] text-cyan-300">Jaminan Keaslian Naskah Kedinasan:</strong>
                </div>
                <p class="text-[10px] opacity-90 leading-relaxed">
                  Jika data pada berkas fisik / cetak berbeda dengan manifes otentikasi di atas, maka naskah dinas tersebut dinyatakan <strong>TIDAK SAH / PALSU</strong>.
                </p>
              </div>
            </div>

          </div>

          <!-- KONDISI 2: KODE BERKAS TIDAK DITEMUKAN / TIDAK VALID -->
          <div v-else class="space-y-4">
            <div class="p-6 rounded-2xl text-center space-y-3 border bg-red-500/20 border-red-400/40 text-red-200">
              <div class="w-12 h-12 bg-red-500 text-white rounded-full flex items-center justify-center mx-auto shadow-md shadow-red-500/30">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 stroke-current" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </div>
              <h3 class="text-lg font-extrabold tracking-tight uppercase text-red-300">TTE BERKAS TIDAK TERDAFTAR</h3>
              <p class="text-xs leading-relaxed">
                Kode verifikasi berkas dinas ({{ verify_code }}) tidak ditemukan pada basis data resmi SINDEN Detasemen Intelijen Koarmada 2 atau berkas belum disahkan secara resmi oleh Komandan.
              </p>
            </div>
          </div>

          <div class="pt-2 border-t border-white/10 flex justify-between items-center text-xs font-bold">
            <Link :href="route('login')" class="text-blue-400 hover:text-blue-300 hover:underline cursor-pointer">
              &larr; Kembali ke Portal SINDEN
            </Link>
            <Link :href="route('skhpp.index')" class="text-slate-400 hover:text-white cursor-pointer">
              Verifikasi SKHPP &rarr;
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