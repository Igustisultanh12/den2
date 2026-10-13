const fs = require('fs');
const path = require('path');

const outDir = __dirname;

// SVG 1: SISINDEN Dashboard (eCommerce UI Kit Style)
const dashboardSvg = `<svg width="430" height="932" viewBox="0 0 430 932" fill="none" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <!-- Background Soft Ice Blue Gradient -->
    <linearGradient id="bgGrad" x1="0" y1="0" x2="430" y2="932" gradientUnits="userSpaceOnUse">
      <stop offset="0%" stop-color="#EFF6FB"/>
      <stop offset="50%" stop-color="#F8FAFC"/>
      <stop offset="100%" stop-color="#F1F5F9"/>
    </linearGradient>

    <!-- Deep Navy Hero Card Gradient -->
    <linearGradient id="heroGrad" x1="0" y1="0" x2="382" y2="200" gradientUnits="userSpaceOnUse">
      <stop offset="0%" stop-color="#0B1B3D"/>
      <stop offset="60%" stop-color="#0E2452"/>
      <stop offset="100%" stop-color="#12306D"/>
    </linearGradient>

    <!-- Soft Drop Shadow Filter -->
    <filter id="cardShadow" x="-10" y="0" width="410" height="230" filterUnits="userSpaceOnUse">
      <feDropShadow dx="0" dy="8" stdDeviation="10" flood-color="#0B1B3D" flood-opacity="0.16"/>
    </filter>
    <filter id="softShadow" x="-10" y="0" width="410" height="120" filterUnits="userSpaceOnUse">
      <feDropShadow dx="0" dy="4" stdDeviation="8" flood-color="#0B1B3D" flood-opacity="0.06"/>
    </filter>
    <filter id="bottomNavShadow" x="-10" y="-10" width="410" height="90" filterUnits="userSpaceOnUse">
      <feDropShadow dx="0" dy="6" stdDeviation="14" flood-color="#0B1B3D" flood-opacity="0.12"/>
    </filter>
  </defs>

  <!-- Frame Background -->
  <rect width="430" height="932" rx="40" fill="url(#bgGrad)"/>

  <!-- Status Bar (09:41) -->
  <text x="36" y="38" font-family="Inter, sans-serif" font-size="15" font-weight="600" fill="#0F172A">09:41</text>
  <rect x="165" y="18" width="100" height="28" rx="14" fill="#000000"/>
  <!-- Status Icons -->
  <circle cx="365" cy="34" r="3" fill="#0F172A"/>
  <circle cx="373" cy="34" r="3" fill="#0F172A"/>
  <circle cx="381" cy="34" r="3" fill="#0F172A"/>
  <rect x="390" y="28" width="20" height="12" rx="3" stroke="#0F172A" stroke-width="2" fill="none"/>
  <rect x="392" y="30" width="12" height="8" rx="1.5" fill="#0F172A"/>

  <!-- Top Header Profile -->
  <g transform="translate(24, 68)">
    <text x="0" y="16" font-family="Inter, sans-serif" font-size="13" font-weight="500" fill="#64748B">Selamat Bertugas,</text>
    <text x="0" y="38" font-family="Inter, sans-serif" font-size="19" font-weight="700" fill="#0F172A">Mayor Laut (P) Hendra</text>
    <text x="0" y="54" font-family="Inter, sans-serif" font-size="11" font-weight="600" fill="#0284C7">DENINTEL KODAERAL V</text>
    
    <!-- Notification Icon Badge -->
    <rect x="338" y="4" width="44" height="44" rx="22" fill="#FFFFFF" filter="url(#softShadow)"/>
    <path d="M360 18C356.686 18 354 20.686 354 24V27L352 29V30H368V29L366 27V24C366 20.686 363.314 18 360 18Z" fill="#0F172A"/>
    <circle cx="366" cy="18" r="4" fill="#EF4444"/>
  </g>

  <!-- Hero Balance Card (Deep Navy Gradient - Style eCommerce) -->
  <g transform="translate(24, 142)">
    <rect width="382" height="186" rx="24" fill="url(#heroGrad)" filter="url(#cardShadow)"/>
    
    <!-- Pattern Circles -->
    <circle cx="340" cy="40" r="80" fill="#FFFFFF" fill-opacity="0.03"/>
    <circle cx="360" cy="60" r="120" fill="#FFFFFF" fill-opacity="0.02"/>

    <!-- Header Card -->
    <text x="24" y="36" font-family="Inter, sans-serif" font-size="11" font-weight="600" letter-spacing="1" fill="#94A3B8">SALDO KAS UTAMA DENINTEL</text>
    
    <!-- Status Badge -->
    <rect x="272" y="22" width="86" height="22" rx="11" fill="#1E3A8A"/>
    <text x="282" y="37" font-family="Inter, sans-serif" font-size="10" font-weight="600" fill="#60A5FA">Terverifikasi</text>

    <!-- Nominal Saldo -->
    <text x="24" y="86" font-family="Inter, sans-serif" font-size="30" font-weight="800" fill="#FFFFFF">Rp 128.450.000</text>
    
    <!-- Eye Privacy Toggle -->
    <circle cx="280" cy="78" r="16" fill="#FFFFFF" fill-opacity="0.1"/>
    <path d="M274 78C274 78 276 73 280 73C284 73 286 78 286 78C286 78 284 83 280 83C276 83 274 78 274 78Z" stroke="#FFFFFF" stroke-width="1.5" stroke-linecap="round" fill="none"/>
    <circle cx="280" cy="78" r="2" fill="#FFFFFF"/>

    <!-- Divider Line -->
    <line x1="24" y1="126" x2="358" y2="126" stroke="#FFFFFF" stroke-opacity="0.1" stroke-width="1"/>

    <!-- Card Footer Stats -->
    <text x="24" y="154" font-family="Inter, sans-serif" font-size="12" font-weight="500" fill="#CBD5E1">Anggaran Operasional 2026</text>
    <text x="294" y="154" font-family="Inter, sans-serif" font-size="12" font-weight="700" fill="#38BDF8">+14.2% MoM</text>
  </g>

  <!-- Trio Metrics (Pemasukan, Pengeluaran, Kas Teknis) -->
  <g transform="translate(24, 348)">
    <!-- Box 1: Pemasukan -->
    <g transform="translate(0, 0)">
      <rect width="118" height="78" rx="18" fill="#FFFFFF" filter="url(#softShadow)"/>
      <text x="14" y="24" font-family="Inter, sans-serif" font-size="11" font-weight="500" fill="#64748B">Pemasukan</text>
      <text x="14" y="48" font-family="Inter, sans-serif" font-size="14" font-weight="700" fill="#00A884">+Rp 45.2M</text>
      <rect x="14" y="56" width="36" height="4" rx="2" fill="#00A884"/>
    </g>

    <!-- Box 2: Pengeluaran -->
    <g transform="translate(132, 0)">
      <rect width="118" height="78" rx="18" fill="#FFFFFF" filter="url(#softShadow)"/>
      <text x="14" y="24" font-family="Inter, sans-serif" font-size="11" font-weight="500" fill="#64748B">Pengeluaran</text>
      <text x="14" y="48" font-family="Inter, sans-serif" font-size="14" font-weight="700" fill="#EF4444">-Rp 12.8M</text>
      <rect x="14" y="56" width="36" height="4" rx="2" fill="#EF4444"/>
    </g>

    <!-- Box 3: Kas Teknis -->
    <g transform="translate(264, 0)">
      <rect width="118" height="78" rx="18" fill="#FFFFFF" filter="url(#softShadow)"/>
      <text x="14" y="24" font-family="Inter, sans-serif" font-size="11" font-weight="500" fill="#64748B">Kas Teknis</text>
      <text x="14" y="48" font-family="Inter, sans-serif" font-size="14" font-weight="700" fill="#0284C7">Rp 8.5M</text>
      <rect x="14" y="56" width="36" height="4" rx="2" fill="#0284C7"/>
    </g>
  </g>

  <!-- Section Title: Layanan Kedinasan -->
  <text x="24" y="458" font-family="Inter, sans-serif" font-size="16" font-weight="700" fill="#0F172A">Layanan Kedinasan</text>

  <!-- Quick Action Grid (4x2) -->
  <g transform="translate(24, 474)">
    <!-- Row 1 -->
    <!-- Item 1: Kas Denintel -->
    <g transform="translate(10, 0)">
      <rect width="56" height="56" rx="18" fill="#E1F7F1"/>
      <circle cx="28" cy="28" r="14" fill="#00A884"/>
      <text x="28" y="32" font-family="Inter, sans-serif" font-size="12" font-weight="700" fill="#FFFFFF" text-anchor="middle">Rp</text>
      <text x="28" y="74" font-family="Inter, sans-serif" font-size="11" font-weight="600" fill="#1E293B" text-anchor="middle">Kas Denintel</text>
    </g>

    <!-- Item 2: Kas Teknis -->
    <g transform="translate(105, 0)">
      <rect width="56" height="56" rx="18" fill="#E5F2FD"/>
      <circle cx="28" cy="28" r="14" fill="#0284C7"/>
      <text x="28" y="32" font-family="Inter, sans-serif" font-size="12" font-weight="700" fill="#FFFFFF" text-anchor="middle">KT</text>
      <text x="28" y="74" font-family="Inter, sans-serif" font-size="11" font-weight="600" fill="#1E293B" text-anchor="middle">Kas Teknis</text>
    </g>

    <!-- Item 3: Buku Tamu -->
    <g transform="translate(200, 0)">
      <rect width="56" height="56" rx="18" fill="#FEF3C7"/>
      <circle cx="28" cy="28" r="14" fill="#D97706"/>
      <text x="28" y="32" font-family="Inter, sans-serif" font-size="12" font-weight="700" fill="#FFFFFF" text-anchor="middle">BT</text>
      <text x="28" y="74" font-family="Inter, sans-serif" font-size="11" font-weight="600" fill="#1E293B" text-anchor="middle">Buku Tamu</text>
    </g>

    <!-- Item 4: SKHPP -->
    <g transform="translate(295, 0)">
      <rect width="56" height="56" rx="18" fill="#EDE9FE"/>
      <circle cx="28" cy="28" r="14" fill="#7C3AED"/>
      <text x="28" y="32" font-family="Inter, sans-serif" font-size="11" font-weight="700" fill="#FFFFFF" text-anchor="middle">DOC</text>
      <text x="28" y="74" font-family="Inter, sans-serif" font-size="11" font-weight="600" fill="#1E293B" text-anchor="middle">SKHPP</text>
    </g>

    <!-- Row 2 -->
    <!-- Item 5: Surat Masuk -->
    <g transform="translate(10, 88)">
      <rect width="56" height="56" rx="18" fill="#E0F2FE"/>
      <circle cx="28" cy="28" r="14" fill="#0369A1"/>
      <text x="28" y="32" font-family="Inter, sans-serif" font-size="11" font-weight="700" fill="#FFFFFF" text-anchor="middle">SRT</text>
      <text x="28" y="74" font-family="Inter, sans-serif" font-size="11" font-weight="600" fill="#1E293B" text-anchor="middle">Surat Masuk</text>
    </g>

    <!-- Item 6: Disposisi -->
    <g transform="translate(105, 88)">
      <rect width="56" height="56" rx="18" fill="#DCFCE7"/>
      <circle cx="28" cy="28" r="14" fill="#15803D"/>
      <text x="28" y="32" font-family="Inter, sans-serif" font-size="11" font-weight="700" fill="#FFFFFF" text-anchor="middle">DSP</text>
      <text x="28" y="74" font-family="Inter, sans-serif" font-size="11" font-weight="600" fill="#1E293B" text-anchor="middle">Disposisi</text>
    </g>

    <!-- Item 7: Mitra Intel -->
    <g transform="translate(200, 88)">
      <rect width="56" height="56" rx="18" fill="#FFEDD5"/>
      <circle cx="28" cy="28" r="14" fill="#C2410C"/>
      <text x="28" y="32" font-family="Inter, sans-serif" font-size="11" font-weight="700" fill="#FFFFFF" text-anchor="middle">MTR</text>
      <text x="28" y="74" font-family="Inter, sans-serif" font-size="11" font-weight="600" fill="#1E293B" text-anchor="middle">Mitra Intel</text>
    </g>

    <!-- Item 8: Personel -->
    <g transform="translate(295, 88)">
      <rect width="56" height="56" rx="18" fill="#F1F5F9"/>
      <circle cx="28" cy="28" r="14" fill="#475569"/>
      <text x="28" y="32" font-family="Inter, sans-serif" font-size="11" font-weight="700" fill="#FFFFFF" text-anchor="middle">PRS</text>
      <text x="28" y="74" font-family="Inter, sans-serif" font-size="11" font-weight="600" fill="#1E293B" text-anchor="middle">Personel</text>
    </g>
  </g>

  <!-- Section: Kondisi Hari Ini -->
  <text x="24" y="682" font-family="Inter, sans-serif" font-size="16" font-weight="700" fill="#0F172A">Kondisi Hari Ini</text>

  <!-- Activity Card -->
  <g transform="translate(24, 698)">
    <rect width="382" height="96" rx="20" fill="#FFFFFF" filter="url(#softShadow)"/>
    <circle cx="44" cy="48" r="22" fill="#E1F7F1"/>
    <path d="M38 48L42 52L50 44" stroke="#00A884" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
    
    <text x="80" y="40" font-family="Inter, sans-serif" font-size="14" font-weight="700" fill="#0F172A">Buku Tamu & Pengamanan Fisik</text>
    <text x="80" y="58" font-family="Inter, sans-serif" font-size="12" font-weight="400" fill="#64748B">14 Tamu Terverifikasi • 3 Disposisi Aktif</text>
    
    <!-- Status Pill -->
    <rect x="290" y="36" width="72" height="24" rx="12" fill="#DCFCE7"/>
    <text x="326" y="52" font-family="Inter, sans-serif" font-size="11" font-weight="700" fill="#15803D" text-anchor="middle">Aman</text>
  </g>

  <!-- Modern Floating Bottom Pill Navigation Bar -->
  <g transform="translate(24, 830)" filter="url(#bottomNavShadow)">
    <rect width="382" height="68" rx="34" fill="#FFFFFF"/>
    
    <!-- Tab 1: Beranda (Active) -->
    <g transform="translate(38, 14)">
      <rect x="0" y="0" width="40" height="40" rx="20" fill="#0B1B3D"/>
      <path d="M20 12L12 18V28H17V22H23V28H28V18L20 12Z" fill="#FFFFFF"/>
    </g>

    <!-- Tab 2: Kas -->
    <g transform="translate(118, 16)">
      <circle cx="16" cy="14" r="10" stroke="#64748B" stroke-width="2" fill="none"/>
      <text x="16" y="18" font-family="Inter, sans-serif" font-size="10" font-weight="700" fill="#64748B" text-anchor="middle">Rp</text>
      <text x="16" y="32" font-family="Inter, sans-serif" font-size="10" font-weight="600" fill="#64748B" text-anchor="middle">Kas</text>
    </g>

    <!-- Tab 3: Surat -->
    <g transform="translate(196, 16)">
      <rect x="6" y="6" width="20" height="15" rx="3" stroke="#64748B" stroke-width="2" fill="none"/>
      <path d="M6 9L16 16L26 9" stroke="#64748B" stroke-width="2" stroke-linecap="round"/>
      <text x="16" y="32" font-family="Inter, sans-serif" font-size="10" font-weight="600" fill="#64748B" text-anchor="middle">Surat</text>
    </g>

    <!-- Tab 4: SKHPP -->
    <g transform="translate(270, 16)">
      <path d="M8 6H20L24 10V24H8V6Z" stroke="#64748B" stroke-width="2" fill="none"/>
      <text x="16" y="32" font-family="Inter, sans-serif" font-size="10" font-weight="600" fill="#64748B" text-anchor="middle">SKHPP</text>
    </g>

    <!-- Tab 5: Akun -->
    <g transform="translate(340, 16)">
      <circle cx="14" cy="11" r="5" stroke="#64748B" stroke-width="2" fill="none"/>
      <path d="M6 23C6 19 10 18 14 18C18 18 22 19 22 23" stroke="#64748B" stroke-width="2" stroke-linecap="round"/>
      <text x="14" y="32" font-family="Inter, sans-serif" font-size="10" font-weight="600" fill="#64748B" text-anchor="middle">Akun</text>
    </g>
  </g>
</svg>`;

fs.writeFileSync(path.join(outDir, '01_sinden_dashboard.svg'), dashboardSvg, 'utf8');

// SVG 2: SISINDEN Finansial & Kas Denintel
const financialSvg = `<svg width="430" height="932" viewBox="0 0 430 932" fill="none" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="finBgGrad" x1="0" y1="0" x2="430" y2="932" gradientUnits="userSpaceOnUse">
      <stop offset="0%" stop-color="#F0F7FA"/>
      <stop offset="100%" stop-color="#F8FAFC"/>
    </linearGradient>
    <filter id="softShadow2" x="-10" y="0" width="410" height="120" filterUnits="userSpaceOnUse">
      <feDropShadow dx="0" dy="4" stdDeviation="8" flood-color="#0B1B3D" flood-opacity="0.06"/>
    </filter>
  </defs>

  <rect width="430" height="932" rx="40" fill="url(#finBgGrad)"/>

  <!-- Status Bar -->
  <text x="36" y="38" font-family="Inter, sans-serif" font-size="15" font-weight="600" fill="#0F172A">09:41</text>
  <rect x="165" y="18" width="100" height="28" rx="14" fill="#000000"/>

  <!-- Header Nav -->
  <g transform="translate(24, 68)">
    <circle cx="20" cy="20" r="20" fill="#FFFFFF" filter="url(#softShadow2)"/>
    <path d="M23 14L17 20L23 26" stroke="#0F172A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    <text x="60" y="26" font-family="Inter, sans-serif" font-size="18" font-weight="700" fill="#0F172A">Kas Denintel Kodaeral V</text>
  </g>

  <!-- Filter Period Chips -->
  <g transform="translate(24, 130)">
    <!-- Chip 1: Semua (Active) -->
    <rect x="0" y="0" width="76" height="36" rx="18" fill="#0B1B3D"/>
    <text x="38" y="22" font-family="Inter, sans-serif" font-size="12" font-weight="600" fill="#FFFFFF" text-anchor="middle">Semua</text>

    <!-- Chip 2: Hari Ini -->
    <rect x="86" y="0" width="86" height="36" rx="18" fill="#FFFFFF" stroke="#E2E8F0"/>
    <text x="129" y="22" font-family="Inter, sans-serif" font-size="12" font-weight="600" fill="#64748B" text-anchor="middle">Hari Ini</text>

    <!-- Chip 3: Minggu Ini -->
    <rect x="182" y="0" width="96" height="36" rx="18" fill="#FFFFFF" stroke="#E2E8F0"/>
    <text x="230" y="22" font-family="Inter, sans-serif" font-size="12" font-weight="600" fill="#64748B" text-anchor="middle">Minggu Ini</text>

    <!-- Chip 4: Bulan Ini -->
    <rect x="288" y="0" width="94" height="36" rx="18" fill="#FFFFFF" stroke="#E2E8F0"/>
    <text x="335" y="22" font-family="Inter, sans-serif" font-size="12" font-weight="600" fill="#64748B" text-anchor="middle">Bulan Ini</text>
  </g>

  <!-- Big Balance Overview Card -->
  <g transform="translate(24, 186)">
    <rect width="382" height="140" rx="22" fill="#FFFFFF" filter="url(#softShadow2)"/>
    <text x="24" y="36" font-family="Inter, sans-serif" font-size="12" font-weight="600" fill="#64748B">TOTAL SALDO AKTIF</text>
    <text x="24" y="76" font-family="Inter, sans-serif" font-size="28" font-weight="800" fill="#0B1B3D">Rp 128.450.000</text>
    
    <g transform="translate(24, 96)">
      <circle cx="6" cy="6" r="6" fill="#00A884"/>
      <text x="20" y="10" font-family="Inter, sans-serif" font-size="12" font-weight="600" fill="#00A884">+Rp 45.2M Masuk</text>

      <circle cx="160" cy="6" r="6" fill="#EF4444"/>
      <text x="174" y="10" font-family="Inter, sans-serif" font-size="12" font-weight="600" fill="#EF4444">-Rp 12.8M Keluar</text>
    </g>
  </g>

  <!-- Recent Transactions Section -->
  <text x="24" y="366" font-family="Inter, sans-serif" font-size="16" font-weight="700" fill="#0F172A">Riwayat Mutasi Transaksi</text>

  <!-- Transaction List -->
  <!-- Tx 1: Pemasukan Operasional -->
  <g transform="translate(24, 386)">
    <rect width="382" height="84" rx="18" fill="#FFFFFF" filter="url(#softShadow2)"/>
    <rect x="16" y="16" width="52" height="52" rx="16" fill="#E1F7F1"/>
    <path d="M42 46V36M42 36L36 42M42 36L48 42" stroke="#00A884" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
    
    <text x="80" y="38" font-family="Inter, sans-serif" font-size="14" font-weight="700" fill="#0F172A">Dukungan Satuan Operasi</text>
    <text x="80" y="56" font-family="Inter, sans-serif" font-size="12" font-weight="500" fill="#64748B">12 Okt 2026 • Kas Masuk</text>

    <text x="362" y="44" font-family="Inter, sans-serif" font-size="15" font-weight="700" fill="#00A884" text-anchor="end">+Rp 25.000.000</text>
    <rect x="296" y="52" width="66" height="18" rx="9" fill="#DCFCE7"/>
    <text x="329" y="65" font-family="Inter, sans-serif" font-size="10" font-weight="600" fill="#15803D" text-anchor="middle">Disetujui</text>
  </g>

  <!-- Tx 2: Pengeluaran Perbekalan -->
  <g transform="translate(24, 482)">
    <rect width="382" height="84" rx="18" fill="#FFFFFF" filter="url(#softShadow2)"/>
    <rect x="16" y="16" width="52" height="52" rx="16" fill="#FEECEE"/>
    <path d="M42 36V46M42 46L36 40M42 46L48 40" stroke="#EF4444" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
    
    <text x="80" y="38" font-family="Inter, sans-serif" font-size="14" font-weight="700" fill="#0F172A">BBM & Harbek Operasional</text>
    <text x="80" y="56" font-family="Inter, sans-serif" font-size="12" font-weight="500" fill="#64748B">11 Okt 2026 • Kas Keluar</text>

    <text x="362" y="44" font-family="Inter, sans-serif" font-size="15" font-weight="700" fill="#EF4444" text-anchor="end">-Rp 4.250.000</text>
    <rect x="296" y="52" width="66" height="18" rx="9" fill="#FEE2E2"/>
    <text x="329" y="65" font-family="Inter, sans-serif" font-size="10" font-weight="600" fill="#B91C1C" text-anchor="middle">Verifikasi</text>
  </g>

  <!-- Tx 3: Kas Teknis -->
  <g transform="translate(24, 578)">
    <rect width="382" height="84" rx="18" fill="#FFFFFF" filter="url(#softShadow2)"/>
    <rect x="16" y="16" width="52" height="52" rx="16" fill="#E5F2FD"/>
    <path d="M36 42H48M42 36V48" stroke="#0284C7" stroke-width="2.5" stroke-linecap="round"/>
    
    <text x="80" y="38" font-family="Inter, sans-serif" font-size="14" font-weight="700" fill="#0F172A">Pemeliharaan Komunikasi Intel</text>
    <text x="80" y="56" font-family="Inter, sans-serif" font-size="12" font-weight="500" fill="#64748B">10 Okt 2026 • Kas Teknis</text>

    <text x="362" y="44" font-family="Inter, sans-serif" font-size="15" font-weight="700" fill="#0284C7" text-anchor="end">-Rp 2.100.000</text>
    <rect x="296" y="52" width="66" height="18" rx="9" fill="#E0F2FE"/>
    <text x="329" y="65" font-family="Inter, sans-serif" font-size="10" font-weight="600" fill="#0369A1" text-anchor="middle">Selesai</text>
  </g>

  <!-- Floating Add Transaction Button -->
  <g transform="translate(24, 820)">
    <rect width="382" height="56" rx="28" fill="#0B1B3D" filter="url(#softShadow2)"/>
    <text x="191" y="34" font-family="Inter, sans-serif" font-size="15" font-weight="700" fill="#FFFFFF" text-anchor="middle">+ Catat Transaksi Baru</text>
  </g>
</svg>`;

fs.writeFileSync(path.join(outDir, '02_sinden_financial_cash.svg'), financialSvg, 'utf8');

// SVG 3: SISINDEN Mitra Intelijen
const mitraSvg = `<svg width="430" height="932" viewBox="0 0 430 932" fill="none" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="mitraBgGrad" x1="0" y1="0" x2="430" y2="932" gradientUnits="userSpaceOnUse">
      <stop offset="0%" stop-color="#F4F8FB"/>
      <stop offset="100%" stop-color="#FFFFFF"/>
    </linearGradient>
    <filter id="softShadow3" x="-10" y="0" width="410" height="120" filterUnits="userSpaceOnUse">
      <feDropShadow dx="0" dy="4" stdDeviation="8" flood-color="#0B1B3D" flood-opacity="0.06"/>
    </filter>
  </defs>

  <rect width="430" height="932" rx="40" fill="url(#mitraBgGrad)"/>

  <!-- Status Bar -->
  <text x="36" y="38" font-family="Inter, sans-serif" font-size="15" font-weight="600" fill="#0F172A">09:41</text>
  <rect x="165" y="18" width="100" height="28" rx="14" fill="#000000"/>

  <!-- Top Header -->
  <g transform="translate(24, 68)">
    <circle cx="20" cy="20" r="20" fill="#FFFFFF" filter="url(#softShadow3)"/>
    <path d="M23 14L17 20L23 26" stroke="#0F172A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    <text x="60" y="26" font-family="Inter, sans-serif" font-size="18" font-weight="700" fill="#0F172A">Mitra Intelijen Maritim</text>
  </g>

  <!-- Search Bar (eCommerce Style) -->
  <g transform="translate(24, 126)">
    <rect width="382" height="52" rx="16" fill="#FFFFFF" stroke="#E2E8F0" filter="url(#softShadow3)"/>
    <circle cx="36" cy="26" r="8" stroke="#94A3B8" stroke-width="2" fill="none"/>
    <line x1="42" y1="32" x2="48" y2="38" stroke="#94A3B8" stroke-width="2" stroke-linecap="round"/>
    <text x="60" y="32" font-family="Inter, sans-serif" font-size="14" font-weight="400" fill="#94A3B8">Cari nama mitra, PT, atau wilayah...</text>
  </g>

  <!-- Mitra Cards List -->
  <!-- Card 1 -->
  <g transform="translate(24, 200)">
    <rect width="382" height="110" rx="20" fill="#FFFFFF" filter="url(#softShadow3)"/>
    <rect x="16" y="18" width="56" height="56" rx="18" fill="#E0F2FE"/>
    <text x="44" y="52" font-family="Inter, sans-serif" font-size="18" font-weight="700" fill="#0369A1" text-anchor="middle">PT</text>
    
    <text x="86" y="38" font-family="Inter, sans-serif" font-size="15" font-weight="700" fill="#0F172A">PT Samudera Bahari Logistik</text>
    <text x="86" y="56" font-family="Inter, sans-serif" font-size="12" font-weight="500" fill="#64748B">Sektor Maritim • Pelabuhan Tanjung Perak</text>
    <text x="86" y="74" font-family="Inter, sans-serif" font-size="12" font-weight="600" fill="#0284C7">Kontak: 0812-3456-7890</text>

    <!-- Payment Status Badge -->
    <rect x="296" y="18" width="70" height="24" rx="12" fill="#DCFCE7"/>
    <text x="331" y="34" font-family="Inter, sans-serif" font-size="11" font-weight="700" fill="#15803D" text-anchor="middle">Lunas</text>
  </g>

  <!-- Card 2 -->
  <g transform="translate(24, 324)">
    <rect width="382" height="110" rx="20" fill="#FFFFFF" filter="url(#softShadow3)"/>
    <rect x="16" y="18" width="56" height="56" rx="18" fill="#FEF3C7"/>
    <text x="44" y="52" font-family="Inter, sans-serif" font-size="18" font-weight="700" fill="#B45309" text-anchor="middle">CV</text>
    
    <text x="86" y="38" font-family="Inter, sans-serif" font-size="15" font-weight="700" fill="#0F172A">CV Marina Nusantara</text>
    <text x="86" y="56" font-family="Inter, sans-serif" font-size="12" font-weight="500" fill="#64748B">Sektor Perikanan • Pesisir Surabaya</text>
    <text x="86" y="74" font-family="Inter, sans-serif" font-size="12" font-weight="600" fill="#0284C7">Kontak: 0821-9876-5432</text>

    <!-- Payment Status Badge -->
    <rect x="296" y="18" width="70" height="24" rx="12" fill="#FEF3C7"/>
    <text x="331" y="34" font-family="Inter, sans-serif" font-size="11" font-weight="700" fill="#B45309" text-anchor="middle">Pending</text>
  </g>

  <!-- Card 3 -->
  <g transform="translate(24, 448)">
    <rect width="382" height="110" rx="20" fill="#FFFFFF" filter="url(#softShadow3)"/>
    <rect x="16" y="18" width="56" height="56" rx="18" fill="#E1F7F1"/>
    <text x="44" y="52" font-family="Inter, sans-serif" font-size="18" font-weight="700" fill="#00A884" text-anchor="middle">PT</text>
    
    <text x="86" y="38" font-family="Inter, sans-serif" font-size="15" font-weight="700" fill="#0F172A">PT Galangan Kapal Madura</text>
    <text x="86" y="56" font-family="Inter, sans-serif" font-size="12" font-weight="500" fill="#64748B">Sektor Galangan • Kamal Madura</text>
    <text x="86" y="74" font-family="Inter, sans-serif" font-size="12" font-weight="600" fill="#0284C7">Kontak: 0857-1122-3344</text>

    <!-- Payment Status Badge -->
    <rect x="296" y="18" width="70" height="24" rx="12" fill="#DCFCE7"/>
    <text x="331" y="34" font-family="Inter, sans-serif" font-size="11" font-weight="700" fill="#15803D" text-anchor="middle">Lunas</text>
  </g>
</svg>`;

fs.writeFileSync(path.join(outDir, '03_sinden_mitra.svg'), mitraSvg, 'utf8');

// SVG 4: SISINDEN Login & Aktivasi Akun
const loginSvg = `<svg width="430" height="932" viewBox="0 0 430 932" fill="none" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="loginBgGrad" x1="0" y1="0" x2="430" y2="932" gradientUnits="userSpaceOnUse">
      <stop offset="0%" stop-color="#0B1B3D"/>
      <stop offset="50%" stop-color="#0E2452"/>
      <stop offset="100%" stop-color="#12306D"/>
    </linearGradient>
    <filter id="loginCardShadow" x="-10" y="0" width="410" height="600" filterUnits="userSpaceOnUse">
      <feDropShadow dx="0" dy="16" stdDeviation="20" flood-color="#000000" flood-opacity="0.3"/>
    </filter>
  </defs>

  <rect width="430" height="932" rx="40" fill="url(#loginBgGrad)"/>

  <!-- Logo SINDEN Hero -->
  <g transform="translate(165, 80)">
    <circle cx="50" cy="50" r="48" fill="#FFFFFF" fill-opacity="0.1"/>
    <circle cx="50" cy="50" r="38" fill="#00A884"/>
    <text x="50" y="58" font-family="Inter, sans-serif" font-size="24" font-weight="800" fill="#FFFFFF" text-anchor="middle">S</text>
  </g>

  <text x="215" y="200" font-family="Inter, sans-serif" font-size="24" font-weight="800" fill="#FFFFFF" text-anchor="middle">SISINDEN MOBILE</text>
  <text x="215" y="222" font-family="Inter, sans-serif" font-size="12" font-weight="500" fill="#94A3B8" text-anchor="middle">Sistem Informasi Denintel Kodaeral V</text>

  <!-- Glassmorphism Login Card -->
  <g transform="translate(24, 252)">
    <rect width="382" height="520" rx="28" fill="#FFFFFF" filter="url(#loginCardShadow)"/>
    
    <!-- Tab Switcher (Masuk / Aktivasi Akun) -->
    <rect x="24" y="24" width="334" height="48" rx="24" fill="#F1F5F9"/>
    <!-- Active Tab: Masuk -->
    <rect x="28" y="28" width="160" height="40" rx="20" fill="#0B1B3D"/>
    <text x="108" y="53" font-family="Inter, sans-serif" font-size="14" font-weight="700" fill="#FFFFFF" text-anchor="middle">Masuk</text>
    
    <!-- Inactive Tab: Aktivasi -->
    <text x="272" y="53" font-family="Inter, sans-serif" font-size="14" font-weight="600" fill="#64748B" text-anchor="middle">Aktivasi Akun</text>

    <!-- Form Inputs -->
    <!-- Input 1: NRP / Email -->
    <text x="28" y="112" font-family="Inter, sans-serif" font-size="12" font-weight="600" fill="#334155">NRP / Email Kedinasan</text>
    <rect x="24" y="124" width="334" height="52" rx="16" fill="#F8FAFC" stroke="#E2E8F0"/>
    <text x="44" y="156" font-family="Inter, sans-serif" font-size="14" font-weight="400" fill="#0F172A">hendra.denintel@tni.mil.id</text>

    <!-- Input 2: Kata Sandi -->
    <text x="28" y="204" font-family="Inter, sans-serif" font-size="12" font-weight="600" fill="#334155">Kata Sandi</text>
    <rect x="24" y="216" width="334" height="52" rx="16" fill="#F8FAFC" stroke="#E2E8F0"/>
    <text x="44" y="248" font-family="Inter, sans-serif" font-size="16" font-weight="700" fill="#0F172A">••••••••••••</text>

    <!-- Remember & Forgot -->
    <circle cx="34" cy="296" r="8" fill="#0B1B3D"/>
    <text x="50" y="300" font-family="Inter, sans-serif" font-size="12" font-weight="500" fill="#475569">Ingat perangkat ini</text>
    <text x="358" y="300" font-family="Inter, sans-serif" font-size="12" font-weight="600" fill="#0284C7" text-anchor="end">Lupa sandi?</text>

    <!-- Login Button -->
    <rect x="24" y="336" width="334" height="54" rx="27" fill="#0B1B3D"/>
    <text x="191" y="369" font-family="Inter, sans-serif" font-size="15" font-weight="700" fill="#FFFFFF" text-anchor="middle">Masuk Kedinasan</text>

    <!-- Divider -->
    <line x1="24" y1="426" x2="358" y2="426" stroke="#E2E8F0" stroke-width="1"/>
    
    <!-- Security Notice -->
    <text x="191" y="460" font-family="Inter, sans-serif" font-size="11" font-weight="500" fill="#64748B" text-anchor="middle">Enkripsi Kedinasan TLS 1.3 Terverifikasi</text>
    <text x="191" y="480" font-family="Inter, sans-serif" font-size="10" font-weight="600" fill="#94A3B8" text-anchor="middle">Komando Daerah Maritim V</text>
  </g>
</svg>`;

fs.writeFileSync(path.join(outDir, '04_sinden_login.svg'), loginSvg, 'utf8');

console.log("All 4 SVG files created successfully in:", outDir);
