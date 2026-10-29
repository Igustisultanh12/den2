<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Data contoh (fiktif) untuk keperluan screenshot paparan / pelatihan.
 * JANGAN dijalankan di server produksi.
 *
 *   php artisan db:seed --class=DemoPresentationSeeder
 */
class DemoPresentationSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $password = bcrypt('password123');

        $people = [
            // email, nama, pangkat, nrp, role, jabatan
            ['admin@mail.com', 'Andika Pratama', 'Mayor Laut (E)', '17540/P', 'admin', 'Pasminlog'],
            ['komandan@mail.com', 'Bramantyo Wicaksono', 'Letkol Laut (P)', '15220/P', 'komandan', 'Komandan Denintel'],
            ['wadan@mail.com', 'Candra Kusuma', 'Mayor Laut (P)', '16310/P', 'wadan', 'Wakil Komandan'],
            ['pasops@mail.com', 'Dimas Saputra', 'Kapten Laut (P)', '20145/P', 'pasops', 'Pasops'],
            ['kaurmintel@mail.com', 'Eko Prasetyo', 'Lettu Laut (E)', '22980/P', 'kaurmintel', 'Kaur Mintel'],
            ['staf@mail.com', 'Fitri Handayani', 'Serma Tlg', '89012', 'staf', 'Staf Administrasi'],
            ['personel@mail.com', 'Gilang Ramadhan', 'Serka Ttg', '102233', 'personel', 'Bati Lid'],
            ['sintel@mail.com', 'Hendra Wijaya', 'Sertu Ede', '110456', 'anggotasintel', 'Anggota Sintel'],
            ['bayu@mail.com', 'Bayu Nugroho', 'Kopda Mes', '121890', 'personel', 'Ta Pamgal'],
            ['rizky@mail.com', 'Rizky Maulana', 'Klk Ttg', '130771', 'personel', 'Ta Lid'],
            ['taufik@mail.com', 'Taufik Hidayat', 'Serda Bah', '118342', 'personel', 'Ta Teknis'],
            ['yoga@mail.com', 'Yoga Permana', 'Kopka Kom', '119503', 'personel', 'Ta Kom'],
        ];

        $ids = [];
        foreach ($people as $i => [$email, $name, $pangkat, $nrp, $role, $jabatan]) {
            DB::table('users')->updateOrInsert(['email' => $email], [
                'name' => $name,
                'pangkat' => $pangkat,
                'nrp' => $nrp,
                'role' => $role,
                'jabatan' => $jabatan,
                'phone' => '0812' . str_pad((string) (34560000 + $i * 1117), 8, '0', STR_PAD_LEFT),
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => $now,
                'tracking_consent_at' => $now,
                'birth_date' => $now->copy()->subYears(28 + $i)->addDays($i * 9)->toDateString(),
                'created_at' => $now->copy()->subMonths(6),
                'updated_at' => $now,
            ]);
            $ids[$email] = DB::table('users')->where('email', $email)->value('id');
        }
        $u = fn (string $email) => $ids[$email];
        $personelEmails = ['personel@mail.com', 'bayu@mail.com', 'rizky@mail.com', 'taufik@mail.com', 'yoga@mail.com', 'sintel@mail.com', 'staf@mail.com'];

        // ---------- Klasifikasi surat ----------
        $cats = [['Surat Biasa', 'B'], ['Surat Rahasia', 'R'], ['Nota Dinas', 'ND'], ['Surat Perintah', 'Sprin'], ['Telegram', 'TR']];
        $catIds = [];
        foreach ($cats as [$name, $code]) {
            DB::table('categories')->updateOrInsert(['code' => $code], ['name' => $name, 'is_telegram' => $code === 'TR', 'start_number' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $catIds[$code] = DB::table('categories')->where('code', $code)->value('id');
        }

        // ---------- Buku agenda surat ----------
        $roman = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        $subjects = [
            ['B', 'Laporan bulanan kegiatan intelijen', 'Danlantamal V'],
            ['R', 'Perkiraan keadaan wilayah pesisir', 'Asintel Kodaeral V'],
            ['ND', 'Permohonan dukungan kendaraan dinas', 'Kadislog'],
            ['Sprin', 'Pelaksanaan latihan pengamanan pangkalan', 'Para Dan Unit'],
            ['B', 'Undangan rapat koordinasi Kominda', 'Bakesbangpol Prov Jatim'],
            ['TR', 'Kesiapsiagaan menghadapi cuaca ekstrem', 'Satuan jajaran'],
            ['R', 'Hasil pulbaket kegiatan masyarakat maritim', 'Asintel Kodaeral V'],
            ['B', 'Pengiriman personel kursus intelijen dasar', 'Kadispers'],
            ['ND', 'Usulan pemeliharaan perangkat komunikasi', 'Dan Unit Teknis'],
            ['Sprin', 'Penugasan pengamanan kunjungan kerja', 'Personel terlampir'],
        ];
        foreach ($subjects as $i => [$code, $subject, $recipient]) {
            $date = $now->copy()->subDays($i * 2 + 1);
            $seq = str_pad((string) (120 + $i), 3, '0', STR_PAD_LEFT);
            $prio = $code === 'R' ? 'R' : 'B';
            DB::table('letter_logs')->insert([
                'full_number' => "{$prio} / {$seq} / {$code} / {$roman[$date->month]} / {$date->year}",
                'sequence' => $seq,
                'priority' => $prio,
                'category_id' => $catIds[$code],
                'subject' => $subject,
                'recipient' => $recipient,
                'date' => $date->toDateString(),
                'is_archived' => $i > 7,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }

        // ---------- Arsip surat ----------
        foreach (array_slice($subjects, 0, 6) as $i => [$code, $subject]) {
            $date = $now->copy()->subDays($i * 3 + 2);
            DB::table('letters')->insert([
                'type' => $i % 2 ? 'masuk' : 'keluar',
                'security_level' => $code === 'R' ? 'rahasia' : 'biasa',
                'category_id' => $catIds[$code],
                'letter_number' => "B/" . (210 + $i) . "/{$roman[$date->month]}/{$date->year}",
                'subject' => $subject,
                'date' => $date->toDateString(),
                'issuer' => $i % 2 ? 'Asintel Kodaeral V' : 'Denintel',
                'file_path' => 'letters/contoh.pdf',
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }

        // ---------- SP Jaga Siaga Sintel (bulan berjalan, published) ----------
        $start = $now->copy()->startOfMonth();
        $spId = DB::table('sp_jagas')->insertGetId([
            'nomor_sprin' => "Sprin/" . (45) . "/{$roman[$start->month]}/{$start->year}",
            'nomor_urut' => '45',
            'bulan' => $start->month,
            'tahun' => $start->year,
            'bulan_romawi' => $roman[$start->month],
            'tmt_mulai' => $start->toDateString(),
            'tmt_selesai' => $start->copy()->endOfMonth()->toDateString(),
            'tanggal_surat' => $start->copy()->subDays(3)->toDateString(),
            'perwira_tertua_user_id' => $u('pasops@mail.com'),
            'perwira_tertua_nama' => 'Dimas Saputra',
            'perwira_tertua_pangkat_nrp' => 'Kapten Laut (P) NRP 20145/P',
            'perwira_tertua_jabatan' => 'Pasops',
            'total_personel_count' => 9,
            'total_personel_terbilang' => 'sembilan',
            'ttd_type' => 'tte',
            'status' => 'published',
            'verification_code' => strtoupper(Str::random(10)),
            'penandatangan_user_id' => $u('komandan@mail.com'),
            'penandatangan_nama' => 'Bramantyo Wicaksono',
            'penandatangan_pangkat_nrp' => 'Letkol Laut (P) NRP 15220/P',
            'penandatangan_jabatan' => 'Komandan Denintel',
            'approved_by' => $u('komandan@mail.com'),
            'approved_at' => $start->copy()->subDays(2),
            'created_by' => $u('admin@mail.com'),
            'created_at' => $start->copy()->subDays(4),
            'updated_at' => $start->copy()->subDays(2),
        ]);
        $perwira = [['pasops@mail.com', 'Dimas Saputra', 'Kapten Laut (P)', '20145/P'], ['kaurmintel@mail.com', 'Eko Prasetyo', 'Lettu Laut (E)', '22980/P'], ['wadan@mail.com', 'Candra Kusuma', 'Mayor Laut (P)', '16310/P']];
        foreach ($perwira as $i => [$email, $nama, $pk, $nrp]) {
            $tgl = array_map(fn ($d) => (string) $d, range($i + 1, 31, 3));
            DB::table('sp_jaga_perwiras')->insert(['sp_jaga_id' => $spId, 'user_id' => $u($email), 'no_urut' => $i + 1, 'nama' => $nama, 'pangkat_korps' => $pk, 'nrp' => $nrp, 'tgl_list' => json_encode($tgl), 'tgl_1' => $tgl[0] ?? null, 'tgl_2' => $tgl[1] ?? null, 'created_at' => $now, 'updated_at' => $now]);
        }
        $divisi = [
            [['personel@mail.com', 'Gilang Ramadhan', 'Serka Ttg', '102233', 'BAGA'], ['bayu@mail.com', 'Bayu Nugroho', 'Kopda Mes', '121890', 'ANGGOTA']],
            [['sintel@mail.com', 'Hendra Wijaya', 'Sertu Ede', '110456', 'BAGA'], ['rizky@mail.com', 'Rizky Maulana', 'Klk Ttg', '130771', 'ANGGOTA']],
            [['taufik@mail.com', 'Taufik Hidayat', 'Serda Bah', '118342', 'BAGA'], ['yoga@mail.com', 'Yoga Permana', 'Kopka Kom', '119503', 'ANGGOTA']],
        ];
        foreach ($divisi as $i => $members) {
            $dates = range($i + 1, $start->daysInMonth, 3);
            DB::table('sp_jaga_anggotas')->insert([
                'sp_jaga_id' => $spId,
                'divisi_no' => $i + 1,
                'tanggal_list_text' => implode(', ', $dates),
                'tanggal_array' => json_encode(array_map(fn ($d) => $start->copy()->day($d)->toDateString(), $dates)),
                'anggota_items' => json_encode(array_map(fn ($m) => ['user_id' => $u($m[0]), 'nama' => $m[1], 'pangkat_korps' => $m[2], 'nrp_nip' => $m[3], 'role_jaga' => $m[4]], $members)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // ---------- Presensi 10 hari terakhir ----------
        $base = [-7.2032, 112.7368]; // area Surabaya utara (contoh)
        foreach ($ids as $email => $id) {
            for ($d = 9; $d >= 0; $d--) {
                $day = $now->copy()->subDays($d);
                if ($day->isWeekend() || ($d === 0 && in_array($email, ['yoga@mail.com', 'wadan@mail.com']))) continue;
                $status = ['hadir', 'hadir', 'hadir', 'dinas_luar', 'hadir', 'piket', 'hadir', 'izin'][($id + $d) % 8];
                DB::table('attendances')->insert([
                    'user_id' => $id,
                    'attendance_date' => $day->toDateString(),
                    'time_in' => sprintf('07:%02d:%02d', ($id * 7 + $d * 3) % 30, ($id * 13) % 60),
                    'status' => $status,
                    'ip_address' => '10.20.30.' . (10 + $id),
                    'device' => $id % 2 ? 'Android' : 'Web',
                    'latitude' => $base[0] + ($id % 5) * 0.0004,
                    'longitude' => $base[1] + ($d % 4) * 0.0004,
                    'location_name' => 'Mako Denintel',
                    'smile_score' => 80 + ($id + $d) % 20,
                    'created_at' => $day->copy()->setTime(7, 10),
                    'updated_at' => $day->copy()->setTime(7, 10),
                ]);
            }
        }

        // ---------- SKHPP ----------
        $skhpp = [
            ['Ahmad Fauzi', 'Serda Mes NRP 140221', 'Ta Bengkel', 'Persyaratan melanjutkan pendidikan Secapa', 'approved'],
            ['Siti Rahmawati', 'PNS III/a NIP 199203122019032004', 'Staf Personalia', 'Persyaratan pengajuan kenaikan pangkat', 'pending'],
            ['Dedi Kurniawan', 'Kopda Ttg NRP 128874', 'Ta Logistik', 'Persyaratan pengajuan izin menikah', 'draft'],
            ['Novi Andriani', 'Masyarakat NIK 3578014505960002', 'Calon pegawai mitra', 'Persyaratan masuk lingkungan pangkalan', 'rejected'],
        ];
        foreach ($skhpp as $i => [$nama, $pkt, $jab, $untuk, $status]) {
            $date = $now->copy()->subDays(1 + $i * 4);
            $skId = DB::table('skhpps')->insertGetId([
                'kategori_personel' => str_contains($pkt, 'NIK') ? 'umum' : 'militer',
                'is_pernikahan' => str_contains($untuk, 'menikah'),
                'nomor_skhpp' => $status === 'approved' ? "SKHPP/" . (88 + $i) . "/{$roman[$date->month]}/{$date->year}" : null,
                'nomor_urut' => 88 + $i,
                'bulan_romawi' => $roman[$date->month],
                'tahun' => $date->year,
                'tanggal_skhpp' => $date->toDateString(),
                'surat_pengantar' => 'Nota Dinas Kasatker Nomor ND/' . (300 + $i) . '/' . $roman[$date->month] . '/' . $date->year,
                'nama' => $nama,
                'pangkat_korps_nrp' => $pkt,
                'jabatan_pekerjaan' => $jab,
                'tempat_lahir' => ['Surabaya', 'Malang', 'Sidoarjo', 'Gresik'][$i],
                'tanggal_lahir' => $now->copy()->subYears(27 + $i)->toDateString(),
                'jenis_kelamin' => in_array($i, [1, 3]) ? 'Perempuan' : 'Laki-laki',
                'agama' => 'Islam',
                'alamat' => 'Jl. Contoh No. ' . (10 + $i) . ', Surabaya',
                'peruntukan' => $untuk,
                'status' => $status,
                'verification_code' => strtoupper(Str::random(12)),
                'submitted_by' => $u($i % 2 ? 'staf@mail.com' : 'personel@mail.com'),
                'operator_name' => $i % 2 ? 'Fitri Handayani' : 'Gilang Ramadhan',
                'operator_nrp_pangkat' => $i % 2 ? 'Serma Tlg / 89012' : 'Serka Ttg / 102233',
                'submitted_at' => $date,
                'approved_by' => $status === 'approved' ? $u('komandan@mail.com') : null,
                'approved_at' => $status === 'approved' ? $date->copy()->addHours(5) : null,
                'catatan_revisi' => $status === 'rejected' ? 'Lengkapi fotokopi KTP & surat keterangan domisili.' : null,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
            DB::table('skhpp_members')->insert(['skhpp_id' => $skId, 'no_urut' => 1, 'nama' => $nama, 'pangkat_nrp_nik' => $pkt, 'jabatan' => $jab, 'created_at' => $date, 'updated_at' => $date]);
        }

        // ---------- Tanda tangan elektronik ----------
        $sig = [['Laporan Kegiatan Pengamanan Pangkalan', 'pending'], ['Surat Keterangan Bebas Narkoba', 'approved'], ['Nota Dinas Usulan Kursus', 'pending'], ['Sprin Pengamanan Kunjungan', 'approved']];
        foreach ($sig as $i => [$subj, $status]) {
            $date = $now->copy()->subDays($i + 1);
            DB::table('signature_requests')->insert([
                'user_id' => $u(['staf@mail.com', 'admin@mail.com', 'personel@mail.com', 'pasops@mail.com'][$i]),
                'subject' => $subj,
                'document_title' => $subj,
                'letter_number' => 'B/' . (400 + $i) . "/{$roman[$date->month]}/{$date->year}",
                'file_path' => 'signatures/contoh.pdf',
                'status' => $status,
                'verification_code' => strtoupper(Str::random(10)),
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }

        // ---------- Tracking SC ----------
        $sc = [['Rendra Saputra', 'Kapten Laut (T)', 'nrp', '19887/P', 'Lantamal V', 'Kursus luar negeri', 6, 'proses'], ['PT Samudra Niaga', null, 'nik', '3578012309880003', 'Mitra galangan', 'Kerja sama perusahaan', 3, 'perbaikan'], ['Wulan Sari', 'Lettu Laut (K/W)', 'nrp', '23011/P', 'Rumkital', 'Penugasan satgas', 10, 'selesai']];
        foreach ($sc as $i => [$nama, $pk, $type, $no, $kes, $kep, $stage, $status]) {
            $date = $now->copy()->subDays(3 + $i * 5);
            DB::table('sc_submissions')->insert(['tracking_code' => 'SC-' . strtoupper(Str::random(6)), 'nomor_resi' => 'RS' . (7100 + $i), 'tipe_permohonan' => 'baru', 'kategori_sc' => $type === 'nik' ? 'perusahaan' : 'dinas', 'nama' => $nama, 'pangkat_korps' => $pk, 'identifier_type' => $type, 'identifier_number' => $no, 'kesatuan' => $kes, 'jabatan' => $kep, 'keperluan' => $kep, 'current_stage' => $stage, 'status' => $status, 'created_by' => $u('admin@mail.com'), 'created_at' => $date, 'updated_at' => $date]);
        }

        // ---------- Pelanggaran prajurit ----------
        $viol = [['Contoh Personel A', 'Kopda', 'Ta Unit', 'Terlambat apel pagi berulang', 'PROSES'], ['Contoh Personel B', 'Serda', 'Ta Logistik', 'Tidak masuk dinas tanpa keterangan 3 hari', 'SIDANG'], ['Contoh Personel C', 'Klk', 'Ta Bengkel', 'Pelanggaran tata tertib asrama', 'SELESAI']];
        foreach ($viol as $i => [$name, $rank, $pos, $case, $status]) {
            $date = $now->copy()->subDays(10 + $i * 12);
            DB::table('soldier_violations')->insert(['name' => $name, 'nrp' => (string) (150100 + $i), 'rank' => $rank, 'position' => $pos, 'unit' => 'Denintel', 'case_description' => $case, 'incident_date' => $date->toDateString(), 'case_development' => 'Telah dilakukan pemeriksaan pendahuluan oleh Provost.', 'status' => $status, 'created_at' => $date, 'updated_at' => $date]);
        }

        // ---------- Kegiatan masyarakat (peta) ----------
        $acts = [['Forum diskusi nelayan pesisir', 'Sosial Budaya', 'Surabaya', -7.2104, 112.7341], ['Pemantauan harga BBM nelayan', 'Ekonomi', 'Gresik', -7.1567, 112.6555], ['Pengamanan kegiatan keagamaan', 'Keamanan', 'Sidoarjo', -7.4478, 112.7183], ['Sosialisasi bahaya narkoba', 'Sosial Budaya', 'Bangkalan', -7.0455, 112.7351], ['Rapat koordinasi Kominda', 'Politik', 'Surabaya', -7.2575, 112.7521]];
        foreach ($acts as $i => [$t, $c, $city, $lat, $lng]) {
            DB::table('community_activities')->insert(['title' => $t, 'description' => 'Laporan kegiatan contoh untuk paparan.', 'category' => $c, 'province' => 'Jawa Timur', 'city' => $city, 'region' => $city, 'location_name' => $city, 'latitude' => $lat, 'longitude' => $lng, 'activity_date' => $now->copy()->subDays($i * 2)->toDateString(), 'status' => ['BERJALAN', 'SELESAI', 'RENCANA'][$i % 3], 'created_at' => $now, 'updated_at' => $now]);
        }

        // ---------- Lokasi personel (Pantau Personel) ----------
        foreach ($personelEmails as $i => $email) {
            DB::table('personnel_locations')->updateOrInsert(['user_id' => $u($email)], ['latitude' => $base[0] + $i * 0.006 - 0.012, 'longitude' => $base[1] + ($i % 3) * 0.008 - 0.006, 'accuracy' => 12, 'battery' => 55 + $i * 6, 'is_charging' => $i % 3 === 0, 'provider' => 'gps', 'device' => 'Android', 'recorded_at' => $now->copy()->subMinutes($i * 4 + 1), 'created_at' => $now, 'updated_at' => $now]);
        }

        // ---------- Rekening komandan & mitra ----------
        $rek = [['Saldo awal bulan', 'MASUK', 25000000], ['Dukungan kegiatan Kominda', 'KELUAR', 3500000], ['Bantuan sosial personel', 'KELUAR', 1500000], ['Penerimaan dukungan satuan atas', 'MASUK', 10000000]];
        foreach ($rek as $i => [$ket, $jenis, $jml]) {
            DB::table('commander_accounts')->insert(['tanggal' => $now->copy()->subDays(20 - $i * 5)->toDateString(), 'keterangan' => $ket, 'jenis' => $jenis, 'jumlah' => $jml, 'petugas_input' => 'Andika Pratama', 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ([['CV Bahari Jaya', 'Kantin'], ['PT Nusantara Clean', 'Kebersihan'], ['UD Sumber Air', 'Air minum']] as $i => [$nama, $ket]) {
            $mid = DB::table('mitras')->insertGetId(['nama' => $nama, 'pt' => $nama, 'no_tlp' => '0315' . (550010 + $i), 'sort_order' => $i, 'keterangan' => $ket, 'created_at' => $now, 'updated_at' => $now]);
            foreach (range(1, $now->month) as $b) {
                DB::table('mitra_payments')->insert(['mitra_id' => $mid, 'tahun' => $now->year, 'bulan' => $b, 'is_paid' => $b < $now->month || $i === 0, 'updated_by' => 'Andika Pratama', 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        // ---------- Notifikasi ----------
        $notifs = [['SP Jaga bulan ini terbit', 'Sprin/45 telah ditandatangani Komandan dan dapat diunduh.', 'sp_jaga', '/sp-jaga'], ['SKHPP disetujui', 'SKHPP a.n. Ahmad Fauzi telah disetujui.', 'skhpp', '/skhpp'], ['Pengingat presensi', 'Jangan lupa melakukan presensi pagi sebelum 07.30.', 'attendance', '/dashboard'], ['Dokumen menunggu TTE', 'Laporan Kegiatan Pengamanan Pangkalan menunggu tanda tangan.', 'signature', '/signature']];
        foreach ($ids as $email => $id) {
            foreach ($notifs as $i => [$title, $msg, $type, $link]) {
                DB::table('app_notifications')->insert(['user_id' => $id, 'title' => $title, 'message' => $msg, 'type' => $type, 'link' => $link, 'is_read' => $i > 1, 'created_at' => $now->copy()->subHours($i * 5 + 1), 'updated_at' => $now]);
            }
        }

        // ---------- Chat dinas antar-personel ----------
        $pairs = [['personel@mail.com', 'pasops@mail.com', ['Izin Kapten, laporan pengamanan sudah saya unggah.', 'Siap, sudah saya cek. Lanjutkan.', 'Siap, terima kasih.']], ['personel@mail.com', 'bayu@mail.com', ['Bay, besok jaga divisi 1 ya.', 'Siap Bang, sudah lihat di SP Jaga.']]];
        foreach ($pairs as [$a, $b, $msgs]) {
            $tid = DB::table('live_chat_threads')->insertGetId(['uuid' => (string) Str::uuid(), 'user_id' => $u($a), 'peer_id' => $u($b), 'subject' => 'Chat Dinas', 'status' => 'open', 'last_message_at' => $now, 'unread_admin' => 0, 'unread_user' => 1, 'created_at' => $now->copy()->subHour(), 'updated_at' => $now]);
            foreach ($msgs as $i => $m) {
                $sender = $i % 2 ? $b : $a;
                DB::table('live_chat_messages')->insert(['thread_id' => $tid, 'sender_type' => 'user', 'sender_id' => $u($sender), 'sender_name' => DB::table('users')->where('id', $u($sender))->value('name'), 'message' => $m, 'is_read' => $i < count($msgs) - 1, 'created_at' => $now->copy()->subMinutes(50 - $i * 7), 'updated_at' => $now]);
            }
        }

        // ---------- Riwayat login ----------
        foreach (array_slice($ids, 0, 8, true) as $email => $id) {
            DB::table('visitor_logs')->insert(['user_id' => $id, 'user_name' => DB::table('users')->where('id', $id)->value('name'), 'ip_address' => '10.20.30.' . (10 + $id), 'device_type' => $id % 2 ? 'mobile' : 'desktop', 'browser' => $id % 2 ? 'SINDEN Android' : 'Chrome', 'platform' => $id % 2 ? 'Android' : 'Windows', 'location' => 'Surabaya', 'login_at' => $now->copy()->subMinutes($id * 13), 'is_suspicious' => false, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
}
