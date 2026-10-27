<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Personnel;
use App\Models\UcapanConfig; // Menggunakan model baru agar tidak bentrok
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BirthdayGreeting extends Command
{
    /**
     * Nama komando yang akan dipanggil di terminal
     * Jalankan via CRON atau manual: php artisan sinden:birthday-greeting
     */
    protected $signature = 'sinden:birthday-greeting';

    /**
     * Deskripsi tugas komando
     */
    protected $description = 'Memeriksa personel yang ulang tahun dan mengirimkan ucapan via WhatsApp berdasarkan template';

    public function handle()
    {
        // Mendapatkan tanggal hari ini (Bulan-Tanggal)
        $today = now()->format('m-d');
        $this->info(" Radar HUT Aktif: Memindai pangkalan untuk tanggal $today...");

        // 1. SCAN TARGET: PERSONEL PUNYA AKUN (USERS)
        // Mengambil data dari tabel users (pangkat, name, phone, birth_date)
        $users = User::whereNotNull('birth_date')
            ->whereRaw("DATE_FORMAT(birth_date, '%m-%d') = ?", [$today])
            ->get();

        foreach ($users as $user) {
            // Mengirim parameter pangkat (kolom 'pangkat' di tabel users) ke fungsi kirimPesan
            $this->kirimPesan($user->phone, $user->name, $user->pangkat, "Personel Terdaftar");
        }

        // 2. SCAN TARGET: PERSONEL NON-AKUN (PERSONNELS)
        // Mengambil data dari tabel personnels (pangkat, name, phone, birth_date)
        $personnels = Personnel::whereRaw("DATE_FORMAT(birth_date, '%m-%d') = ?", [$today])
            ->get();

        foreach ($personnels as $p) {
            // Mengirim parameter pangkat (kolom 'pangkat' di tabel personnels) ke fungsi kirimPesan
            $this->kirimPesan($p->phone, $p->name, $p->pangkat, "Personel Tidak Terdaftar");
        }

        $this->info(" Selesai. Seluruh ucapan telah dipancarkan.");
    }

    /**
     * Protokol Pengiriman via WA Gateway (Port 3000)
     */
    private function kirimPesan($phone, $name, $pangkat, $type)
    {
        // Validasi Nomor Telepon
        if (!$phone) {
            $this->error(" Gagal mengirim ke $name ($type): Nomor telepon tidak ditemukan.");
            return;
        }

        // --- AMBIL TEMPLATE DARI DATABASE (UCAPAN CONFIG) ---
        // Mencari berdasarkan slug 'birthday_message' yang dikelola di Admin Panel
        $config = UcapanConfig::where('slug', 'birthday_message')->first();
        
        // Pesan Cadangan jika di database belum di-set narasi-nya
        $fallback = "KOMANDO ARMADA II\nDETASEMEN INTELIJEN\n\nSelamat Ulang Tahun, *{name}*!\n\nSemoga panjang umur, sehat selalu, dan senantiasa dalam lindungan Tuhan YME.\n\n*JALESVEVA JAYAMAHE!*";
        
        $template = $config ? $config->narasi : $fallback;

        // --- PROSES PENGGABUNGAN PANGKAT DAN NAMA ---
        // Jika pangkat ada, tambahkan spasi setelah pangkat. Jika tidak ada (kosong), hanya nama.
        $identitasLengkap = ($pangkat ? $pangkat . ' ' : '') . $name;

        // Injeksi: Ganti variabel {name} menjadi (Pangkat) (Nama) secara dinamis
        $message = str_replace('{name}', $identitasLengkap, $template);

        try {
            // Memancarkan sinyal ke WA Gateway Baileys Port 3000
            $response = Http::timeout(20)->get("http://localhost:3000/send", [
                'number' => $phone,
                'msg' => $message
            ]);

            if ($response->successful()) {
                $this->info(" Berhasil mengirim ke: $identitasLengkap [$type]");
            } else {
                Log::error("Gagal mengirim WA ke $identitasLengkap: " . $response->body());
                $this->error(" Respon Gateway Gagal untuk: $identitasLengkap");
            }
        } catch (\Exception $e) {
            // Log jika terjadi gangguan koneksi ke port 3000
            Log::error("Gangguan Radar WA Gateway: " . $e->getMessage());
            $this->error(" Gagal menghubungi WA Gateway. Pastikan PM2 wa-gateway sedang aktif!");
        }
    }
}