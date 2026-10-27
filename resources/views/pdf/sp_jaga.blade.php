@php
    $bulanBaku = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    $bulanRomawiList = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
    ];

    $formatTglBaku = function($date) use ($bulanBaku) {
        if (!$date) return '-';
        $c = \Carbon\Carbon::parse($date);
        $m = (int)$c->format('n');
        $bln = $bulanBaku[$m] ?? $c->format('F');
        return $c->format('j') . ' ' . $bln . ' ' . $c->format('Y');
    };

    // Bulan & tahun dari data spJaga
    $blnAngka = (int)($spJaga->bulan ?: ($spJaga->tmt_mulai ? \Carbon\Carbon::parse($spJaga->tmt_mulai)->format('n') : 1));
    $tahunAngka = (int)($spJaga->tahun ?: ($spJaga->tmt_mulai ? \Carbon\Carbon::parse($spJaga->tmt_mulai)->format('Y') : 2026));

    $namaBulanBaku = $bulanBaku[$blnAngka] ?? 'Januari';
    $namaBulanTahun = strtoupper($namaBulanBaku . ' ' . $tahunAngka);
    $romawiBaku = $spJaga->bulan_romawi ?: ($bulanRomawiList[$blnAngka] ?? 'IX');

    $nomorUrutVal = $spJaga->nomor_urut ?: '29';
    if (!empty($spJaga->nomor_sprin)) {
        $nomorSprinBaku = $spJaga->nomor_sprin;
    } else {
        $nomorSprinBaku = "Sprin/ {$nomorUrutVal} /{$romawiBaku}/{$tahunAngka}";
    }

    $tmtMulai = $spJaga->tmt_mulai ? \Carbon\Carbon::parse($spJaga->tmt_mulai)->format('d') : '01';
    $tmtSelesai = $spJaga->tmt_selesai ? $formatTglBaku($spJaga->tmt_selesai) : ('30 ' . $namaBulanBaku . ' ' . $tahunAngka);
    $tglSuratBaku = $spJaga->tanggal_surat ? $formatTglBaku($spJaga->tanggal_surat) : ('30 ' . $namaBulanBaku . ' ' . $tahunAngka);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Surat Perintah Jaga - {{ $nomorSprinBaku }}</title>
    <style>
        @page {
            margin-top: 1.5cm;
            margin-right: 1.5cm;
            margin-bottom: 2cm;
            margin-left: 1.5cm;
            margin: 1.5cm 1.5cm 2cm 1.5cm;
            size: 215mm 330mm portrait; /* Format F4 / Folio Resmi Kedinasan TNI */
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12pt;
            line-height: 1.35;
            color: #000000;
            margin: 0;
            padding: 0;
        }

        .page-break {
            page-break-before: always;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .table-bordered {
            border-collapse: collapse;
            width: 100%;
            font-size: 10pt;
        }

        .table-bordered th, .table-bordered td {
            border: 1px solid #000000;
            padding: 4px 6px;
            text-align: left;
        }

        .table-bordered th {
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
        }

        .text-center { text-align: center; }
        .text-justify { text-align: justify; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }

        .kop-surat {
            width: 340px;
            text-align: center;
            margin-bottom: 5px;
        }
        .kop-surat .line1 { font-size: 11pt; font-weight: normal; }
        .kop-surat .line2 { font-size: 11pt; font-weight: normal; }
        .kop-surat .divider { border-bottom: 1.5px solid #000000; margin-top: 2px; }

        .logo-tni {
            display: block;
            margin: 0 auto;
            width: 120px;
            height: auto;
        }

        .ttd-box {
            width: 360px;
            margin-left: auto;
            text-align: center;
            font-size: 11.5pt;
        }
    </style>
</head>
<body>

    <!-- ========================================================================= -->
    <!-- HALAMAN 1: SURAT PERINTAH (SP JAGA UTAMA)                                -->
    <!-- ========================================================================= -->
    <div style="width: 100%;">
        
        <!-- Kop Surat Kiri -->
        <div class="kop-surat">
            <div class="line1">KOMANDO ARMADA II</div>
            <div class="line2">DETASEMEN INTELIJEN</div>
            <div class="divider"></div>
        </div>

        <!-- Logo Hitam Putih TNI AL Jalesveva Jayamahe -->
        <div style="text-align: center; margin-top: 4px; margin-bottom: 4px;">
            @php
                $bwLogoPath = public_path('images/logo_tni_al_bw.png');
                $colorLogoPath = public_path('images/logo_tni_al.png');
                $logoSrc = file_exists($bwLogoPath) ? $bwLogoPath : (file_exists($colorLogoPath) ? $colorLogoPath : '');
            @endphp
            @if($logoSrc)
                <img src="{{ $logoSrc }}" class="logo-tni" alt="Logo TNI AL" />
            @endif
        </div>

        <!-- Judul Surat Perintah -->
        <div style="text-align: center; margin-bottom: 14px;">
            <div style="font-size: 12.5pt; font-weight: normal; text-decoration: underline; text-transform: uppercase;">
                SURAT PERINTAH
            </div>
            <div style="font-size: 12pt; margin-top: 2px;">
                Nomor: {{ $nomorSprinBaku }}
            </div>
        </div>

        <!-- Konsideran: Menimbang & Dasar -->
        <table style="width: 100%; margin-bottom: 8px; font-size: 12pt; border-collapse: collapse;">
            <tr>
                <td style="width: 140px; vertical-align: top; padding-bottom: 8px;">Menimbang</td>
                <td style="width: 25px; vertical-align: top; text-align: center; padding-bottom: 8px;">:</td>
                <td style="vertical-align: top; text-align: justify; padding-bottom: 8px; padding-left: 15px;">
                    Bahwa dalam rangka melaksanakan tugas jaga Siaga Sintel Kepada Perwira Sintel/Den Intel/Pam Denma Koarmada 2, maka perlu dikeluarkan surat perintah.
                </td>
            </tr>
            <tr>
                <td style="vertical-align: top; padding-bottom: 8px;">Dasar</td>
                <td style="vertical-align: top; text-align: center; padding-bottom: 8px;">:</td>
                <td style="vertical-align: top; text-align: justify; padding-bottom: 8px; padding-left: 15px;">
                    Prosedur Tetap Pangkoarmada 2 Nomor Protap/01/II/2015 tanggal 18 Maret 2015 tentang Pengamanan Basis TNI AL dan Obyek Vital di Ujung Surabaya.
                </td>
            </tr>
        </table>

        <!-- DIPERINTAHKAN -->
        <div style="text-align: center; font-weight: normal; margin: 12px 0; text-transform: uppercase; letter-spacing: 1px; font-size: 12pt;">
            DIPERINTAHKAN
        </div>

        <!-- Diktum: Kepada & Untuk (Font 12pt) -->
        <table style="width: 100%; margin-bottom: 10px; font-size: 12pt; border-collapse: collapse;">
            <tr>
                <td style="width: 140px; vertical-align: top; padding-bottom: 10px;">Kepada</td>
                <td style="width: 25px; vertical-align: top; text-align: center; padding-bottom: 10px;">:</td>
                <td style="vertical-align: top; text-align: justify; padding-bottom: 10px; padding-left: 15px;">
                    {{ $penerimaDiktumKepada ?? (($spJaga->perwira_tertua_pangkat_nrp ?: 'Kapten Laut (P)') . ' ' . ($spJaga->perwira_tertua_nama ?: 'Indra Gunawan')) }}, {{ $perwiraTertuaJabatan ?? ($spJaga->perwira_tertua_jabatan ?: 'Dan Unit 1 Lid Den Intel Koarmada 2') }}, beserta {{ $totalPengikut ?? ($spJaga->total_personel_count > 1 ? $spJaga->total_personel_count - 1 : 26) }} ({{ $totalPengikutTerbilang ?? ($spJaga->total_personel_terbilang ?: 'Dua puluh enam') }}) orang sesuai lampiran.
                </td>
            </tr>
            <tr>
                <td style="vertical-align: top;">Untuk</td>
                <td style="vertical-align: top; text-align: center;">:</td>
                <td style="vertical-align: top; padding-left: 15px;">
                    <table style="width: 100%; font-size: 12pt; border-collapse: collapse;">
                        <tr>
                            <td style="width: 25px; vertical-align: top; padding-bottom: 6px;">1.</td>
                            <td style="vertical-align: top; text-align: justify; padding-bottom: 6px; padding-left: 8px;">
                                Seterimanya surat perintah ini disamping tugas dan tanggung jawab yang ada, ditunjuk menjabat sebagai Perwira Siaga dan Anggota Siaga Sintel Koarmada 2 sesuai dengan Jadwal terlampir.
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 25px; vertical-align: top; padding-bottom: 6px;">2.</td>
                            <td style="vertical-align: top; text-align: justify; padding-bottom: 6px; padding-left: 8px;">
                                Pelaksanaan TMT {{ $tmtMulai }} s.d. {{ $tmtSelesai }}
                            </td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top; padding-bottom: 6px;">3.</td>
                            <td style="vertical-align: top; text-align: justify; padding-bottom: 6px; padding-left: 8px;">
                                Melaksanakan perintah ini dengan penuh rasa tanggung jawab, serta melaporkan hasil pelaksanaannya kepada Asintel Pangkoarmada 2 dan Dandenintel Koarmada 2.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div style="margin-bottom: 12px; font-size: 12pt;">Selesai.</div>

        <!-- Kolom Tanda Tangan & Tanggal Keluar -->
        <table style="width: 100%; margin-top: 4px;">
            <tr>
                <td style="width: 42%; vertical-align: bottom;">
                    <!-- Tembusan -->
                    <div style="font-size: 11pt; width: 220px;">
                        <div style="margin-bottom: 4px;">Tembusan:</div>
                        <div style="border-bottom: 1.5px solid #000; padding-bottom: 4px;">
                            <ol style="margin: 0; padding-left: 18px;">
                                <li>Asintel Pangkoarmada 2</li>
                                <li>Dandenma Koarmada 2</li>
                            </ol>
                        </div>
                    </div>
                </td>
                <td style="width: 58%; vertical-align: top;">
                    <div class="ttd-box">
                        <table style="width: 100%; text-align: left; margin-bottom: 3px; font-size: 11.5pt;">
                            <tr>
                                <td style="width: 100px;">Dikeluarkan di</td>
                                <td style="width: 10px;">:</td>
                                <td style="padding-left: 10px;">Surabaya</td>
                            </tr>
                            <tr>
                                <td>pada tanggal</td>
                                <td>:</td>
                                <td style="padding-left: 10px;">{{ $tglSuratBaku }}</td>
                            </tr>
                        </table>
                        <div style="border-bottom: 1px solid #000; margin-bottom: 4px;"></div>

                        <div style="text-align: center;">
                            <div style="font-size: 11.5pt; white-space: nowrap;">a.n. Komandan Detasemen Intelijen Koarmada 2,</div>
                            <div style="margin-bottom: 4px; font-size: 11.5pt;">Pasiops,</div>

                            <!-- Area TTE QR Code vs TTD Basah -->
                            @if($spJaga->ttd_type === 'tte' && $spJaga->status === 'published' && !empty($qr_base64))
                                <div style="margin: 4px auto; text-align: center;">
                                    <img src="{{ $qr_base64 }}" style="width: 75px; height: 75px; display: inline-block;" alt="QR TTE" />
                                    <div style="font-size: 7.5pt; font-family: monospace; color: #333; margin-top: 2px;">
                                        TTE VALID: {{ $spJaga->verification_code }}
                                    </div>
                                </div>
                            @else
                                <div style="height: 60px;"></div>
                            @endif

                            <div style="font-weight: normal; text-decoration: none; font-size: 12pt;">
                                {{ $pasiopsNama ?? ($spJaga->penandatangan_nama ?: 'Roni Sumantri') }}
                            </div>
                            <div style="font-size: 11pt;">
                                {{ $pasiopsPangkatNrp ?? ($spJaga->penandatangan_pangkat_nrp ?: 'Mayor Laut (P) NRP 17456/P') }}
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

    </div>

    <!-- ========================================================================= -->
    <!-- HALAMAN 2: LAMPIRAN 1 - TABEL PERWIRA JAGA SINTEL                         -->
    <!-- ========================================================================= -->
    <div class="page-break" style="width: 100%;">
        
        <!-- Header Lampiran -->
        <table style="width: 100%; margin-bottom: 12px;">
            <tr>
                <td style="width: 45%; vertical-align: top;">
                    <div class="kop-surat" style="width: 310px;">
                        <div class="line1">KOMANDO ARMADA II</div>
                        <div class="line2">DETASEMEN INTELIJEN</div>
                        <div class="divider"></div>
                    </div>
                </td>
                <td style="width: 55%; vertical-align: top; text-align: right; font-size: 11.5pt;">
                    <div style="width: 350px; margin-left: auto; text-align: left;">
                        <table style="width: 100%; font-size: 11.5pt;">
                            <tr>
                                <td style="vertical-align: top; width: 68px;">Lampiran</td>
                                <td style="vertical-align: top; width: 10px;">:</td>
                                <td style="padding-left: 8px; white-space: nowrap;">Sprin Dandenintel Koarmada 2</td>
                            </tr>
                            <tr>
                                <td style="vertical-align: top;">Nomor</td>
                                <td style="vertical-align: top;">:</td>
                                <td style="padding-left: 8px; white-space: nowrap;">{{ $nomorSprinBaku }}</td>
                            </tr>
                            <tr>
                                <td style="vertical-align: top;">Tanggal</td>
                                <td style="vertical-align: top;">:</td>
                                <td style="padding-left: 8px; white-space: nowrap;">{{ $tglSuratBaku }}</td>
                            </tr>
                        </table>
                        <div style="border-bottom: 1px solid #000; margin-top: 3px;"></div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Judul Lampiran 1 (Font 12pt Bold) -->
        <div style="text-align: center; margin-bottom: 12px;">
            <div style="font-size: 12pt; font-weight: bold; text-transform: uppercase;">
                DAFTAR NAMA PERWIRA JAGA SINTEL KOARMADA 2
            </div>
            <div style="font-size: 12pt; font-weight: bold; text-transform: uppercase;">
                JL. STASIUN BENTENG UJUNG SURABAYA BULAN {{ $namaBulanTahun }}
            </div>
        </div>

        @php
            $hasTgl7 = false;
            if (isset($spJaga->perwiras)) {
                foreach($spJaga->perwiras as $p) {
                    $v = trim($p->tgl_7 ?? '');
                    if ($v !== '' && $v !== '-') {
                        $hasTgl7 = true;
                        break;
                    }
                }
            }
        @endphp
        <!-- Tabel Perwira Jaga (Width 100% Fixed, Font 10pt di dalam tabel) -->
        <table class="table-bordered" style="width: 100%; table-layout: fixed; margin-bottom: 10px; font-size: 10pt;">
            <thead>
                <tr>
                    <th style="width: 5%;">NO.</th>
                    <th style="width: 25%;">N A M A</th>
                    <th style="width: 20%;">PANGKAT, KORPS</th>
                    <th style="width: 15%;">NRP</th>
                    <th colspan="{{ $hasTgl7 ? 7 : 6 }}" style="width: 35%;">{{ $namaBulanTahun }}<br>TANGGAL</th>
                </tr>
            </thead>
            <tbody>
                @forelse($spJaga->perwiras as $idx => $perwira)
                <tr>
                    <td class="text-center" style="padding: 4px 2px;">{{ $idx + 1 }}.</td>
                    <td style="padding: 4px 6px;">{{ $perwira->nama }}</td>
                    <td style="padding: 4px 6px;">{{ $perwira->pangkat_korps }}</td>
                    <td class="text-center" style="padding: 4px 4px;">{{ $perwira->nrp }}</td>
                    <td class="text-center" style="padding: 4px 2px;">{{ $perwira->tgl_1 ?: '-' }}</td>
                    <td class="text-center" style="padding: 4px 2px;">{{ $perwira->tgl_2 ?: '-' }}</td>
                    <td class="text-center" style="padding: 4px 2px;">{{ $perwira->tgl_3 ?: '-' }}</td>
                    <td class="text-center" style="padding: 4px 2px;">{{ $perwira->tgl_4 ?: '-' }}</td>
                    <td class="text-center" style="padding: 4px 2px;">{{ $perwira->tgl_5 ?: '-' }}</td>
                    <td class="text-center" style="padding: 4px 2px;">{{ $perwira->tgl_6 ?: '-' }}</td>
                    @if($hasTgl7)
                    <td class="text-center" style="padding: 4px 2px;">{{ $perwira->tgl_7 ?: '-' }}</td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $hasTgl7 ? 11 : 10 }}" class="text-center">Belum ada daftar perwira jaga.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Catatan SOP Jaga -->
        <div style="font-size: 11pt; margin-bottom: 10px;">
            <div style="font-weight: normal; margin-bottom: 2px;">Catatan:</div>
            <table style="width: 100%; font-size: 11pt;">
                <tr>
                    <td style="width: 20px; vertical-align: top;">1.</td>
                    <td style="text-align: justify; padding-left: 10px;">Perwira Siaga Intel bertanggung jawab atas keamanan dan kebersihan kantor Sintel dan Kantor Tim Intel.</td>
                </tr>
                <tr>
                    <td style="vertical-align: top; padding-top: 2px;">2.</td>
                    <td style="text-align: justify; padding-top: 2px; padding-left: 10px;">Apabila anggota Jaga yang melaksanakan ijin/cuti, sehari sebelumnya laporan kepada Wadantim/Pasops.</td>
                </tr>
                <tr>
                    <td style="vertical-align: top; padding-top: 2px;">3.</td>
                    <td style="text-align: justify; padding-top: 2px; padding-left: 10px;">Apabila ada pertukaran/ganti jaga harap melaporkan ke Wadantim/Pasops.</td>
                </tr>
            </table>
        </div>

        <!-- Kolom Tanda Tangan Lampiran 1 -->
        <table style="width: 100%; margin-top: 4px;">
            <tr>
                <td style="width: 42%;"></td>
                <td style="width: 58%;">
                    <div class="ttd-box">
                        <div style="font-size: 11.5pt; white-space: nowrap;">a.n. Komandan Detasemen Intelijen Koarmada 2,</div>
                        <div style="margin-bottom: 4px; font-size: 11.5pt;">Pasiops,</div>

                        @if($spJaga->ttd_type === 'tte' && $spJaga->status === 'published' && !empty($qr_base64))
                            <div style="margin: 4px auto; text-align: center;">
                                <img src="{{ $qr_base64 }}" style="width: 70px; height: 70px; display: inline-block;" alt="QR TTE" />
                            </div>
                        @else
                            <div style="height: 60px;"></div>
                        @endif

                        <div style="font-weight: normal; text-decoration: none; font-size: 12pt;">
                            {{ $pasiopsNama ?? ($spJaga->penandatangan_nama ?: 'Roni Sumantri') }}
                        </div>
                        <div style="font-size: 11pt;">
                            {{ $pasiopsPangkatNrp ?? ($spJaga->penandatangan_pangkat_nrp ?: 'Mayor Laut (P) NRP 17456/P') }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>

    </div>

    <!-- ========================================================================= -->
    <!-- HALAMAN 3: LAMPIRAN 2 - TABEL ANGGOTA JAGA SINTEL (KELOMPOK DIVISI)       -->
    <!-- ========================================================================= -->
    <div class="page-break" style="width: 100%;">
        
        <!-- Kop Kiri -->
        <div class="kop-surat" style="width: 320px; margin-bottom: 8px;">
            <div class="line1">KOMANDO ARMADA II</div>
            <div class="line2">DETASEMEN INTELIJEN</div>
            <div class="divider"></div>
        </div>

        <!-- Judul Lampiran 2 (Font 12pt Bold) -->
        <div style="text-align: center; margin-bottom: 10px;">
            <div style="font-size: 12pt; font-weight: bold; text-transform: uppercase;">
                DAFTAR NAMA ANGGOTA JAGA SINTEL KOARMADA 2
            </div>
            <div style="font-size: 12pt; font-weight: bold; text-transform: uppercase;">
                JL. STASIUN BENTENG UJUNG SURABAYA BULAN {{ $namaBulanTahun }}
            </div>
        </div>

        <!-- Tabel Anggota Jaga Divisi (Width 100% Fixed, Font di dalam tabel 10pt & 8.5pt NRP) -->
        <table class="table-bordered" style="width: 100%; table-layout: fixed; margin-bottom: 10px; font-size: 10pt;">
            <thead>
                <tr>
                    <th style="width: 4%;">NO</th>
                    <th style="width: 21%;">TANGGAL</th>
                    <th style="width: 25%;">NAMA</th>
                    <th style="width: 17%;">PANGKAT/<br>KORPS</th>
                    <th style="width: 22%;">NRP/NIP</th>
                    <th style="width: 11%;">KET</th>
                </tr>
                <tr style="background-color: #fafafa; font-size: 9pt;">
                    <th style="font-weight: normal; padding: 2px;">1</th>
                    <th style="font-weight: normal; padding: 2px;">2</th>
                    <th style="font-weight: normal; padding: 2px;">3</th>
                    <th style="font-weight: normal; padding: 2px;">4</th>
                    <th style="font-weight: normal; padding: 2px;">5</th>
                    <th style="font-weight: normal; padding: 2px;">6</th>
                </tr>
            </thead>
            <tbody>
                @forelse($spJaga->anggotas as $idx => $divisi)
                    @php
                        $items = is_array($divisi->anggota_items) ? $divisi->anggota_items : json_decode($divisi->anggota_items, true) ?? [];
                        $rawTgl = $divisi->tanggal_list_text;
                        $tglClean = preg_replace('/[A-Za-z]+\s+[0-9]{4}/i', $namaBulanTahun, $rawTgl);
                        if (!str_contains($tglClean, $namaBulanTahun)) {
                            $tglClean .= ' ' . $namaBulanTahun;
                        }
                    @endphp
                    <tr>
                        <td class="text-center" style="vertical-align: middle; font-weight: normal; padding: 5px 2px;">{{ $idx + 1 }}.</td>
                        <td class="text-center" style="vertical-align: middle; font-weight: bold; padding: 5px 4px; line-height: 1.35;">
                            {{ $tglClean }}
                        </td>
                        <td style="vertical-align: middle; padding: 5px 6px; line-height: 1.45;">
                            @foreach($items as $item)
                                <div style="font-weight: normal; white-space: nowrap;">{{ $item['nama'] ?? '-' }}</div>
                            @endforeach
                        </td>
                        <td style="vertical-align: middle; padding: 5px 6px; line-height: 1.45;">
                            @foreach($items as $item)
                                <div style="white-space: nowrap;">{{ $item['pangkat_korps'] ?? '-' }}</div>
                            @endforeach
                        </td>
                        <td class="text-center" style="vertical-align: middle; padding: 5px 2px; line-height: 1.45; font-size: 8.5pt;">
                            @foreach($items as $item)
                                <div style="white-space: nowrap; letter-spacing: -0.2px;">{{ $item['nrp_nip'] ?? '-' }}</div>
                            @endforeach
                        </td>
                        <td class="text-center" style="vertical-align: middle; padding: 5px 2px; line-height: 1.45;">
                            @foreach($items as $item)
                                <div style="font-weight: normal; white-space: nowrap;">{{ $item['role_jaga'] ?? 'ANGGOTA' }}</div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">Belum ada data regu anggota jaga.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Catatan SOP Jaga -->
        <div style="font-size: 11pt; margin-bottom: 8px;">
            <div style="font-weight: normal; margin-bottom: 2px;">Catatan:</div>
            <table style="width: 100%; font-size: 11pt;">
                <tr>
                    <td style="width: 20px; vertical-align: top;">1.</td>
                    <td style="text-align: justify; padding-left: 10px;">Anggota Siaga Sintel bertanggung jawab atas keamanan dan kebersihan kantor Sintel dan kantor Tim intel.</td>
                </tr>
                <tr>
                    <td style="vertical-align: top; padding-top: 2px;">2.</td>
                    <td style="text-align: justify; padding-top: 2px; padding-left: 10px;">Apabila anggota Divisi Jaga yang melaksanakan ijin / cuti, sehari sebelumnya laporan kepada Wadantim / Pasops.</td>
                </tr>
                <tr>
                    <td style="vertical-align: top; padding-top: 2px;">3.</td>
                    <td style="text-align: justify; padding-top: 2px; padding-left: 10px;">Apabila ada pertukaran/ganti jaga harap melaporkan ke Wadantim/Pasops.</td>
                </tr>
            </table>
        </div>

        <!-- Kolom Tanda Tangan Lampiran 2 -->
        <table style="width: 100%; margin-top: 4px;">
            <tr>
                <td style="width: 42%;"></td>
                <td style="width: 58%;">
                    <div class="ttd-box">
                        <table style="width: 100%; text-align: left; margin-bottom: 3px; font-size: 11.5pt;">
                            <tr>
                                <td style="width: 100px;">Dikeluarkan di</td>
                                <td style="width: 10px;">:</td>
                                <td style="padding-left: 10px;">Surabaya</td>
                            </tr>
                            <tr>
                                <td>pada tanggal</td>
                                <td>:</td>
                                <td style="padding-left: 10px;">{{ $tglSuratBaku }}</td>
                            </tr>
                        </table>
                        <div style="border-bottom: 1px solid #000; margin-bottom: 4px;"></div>

                        <div style="font-size: 11.5pt; white-space: nowrap;">a.n. Komandan Detasemen Intelijen Koarmada 2,</div>
                        <div style="margin-bottom: 4px; font-size: 11.5pt;">Pasiops,</div>

                        @if($spJaga->ttd_type === 'tte' && $spJaga->status === 'published' && !empty($qr_base64))
                            <div style="margin: 4px auto; text-align: center;">
                                <img src="{{ $qr_base64 }}" style="width: 70px; height: 70px; display: inline-block;" alt="QR TTE" />
                            </div>
                        @else
                            <div style="height: 60px;"></div>
                        @endif

                        <div style="font-weight: normal; text-decoration: none; font-size: 12pt;">
                            {{ $pasiopsNama ?? ($spJaga->penandatangan_nama ?: 'Roni Sumantri') }}
                        </div>
                        <div style="font-size: 11pt;">
                            {{ $pasiopsPangkatNrp ?? ($spJaga->penandatangan_pangkat_nrp ?: 'Mayor Laut (P) NRP 17456/P') }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>

    </div>

</body>
</html>