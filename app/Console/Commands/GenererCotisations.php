<?php

// ──────────────────────────────────────────────────────────────
// app/Console/Commands/GenererCotisations.php
//
// USAGE :
//   php artisan tontine:generer-cotisations          # année courante
//   php artisan tontine:generer-cotisations --annee=2025
//   php artisan tontine:generer-cotisations --tous   # membres actifs ET abandonnés
//
// Utile pour initialiser les données en DB la première fois,
// ou pour corriger les membres existants qui n'ont pas de semaines.
// ──────────────────────────────────────────────────────────────
namespace App\Console\Commands;

use App\Models\Membre;
use App\Services\{TontineCalcService, CotisationService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenererCotisations extends Command
{
    protected $signature   = 'tontine:generer-cotisations {--annee= : Année cible} {--tous : Inclure les membres abandonnés}';
    protected $description = 'Génère toutes les semaines manquantes en statut impayé pour tous les membres';

    public function __construct(
        private CotisationService  $cotisationService,
        private TontineCalcService $calc
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $annee           = (int) ($this->option('annee') ?? date('Y'));
        $semaineCourante = $this->calc->semaineCourante($annee);
        $inclureTous     = $this->option('tous');

        $this->info("=== Génération des cotisations ===");
        $this->info("Année : $annee | Semaine courante : $semaineCourante");

        // Récupérer les membres à traiter
        $query = Membre::withoutTrashed();
        if (!$inclureTous) {
            $query->where('is_active', true)->where('a_abandonne', false);
        }
        $membres = $query->get(['id', 'nom', 'num_registre']);

        $this->info("Membres trouvés : " . $membres->count());

        if ($membres->isEmpty()) {
            $this->warn("Aucun membre actif trouvé.");
            return self::SUCCESS;
        }

        // Compter avant
        $avantTotal = DB::table('cotisations')->where('annee', $annee)->count();

        $bar = $this->output->createProgressBar($membres->count());
        $bar->start();

        foreach ($membres as $membre) {
            $this->cotisationService->genererPourMembre($membre->id, $annee);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Compter après
        $apresTotal = DB::table('cotisations')->where('annee', $annee)->count();
        $inseres    = $apresTotal - $avantTotal;

        $this->info("✅ Terminé !");
        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Membres traités',    $membres->count()],
                ['Semaines attendues', $membres->count() * $semaineCourante],
                ['Lignes en DB avant', $avantTotal],
                ['Lignes en DB après', $apresTotal],
                ['Nouvelles insertions', $inseres],
            ]
        );

        if ($inseres === 0) {
            $this->info("ℹ️  Toutes les semaines étaient déjà présentes en base.");
        }

        return self::SUCCESS;
    }
}