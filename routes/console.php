<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jadwal Rutin Otomatis: Pengingat Pembayaran Cicilan Koperasi SINDEN Setiap Tanggal 1 Pukul 07:00 WIB
Schedule::command('koperasi:send-reminders')->monthlyOn(1, '07:00');

// Jadwal Rutin Otomatis: Hapus / Nonaktifkan Tautan Tamu yang Telah Melewati Batas Waktu Setiap Jam
Schedule::call(function () {
    \App\Models\BackupShare::where('allow_guest', true)
        ->whereNotNull('guest_expires_at')
        ->where('guest_expires_at', '<=', now())
        ->update([
            'allow_guest' => false,
            'guest_expires_at' => null,
        ]);
})->hourly()->name('cleanup-expired-guest-shares');

// Jadwal Rutin Otomatis: Bersihkan jejak lokasi personel yang lebih tua dari 30 hari
Schedule::call(fn () => \App\Support\PersonnelTracking::pruneHistory())
    ->dailyAt('02:00')
    ->name('prune-personnel-location-history');
