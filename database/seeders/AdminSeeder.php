<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('admins')->insert([
            'nom' => 'Admin',
            'telephone' => '97000000',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
            
        ]);
    }
}