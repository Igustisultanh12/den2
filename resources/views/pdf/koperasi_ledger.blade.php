<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rekapitulasi Buku Kas & Pinjaman Koperasi SINDEN</title>
    <style>
        @page {
            size: a4 landscape;
            margin: 1cm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
            color: #000;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        .title {
            font-size: 13pt;
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        th, td {
            border: 1px solid #000;
            padding: 4px 6px;
            font-size: 8.5pt;
        }
        th {
            background-color: #f1f5f9;
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>

    <div class="header">
        <div style="font-size: 10pt; font-weight: bold; text-transform: uppercase;">KOMANDO ARMADA II</div>
        <div style="font-size: 10pt; font-weight: bold; text-transform: uppercase;">DETASEMEN INTELIJEN - UNIT SIMPAN PINJAM (SINDEN)</div>
        <div style="border-bottom: 2px solid #000; margin: 4px 0 10px 0;"></div>
        <div class="title">LAPORAN MUTASI KAS & REKAP PINJAMAN KOPERASI</div>
        <div style="font-size: 9pt; margin-top: 3px;">Status Per Tanggal: {{ \Carbon\Carbon::now()->isoFormat('D MMMM Y') }}</div>
    </div>

    <!-- Ringkasan Pinjaman Berjalan -->
    <div style="font-weight: bold; margin-bottom: 4px; text-transform: uppercase;">I. DAFTAR PINJAMAN ANGGOTA</div>
    <table>
        <thead>
            <tr>
                <th style="width: 4%;">NO</th>
                <th style="width: 15%;">KODE PINJAMAN</th>
                <th style="width: 20%;">NAMA ANGGOTA</th>
                <th style="width: 13%;">PANGKAT / NRP</th>
                <th style="width: 12%;">PLAFON (RP)</th>
                <th style="width: 12%;">TOTAL DIBAYAR (RP)</th>
                <th style="width: 12%;">SISA HUTANG (RP)</th>
                <th style="width: 12%;">STATUS</th>
            </tr>
        </thead>
        <tbody>
            @forelse($loans as $idx => $loan)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td class="text-center" style="font-family: monospace;">{{ $loan->loan_code }}</td>
                <td style="text-transform: uppercase;">{{ $loan->user->name ?? '-' }}</td>
                <td class="text-center">{{ $loan->user->pangkat ?? '' }} {{ $loan->user->nrp ?? '' }}</td>
                <td class="text-right">{{ number_format($loan->amount_approved ?: $loan->amount_requested, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($loan->total_paid, 0, ',', '.') }}</td>
                <td class="text-right" style="font-weight: bold;">{{ number_format($loan->remaining_amount, 0, ',', '.') }}</td>
                <td class="text-center" style="font-weight: bold; text-transform: uppercase;">{{ $loan->status }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center">Belum ada data pinjaman.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Mutasi Kas Koperasi -->
    <div style="font-weight: bold; margin-top: 15px; margin-bottom: 4px; text-transform: uppercase;">II. BUKU KAS & MUTASI KEUANGAN KOPERASI</div>
    <table>
        <thead>
            <tr>
                <th style="width: 4%;">NO</th>
                <th style="width: 12%;">TANGGAL</th>
                <th style="width: 15%;">KODE TRANSAKSI</th>
                <th style="width: 16%;">KATEGORI</th>
                <th style="width: 25%;">URAIAN KETERANGAN</th>
                <th style="width: 14%;">DEBET / KELUAR (RP)</th>
                <th style="width: 14%;">KREDIT / MASUK (RP)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($mutations as $idx => $mut)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td class="text-center">{{ \Carbon\Carbon::parse($mut->date)->format('d/m/Y') }}</td>
                <td class="text-center" style="font-family: monospace;">{{ $mut->transaction_code }}</td>
                <td class="text-center">{{ strtoupper(str_replace('_', ' ', $mut->category)) }}</td>
                <td>{{ $mut->description }}</td>
                <td class="text-right">{{ $mut->type === 'out' ? number_format($mut->amount, 0, ',', '.') : '-' }}</td>
                <td class="text-right">{{ $mut->type === 'in' ? number_format($mut->amount, 0, ',', '.') : '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center">Belum ada catatan mutasi kas.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
