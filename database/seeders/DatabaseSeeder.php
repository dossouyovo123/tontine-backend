<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ── Ordre important : Tontines avant tout le reste ────────────────
        // Les membres ont une FK tontine_id → tontines doit exister en premier
        $this->call([
            TontineSeeder::class, // ← NOUVEAU : crée les 12 tontines
            AdminSeeder::class,   // Admin par défaut
        ]);
    }
}