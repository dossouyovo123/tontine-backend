<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TontineSeeder extends Seeder
{
    public function run(): void
    {
        $tontines = [
            // ── Petits montants ────────────────────────────────
            ['nom' => 'Tontine 1 700 F',  'categorie' => 'petits',  'montant' => 1700],
            ['nom' => 'Tontine 2 500 F',  'categorie' => 'petits',  'montant' => 2500],
            ['nom' => 'Tontine 5 000 F',  'categorie' => 'petits',  'montant' => 5000],
            ['nom' => 'Tontine 6 000 F', 'categorie' => 'petits', 'montant' => 6000],
            ['nom' => 'Tontine 7 000 F', 'categorie' => 'petits', 'montant' => 7000],
             ['nom' => 'Tontine 7 500 F', 'categorie' => 'petits', 'montant' => 7500],


            // ── Moyens montants ───────────────────────────────
            ['nom' => 'Tontine 10 000 F', 'categorie' => 'moyens',  'montant' => 10000],
            ['nom' => 'Tontine 10 500 F', 'categorie' => 'moyens',  'montant' => 10500,
                         'description' => 'Montant différent de 10 000 F — vérifiez bien'],
             ['nom' => 'Tontine 12 000 F', 'categorie' => 'moyens',  'montant' => 12000],
           ['nom' => 'Tontine 14 000 F', 'categorie' => 'moyens',  'montant' => 14000],
           ['nom' => 'Tontine 15 000 F', 'categorie' => 'moyens',  'montant' => 15000],

            // ── Grands montants ────────────────────────────────
            ['nom' => 'Tontine 20 000 F', 'categorie' => 'grands',  'montant' => 20000],
            ['nom' => 'Tontine 21 000 F', 'categorie' => 'grands',  'montant' => 21000],
            ['nom' => 'Tontine 25 000 F', 'categorie' => 'grands',  'montant' => 25000],
            ['nom' => 'Tontine 30 000 F', 'categorie' => 'grands',  'montant' => 30000],

            // ── Premium ────────────────────────────────────────
            ['nom' => 'Tontine 40 000 F', 'categorie' => 'premium', 'montant' => 40000],
            ['nom' => 'Tontine 45 000 F', 'categorie' => 'premium', 'montant' => 45000],
            ['nom' => 'Tontine 60 000 F', 'categorie' => 'premium', 'montant' => 60000],
        ];

        foreach ($tontines as $t) {
            DB::table('tontines')->updateOrInsert(
                ['montant' => $t['montant']],
                array_merge($t, [
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}