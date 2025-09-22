<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

     User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'middle_initial' => 'A',
            'sex' => 'Male',
            'birth_date' => '1990-01-01',
            'address' => '123 Admin Street, Admin City',
            'contact_number' => '+1234567890',
            'province' => 'Admin Province',
            'district' => 1,
            'email' => 'admin@example.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'is_verified' => true,
            'created_by' => null,
        ]);
    }
}
