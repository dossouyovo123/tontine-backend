<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::updateOrCreate(
            ['email' => 'trigagant1@gmail.com'],
            [
                'nom'       => 'Admin',
                'telephone' => '97000000',
                'password'  => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}