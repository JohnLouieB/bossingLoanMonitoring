<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the admin user
        User::firstOrCreate(
            ['email' => 'admin@bossingloan'],
            [
                'name' => 'admin',
                'email' => 'admin@bossingloan',
                'password' => Hash::make('@123bossAdmin2026'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );
    }
}
