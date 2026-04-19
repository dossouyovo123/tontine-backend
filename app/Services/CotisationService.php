<?php

// ──────────────────────────────────────────────────────────────
// app/Services/CotisationService.php
// ──────────────────────────────────────────────────────────────
namespace App\Services;

use App\Models\Cotisation;
use App\Models\Membre;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CotisationService
{
    public function __construct(private TontineCalcService $calc) {}

    /**
     * Génère en base TOUTES les semaines manquantes (1 → semaine courante).
     *
     * CORRECTION CLÉ :
     * Quand $membreId est fourni (après création / réactivation),
     * on ne filtre PAS sur is_active/a_abandonne.
     * Raison : juste après Membre::create(), les valeurs is_active/a_abandonne
     * peuvent ne pas encore être celles de la DB si la migration membres
     * n'a pas de default explicite — le filtre exclurait alors le nouveau membre.
     *
     * @param int|null $annee    Année cible (défaut : année courante)
     * @param int|null $membreId Membre spécifique (null = tous les actifs)
     */
    public function genererSemainesManquantes(int $annee = null, int $membreId = null): void
    {
        try {
            $annee           = $annee ?? (int) date('Y');
            $semaineCourante = $this->calc->semaineCourante($annee);

            if ($membreId !== null) {
                // ── Membre spécifique : pas de filtre is_active ────────
                // On vérifie juste que le membre existe et n'est pas supprimé (SoftDelete)
                $membres = Membre::withoutTrashed()
                    ->where('id', $membreId)
                    ->get(['id']);
            } else {
                // ── Tous les membres actifs ────────────────────────────
                $membres = Membre::withoutTrashed()
                    ->where('is_active', true)
                    ->where('a_abandonne', false)
                    ->get(['id']);
            }

            if ($membres->isEmpty()) {
                Log::debug("genererSemainesManquantes: aucun membre trouvé", [
                    'membreId' => $membreId, 'annee' => $annee
                ]);
                return;
            }

            $membreIds = $membres->pluck('id')->all();

            // Lire les lignes existantes en UNE seule requête (pas de N×52 firstOrCreate)
            $existantes = DB::table('cotisations')
                ->where('annee', $annee)
                ->whereIn('membre_id', $membreIds)
                ->whereBetween('num_semaine', [1, $semaineCourante])
                ->select(['membre_id', 'num_semaine'])
                ->get()
                ->mapWithKeys(fn($c) => [$c->membre_id . '_' . $c->num_semaine => true])
                ->all();

            // Construire uniquement les lignes manquantes
            $aInserer = [];
            $now      = now()->toDateTimeString();

            foreach ($membreIds as $mid) {
                for ($s = 1; $s <= $semaineCourante; $s++) {
                    $key = $mid . '_' . $s;
                    if (!isset($existantes[$key])) {
                        $aInserer[] = [
                            'membre_id'   => $mid,
                            'num_semaine' => $s,
                            'annee'       => $annee,
                            'statut'      => 'impaye',  // jamais 'paye' à la génération
                            'montant'     => 0,
                            'date_samedi' => $this->calc->dateSamedi($s, $annee)->toDateString(),
                            'created_at'  => $now,
                            'updated_at'  => $now,
                        ];
                    }
                }
            }

            // Insertion batch — insertOrIgnore respecte la contrainte unique
            // sans lever d'exception (protection contre les race conditions)
            if (!empty($aInserer)) {
                $nb = count($aInserer);
                Log::debug("genererSemainesManquantes: insertion de $nb lignes", [
                    'membreId' => $membreId, 'annee' => $annee
                ]);
                foreach (array_chunk($aInserer, 500) as $chunk) {
                    DB::table('cotisations')->insertOrIgnore($chunk);
                }
            }

        } catch (\Throwable $e) {
            Log::error('CotisationService::genererSemainesManquantes — ' . $e->getMessage(), [
                'annee'    => $annee,
                'membreId' => $membreId,
                'trace'    => $e->getTraceAsString(),
            ]);
            // Ne pas propager — la génération est un "best effort"
        }
    }

    /**
     * Génère les semaines pour un membre spécifique.
     * Appelé après : création, réactivation, consultation du profil.
     */
    public function genererPourMembre(int $membreId, int $annee = null): void
    {
        $this->genererSemainesManquantes($annee, $membreId);
    }
}