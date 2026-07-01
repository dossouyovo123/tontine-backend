<?php

namespace App\Console\Commands;

use App\Models\Cotisation;
use App\Models\Membre;
use App\Models\Sanction;
use App\Services\TontineCalcService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SanctionnerRetardCotisation extends Command
{
    protected $signature   = 'tontine:sanctionner-retard
                                {--dry-run : Affiche les sanctions sans les créer}
                                {--semaine= : Numéro de semaine ciblée (défaut: semaine courante)}';

    protected $description = 'Applique automatiquement une sanction 300 CFA pour chaque membre qui n\'a pas cotisé.';

    public function __construct(private TontineCalcService $calc)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDry   = $this->option('dry-run');
        $annee   = (int) now()->year;

        $semaine = $this->option('semaine')
            ? (int) $this->option('semaine')
            : $this->calc->semaineCourante();

        $this->info("=== Sanction retard cotisation — Semaine $semaine / $annee ===");
        if ($isDry) {
            $this->warn('[DRY-RUN] Aucune sanction ne sera créée.');
        }

        // 1. Membres actifs
        $membres = Membre::where('is_active', true)
            ->where('a_abandonne', false)
            ->pluck('id');

        if ($membres->isEmpty()) {
            $this->info('Aucun membre actif trouvé.');
            return self::SUCCESS;
        }

        // 2. Membres qui ont déjà payé (Correction ici : on utilise toArray)
        $dejaPayes = Cotisation::where('num_semaine', $semaine)
            ->where('annee', $annee)
            ->where('statut', 'paye')
            ->pluck('membre_id')
            ->toArray(); 

        // 3. Membres impayés (Correction ici : in_array pour chercher dans le tableau)
        $membresImpaye = $membres->reject(fn($id) => in_array($id, $dejaPayes));

        if ($membresImpaye->isEmpty()) {
            $this->info('Tous les membres ont cotisé. Aucune sanction nécessaire.');
            return self::SUCCESS;
        }

        $this->info("{$membresImpaye->count()} membre(s) n'ont pas cotisé.");

        $dateSanction = now()->toDateString();
        $created      = 0;
        $skipped      = 0;

        foreach ($membresImpaye as $membreId) {
            $exists = Sanction::where('membre_id',    $membreId)
                ->where('motif',       'retard_cotisation')
                ->where('auto_genere', true)
                ->whereYear('date_sanction', $annee)
                ->whereBetween('date_sanction', [
                    $this->calc->dateSamedi($semaine, $annee)->copy()->addDay(),
                    $this->calc->dateSamedi($semaine, $annee)->copy()->addDays(7),
                ])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            if (!$isDry) {
                DB::transaction(function () use ($membreId, $dateSanction) {
                    Sanction::create([
                        'membre_id'     => $membreId,
                        'motif'         => 'retard_cotisation',
                        'montant'       => Sanction::montantPourMotif('retard_cotisation'),
                        'statut'        => 'en_attente',
                        'notes'         => 'Sanction automatique — absence de cotisation le samedi',
                        'date_sanction' => $dateSanction,
                        'auto_genere'   => true,
                    ]);
                });
            }
            $created++;
            $this->line(" → Membre #$membreId — sanction 300  créée");
        }

        $this->newLine();
        $this->info("Sanctions créées  : $created");
        $this->info("Doublons ignorés  : $skipped");

        return self::SUCCESS;
    }
}
