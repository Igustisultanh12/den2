<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SKHPP {{ $skhpp->nama }}</title>
    <style>
        @page {
            margin: 1cm 1.5cm;
            size: 215mm 330mm portrait;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12pt;
            line-height: 1.35;
            color: #000;
            margin: 0;
            padding: 0;
            font-weight: normal;
        }

        .page-break {
            page-break-before: always;
        }

        table {
            border-collapse: collapse;
            font-weight: normal;
        }

        .table-data td {
            padding: 2px 0;
            font-weight: normal;
        }

        .table-members th, .table-members td {
            border: 1px solid #000;
            padding: 6px 8px;
            font-weight: normal;
        }
    </style>
</head>
<body>

    <!-- HALAMAN 1: DOKUMEN SKHPP UTAMA -->
    <div style="width: 100%;">
        
        <!-- Kop Surat (Rata Tengah dalam Kop Block, Tanpa Wrap pada V) -->
        <div style="margin-bottom: 20px; width: 380px; text-align: center;">
            <div style="font-weight: normal; text-transform: uppercase; font-size: 12pt; white-space: nowrap;">KOMANDO ARMADA II</div>
            <div style="font-weight: normal; text-transform: uppercase; font-size: 12pt; white-space: nowrap;">DETASEMEN INTELIJEN</div>
            <div style="border-bottom: 2px solid #000; width: 100%; margin-top: 2px;"></div>
        </div>

        <!-- Judul Surat -->
        <div style="text-align: center; margin-top: 15px; margin-bottom: 25px;">
            <div style="font-weight: normal; text-transform: uppercase; font-size: 12pt;"> SURAT KETERANGAN HASIL PENELITIAN PERSONEL(SKHPP)
            </div>
            @if($skhpp->kategori_personel === 'perusahaan')
            <div style="font-weight: normal; text-transform: uppercase; font-size: 12pt;"> MITRA KERJA TNI ANGKATAN LAUT
            </div>
            @endif
            <div style="font-weight: normal; font-size: 12pt; margin-top: 3px;">
                @if($skhpp->nomor_skhpp)
                    Nomor : {{ $skhpp->nomor_skhpp }}
                @else
                    Nomor : R/ &nbsp;&nbsp;&nbsp;&nbsp;{{ $skhpp->nomor_urut ?? '        ' }}&nbsp;&nbsp;&nbsp;&nbsp; /SKHPP/{{ $skhpp->bulan_romawi ?? 'VIII' }}/{{ $skhpp->tahun ?? '2026' }}
                @endif
            </div>
        </div>

        <!-- Point 1: Dasar -->
        <table style="width: 100%; margin-bottom: 10px;">
            <tr>
                <td style="width: 30px; vertical-align: top; font-weight: normal;">1.</td>
                <td style="vertical-align: top;">
                    <div style="font-weight: normal;">Dasar :</div>
                    <table style="width: 100%; margin-top: 3px;">
                        <tr>
                            <td style="width: 25px; vertical-align: top;">a.</td>
                            <td style="text-align: justify;">Peraturan Kasal Nomor Perkasal/50/XII/2007 tanggal 04 Desember 2007 tentang Petunjuk Pelaksanaan Penelitian Personel di lingkungan TNI AL;</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">b.</td>
                            <td style="text-align: justify;">Prosedur Tetap Nomor Protap/01/VIII/2024 tanggal 26 Agustus 2024 tentang Pengurusan Surat Keterangan Hasil Penelitian Personel (SKHPP) di Lingkungan Tentara Nasional Indonesia Angkatan Laut; dan</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">c.</td>
                            <td style="text-align: justify;">{{ $skhpp->surat_pengantar }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Point 2: Data Personel (Menjorok Rata Huruf "Dengan") -->
        <table style="width: 100%; margin-bottom: 10px;">
            <tr>
                <td style="width: 30px; vertical-align: top; font-weight: normal;">2.</td>
                <td style="vertical-align: top;">
                    <div>Dengan ini menerangkan bahwa hasil penelitian terhadap :</div>
                    
                    @if($skhpp->pangkat_korps_nrp)
                    <table class="table-data" style="width: 100%; margin-top: 4px; padding-left: 45px;">
                        <tr>
                            <td style="width: 25px; vertical-align: top;">a.</td>
                            <td style="width: 145px; vertical-align: top;">Nama</td>
                            <td style="width: 12px; vertical-align: top;">:</td>
                            <td style="vertical-align: top; font-weight: normal;">{{ $skhpp->nama }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">b.</td>
                            <td style="vertical-align: top;">Pangkat/Korp/NRP</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->pangkat_korps_nrp }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">c.</td>
                            <td style="vertical-align: top;">Jabatan</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->jabatan_pekerjaan }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">d.</td>
                            <td style="vertical-align: top;">Tempat/Tgl. lahir</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->tempat_lahir }}, {{ $tanggal_lahir_indo }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">e.</td>
                            <td style="vertical-align: top;">Jenis kelamin</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->jenis_kelamin }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">f.</td>
                            <td style="vertical-align: top;">Agama</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->agama }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">g.</td>
                            <td style="vertical-align: top;">Alamat rumah</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->alamat }}</td>
                        </tr>
                    </table>
                    @else
                    <table class="table-data" style="width: 100%; margin-top: 4px; padding-left: 45px;">
                        <tr>
                            <td style="width: 25px; vertical-align: top;">a.</td>
                            <td style="width: 145px; vertical-align: top;">Nama</td>
                            <td style="width: 12px; vertical-align: top;">:</td>
                            <td style="vertical-align: top; font-weight: normal;">{{ $skhpp->nama }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">b.</td>
                            <td style="vertical-align: top;">NIK</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->nik ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">c.</td>
                            <td style="vertical-align: top;">Tempat/Tgl. lahir</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->tempat_lahir }}, {{ $tanggal_lahir_indo }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">d.</td>
                            <td style="vertical-align: top;">Jenis kelamin</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->jenis_kelamin }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">e.</td>
                            <td style="vertical-align: top;">Agama</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->agama }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">f.</td>
                            <td style="vertical-align: top;">Pekerjaan</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->jabatan_pekerjaan }}</td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;">g.</td>
                            <td style="vertical-align: top;">Alamat rumah</td>
                            <td style="vertical-align: top;">:</td>
                            <td style="vertical-align: top;">{{ $skhpp->alamat }}</td>
                        </tr>
                    </table>
                    @endif
                </td>
            </tr>
        </table>

        <!-- Point 2: Hasil (HANYA 'Memenuhi Syarat' YANG DI BOLD) -->
        <table style="width: 100%; margin-bottom: 10px;">
            <tr>
                <td style="width: 30px; vertical-align: top; font-weight: normal;">2.</td>
                <td style="vertical-align: top;"> Hasil Penelitian Personel <span style="font-weight: bold;">Memenuhi Syarat</span>
                </td>
            </tr>
        </table>

        <!-- Point 3: Peruntukan -->
        <table style="width: 100%; margin-bottom: 10px;">
            <tr>
                <td style="width: 30px; vertical-align: top; font-weight: normal;">3.</td>
                <td style="vertical-align: top; text-align: justify;"> SKHPP ini diberikan {{ $skhpp->peruntukan }}.
                </td>
            </tr>
        </table>

        <!-- Point 4: Penutup -->
        <table style="width: 100%; margin-bottom: 15px;">
            <tr>
                <td style="width: 30px; vertical-align: top; font-weight: normal;">4.</td>
                <td style="vertical-align: top; text-align: justify;"> Apabila kemudian terdapat kekeliruan, SKHPP ini akan dicabut dan diadakan pembetulan seperlunya.
                </td>
            </tr>
        </table>

        <!-- Pas Foto 4x6 (Mepet TTD Komandan & Foto Suami-Istri Nempel) & Signature Block Komandan -->
        <table style="width: 100%; margin-top: 15px;">
            <tr>
                <!-- Ruang Kosong Kiri -->
                <td style="width: 25%;"></td>

                <!-- Kolom Pas Foto 4x6 (Nempel Berhimpitan Tanpa Jarak & Mepet TTD Komandan, Tanpa Garis/Border) -->
                <td style="vertical-align: top; text-align: right; padding-right: 4px; width: 1px; white-space: nowrap; font-size: 0;">
                    @if($foto1_base64)
                        <img src="{{ $foto1_base64 }}" style="width: 4cm; height: 6cm; object-fit: cover; border: none; display: inline-block; vertical-align: top;" />
                    @endif
                    @if($skhpp->is_pernikahan && $foto2_base64)
                        <img src="{{ $foto2_base64 }}" style="width: 4cm; height: 6cm; object-fit: cover; border: none; margin-left: 0px; display: inline-block; vertical-align: top;" />
                    @endif
                </td>

                <!-- Kolom TTD Komandan -->
                <td style="vertical-align: top; width: 330px;">
                    <div style="width: 330px;">
                        <div style="text-align: left;">Dikeluarkan di Surabaya</div>
                        <table style="width: 100%; border-bottom: 1px solid #000; margin-bottom: 6px;">
                            <tr>
                                <td style="text-align: left; padding-bottom: 1px;">pada tanggal</td>
                                <td style="text-align: right; padding-bottom: 1px;">{{ $tanggal_skhpp_indo }}</td>
                            </tr>
                        </table>
                        
                        <div style="font-weight: normal; line-height: 1.2; text-align: center; white-space: nowrap; margin-top: 4px;"> Komandan Detasemen Intelijen Koarmada 2,
                        </div>

                        <!-- TTD QR Code Rata Tengah -->
                        <div style="height: 95px; margin-top: 5px; margin-bottom: 5px; text-align: center;">
                            @if($qr_base64 && $skhpp->status === 'approved')
                                <img src="{{ $qr_base64 }}" style="width: 85px; height: 85px; border: 1px solid #ccc; padding: 2px; margin: 0 auto; display: block;" />
                            @endif
                        </div>

                        <div style="font-weight: normal; text-decoration: none; text-align: center; white-space: nowrap;">Hari Bagio Wijayanto, M.Tr.Opsla.</div>
                        <div style="font-weight: normal; text-decoration: none; text-align: center; white-space: nowrap;">Kolonel Laut (E) NRP 16085/P</div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Footer Kepada (Tanpa Bold & Tanpa Turun Baris / Wrap pada "V") -->
        <div style="margin-top: 25px; font-weight: normal;">
            <div>Kepada :</div>
            <div style="font-weight: normal; border-bottom: 1px solid #000; display: inline-block; padding-bottom: 2px; white-space: nowrap;">Yth. Asintel Pangkoarmada 2</div>
        </div>
    </div>

    <!-- HALAMAN 2: LAMPIRAN SKHPP (JIKA ANGGOTA TERSEDIA) -->
    @if($skhpp->has_pengikut && count($skhpp->members) > 0)
    <div class="page-break"></div>

    <div style="width: 100%; padding-top: 10px;">
        
        <!-- Header Lampiran (Kop Kiri & Detail Lampiran Kanan) -->
        <table style="width: 100%; margin-bottom: 25px;">
            <tr>
                <td style="width: 55%; vertical-align: top;">
                    <div style="width: 380px; text-align: center;">
                        <div style="font-weight: normal; text-transform: uppercase; font-size: 12pt; white-space: nowrap;">KOMANDO ARMADA II</div>
                        <div style="font-weight: normal; text-transform: uppercase; font-size: 12pt; white-space: nowrap;">DETASEMEN INTELIJEN</div>
                        <div style="border-bottom: 2px solid #000; width: 100%; margin-top: 2px;"></div>
                    </div>
                </td>
                <td style="width: 45%; vertical-align: top; text-align: right; font-size: 11pt; line-height: 1.3; font-weight: normal;">
                    <div>Lampiran SKHPP Den Intel Koarmada 2</div>
                    <div style="border-bottom: 1px solid #000; display: inline-block; padding-bottom: 2px;">
                        @if($skhpp->nomor_skhpp)
                            Nomor {{ $skhpp->nomor_skhpp }}<br>
                        @else
                            Nomor SKHPP/ &nbsp;&nbsp;&nbsp;&nbsp;{{ $skhpp->nomor_urut ?? '        ' }}&nbsp;&nbsp;&nbsp;&nbsp; /{{ $skhpp->bulan_romawi ?? 'VIII' }}/{{ $skhpp->tahun ?? '2026' }}<br>
                        @endif
                        Tanggal &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $tanggal_skhpp_indo }}
                    </div>
                </td>
            </tr>
        </table>

        <!-- Judul Lampiran -->
        <div style="text-align: center; font-weight: normal; text-transform: uppercase; margin-bottom: 15px; font-size: 12pt;"> DAFTAR NAMA-NAMA ANGGOTA PENGIKUT
        </div>

        <!-- Tabel Anggota Pengikut -->
        <table class="table-members" style="width: 100%; font-size: 11pt; margin-bottom: 25px;">
            <thead>
                <tr style="background-color: #f8f8f8; text-transform: uppercase; text-align: center; font-weight: normal;">
                    <th style="width: 35px;">NO</th>
                    <th>NAMA</th>
                    <th style="width: 180px;">NIK / NRP / NIP</th>
                    <th>JABATAN</th>
                </tr>
            </thead>
            <tbody>
                @foreach($skhpp->members as $idx => $m)
                <tr>
                    <td style="text-align: center; font-weight: normal;">{{ $idx + 1 }}.</td>
                    <td style="font-weight: normal;">{{ $m->nama }}</td>
                    <td style="text-align: center;">{{ $m->pangkat_nrp_nik }}</td>
                    <td>{{ $m->jabatan }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- TTD Komandan Lampiran (Rata Tengah Tanpa Bold) -->
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%;"></td>
                <td style="width: 50%; text-align: center;">
                    <div style="text-align: center; width: 280px; margin: 0 auto; font-weight: normal;">
                        <div style="line-height: 1.2; white-space: nowrap;"> Komandan Detasemen Intelijen Koarmada 2,
                        </div>
                        <div style="height: 95px; margin-top: 5px; margin-bottom: 5px; text-align: center;">
                            @if($qr_base64 && $skhpp->status === 'approved')
                                <img src="{{ $qr_base64 }}" style="width: 85px; height: 85px; border: 1px solid #ccc; padding: 2px; margin: 0 auto; display: block;" />
                            @endif
                        </div>
                        <div style="text-decoration: none;">Hari Bagio Wijayanto, M.Tr.Opsla.</div>
                        <div>Kolonel Laut (E) NRP 16085/P</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
    @endif

</body>
</html>
