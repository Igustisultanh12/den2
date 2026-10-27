<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Kode Verifikasi Personel - SINDEN</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 1.2cm 1.5cm;
        }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #000; line-height: 1.4; margin: 0; padding: 0; }
        .kop-surat { font-weight: bold; font-size: 12px; margin-bottom: 2px; }
        .title-block { text-align: center; margin-bottom: 20px; }
        .title { font-size: 15px; font-weight: bold; text-decoration: underline; margin-bottom: 4px; }
        .subtitle { font-size: 11px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table, th, td { border: 1px solid #000; }
        th { padding: 8px 6px; font-weight: bold; background-color: #f2f2f2; text-align: center; font-size: 10px; }
        td { padding: 6px 8px; font-size: 10px; }
        .text-center { text-align: center; }
        .otp-code { font-size: 11px; font-weight: bold; text-align: center; background-color: #f9f9f9; }
        .instruction-box { border: 1px solid #999; padding: 10px 14px; margin-top: 20px; font-size: 10px; background-color: #f9f9f9; }
        .instruction-box ol { margin: 5px 0 0 16px; padding: 0; }
        .instruction-box li { margin-bottom: 3px; }
        .signature-block { float: right; margin-top: 30px; width: 320px; font-size: 11px; text-align: center; page-break-inside: avoid; }
        .clearfix::after { content: ''; display: table; clear: both; }
        .info-row { font-size: 10px; color: #555; margin-top: 4px; }
        .summary-box { font-size: 10px; margin-top: 8px; }
    </style>
</head>
<body>
    {{-- KOP SURAT --}}
    <div style="display: inline-block; border-bottom: 2px solid #000; padding-bottom: 3px; margin-bottom: 20px; text-align: center;">
        <div class="kop-surat">KOMANDO ARMADA II</div>
        <div class="kop-surat">DETASEMEN INTELIJEN</div>
    </div>

    {{-- JUDUL --}}
    <div class="title-block">
        <div class="title">LAPORAN KODE VERIFIKASI AKUN PERSONEL</div>
        <div class="subtitle">NOMOR: {{ $nomorSurat ?? 'SINDEN/VERIF/' . date('Ymd/His') }}</div>
        <div class="info-row">Diterbitkan: {{ $generatedAt ?? date('d F Y') }}</div>
    </div>

    {{-- RINGKASAN --}}
    <div class="summary-box">
        Jumlah Personel: <strong>{{ count($personels) }} orang</strong> &nbsp;|&nbsp;
        Masa berlaku Token: <strong>Berlaku sampai diaktifkan oleh personel</strong>
    </div>

    {{-- TABEL KEKUATAN PERSONEL + KODE VERIFIKASI --}}
    <table>
        <thead>
            <tr>
                <th style="width: 4%;">NO</th>
                <th style="width: 16%;">NRP / NIP.</th>
                <th>NAMA LENGKAP</th>
                <th style="width: 20%;">PANGKAT / JABATAN</th>
                <th style="width: 22%;">EMAIL REGISTRASI</th>
                <th style="width: 14%;">NOMOR HP</th>
                <th style="width: 18%;">KODE VERIFIKASI</th>
            </tr>
        </thead>
        <tbody>
            @foreach($personels as $p)
            <tr>
                <td class="text-center">{{ $loop->iteration }}</td>
                <td class="text-center" style="font-weight: bold;">{{ $p->nrp ?? '-' }}</td>
                <td style="font-weight: bold;">{{ $p->name }}</td>
                <td class="text-center">
                    @php
                        $roleMapping = [
                            'admin' => 'ADMINISTRATOR SISTEM',
                            'komandan' => 'KOMANDAN',
                            'wadan' => 'WAKIL KOMANDAN',
                            'pasops' => 'PASOPS',
                            'danunit1' => 'DAN UNIT I / LID',
                            'danunit2' => 'DAN UNIT II / PAMGAL',
                            'danunitteknis' => 'DAN UNIT TEKNIS',
                            'kaurmintel' => 'KAUR MINTEL',
                            'paurset' => 'PAUR SET',
                            'staf' => 'STAF ADMINISTRASI',
                            'personel' => 'PERSONEL SATUAN'
                        ];
                        $jName = $roleMapping[$p->role ?? 'personel'] ?? 'PERSONEL';
                    @endphp
                    {{ $p->pangkat ?? '-' }} / {{ $jName }}
                </td>
                <td>{{ $p->email }}</td>
                <td class="text-center">{{ $p->phone ?? '-' }}</td>
                <td class="otp-code">{{ $p->activation_token ?? 'SINDEN-XQOWES' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- INSTRUKSI --}}
    <div class="instruction-box">
        <strong>PETUNJUK PENGGUNAAN KODE VERIFIKASI:</strong>
        <ol>
            <li>Buka halaman <strong>https://sisinden.my.id/aktivasi</strong> dan masukkan NRP / NIP. atau Email Anda.</li>
            <li>Setelah mengisi formulir, masukkan <strong>Kode Verifikasi</strong> (Token) sesuai nama Anda pada tabel di atas.</li>
            <li>Buat Kata Sandi (Password) baru minimal 8 karakter lalu konfirmasi.</li>
            <li>Klik tombol <strong>Aktifkan Otoritas Akun</strong>. Akun Anda akan langsung aktif.</li>
            <li><em>Kode Verifikasi bersifat RAHASIA " distribusikan hanya kepada personel bersangkutan.</em></li>
        </ol>
    </div>

    {{-- TANDA TANGAN DIGITALLY SIGNED VIA QR --}}
    <div class="clearfix">
        <div class="signature-block">
            <div>Dikeluarkan di: Surabaya</div>
            <div>Pada tanggal: {{ $generatedAt ?? date('d F Y') }}</div>
            <div style="margin-top: 6px;">a.n. Komandan Detasemen Intelijen Koarmada 2</div>
            <div style="font-weight: bold; margin-bottom: 6px;">{{ $signerJabatan ?? 'Administrator SINDEN' }},</div>

            @if(isset($qrCodeBase64) && $qrCodeBase64)
            <div style="margin: 6px auto; text-align: center;">
                <img src="{{ $qrCodeBase64 }}" style="width: 75px; height: 75px; border: none; padding: 0; background: transparent;" alt="QR Code Signature" />
            </div>
            @else
            <div style="margin-top: 50px;"></div>
            @endif

            <div style="margin-top: 6px; font-weight: bold; text-decoration: underline;">{{ $signerName ?? auth()->user()->name }}</div>
            <div>{{ $signerPangkat ?? auth()->user()->pangkat }} NRP. {{ $signerNrp ?? auth()->user()->nrp }}</div>
        </div>
    </div>
</body>
</html>