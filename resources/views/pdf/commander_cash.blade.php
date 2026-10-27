<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>REKENING KORAN - REKENING KOMANDAN - {{ $periodLabel ?? date('Y') }}</title>
    <style>
        @page {
            margin: 1.2cm 1.5cm 1.5cm 1.5cm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9.5px;
            color: #1e293b;
            line-height: 1.35;
        }
        
        /* HEADER KOP */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }
        .kop-left {
            text-align: left;
            vertical-align: top;
            width: 55%;
        }
        .kop-instansi {
            font-size: 10px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            line-height: 1.2;
            letter-spacing: 0.5px;
        }
        .kop-right {
            text-align: right;
            vertical-align: top;
            width: 45%;
        }
        .statement-title {
            font-size: 15px;
            font-weight: 900;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }
        .statement-subtitle {
            font-size: 8.5px;
            color: #64748b;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 2px;
        }

        /* INFO REKENING BOX */
        .info-card {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
        }
        .info-card td {
            padding: 5px 10px;
            vertical-align: middle;
            font-size: 9px;
        }
        .info-label {
            color: #64748b;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8px;
            width: 22%;
        }
        .info-value {
            color: #0f172a;
            font-weight: bold;
            width: 28%;
        }

        /* RINGKASAN SALDO BOX */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            border: 1px solid #0284c7;
        }
        .summary-header-cell {
            background-color: #0284c7;
            color: #ffffff;
            font-size: 8px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 5px 8px;
            text-align: center;
            border: 1px solid #0284c7;
        }
        .summary-data-cell {
            background-color: #f0f9ff;
            color: #0f172a;
            font-size: 10.5px;
            font-weight: bold;
            padding: 6px 8px;
            text-align: center;
            border: 1px solid #bae6fd;
            font-family: 'Courier New', Courier, monospace;
        }
        .summary-data-ending {
            background-color: #e0f2fe;
            color: #0369a1;
            font-size: 11.5px;
            font-weight: 900;
        }
        .summary-subtext {
            font-size: 7.5px;
            font-weight: normal;
            color: #475569;
            display: block;
            margin-top: 1px;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }

        /* TABEL MUTASI */
        .ledger-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .ledger-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 6px 5px;
            border: 1px solid #0f172a;
            text-align: center;
        }
        .ledger-table td {
            padding: 5px 6px;
            border: 1px solid #cbd5e1;
            font-size: 8.5px;
            vertical-align: top;
        }
        .row-even {
            background-color: #ffffff;
        }
        .row-odd {
            background-color: #f8fafc;
        }
        .row-initial {
            background-color: #fefce8;
            font-weight: bold;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-mono { font-family: 'Courier New', Courier, monospace; font-size: 9px; }
        .font-bold { font-weight: bold; }
        .text-masuk { color: #059669; }
        .text-keluar { color: #dc2626; }
        .text-saldo { color: #0284c7; font-weight: bold; }

        /* FOOTER TOTALS */
        .total-row td {
            background-color: #f1f5f9;
            font-weight: bold;
            border-top: 2px solid #0f172a;
            border-bottom: 2px solid #0f172a;
            padding: 6px 6px;
        }

        /* CATATAN & SIGNATURE */
        .bottom-container {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .bottom-container td {
            vertical-align: top;
        }
        .notice-box {
            font-size: 8px;
            color: #64748b;
            line-height: 1.35;
            padding: 8px;
            border: 1px dashed #cbd5e1;
            background-color: #fafafa;
            border-radius: 4px;
        }
        .sign-box {
            text-align: center;
            width: 220px;
            float: right;
            font-size: 9px;
        }
        .sign-space {
            height: 55px;
        }
    </style>
</head>
<body>

    <!-- KOP RESMI PERBANKAN / KEDINASAN -->
    <table class="kop-table">
        <tr>
            <td class="kop-left">
                <div class="kop-instansi">
                    KOMANDO ARMADA II<br>
                    DETASEMEN INTELIJEN<br>
                    <span style="font-size: 8px; font-weight: normal; color: #475569; text-transform: none;">
                        Sistem Informasi Detasemen Intelijen (SINDEN)
                    </span>
                </div>
            </td>
            <td class="kop-right">
                <h1 class="statement-title">REKENING KORAN</h1>
                <div class="statement-subtitle">BANK ACCOUNT STATEMENT &bull; LOGISTIK KOMANDAN</div>
            </td>
        </tr>
    </table>

    <!-- KOTAK IDENTITAS REKENING -->
    <table class="info-card">
        <tr>
            <td class="info-label">Nama Rekening</td>
            <td class="info-value">{{ $accountHolder ?? 'REKENING KOMANDAN' }}</td>
            <td class="info-label">Periode Mutasi</td>
            <td class="info-value" style="color: #0284c7;">{{ $periodDates ?? $periodLabel }}</td>
        </tr>
        <tr>
            <td class="info-label">Nomor Rekening</td>
            <td class="info-value font-mono">{{ $accountNo ?? 'SINDEN-KMD-01' }}</td>
            <td class="info-label">Mata Uang</td>
            <td class="info-value">IDR (Indonesian Rupiah)</td>
        </tr>
        <tr>
            <td class="info-label">Jenis Pembukuan</td>
            <td class="info-value">Kas Logistik Khusus Komandan</td>
            <td class="info-label">Tanggal Cetak</td>
            <td class="info-value">{{ date('d F Y, H:i') }} WIB</td>
        </tr>
    </table>

    <!-- RINGKASAN SALDO (ACCOUNT SUMMARY) -->
    <table class="summary-table">
        <thead>
            <tr>
                <th class="summary-header-cell" style="width: 25%;">SALDO AWAL</th>
                <th class="summary-header-cell" style="width: 25%;">TOTAL PEMASUKAN (+)</th>
                <th class="summary-header-cell" style="width: 25%;">TOTAL PENGELUARAN (-)</th>
                <th class="summary-header-cell" style="width: 25%;">SALDO AKHIR</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="summary-data-cell">
                    Rp {{ number_format($saldoAwal ?? 0, 0, ',', '.') }}
                    <span class="summary-subtext">Saldo per awal periode</span>
                </td>
                <td class="summary-data-cell" style="color: #059669;">
                    Rp {{ number_format($totalMasuk ?? 0, 0, ',', '.') }}
                    <span class="summary-subtext">{{ $countMasuk ?? 0 }} transaksi masuk</span>
                </td>
                <td class="summary-data-cell" style="color: #dc2626;">
                    Rp {{ number_format($totalKeluar ?? 0, 0, ',', '.') }}
                    <span class="summary-subtext">{{ $countKeluar ?? 0 }} transaksi keluar</span>
                </td>
                <td class="summary-data-cell summary-data-ending">
                    Rp {{ number_format($saldoAkhir ?? 0, 0, ',', '.') }}
                    <span class="summary-subtext" style="color: #0284c7; font-weight: bold;">Sisa kas tersedia</span>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- TABEL MUTASI TRANSAKSI -->
    <table class="ledger-table">
        <thead>
            <tr>
                <th style="width: 4%;">NO</th>
                <th style="width: 10%;">TANGGAL</th>
                <th style="width: 42%;">URAIAN / KETERANGAN TRANSAKSI</th>
                <th style="width: 12%;">PETUGAS</th>
                <th style="width: 16%;">PENERIMAAN (MASUK)</th>
                <th style="width: 16%;">PENGELUARAN (KELUAR)</th>
                <th style="width: 16%;">SALDO (BALANCE)</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp

            @if(!empty($month) && ($saldoAwal ?? 0) != 0)
                <!-- Baris Saldo Awal -->
                <tr class="row-initial">
                    <td class="text-center">-</td>
                    <td class="text-center">{{ date('01/m/Y', strtotime($month . '-01')) }}</td>
                    <td colspan="2">
                        <span style="letter-spacing: 0.5px;">SALDO AWAL PERIODE (BEGINNING BALANCE)</span>
                    </td>
                    <td class="text-right">-</td>
                    <td class="text-right">-</td>
                    <td class="text-right font-mono text-saldo">
                        Rp {{ number_format($saldoAwal, 0, ',', '.') }}
                    </td>
                </tr>
            @endif

            @forelse($logs as $log)
                <tr class="{{ $loop->even ? 'row-even' : 'row-odd' }}">
                    <td class="text-center font-mono">{{ $no++ }}</td>
                    <td class="text-center whitespace-nowrap">{{ date('d/m/Y', strtotime($log->tanggal)) }}</td>
                    <td>
                        <span class="font-bold" style="text-transform: uppercase;">{{ $log->keterangan }}</span>
                    </td>
                    <td class="text-center" style="font-size: 7.5px; color: #475569;">
                        {{ $log->petugas_input ?? 'ADMIN' }}
                    </td>
                    <td class="text-right font-mono text-masuk">
                        {{ $log->jenis == 'MASUK' ? '+ Rp ' . number_format($log->jumlah, 0, ',', '.') : '-' }}
                    </td>
                    <td class="text-right font-mono text-keluar">
                        {{ $log->jenis == 'KELUAR' ? '- Rp ' . number_format($log->jumlah, 0, ',', '.') : '-' }}
                    </td>
                    <td class="text-right font-mono text-saldo">
                        Rp {{ number_format($log->saldo_berjalan ?? 0, 0, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="padding: 25px; color: #64748b; font-style: italic;">
                        Tidak terdapat catatan mutasi transaksi pada periode ini.
                    </td>
                </tr>
            @endforelse

            <!-- BARIS TOTAL MUTASI -->
            <tr class="total-row">
                <td colspan="4" class="text-right" style="text-transform: uppercase; font-size: 9px;">
                    TOTAL MUTASI PERIODE INI:
                </td>
                <td class="text-right font-mono text-masuk" style="font-size: 9.5px;">
                    + Rp {{ number_format($totalMasuk ?? 0, 0, ',', '.') }}
                </td>
                <td class="text-right font-mono text-keluar" style="font-size: 9.5px;">
                    - Rp {{ number_format($totalKeluar ?? 0, 0, ',', '.') }}
                </td>
                <td class="text-right font-mono text-saldo" style="font-size: 10px; background-color: #e0f2fe;">
                    Rp {{ number_format($saldoAkhir ?? 0, 0, ',', '.') }}
                </td>
            </tr>
        </tbody>
    </table>

    <!-- LEMBAR PENGESAHAN & CATATAN -->
    <table class="bottom-container">
        <tr>
            <td style="width: 58%; padding-right: 15px;">
                <div class="notice-box">
                    <b>CATATAN RESMI PERBANKAN / KEDINASAN:</b><br>
                    1. Rekening koran ini dihasilkan secara otomatis melalui Sistem Informasi Detasemen Intelijen (SINDEN).<br>
                    2. Seluruh pencatatan arus dana keluar dan masuk diverifikasi secara real-time berdasarkan buku kas pangkalan.<br>
                    3. Saldo berjalan (running balance) dihitung berurutan berdasarkan mutasi transaksi yang telah diverifikasi.
                </div>
            </td>
            <td style="width: 42%;">
                <div class="sign-box">
                    <p style="margin: 0;">Surabaya, {{ date('d F Y') }}</p>
                    <p style="margin: 2px 0 0 0; font-weight: bold;">A.n. Komandan Detasemen Intelijen</p>
                    <p style="margin: 0; color: #475569;">Petugas Logistik Keuangan,</p>
                    <div class="sign-space"></div>
                    <p style="margin: 0; font-weight: bold; text-decoration: underline; text-transform: uppercase;">
                        {{ Auth::user()->name ?? 'I GUSTI SULTAN H.A, A.Md.Kom' }}
                    </p>
                    <p style="margin: 1px 0 0 0; font-size: 8px; color: #475569;">
                        {{ strtoupper(Auth::user()->pangkat ?? 'PRAJURIT') }} &bull; NRP. {{ Auth::user()->nrp ?? '---------' }}
                    </p>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>