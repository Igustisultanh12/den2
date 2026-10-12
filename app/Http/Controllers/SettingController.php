<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http; // PERBAIKAN: Impor Http Facade agar getWaStatus() berfungsi

class SettingController extends Controller
{
    public function index()
    {
        // Mengambil semua pengaturan dan mengubahnya menjadi format Key-Value
        $settings = Setting::pluck('value', 'key')->toArray();
        return Inertia::render('Settings/Index', [
            'settings' => $settings
        ]);
    }

    public function update(Request $request)
    {
        // TAMBAHAN: Validasi untuk 'start_number' dan 'favicon' tanpa menghapus yang lama
        $request->validate([
            'agency_name' => 'required|string|max:255',
            'copyright' => 'required|string',
            'start_number' => 'required|integer|min:1', // Tambahan validasi nomor urut surat
            'wa_notifications_enabled' => 'nullable|in:0,1',
            'google_client_id' => 'nullable|string|max:500',
            'google_client_secret' => 'nullable|string|max:500',
            'google_login_enabled' => 'nullable|in:0,1',
            'agora_app_id' => 'nullable|string|max:100',
            'agora_app_certificate' => 'nullable|string|max:100',
            'logo' => 'nullable|image|max:2048', 
            'login_background' => 'nullable|image|max:5120', // Background login (Maks 5MB)
            'signature_file' => 'nullable|image|mimes:png|max:2048', // Validasi khusus PNG
            'favicon' => 'nullable|image|mimes:ico,png,jpg,jpeg|max:1024', // Tambahan validasi favicon (Maks 1MB)
        ]);

        // 1. Update pengaturan teks & integrasi (agency_name, copyright, google oauth, mobile api, wa, agora)
        $textFields = [
            'agency_name', 'copyright', 'start_number', 'wa_notifications_enabled', 
            'mobile_api_base_url', 'mobile_apk_download_url', 'mobile_api_status', 'mobile_min_version',
            'mobile_theme_mode', 'mobile_primary_color', 'mobile_accent_color', 'mobile_card_radius',
            'mobile_show_balance_card', 'mobile_show_summary_trio', 'mobile_show_quick_actions',
            'google_client_id', 'google_client_secret', 'google_login_enabled',
            'agora_app_id', 'agora_app_certificate'
        ];
        foreach ($request->only($textFields) as $key => $value) {
            if ($value !== null) {
                Setting::updateOrCreate(['key' => $key], ['value' => (string)$value]);
            }
        }

        // 2. Logika Update Logo Instansi
        if ($request->hasFile('logo')) {
            $oldLogo = Setting::where('key', 'agency_logo')->first();
            if ($oldLogo && $oldLogo->value) {
                Storage::disk('public')->delete($oldLogo->value);
            }

            $path = $request->file('logo')->store('agency', 'public');
            Setting::updateOrCreate(['key' => 'agency_logo'], ['value' => $path]);
        }

        // 2b. Logika Update Background Login
        if ($request->hasFile('login_background')) {
            $oldBg = Setting::where('key', 'login_background')->first();
            if ($oldBg && $oldBg->value) {
                Storage::disk('public')->delete($oldBg->value);
            }

            $path = $request->file('login_background')->store('backgrounds', 'public');
            Setting::updateOrCreate(['key' => 'login_background'], ['value' => $path]);
        }

        // 3. Logika Update Tanda Tangan Komandan (Fitur Baru)
        if ($request->hasFile('signature_file')) {
            // Kita simpan dengan nama tetap 'komandan_ttd.png' agar mudah dipanggil sistem
            $path = $request->file('signature_file')->storeAs('signatures', 'komandan_ttd.png', 'public');
            
            // Simpan path-nya ke database dengan key 'commander_signature'
            Setting::updateOrCreate(
                ['key' => 'commander_signature'], 
                ['value' => 'signatures/komandan_ttd.png']
            );
        }

        // 4. TAMBAHAN: Logika Update Favicon Web
        if ($request->hasFile('favicon')) {
            $oldFavicon = Setting::where('key', 'favicon')->first();
            if ($oldFavicon && $oldFavicon->value) {
                Storage::disk('public')->delete($oldFavicon->value);
            }

            $path = $request->file('favicon')->store('favicon', 'public');
            Setting::updateOrCreate(['key' => 'favicon'], ['value' => $path]);
        }

        return back()->with('success', 'Pengaturan, Logo, Background Login, Tanda Tangan, dan Favicon berhasil diperbarui!');
    }
    
    // app/Http/Controllers/Admin/SettingController.php
    public function getWaStatus()
    {
        try {
            // Panggil API Node.js yang kita buat tadi
            $response = Http::get('http://localhost:3000/status-wa');
            return $response->json();
        } catch (\Exception $e) {
            return ['status' => 'OFFLINE', 'qr' => null];
        }
    }
    
    public function updateStamp(Request $request)
    {
        $request->validate([
            'stamp_file' => 'required|image|mimes:png|max:2048', // Wajib PNG agar transparan
        ]);

        if ($request->hasFile('stamp_file')) {
            $path = $request->file('stamp_file')->store('settings', 'public');
            
            // Simpan ke database (mengasumsikan tabel settings dengan key-value)
            Setting::updateOrCreate(
                ['key' => 'commander_stamp'],
                ['value' => $path]
            );
        }

        return back()->with('success', 'Radar: Stempel berhasil diperbarui.');
    }
}