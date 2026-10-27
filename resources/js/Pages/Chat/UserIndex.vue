<template>
  <AuthenticatedLayout>
    <template #header-title>Pusat Komunikasi Dinas &amp; VC</template>

    <div class="max-w-5xl mx-auto px-2 sm:px-6 lg:px-8 py-3 sm:py-6 font-sans space-y-3 sm:space-y-5">
      <!-- 1. Banner Header Militer & Status Sesi -->
      <div class="bg-gradient-to-br from-slate-900 via-slate-800 to-blue-950 rounded-2xl sm:rounded-3xl p-4 sm:p-7 text-white shadow-xl relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-blue-600/20 blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-3 sm:gap-4">
          <div class="space-y-1.5 sm:space-y-2">
            <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
              <span class="px-2 py-0.5 rounded text-[9px] sm:text-[10px] font-extrabold uppercase tracking-widest bg-blue-500/20 border border-blue-400/30 text-blue-300">
                SISTEM INFORMASI DENINTEL Koarmada 2
              </span>

              <!-- Status Indikator Sesi / Respon -->
              <span 
                v-if="thread?.status === 'CLOSED'"
                class="inline-flex items-center gap-1 px-2.5 py-0.5 sm:px-3 sm:py-1 rounded-xl text-[10px] sm:text-xs font-bold bg-slate-500/30 border border-slate-400/30 text-slate-300"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                Sesi Ditutup
              </span>
              <span 
                v-else-if="isAwaitingResponse"
                class="inline-flex items-center gap-1 px-2.5 py-0.5 sm:px-3 sm:py-1 rounded-xl text-[10px] sm:text-xs font-bold bg-amber-500/20 border border-amber-400/30 text-amber-300 shadow-sm"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                Menunggu Respon
              </span>
              <span 
                v-else
                class="inline-flex items-center gap-1 px-2.5 py-0.5 sm:px-3 sm:py-1 rounded-xl text-[10px] sm:text-xs font-bold bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 shadow-sm"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="truncate max-w-[200px] sm:max-w-none">Terhubung dengan Operator</span>
              </span>
            </div>

            <h1 class="text-lg sm:text-2xl font-black text-white tracking-tight">
              Pusat Komunikasi Dinas
            </h1>
            <p class="text-[11px] sm:text-xs text-slate-300 max-w-2xl leading-relaxed">
              Saluran komunikasi dinas langsung dengan Pengelola SINDEN Denintel Koarmada 2. Seluruh berkas yang dilampirkan akan terhapus saat Anda mengakhiri sesi percakapan.
            </p>
          </div>

          <!-- Aksi Sesi (Akhiri Sesi / Buka Sesi Baru) -->
          <div class="shrink-0 flex items-center gap-2">
            <button
              v-if="thread?.status === 'OPEN'"
              @click="confirmEndSession"
              :disabled="isEndingSession"
              type="button"
              class="inline-flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-red-600/90 hover:bg-red-600 text-white font-bold text-xs shadow-lg shadow-red-950/30 border border-red-400/30 transition cursor-pointer disabled:opacity-50"
            >
              <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
              </svg>
              <span>{{ isEndingSession ? 'Mengakhiri Sesi...' : 'Akhiri Sesi Percakapan' }}</span>
            </button>

            <button
              v-else
              @click="startNewSession"
              :disabled="isStartingSession"
              type="button"
              class="inline-flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs shadow-lg shadow-blue-950/40 border border-blue-400/30 transition cursor-pointer disabled:opacity-50"
            >
              <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>{{ isStartingSession ? 'Membuka Chat...' : 'Mulai Chat Baru' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- 2. Kotak Percakapan Standalone -->
      <div class="bg-white rounded-2xl sm:rounded-3xl border border-slate-200 shadow-sm overflow-hidden flex flex-col h-[calc(100dvh-10.5rem)] sm:h-[calc(100vh-14rem)] min-h-[460px] sm:min-h-[580px] max-h-[850px]">
        
        <!-- Header Kotak Obrolan -->
        <div class="px-3.5 sm:px-6 py-2.5 sm:py-4 bg-slate-50/90 border-b border-slate-200 flex items-center justify-between shrink-0 gap-2">
          <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
            <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl sm:rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center font-black text-sm border border-blue-200 select-none shrink-0">
              <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
              </svg>
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-1.5 sm:gap-2">
                <h3 class="text-xs sm:text-sm font-bold text-slate-900 truncate">Pusat Komunikasi Dinas</h3>
                <span 
                  v-if="thread?.status === 'CLOSED'"
                  class="text-[9px] sm:text-[10px] font-extrabold uppercase px-1.5 sm:px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200 shrink-0"
                >
                  Sesi Ditutup
                </span>
                <span 
                  v-else-if="isAwaitingResponse"
                  class="text-[9px] sm:text-[10px] font-extrabold uppercase px-1.5 sm:px-2 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200 animate-pulse shrink-0"
                >
                  Menunggu Respon
                </span>
                <span 
                  v-else
                  class="text-[9px] sm:text-[10px] font-extrabold uppercase px-1.5 sm:px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 shrink-0"
                >
                  Terhubung
                </span>
              </div>
              <p class="text-[10px] sm:text-[11px] text-slate-600 font-medium flex items-center gap-1.5 sm:gap-2 mt-0.5 truncate">
                <span class="truncate"><strong class="text-slate-800">{{ currentUser?.pangkat ? currentUser.pangkat + ' ' : '' }}{{ currentUser?.name }}</strong> <span class="text-slate-500 hidden sm:inline">(NRP: {{ currentUser?.nrp || '-' }})</span></span>
                <span class="inline-flex items-center gap-1 text-[9px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.2 rounded-full shrink-0">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                  Online
                </span>
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            <!-- Tombol Mulai Panggilan Video Dinas (Vicon) -->
            <button 
              v-if="thread?.status === 'OPEN'"
              @click="openPersonelVideoCall"
              type="button" 
              class="px-2.5 sm:px-3 py-1.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
              title="Mulai Panggilan Video Dinas"
            >
              <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
              <span class="hidden sm:inline">Panggilan Video</span>
            </button>

            <div class="text-right hidden md:block">
              <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider block">Batas Lampiran</span>
              <span class="text-xs font-bold text-slate-700">Maks. 15 MB per Unggahan</span>
            </div>
          </div>
        </div>

        <!-- Banner Sub-Header: Status Interaktif Percakapan & Respon -->
        <div 
          v-if="thread?.status === 'OPEN'"
          :class="isOperatorTyping ? 'bg-emerald-100/90 border-b border-emerald-300/80 text-emerald-950' : (isAwaitingResponse ? 'bg-amber-50/80 border-b border-amber-200/60 text-amber-900' : 'bg-emerald-50/80 border-b border-emerald-200/60 text-emerald-900')"
          class="px-6 py-2.5 flex items-center justify-between text-xs transition shrink-0"
        >
          <div class="flex items-center gap-2 font-bold">
            <span 
              :class="isOperatorTyping ? 'bg-emerald-600 animate-ping' : (isAwaitingResponse ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500')"
              class="w-2 h-2 rounded-full shrink-0"
            ></span>
            <span v-if="isOperatorTyping" class="text-emerald-800 animate-pulse">
              {{ operatorTypingName }} sedang mengetik...
            </span>
            <span v-else-if="isAwaitingResponse">
              Menunggu Respon dari Petugas Dinas...
            </span>
            <span v-else>
              Anda sedang terhubung dengan Operator Dinas SINDEN
            </span>
          </div>
          <span class="text-[10px] font-semibold text-slate-500 hidden md:inline">
            Seluruh berkas yang dilampirkan akan terhapus saat Anda mengakhiri sesi percakapan.
          </span>
        </div>

        <!-- Wadah Daftar Pesan -->
        <div 
          ref="chatScrollContainer"
          class="flex-1 min-h-0 overflow-y-auto p-4 sm:p-6 space-y-4 bg-slate-50/40 scroll-smooth"
        >
          <!-- Pesan Sambutan Sistem -->
          <div class="bg-blue-50/70 border border-blue-200/80 rounded-2xl p-4 text-xs text-blue-900 space-y-1.5 shadow-xs">
            <div class="flex items-center gap-2 font-bold text-blue-800">
              <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>Instruksi Pusat Komunikasi Dinas</span>
            </div>
            <p class="text-slate-600 leading-relaxed text-[11px]">
              Gunakan saluran ini untuk koordinasi kedinasan, konsultasi administrasi, permohonan bantuan teknis SINDEN, atau panggilan video langsung dengan operator. Seluruh berkas lampiran akan terhapus secara otomatis saat sesi diakhiri.
            </p>
          </div>

          <!-- Belum ada riwayat pesan -->
          <div v-if="messagesList.length === 0" class="py-16 text-center space-y-3">
            <div class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
              </svg>
            </div>
            <p class="text-xs text-slate-500 font-medium">
              Belum ada pesan dalam sesi ini. Silakan ketik pesan Anda pada kolom di bawah.
            </p>
          </div>

          <!-- Daftar Pesan Masuk & Keluar -->
          <template v-for="msg in messagesList" :key="msg.id">
            <!-- Pesan Sistem (Tengah) -->
            <div v-if="msg.sender_type === 'SYSTEM'" class="flex justify-center my-2">
              <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-200/80 border border-slate-300 text-slate-700 text-[11px] font-semibold shadow-2xs">
                <svg class="w-3.5 h-3.5 text-blue-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                <span>{{ msg.message }}</span>
                <span class="text-[10px] text-slate-500">&bull; {{ formatTime(msg.created_at) }}</span>
              </div>
            </div>

            <!-- Pesan Keluar dari Pengguna (Kanan) -->
            <div v-else-if="msg.sender_type === 'USER'" class="flex justify-end">
              <div class="max-w-[85%] sm:max-w-md space-y-1 text-right">
                <div class="flex items-center justify-end gap-2 text-[10px] text-slate-400">
                  <span>{{ formatTime(msg.created_at) }}</span>
                  <span class="font-bold text-slate-700">Anda</span>
                </div>
                <div class="bg-blue-600 text-white text-xs p-3.5 rounded-2xl rounded-tr-xs shadow-sm text-left leading-relaxed">
                  <p v-if="msg.message" class="whitespace-pre-wrap">{{ msg.message }}</p>

                  <!-- Lampiran Pengguna -->
                  <div v-if="msg.attachments && msg.attachments.length > 0" class="mt-2.5 pt-2 border-t border-blue-500/50 space-y-1.5">
                    <p class="text-[10px] font-bold text-blue-200 uppercase tracking-wider">Lampiran Berkas ({{ msg.attachments.length }}):</p>
                    <div 
                      v-for="(att, idx) in msg.attachments" 
                      :key="idx" 
                      class="flex items-center justify-between gap-2 p-2 rounded-xl bg-blue-700/60 border border-blue-400/40 text-[11px]"
                    >
                      <div class="flex items-center gap-2 truncate min-w-0">
                        <svg class="w-4 h-4 text-blue-200 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                        </svg>
                        <span class="truncate text-white font-medium">{{ att.original_name }}</span>
                        <span class="text-[9px] text-blue-200 shrink-0">({{ formatBytes(att.size) }})</span>
                      </div>
                      <span v-if="att.purged || !att.file_path" class="shrink-0 text-amber-200 text-[10px] font-semibold italic">
                        Berkas Terhapus
                      </span>
                      <a 
                        v-else
                        :href="`/live-chat/attachment/download?path=${encodeURIComponent(att.file_path)}&name=${encodeURIComponent(att.original_name)}`" 
                        target="_blank" 
                        class="shrink-0 text-blue-100 hover:text-white font-bold underline text-[10px]"
                      >
                        Unduh
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Pesan Masuk dari Operator / Admin (Kiri) -->
            <div v-else class="flex gap-3 max-w-[85%] sm:max-w-md">
              <div class="w-8 h-8 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0 text-xs font-bold shadow-xs">
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
              </div>
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <span class="text-xs font-bold text-slate-800">{{ msg.sender_name }}</span>
                  <span class="text-[10px] text-slate-400">{{ formatTime(msg.created_at) }}</span>
                </div>
                <div class="bg-white border border-slate-200 text-slate-800 text-xs p-3.5 rounded-2xl rounded-tl-xs shadow-xs leading-relaxed">
                  <p v-if="msg.message" class="whitespace-pre-wrap">{{ msg.message }}</p>

                  <!-- Lampiran Petugas -->
                  <div v-if="msg.attachments && msg.attachments.length > 0" class="mt-2.5 pt-2 border-t border-slate-100 space-y-1.5">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Lampiran Berkas ({{ msg.attachments.length }}):</p>
                    <div 
                      v-for="(att, idx) in msg.attachments" 
                      :key="idx" 
                      class="flex items-center justify-between gap-2 p-2 rounded-xl bg-slate-50 border border-slate-200 text-[11px]"
                    >
                      <div class="flex items-center gap-2 truncate min-w-0">
                        <svg class="w-4 h-4 text-slate-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                        </svg>
                        <span class="truncate text-slate-700 font-medium">{{ att.original_name }}</span>
                        <span class="text-[9px] text-slate-400 shrink-0">({{ formatBytes(att.size) }})</span>
                      </div>
                      <span v-if="att.purged || !att.file_path" class="shrink-0 text-slate-400 text-[10px] font-semibold italic">
                        Berkas Terhapus
                      </span>
                      <a 
                        v-else
                        :href="`/live-chat/attachment/download?path=${encodeURIComponent(att.file_path)}&name=${encodeURIComponent(att.original_name)}`" 
                        target="_blank" 
                        class="shrink-0 text-blue-600 hover:text-blue-800 font-bold underline text-[10px]"
                      >
                        Unduh
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- Animasi Indikator Operator Sedang Mengetik -->
          <div v-if="isOperatorTyping" class="flex items-end gap-2.5 max-w-[85%] transition-all">
            <div class="w-8 h-8 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0 text-xs font-bold shadow-xs">
              <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <div class="space-y-1">
              <div class="flex items-center gap-1.5 text-[10px] text-slate-500 font-semibold">
                <span>{{ operatorTypingName }}</span>
              </div>
              <div class="bg-white border border-slate-200 text-slate-800 text-xs px-4 py-2.5 rounded-2xl rounded-tl-xs shadow-xs flex items-center gap-2.5">
                <div class="flex items-center gap-1 py-1">
                  <span class="w-2 h-2 rounded-full bg-blue-600 animate-bounce [animation-delay:-0.3s]"></span>
                  <span class="w-2 h-2 rounded-full bg-blue-600 animate-bounce [animation-delay:-0.15s]"></span>
                  <span class="w-2 h-2 rounded-full bg-blue-600 animate-bounce"></span>
                </div>
                <span class="text-[11px] font-semibold text-slate-600 italic">sedang mengetik...</span>
              </div>
            </div>
          </div>
        </div>

        <!-- 3. Bilah Input Pesan (Bawah) -->
        <div v-if="thread?.status === 'OPEN'" class="p-3 sm:p-4 bg-white border-t border-slate-200 shrink-0 space-y-2">
          <!-- Pratinjau Berkas Terpilih -->
          <div v-if="stagedFiles.length > 0" class="flex flex-wrap gap-2 pt-1 pb-2">
            <div 
              v-for="(f, idx) in stagedFiles" 
              :key="idx" 
              class="flex items-center gap-2 bg-slate-100 border border-slate-200 rounded-xl px-2.5 py-1 text-xs text-slate-700"
            >
              <svg class="w-3.5 h-3.5 text-slate-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
              </svg>
              <span class="max-w-[150px] truncate font-medium">{{ f.name }}</span>
              <span class="text-[10px] text-slate-400">({{ formatBytes(f.size) }})</span>
              <button 
                @click="removeStagedFile(idx)" 
                type="button" 
                class="text-red-500 hover:text-red-700 font-bold ml-1 cursor-pointer"
              >
                &times;
              </button>
            </div>
          </div>

          <div class="flex items-end gap-2">
            <!-- Tombol Unggah Lampiran -->
            <input 
              ref="fileInputRef" 
              type="file" 
              multiple 
              accept=".png,.jpg,.jpeg,.pdf,.doc,.docx,.xls,.xlsx" 
              class="hidden" 
              @change="handleFileChange" 
            />
            <button 
              @click="fileInputRef.click()" 
              type="button" 
              class="p-2.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-600 transition cursor-pointer shrink-0"
              title="Lampirkan Dokumen (PDF, Word, Excel, Gambar)"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
              </svg>
            </button>

            <!-- Kolom Teks Pesan -->
            <div class="flex-1 min-w-0">
              <textarea 
                v-model="inputMessage" 
                @input="onTypingInput"
                @keydown.enter.prevent="sendMessage"
                rows="1" 
                placeholder="Tuliskan pesan koordinasi dinas... (Tekan Enter untuk kirim)" 
                class="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-600 resize-none max-h-28 leading-relaxed"
              ></textarea>
            </div>

            <!-- Tombol Kirim Pesan -->
            <button 
              @click="sendMessage" 
              :disabled="isSending || (!inputMessage.trim() && stagedFiles.length === 0)"
              type="button" 
              class="p-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-xl shadow-xs transition cursor-pointer shrink-0 flex items-center justify-center"
              title="Kirim Pesan"
            >
              <svg v-if="isSending" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <svg v-else class="w-5 h-5 rotate-90" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Banner Jika Sesi Ditutup -->
        <div v-else class="p-5 bg-slate-100 border-t border-slate-200 text-center space-y-3 shrink-0">
          <div class="flex items-center justify-center gap-2 text-xs font-bold text-slate-700">
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            <span>Sesi percakapan ini telah berakhir. Seluruh berkas lampiran telah terhapus otomatis demi keamanan dinas.</span>
          </div>

          <button
            @click="startNewSession"
            :disabled="isStartingSession"
            type="button"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md shadow-blue-600/20 transition cursor-pointer disabled:opacity-50"
          >
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>{{ isStartingSession ? 'Membuka Chat...' : 'Mulai Chat Baru' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Komponen Ruang Panggilan Video Dinas (Vicon) -->
    <VideoCallModal 
      :show="showPersonelVideoCallModal"
      :thread-uuid="activeIncomingCallData?.thread_uuid || thread?.uuid"
      user-role="USER"
      :current-user="currentUserInfo"
      :partner-user="callPartnerInfo"
      :incoming-call-data="activeIncomingCallData"
      url-prefix=""
      @close="closePersonelVideoCallModal"
      @call-ended="onPersonelCallEnded"
      @call-accepted="onPersonelCallAccepted"
    />
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import VideoCallModal from '@/Components/Chat/VideoCallModal.vue';
import { playNotificationSound } from '@/Utils/sound';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  thread: Object,
  messages: Array,
  initialCall: Object,
  currentUser: Object,
});

const showPersonelVideoCallModal = ref(Boolean(props.initialCall));
const activeIncomingCallData = ref(props.initialCall || null);

const currentUserInfo = computed(() => ({
  id: props.currentUser?.id,
  name: props.currentUser?.name,
  pangkat: props.currentUser?.pangkat,
  avatar: props.currentUser?.avatar,
}));

const callPartnerInfo = computed(() => {
  if (activeIncomingCallData.value?.caller) {
    return {
      id: activeIncomingCallData.value.caller.id,
      name: activeIncomingCallData.value.caller.name,
      pangkat: activeIncomingCallData.value.caller.pangkat,
      avatar: activeIncomingCallData.value.caller.avatar,
      is_online: true,
    };
  }
  return {
    id: null,
    name: 'Operator Dinas SINDEN',
    pangkat: 'Pengelola Dinas',
    avatar: null,
    is_online: true,
  };
});

const openPersonelVideoCall = () => {
  if (!thread.value || thread.value.status !== 'OPEN') return;
  activeIncomingCallData.value = null;
  showPersonelVideoCallModal.value = true;
};

const closePersonelVideoCallModal = () => {
  showPersonelVideoCallModal.value = false;
  activeIncomingCallData.value = null;
};

const onPersonelCallEnded = () => {
  if (thread.value?.uuid) {
    axios.get(`/live-chat/${thread.value.uuid}/messages`).then((res) => {
      if (res.data.messages) {
        messagesList.value = res.data.messages;
        scrollToBottom();
      }
    });
  }
};

const onPersonelCallAccepted = () => {};

const thread = ref(props.thread || null);
const messagesList = ref(props.messages || []);
const inputMessage = ref('');
const stagedFiles = ref([]);
const isSending = ref(false);
const isEndingSession = ref(false);
const isStartingSession = ref(false);

const chatScrollContainer = ref(null);
const fileInputRef = ref(null);

const isOperatorTyping = ref(false);
const operatorTypingName = ref('');
let operatorTypingTimer = null;
let lastTypingSentAt = 0;

const updateOperatorTypingStatus = (typing) => {
  if (typing) {
    isOperatorTyping.value = true;
    operatorTypingName.value = 'Operator Dinas';
    if (operatorTypingTimer) clearTimeout(operatorTypingTimer);
    operatorTypingTimer = setTimeout(() => {
      isOperatorTyping.value = false;
    }, 4500);
  } else {
    isOperatorTyping.value = false;
    if (operatorTypingTimer) {
      clearTimeout(operatorTypingTimer);
      operatorTypingTimer = null;
    }
  }
};

const onTypingInput = () => {
  const now = Date.now();
  if (now - lastTypingSentAt > 2000 && thread.value?.uuid) {
    lastTypingSentAt = now;
    axios.post(`/live-chat/${thread.value.uuid}/typing`).catch(() => {});
  }
};

const isAwaitingResponse = computed(() => {
  if (!messagesList.value.length) return false;
  const last = messagesList.value[messagesList.value.length - 1];
  return last.sender_type === 'USER';
});

const maxAllowedBytes = 15 * 1024 * 1024;

const formatBytes = (bytes) => {
  if (!bytes || bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
};

const formatTime = (isoString) => {
  if (!isoString) return '';
  const d = new Date(isoString);
  const h = String(d.getHours()).padStart(2, '0');
  const m = String(d.getMinutes()).padStart(2, '0');
  return `${h}:${m} WIB`;
};

const scrollToBottom = async () => {
  await nextTick();
  if (chatScrollContainer.value) {
    chatScrollContainer.value.scrollTop = chatScrollContainer.value.scrollHeight;
  }
};

const handleFileChange = (e) => {
  const selected = Array.from(e.target.files || []);
  if (!selected.length) return;
  stagedFiles.value.push(...selected);
  if (fileInputRef.value) {
    fileInputRef.value.value = '';
  }
};

const removeStagedFile = (idx) => {
  stagedFiles.value.splice(idx, 1);
};

const sendMessage = async () => {
  if (isSending.value || !thread.value) return;
  const msgText = inputMessage.value.trim();
  if (!msgText && stagedFiles.value.length === 0) return;

  const totalBytes = stagedFiles.value.reduce((sum, f) => sum + (f.size || 0), 0);
  if (totalBytes > maxAllowedBytes) {
    Swal.fire({
      icon: 'error',
      title: 'Ukuran Terlalu Besar',
      text: 'Total seluruh lampiran berkas tidak boleh melebihi 15 MB.',
      confirmButtonColor: '#2563EB',
      customClass: { popup: 'rounded-2xl' },
    });
    return;
  }

  isSending.value = true;
  const formData = new FormData();
  if (msgText) {
    formData.append('message', msgText);
  }
  stagedFiles.value.forEach((f) => {
    formData.append('attachments[]', f);
  });

  try {
    const res = await axios.post(`/live-chat/${thread.value.uuid}/send`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    if (res.data.message) {
      messagesList.value.push(res.data.message);
      inputMessage.value = '';
      stagedFiles.value = [];
      scrollToBottom();
    }
  } catch (err) {
    const errMsg = err.response?.data?.error || err.response?.data?.message || 'Gagal mengirimkan pesan dinas.';
    Swal.fire({
      icon: 'error',
      title: 'Pesan Gagal Terkirim',
      text: errMsg,
      confirmButtonColor: '#2563EB',
      customClass: { popup: 'rounded-2xl' },
    });
  } finally {
    isSending.value = false;
  }
};

const confirmEndSession = () => {
  Swal.fire({
    icon: 'warning',
    title: 'Akhiri Sesi Percakapan?',
    text: 'Seluruh berkas lampiran yang pernah Anda atau operator kirimkan pada sesi ini akan otomatis dihapus permanen dari server demi kerahasiaan dinas.',
    showCancelButton: true,
    confirmButtonText: 'Ya, Akhiri Sesi & Hapus Berkas',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#DC2626',
    cancelButtonColor: '#64748B',
    customClass: { popup: 'rounded-2xl', confirmButton: 'rounded-xl', cancelButton: 'rounded-xl' },
  }).then((result) => {
    if (result.isConfirmed) {
      executeEndSession();
    }
  });
};

const executeEndSession = async () => {
  if (!thread.value) return;
  isEndingSession.value = true;

  try {
    const res = await axios.post(`/live-chat/${thread.value.uuid}/status`);
    if (res.data.success) {
      if (thread.value) {
        thread.value.status = 'CLOSED';
      }
      messagesList.value.forEach((msg) => {
        if (msg.attachments) {
          msg.attachments.forEach((att) => {
            att.purged = true;
            att.file_path = null;
          });
        }
      });

      Swal.fire({
        icon: 'success',
        title: 'Sesi Selesai',
        text: 'Sesi percakapan ditutup. Berkas telah dibersihkan secara aman dari server.',
        confirmButtonColor: '#2563EB',
        customClass: { popup: 'rounded-2xl' },
      });
    }
  } catch (err) {
    Swal.fire({
      icon: 'error',
      title: 'Terjadi Hambatan',
      text: err.response?.data?.error || 'Gagal mengakhiri sesi percakapan dinas.',
      confirmButtonColor: '#2563EB',
      customClass: { popup: 'rounded-2xl' },
    });
  } finally {
    isEndingSession.value = false;
  }
};

const startNewSession = async () => {
  isStartingSession.value = true;
  try {
    const res = await axios.post('/live-chat/start', {
      user_id: props.currentUser?.id,
      message: '',
    });

    if (res.data.success) {
      router.visit(window.location.pathname, {
        preserveScroll: true,
      });
    }
  } catch (err) {
    Swal.fire({
      icon: 'error',
      title: 'Hambatan Buka Sesi',
      text: 'Gagal membuat sesi obrolan baru. Silakan coba kembali.',
      confirmButtonColor: '#2563EB',
      customClass: { popup: 'rounded-2xl' },
    });
  } finally {
    isStartingSession.value = false;
  }
};

let userPollingTimer = null;

const pollUserSync = async () => {
  if (!thread.value?.uuid) return;

  try {
    const res = await axios.get('/live-chat/sync');

    if (res.data.thread) {
      thread.value.status = res.data.thread.status;
    }

    if (res.data.messages) {
      const prevLength = messagesList.value.length;
      messagesList.value = res.data.messages;

      if (res.data.messages.length > prevLength) {
        const newMsg = res.data.messages[res.data.messages.length - 1];
        if (newMsg?.sender_type !== 'USER') {
          playNotificationSound();
        }
        scrollToBottom();
      }
    }

    if (res.data.is_typing !== undefined) {
      updateOperatorTypingStatus(res.data.is_typing);
    }

    // Deteksi panggilan masuk
    if (res.data.incoming_call && ['RINGING', 'ACCEPTED', 'CONNECTING', 'CONNECTED'].includes(res.data.incoming_call.status)) {
      if (!showPersonelVideoCallModal.value) {
        activeIncomingCallData.value = res.data.incoming_call;
        showPersonelVideoCallModal.value = true;
      }
    } else if (res.data.call) {
      const activeCall = res.data.call;
      if (['RINGING', 'ACCEPTED', 'CONNECTING', 'CONNECTED'].includes(activeCall.status) && !showPersonelVideoCallModal.value) {
        activeIncomingCallData.value = activeCall;
        showPersonelVideoCallModal.value = true;
      } else if (['ENDED', 'REJECTED'].includes(activeCall.status) && showPersonelVideoCallModal.value) {
        showPersonelVideoCallModal.value = false;
        activeIncomingCallData.value = null;
      }
    }
  } catch (err) {
    console.debug('Penyelarasan pesan personel terhambat:', err);
  }
};

const startUserPolling = () => {
  stopUserPolling();
  userPollingTimer = setInterval(() => {
    pollUserSync();
  }, 2500);
};

const stopUserPolling = () => {
  if (userPollingTimer) {
    clearInterval(userPollingTimer);
    userPollingTimer = null;
  }
};

onMounted(() => {
  scrollToBottom();
  startUserPolling();
});

onUnmounted(() => {
  stopUserPolling();
  if (operatorTypingTimer) clearTimeout(operatorTypingTimer);
});
</script>
