<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Super Admin Denintel',
                'email' => 'superadmin@mail.com',
                'nrp' => '0000000001',
                'pangkat' => 'Letkol Laut',
                'role' => 'super_admin',
                'password' => bcrypt('password123'),
                'is_active' => true,
            ],
            [
                'name' => 'Admin Denintel',
                'email' => 'admin@mail.com',
                'nrp' => '0000000002',
                'pangkat' => 'Mayor Laut',
                'role' => 'admin',
                'password' => bcrypt('password123'),
                'is_active' => true,
            ],
            [
                'name' => 'Personel Denintel',
                'email' => 'personel@mail.com',
                'nrp' => '0000000003',
                'pangkat' => 'Serka',
                'role' => 'personel',
                'password' => bcrypt('password123'),
                'is_active' => true,
            ],
            [
                'name' => 'Personel Sintel',
                'email' => 'sintel@mail.com',
                'nrp' => '0000000004',
                'pangkat' => 'Sertu',
                'role' => 'personel_sintel',
                'password' => bcrypt('password123'),
                'is_active' => true,
            ],
        ];

        foreach ($users as $userData) {
            \App\Models\User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        $this->call(SettingSeeder::class);
    }
}
