<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 1cm 1.5cm; }
        body { 
            font-family: 'Times New Roman', Times, serif; 
            font-size: 11px; 
            line-height: 1.3;
            color: #000;
        }
        /* KOP SURAT RATA KIRI - TEKS TENGAH */
        .kop-container {
            text-align: left;
            margin-bottom: 30px;
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
            font-size: 15px;
            text-decoration: underline;
        }
        /* TABEL RIGID (PENGATURAN LEBAR PAKSA) */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed; /* WAJIB: Agar width th dipatuhi */
        }
        th {
            background-color: #f2f2f2;
            border: 1px solid #000;
            padding: 8px 2px;
            font-size: 9px;
            text-transform: uppercase;
        }
        td {
            border: 1px solid #000;
            padding: 6px 4px;
            font-size: 10px;
            vertical-align: top;
            word-wrap: break-word; 
        }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }

        /* TANDA TANGAN */
        .ttd-container {
            margin-top: 40px;
            width: 100%;
        }
        .ttd-wrapper {
            float: right;
            width: 250px;
            text-align: left;
        }
        .ttd-pejabat {
            text-align: center;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="kop-container">
        <div class="kop-inner">
            <p>KOMANDO ARMADA II</p>
            <p>DETASEMEN INTELIJEN</p>
            <div class="garis-kop"></div>
        </div>
    </div>

    <div class="judul-container">
        <h3>LAPORAN REKAPITULASI PENOMORAN SURAT</h3>
        <p style="font-size: 10px; font-weight: bold; margin-top: 5px;"> KATEGORI: {{ $category_name }}
        </p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 20px !important;">NO</th>
                <th style="width: 130px;">NOMOR SURAT</th>
                <th style="width: 110px;">ALAMAT TUJUAN</th>
                <th>PERIHAL</th>
                <th style="width: 70px;">TANGGAL</th>
                <th style="width: 70px;">STATUS</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $index => $log)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="font-bold">{{ $log->full_number }}</td>
                <td>{{ $log->recipient ?? '-' }}</td>
                <td style="font-style: italic;">"{{ $log->subject }}"</td>
                <td class="text-center">{{ $log->date->format('d/m/Y') }}</td>
                <td class="text-center font-bold" style="font-size: 9px;">{{ $log->is_archived ? 'TERARSIP' : 'PENDING' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="ttd-container">
        <div class="ttd-wrapper">
            <p>Dikeluarkan di: Surabaya</p>
            <p>Pada tanggal: {{ now()->translatedFormat('d F Y') }}</p>
            <div class="ttd-pejabat">
                <p class="font-bold">a.n. Komandan Datasemen Intelijen</p>
                <p class="font-bold">Perwira Administrasi,</p>
                <p style="margin-top: 70px;" class="font-bold"><u>{{ auth()->user()->name }}</u></p>
                <p>{{ auth()->user()->pangkat ?? 'Letnan Dua' }} NRP. {{ auth()->user()->nrp ?? '-' }}</p>
            </div>
        </div>
    </div>
</body>
</html>