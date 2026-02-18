<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@U9itus.com',
            'password' => Hash::make('password'), // Change this in production
            'user_type' => 'admin',
            'email_verified_at' => now(),
            'is_verified' => true,
            'kyc_status' => 'approved',
        ]);

        $admin->assignRole('admin');
    }
}
