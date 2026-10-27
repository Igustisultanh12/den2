<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultSettings = [
            'agency_name' => 'DETASEMEN INTELIJEN KOARMADA 2',
            'copyright' => 'Denintel Koarmada 2',
            'format_rahasia' => 'R/{no}/{code}/{month}/{year}',
            'format_biasa' => 'B/{no}/{code}/{month}/{year}',
            'start_number' => '1',
            'wa_notifications_enabled' => '1',
        ];

        foreach ($defaultSettings as $key => $value) {
            \App\Models\Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
