<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Buku Agenda Pengambilan SC</title>
    <style>
        @page { 
            margin: 1cm 1.5cm; 
        }
        body { 
            font-family: 'Times New Roman', Times, serif; 
            font-size: 11px; 
            line-height: 1.3;
            color: #000;
        }
        /* KOP SURAT RATA KIRI - TEKS TENGAH */
        .kop-container {
            text-align: left;
            margin-bottom: 25px;
        }
        .kop-inner {
            display: inline-block;
            text-align: center;
        }
        .kop-inner p {
            margin: 0;
            font-weight: bold;
            font-size: 13px;
            text-transform: uppercase;
            line-height: 1.2;
        }
        .garis-kop {
            border-top: 1px solid #000;
            height: 2px;
            width: 320px;
            margin: 5px auto 0 auto;
        }
        /* JUDUL */
        .judul-container {
            text-align: center;
            margin-bottom: 20px;
            text-transform: uppercase;
        }
        .judul-container h3 {
            margin: 0;
            font-size: 14px;
            text-decoration: underline;
            letter-spacing: 0.5px;
        }
        .judul-container p {
            margin: 4px 0 0 0;
            font-size: 10px;
            font-weight: bold;
        }
        /* TABEL RIGID SESUAI BUKU AGENDA SURAT */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th {
            background-color: #f2f2f2;
            border: 1px solid #000;
            padding: 8px 3px;
            font-size: 9.5px;
            text-transform: uppercase;
            text-align: center;
            font-weight: bold;
        }
        td {
            border: 1px solid #000;
            padding: 6px 5px;
            font-size: 10px;
            vertical-align: top;
            word-wrap: break-word; 
        }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }

        /* TANDA TANGAN KEDINASAN */
        .ttd-container {
            margin-top: 35px;
            width: 100%;
        }
        .ttd-wrapper {
            float: right;
            width: 250px;
            text-align: left;
        }
        .ttd-pejabat {
            text-align: center;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <!-- KOP RESMI DETASEMEN INTELIJEN -->
    <div class="kop-container">
        <div class="kop-inner">
            <p>KOMANDO ARMADA II</p>
            <p>DETASEMEN INTELIJEN</p>
            <div class="garis-kop"></div>
        </div>
    </div>

    <!-- JUDUL BUKU AGENDA -->
    <div class="judul-container">
        <h3>BUKU AGENDA PENGAMBILAN SECURITY CLEARANCE (SC)</h3>
        <p>{{ strtoupper($filter_kategori ?? 'SEMUA KATEGORI (DINAS & PERUSAHAAN)') }}</p>
        <p style="font-size: 9.5px; margin-top: 2px;">STATUS: {{ strtoupper($filter_pengambilan ?? 'SEMUA STATUS PENGAMBILAN') }}</p>
        @if(!empty($periode_text))
            <p style="font-weight: normal; font-size: 9.5px; text-transform: none;">Periode: {{ $periode_text }}</p>
        @endif
    </div>

    <!-- TABEL AGENDA PENGAMBILAN SC -->
    <table>
        <thead>
            <tr>
                <th style="width: 25px;">NO</th>
                <th style="width: 135px;">NOMOR SC</th>
                <th style="width: 75px;">TANGGAL SC</th>
                <th style="width: 85px;">TANGGAL DIAMBIL</th>
                <th>KETERANGAN</th>
            </tr>
        </thead>
        <tbody>
            @forelse($submissions as $index => $sub)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="font-bold text-center">
                    {{ $sub->nomor_sc ?: ($sub->tracking_code ?: '-') }}
                </td>
                <td class="text-center">
                    @if($sub->tanggal_sc)
                        {{ \Illuminate\Support\Carbon::parse($sub->tanggal_sc)->format('d/m/Y') }}
                    @elseif($sub->created_at)
                        {{ $sub->created_at->format('d/m/Y') }}
                    @else
                        -
                    @endif
                </td>
                <td class="text-center font-bold">
                    @if($sub->is_taken && $sub->taken_at)
                        {{ \Illuminate\Support\Carbon::parse($sub->taken_at)->format('d/m/Y') }}
                        @if($sub->taken_by && strtolower($sub->taken_by) !== strtolower($sub->nama))
                            <br><span style="font-size: 8.5px; font-weight: normal;">(Oleh: {{ $sub->taken_by }})</span>
                        @endif
                    @else
                        <span style="color: #666; font-style: italic; font-weight: normal;">Belum Diambil</span>
                    @endif
                </td>
                <td>
                    <div class="font-bold">
                        [{{ $sub->nama ?: '-' }}]
                    </div>
                    <div style="margin-top: 2px;">
                        [{{ strtoupper($sub->identifier_type ?: 'NRP') }}. {{ $sub->identifier_number ?: '-' }}{{ $sub->pangkat_korps ? ' - ' . $sub->pangkat_korps : '' }}]
                    </div>
                    <div style="margin-top: 2px; font-style: italic; color: #111;">
                        [{{ $sub->keperluan ?: 'Kedinasan' }}{{ $sub->kesatuan ? ' - ' . $sub->kesatuan : '' }}]
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center" style="padding: 20px; font-style: italic;">
                    Tidak ada catatan berkas Security Clearance pada kriteria yang dipilih.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- TANDA TANGAN KEDINASAN -->
    <div class="ttd-container">
        <div class="ttd-wrapper">
            <p>Dikeluarkan di: Surabaya</p>
            <p>Pada tanggal: {{ now()->translatedFormat('d F Y') }}</p>
            <div class="ttd-pejabat">
                <p class="font-bold">a.n. Komandan Detasemen Intelijen</p>
                <p class="font-bold">Perwira Administrasi,</p>
                <p style="margin-top: 65px;" class="font-bold"><u>{{ auth()->user()->name ?? 'Administrator' }}</u></p>
                <p>{{ auth()->user()->pangkat ?? 'Perwira Pertama' }} {{ auth()->user()->nrp ? 'NRP. ' . auth()->user()->nrp : '' }}</p>
            </div>
        </div>
    </div>
</body>
</html>
