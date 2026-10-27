<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Kuitansi Pembayaran Cicilan - {{ $installment->receipt_number }}</title>
    <style>
        @page {
            size: a5 landscape;
            margin: 0.8cm 1cm 0.8cm 1cm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 5px;
            margin-bottom: 12px;
        }
        .title {
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 2px;
        }
        .subtitle {
            text-align: center;
            font-size: 9pt;
            color: #475569;
            margin-bottom: 12px;
        }
        .receipt-box {
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 12px;
        }
        .data-table {
            width: 100%;
            font-size: 10pt;
            border-collapse: collapse;
        }
        .data-table td {
            padding: 3.5px 0;
            vertical-align: top;
        }
        .label-col {
            width: 155px;
            color: #334155;
            font-weight: 500;
        }
        .colon-col {
            width: 15px;
            text-align: center;
        }
        .nominal-badge {
            display: inline-block;
            background-color: #e0f2fe;
            border: 1.5px solid #0284c7;
            color: #0369a1;
            font-size: 12pt;
            font-weight: bold;
            padding: 5px 12px;
            border-radius: 4px;
            margin-top: 4px;
        }
        .ttd-table {
            width: 100%;
            margin-top: 15px;
        }
        .ttd-table td {
            text-align: center;
            vertical-align: top;
            width: 50%;
        }
    </style>
</head>
<body>

    <!-- Header / Kop Dokumen -->
    <table class="header-table">
        <tr>
            <td style="width: 60%; vertical-align: middle;">
                <div style="font-size: 8.5pt; font-weight: bold; text-transform: uppercase; line-height: 1.2;">
                    KOMANDO ARMADA II<br>
                    DETASEMEN INTELIJEN<br>
                    <span style="color: #0284c7;">UNIT SIMPAN PINJAM (KOPERASI SINDEN)</span>
                </div>
            </td>
            <td style="width: 40%; text-align: right; vertical-align: middle;">
                <div style="font-size: 9pt; font-weight: bold; color: #0f172a;">
                    BUKTI TRANSAKSI ANGSURAN
                </div>
                <div style="font-size: 8pt; color: #64748b; font-family: monospace;">
                    NO: {{ $installment->receipt_number }}
                </div>
            </td>
        </tr>
    </table>

    <!-- Judul -->
    <div class="title">KUITANSI PEMBAYARAN CICILAN</div>
    <div class="subtitle">Tanda Terima Pembayaran Angsuran Pinjaman Koperasi Personel SINDEN</div>

    <!-- Data Detail Transaksi -->
    <div class="receipt-box">
        <table class="data-table">
            <tr>
                <td class="label-col">Telah Diterima Dari</td>
                <td class="colon-col">:</td>
                <td style="font-weight: bold; text-transform: uppercase;">
                    {{ $installment->user->pangkat ?? '' }} {{ $installment->user->name }}
                </td>
            </tr>
            <tr>
                <td class="label-col">NRP / NIP Personel</td>
                <td class="colon-col">:</td>
                <td style="font-family: monospace; font-weight: bold;">
                    {{ $installment->user->nrp ?: '-' }}
                </td>
            </tr>
            <tr>
                <td class="label-col">Nomor Kontrak Pinjaman</td>
                <td class="colon-col">:</td>
                <td style="font-family: monospace;">
                    {{ $installment->loan->loan_code }} (Tenor {{ $installment->loan->duration_months }} Bulan)
                </td>
            </tr>
            <tr>
                <td class="label-col">Angsuran Ke</td>
                <td class="colon-col">:</td>
                <td>
                    <b>Angsuran Ke-{{ $installment->installment_no }}</b>
                    <span style="color: #64748b; font-size: 9pt; margin-left: 8px;">
                        (Metode: {{ strtoupper(str_replace('_', ' ', $installment->payment_method)) }})
                    </span>
                </td>
            </tr>
            <tr>
                <td class="label-col">Tanggal Pembayaran</td>
                <td class="colon-col">:</td>
                <td>
                    {{ \Carbon\Carbon::parse($installment->payment_date)->isoFormat('dddd, D MMMM Y') }}
                </td>
            </tr>
            <tr>
                <td class="label-col" style="padding-top: 6px;">Nominal Pembayaran</td>
                <td class="colon-col" style="padding-top: 6px;">:</td>
                <td>
                    <div class="nominal-badge">
                        Rp {{ number_format($installment->amount_paid, 0, ',', '.') }}
                    </div>
                </td>
            </tr>
            <tr>
                <td class="label-col">Sisa Pokok Pinjaman</td>
                <td class="colon-col">:</td>
                <td style="font-weight: bold; color: {{ $installment->remaining_loan_after <= 0 ? '#16a34a' : '#ea580c' }};">
                    @if($installment->remaining_loan_after <= 0)
                        LUNAS SEPENUHNYA (Rp 0)
                    @else
                        Rp {{ number_format($installment->remaining_loan_after, 0, ',', '.') }}
                    @endif
                </td>
            </tr>
            @if($installment->notes)
            <tr>
                <td class="label-col">Catatan Petugas</td>
                <td class="colon-col">:</td>
                <td style="font-style: italic; color: #64748b;">
                    {{ $installment->notes }}
                </td>
            </tr>
            @endif
        </table>
    </div>

    <!-- Tanda Tangan -->
    <table class="ttd-table">
        <tr>
            <td>
                <div style="font-size: 9pt; color: #475569;">Penyetor / Anggota Peminjam,</div>
                <div style="height: 48px;"></div>
                <div style="font-weight: bold; text-decoration: underline; text-transform: uppercase; font-size: 9.5pt;">
                    {{ $installment->user->name }}
                </div>
                <div style="font-size: 8.5pt; color: #64748b;">
                    {{ $installment->user->pangkat }} NRP {{ $installment->user->nrp }}
                </div>
            </td>
            <td>
                <div style="font-size: 9pt; color: #475569;">
                    Surabaya, {{ \Carbon\Carbon::parse($installment->payment_date)->isoFormat('D MMMM Y') }}<br>
                    Petugas / Bendahara Koperasi,
                </div>
                <div style="height: 48px;"></div>
                <div style="font-weight: bold; text-decoration: underline; text-transform: uppercase; font-size: 9.5pt;">
                    {{ $installment->recorder->name ?? 'Pengurus Koperasi' }}
                </div>
                <div style="font-size: 8.5pt; color: #64748b;">
                    Unit Simpan Pinjam SINDEN
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
