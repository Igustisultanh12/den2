<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Buku Kas Digital</title>
    <style>
        /* --- PROTOKOL KERANGKA DINAS --- */
        @page { 
            margin: 1cm 1.5cm; 
        }
        
        body { 
            font-family: 'Times New Roman', Times, serif; 
            font-size: 11px; 
            margin: 0;
            padding: 0;
            color: #000;
        }
        
        /* KOP SURAT RATA KIRI DENGAN GARIS DINAS (SESUAI INSTRUKSI) */
        .kop-surat {
            width: 100%;
            margin-bottom: 30px;
            text-align: left;
            text-transform: uppercase;
        }

        .kop-surat p {
            margin: 0;
            line-height: 1.1;
            font-weight: bold;
            font-size: 14px;
            letter-spacing: -0.5px;
        }

        .garis-kop {
            border-bottom: 2px solid #000;
            margin-top: 8px;
            width: 320px; /* Lebar garis kop mengikuti teks Satuan */
        }

        /* JUDUL LAPORAN (CENTERED) */
        .judul-container {
            text-align: center;
            margin-top: 25px;
            margin-bottom: 20px;
        }
        
        .judul-container h3 {
            margin: 0;
            font-size: 15px;
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
        }
        
        .judul-container p {
            margin: 5px 0 0 0;
            font-size: 10px;
            font-style: italic;
            font-weight: bold;
        }

        /* TABEL TRANSAKSI STANDAR */
        table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        th { 
            background: #f2f2f2; 
            border: 1px solid #000; 
            padding: 8px 4px; 
            text-align: center; 
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
        }
        td { 
            border: 1px solid #000; 
            padding: 6px 4px; 
            font-size: 10px; 
            vertical-align: middle;
        }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: 'Courier New', Courier, monospace; }
        
        /* FOOTER TOTAL SALDO */
        .footer-total { 
            background: #eee; 
            font-weight: bold; 
        }
    </style>
</head>
<body>

    <div class="kop-surat">
        <p>KOMANDO ARMADA II</p>
        <p>DETASEMEN INTELIJEN</p>
        <div class="garis-kop"></div>
    </div>

    <div class="judul-container">
        <h3>REKENING KORAN BUKU KAS</h3>
        <p>Periode: Awal s/d Sekarang (SINDEN Digital System)</p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">NO</th>
                <th width="15%">TANGGAL</th>
                <th width="32%">KETERANGAN</th>
                <th width="16%">DEBIT (IN)</th>
                <th width="16%">KREDIT (OUT)</th>
                <th width="16%">SALDO</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @forelse($cashes as $cash)
            <tr>
                <td class="text-center">{{ $no++ }}</td>
                <td class="text-center">{{ \Carbon\Carbon::parse($cash->date)->format('d/m/Y') }}</td>
                <td style="text-transform: uppercase;">{{ $cash->description }}</td>
                <td class="text-right font-mono">
                    {{ $cash->debit > 0 ? number_format($cash->debit, 0, ',', '.') : '-' }}
                </td>
                <td class="text-right font-mono">
                    {{ $cash->credit > 0 ? number_format($cash->credit, 0, ',', '.') : '-' }}
                </td>
                <td class="text-right font-mono" style="font-weight: bold;">
                    {{ number_format($cash->balance, 0, ',', '.') }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center" style="padding: 30px; font-style: italic;"> Belum ada transmisi data kas pada pangkalan database.
                </td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="footer-total">
                <td colspan="5" class="text-right" style="padding: 10px; text-transform: uppercase;">Total Saldo Akhir :</td>
                <td class="text-right font-mono" style="padding: 10px; font-size: 11px;"> Rp {{ number_format($totalSaldo, 0, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>

</body>
</html>