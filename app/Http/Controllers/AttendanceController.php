<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Support\PersonnelTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;

class AttendanceController extends Controller
{
    /**
     * Menyimpan presensi kehadiran personel (Opsional) dengan foto verifikasi senyum.
     */
    public function store(Request $request)
    {
        $request->validate([
            // Lokasi GPS & foto senyum wajib (selaras dengan aplikasi Android).
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'location_name' => 'nullable|string|max:255',
            'device_model' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:hadir,dinas_luar,piket,izin',
            'notes' => 'nullable|string|max:500',
            'photo' => 'required|string',
            'smile_score' => 'nullable|integer|between:0,100',
        ], [
            'latitude.required' => 'Lokasi GPS wajib. Izinkan akses lokasi pada browser lalu coba lagi.',
            'longitude.required' => 'Lokasi GPS wajib. Izinkan akses lokasi pada browser lalu coba lagi.',
            'photo.required' => 'Foto verifikasi senyum wajib diambil sebelum presensi.',
        ]);

        $user = Auth::user();
        $today = now()->toDateString();
        $timeNow = now()->format('H:i:s');

        // Deteksi IP Address
        $ipAddress = $request->header('X-Forwarded-For') 
            ? trim(explode(',', $request->header('X-Forwarded-For'))[0]) 
            : $request->ip();

        // Deteksi Perangkat (Merk & Tipe HP)
        $deviceInfo = $request->device_model;
        if (empty($deviceInfo)) {
            try {
                $agent = new Agent();
                $platform = $agent->platform();
                $device = $agent->device();
                $browser = $agent->browser();

                if ($agent->isDesktop()) {
                    $deviceInfo = "{$platform} PC ({$browser})";
                } elseif (!empty($device)) {
                    $deviceInfo = "{$device} ({$platform})";
                } else {
                    $deviceInfo = "{$platform} Mobile ({$browser})";
                }
            } catch (\Exception $e) {
                $deviceInfo = 'Perangkat Tidak Dikenal';
            }
        }

        // Penanganan Koordinat GPS & Lokasi
        $lat = $request->latitude;
        $lng = $request->longitude;
        $locationName = $request->location_name;

        if ($lat && $lng && empty($locationName)) {
            $locationName = $this->reverseGeocode($lat, $lng);
            if (empty($locationName)) {
                $locationName = "GPS: {$lat}, {$lng}";
            }
        }

        if (empty($locationName)) {
            $locationName = 'Lokasi GPS Belum Diizinkan';
        }

        // Penanganan Berkas Foto Presensi (Base64 dari Kamera Antarmuka)
        $photoPath = null;
        if (!empty($request->photo)) {
            if (str_starts_with($request->photo, 'data:image')) {
                try {
                    $parts = explode(',', $request->photo);
                    if (count($parts) === 2) {
                        $decoded = base64_decode($parts[1]);
                        if ($decoded) {
                            $filename = 'attendances/presensi_' . $user->id . '_' . date('Ymd_His') . '_' . Str::random(6) . '.jpg';
                            Storage::disk('public')->put($filename, $decoded);
                            $photoPath = $filename;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("Gagal menyimpan foto presensi: " . $e->getMessage());
                }
            } else {
                $photoPath = $request->photo;
            }
        }

        // Data yang akan disimpan / diperbarui
        $saveData = [
            'time_in' => $timeNow,
            'status' => $request->status ?: 'hadir',
            'ip_address' => $ipAddress,
            'device' => $deviceInfo,
            'user_agent' => $request->userAgent(),
            'latitude' => $lat,
            'longitude' => $lng,
            'location_name' => $locationName,
            'notes' => $request->notes,
        ];

        if ($photoPath) {
            $saveData['photo'] = $photoPath;
        }
        if ($request->filled('smile_score')) {
            $saveData['smile_score'] = (int) $request->smile_score;
        }

        // Simpan / Perbarui Presensi Kehadiran Hari Ini
        $attendance = Attendance::updateOrCreate(
            [
                'user_id' => $user->id,
                'attendance_date' => $today,
            ],
            $saveData
        );

        // Titik presensi sekaligus memperbarui posisi personel di peta pemantauan.
        PersonnelTracking::record($user, [[
            'latitude' => $lat,
            'longitude' => $lng,
            'provider' => 'attendance',
        ]], $deviceInfo);

        return redirect()->back()->with('success', "Lapor! Presensi kehadiran mandiri berhasil dicatat pada pukul {$timeNow} WIB.");
    }

    /**
     * Konversi koordinat GPS menjadi nama wilayah/kota secara ringkas.
     */
    private function reverseGeocode($lat, $lng)
    {
        try {
            $response = Http::timeout(3)->get("https://api.bigdatacloud.net/data/reverse-geocode-client", [
                'latitude' => $lat,
                'longitude' => $lng,
                'localityLanguage' => 'id',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $locality = $data['locality'] ?? '';
                $city = $data['city'] ?? $data['principalSubdivision'] ?? '';
                if ($locality && $city) {
                    return "{$locality}, {$city}";
                }
                return $city ?: $locality;
            }
        } catch (\Exception $e) {
            Log::warning("Gagal reverse geocode presensi: " . $e->getMessage());
        }
        return null;
    }
}
