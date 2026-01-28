<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MemberUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the member user with view-only role
        User::firstOrCreate(
            ['email' => 'member@bossingloan'],
            [
                'name' => 'member',
                'email' => 'member@bossingloan',
                'password' => Hash::make('bossing2026'),
                'role' => 'viewer',
                'email_verified_at' => now(),
            ]
        );
    }
}
