<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Location;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Create Admin User
        User::create([
            'name' => 'Administrator',
            'email' => 'admin@tongod.gov.my',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'department' => 'Pentadbiran',
            'position' => 'Pentadbir Sistem',
            'is_active' => true,
        ]);

        // Create Staff Users
        User::create([
            'name' => 'Ahmad bin Abdullah',
            'email' => 'ahmad@tongod.gov.my',
            'password' => Hash::make('password123'),
            'role' => 'staff_jabatan',
            'department' => 'Pentadbiran',
            'position' => 'Pegawai Jabatan',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Siti Nurhaliza',
            'email' => 'siti@tongod.gov.my',
            'password' => Hash::make('password123'),
            'role' => 'staff_pembantu',
            'department' => 'Kewangan',
            'position' => 'Pembantu Tadbir',
            'is_active' => true,
        ]);

        // Create Sample Locations
        $rooms = ['Bilik A', 'Bilik B', 'Bilik C'];
        $racks = ['Rak 1', 'Rak 2', 'Rak 3'];
        $slots = ['Slot A', 'Slot B', 'Slot C'];

        foreach ($rooms as $room) {
            foreach ($racks as $rack) {
                foreach ($slots as $slot) {
                    Location::create([
                        'room' => $room,
                        'rack' => $rack,
                        'slot' => $slot,
                        'description' => "Lokasi $room - $rack - $slot",
                        'is_available' => true,
                    ]);
                }
            }
        }
    }
}