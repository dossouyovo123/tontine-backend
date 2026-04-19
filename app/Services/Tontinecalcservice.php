<?php

// ──────────────────────────────────────────────────────────────
// app/Services/TontineCalcService.php
// ──────────────────────────────────────────────────────────────
namespace App\Services;

use Carbon\Carbon;

/**
 * TontineCalcService
 * ──────────────────
 * Centralise tous les calculs métier de la tontine :
 *  - numéro de semaine basé sur le 1er samedi de janvier
 *  - date réelle d'un samedi donné
 *  - éligibilité moto
 *  - règle abandon
 *
 * Toutes les constantes métier sont définies ici — une seule source de vérité
 * partagée entre CotisationService, les contrôleurs et les modèles.
 */
class TontineCalcService
{
    // ── Constantes métier (utilisées dans tout le backend) ────
    const MONTANT_HEBDO     = 10000; // CFA par semaine
    const TOTAL_SEMAINES    = 52;    // semaines par an
    const SEUIL_ABANDON     = 50000; // CFA — en dessous → perd son argent
    const SEUIL_ELIGIBILITE = 12;    // semaines min pour moto

    // ── Calcul du calendrier ──────────────────────────────────

    /**
     * Premier samedi de janvier pour une année donnée.
     *
     * Exemples : 2026 → 03/01 | 2027 → 02/01 | 2028 → 07/01
     *
     * Carbon dayOfWeek : 0 = dim, 1 = lun … 6 = sam
     */
    public function premierSamediJanvier(int $annee = null): Carbon
    {
        $annee = $annee ?? now()->year;
        $jan1  = Carbon::create($annee, 1, 1);
        // Jours à ajouter pour atteindre le premier samedi
        $joursAvantSam = (6 - $jan1->dayOfWeek + 7) % 7;
        return $jan1->copy()->addDays($joursAvantSam);
    }

    /**
     * Date réelle du samedi correspondant à la semaine N.
     * Semaine 1 = premier samedi de janvier.
     */
    public function dateSamedi(int $semaine, int $annee = null): Carbon
    {
        return $this->premierSamediJanvier($annee)
                    ->addWeeks($semaine - 1);
    }

    /**
     * Numéro de semaine courante basé sur le 1er samedi de janvier.
     *
     * - Retourne 1 si on est avant le premier samedi.
     * - Plafonné à TOTAL_SEMAINES (52).
     */
   public function semaineCourante(int $annee = null): int
{
    $debut = $this->premierSamediJanvier($annee);
    $now   = now();

    // si on est avant le début → semaine 1
    if ($now->lt($debut)) {
        return 1;
    }

    $diffDays = $debut->diffInDays($now); // TOUJOURS positif

    $semaine = intdiv($diffDays, 7) + 1;

    return min($semaine, self::TOTAL_SEMAINES);
}

    /**
     * Montant total attendu si toutes les semaines écoulées sont payées.
     */
    public function montantAttendu(int $annee = null): int
    {
        return $this->semaineCourante($annee) * self::MONTANT_HEBDO;
    }

    // ── Règles métier ─────────────────────────────────────────

    /**
     * Éligible au complément moto si ≥ SEUIL_ELIGIBILITE semaines payées.
     */
    public function estEligibleMoto(int $semainesCotisees): bool
    {
        return $semainesCotisees >= self::SEUIL_ELIGIBILITE;
    }

    /**
     * Perd son argent en cas d'abandon si total cotisé < SEUIL_ABANDON CFA.
     */
    public function perdArgentSiAbandon(int $totalCotiseCfa): bool
    {
        return $totalCotiseCfa < self::SEUIL_ABANDON;
    }

    /**
     * Résumé du statut d'un membre pour une semaine donnée.
     * Utilisé dans les contrôleurs pour retourner l'état complet.
     */
    public function statutSemaine(\App\Models\Membre $membre, int $semaine = null, int $annee = null): array
    {
        $annee   = $annee   ?? now()->year;
        $semaine = $semaine ?? $this->semaineCourante($annee);
        $paye    = $membre->aPayeSemaine($semaine, $annee);

        // Statut enrichi pour l'affichage Flutter
        $statut = 'impaye';
        if ($paye) {
            $statut = 'paye';
        } elseif ($membre->semainesCotisees > 0) {
            // A déjà cotisé par le passé mais pas cette semaine
            $statut = 'en_retard';
        }

        return [
            'membre_id'        => $membre->id,
            'semaine_courante' => $semaine,
            'paye_cette_sem'   => $paye,
            'statut'           => $statut,
            'semaines_payees'  => $membre->semainesCotisees,
            'total_cotise'     => $membre->totalCotiseCfa,
            'eligible_moto'    => $this->estEligibleMoto($membre->semainesCotisees),
        ];
    }
}