<?php
// ──────────────────────────────────────────────────────────────
// app/Models/Membre.php
// Logique dû : le membre doit à la tontine (distributions reçues > cotisations)
// Le dû diminue automatiquement à chaque cotisation — aucune saisie manuelle
// ──────────────────────────────────────────────────────────────
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membre extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'num_registre', 'nom', 'telephone', 'adresse',
        'profession', 'is_active', 'a_abandonne', 'date_inscription',
        'tontine_id',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'a_abandonne'      => 'boolean',
        'date_inscription' => 'date',
    ];

    protected $appends = [
        'semaines_cotisees',
        'total_cotise_cfa',
        'est_eligible_moto',
        'perd_argent_si_abandon',
        'montant_cotisation',
        'total_distributions_recues',
        // ── Dû (ce que le membre doit À LA TONTINE) ───────────
        'du_vers_tontine',
        // ── Crédit (ce que la tontine doit encore AU MEMBRE) ──
        'credit_vers_membre',
        // ── Solde net (négatif = membre doit, positif = tontine doit) ──
        'solde_net',
        'a_termine_tontine',
    ];

    // ── Relations ─────────────────────────────────────────────
    public function cotisations(): HasMany
    {
        return $this->hasMany(Cotisation::class);
    }

    public function sanctions(): HasMany
    {
        return $this->hasMany(Sanction::class);
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class);
    }

    public function complements(): HasMany
    {
        return $this->hasMany(Complement::class);
    }

    public function tontine(): BelongsTo
    {
        return $this->belongsTo(Tontine::class);
    }

    // ── Accessors existants ────────────────────────────────────

    public function getMontantCotisationAttribute(): int
    {
        return $this->tontine?->montant ?? \App\Services\TontineCalcService::MONTANT_HEBDO;
    }

    public function getSemainesCotiseesAttribute(): int
    {
        return $this->cotisations()
            ->where('annee', (int) date('Y'))
            ->where('statut', 'paye')
            ->count();
    }

    public function getTotalCotiseCfaAttribute(): int
    {
        return (int) $this->cotisations()
            ->where('statut', 'paye')
            ->sum('montant');
    }

    public function getEstEligibleMotoAttribute(): bool
    {
        return $this->semainesCotisees >= \App\Services\TontineCalcService::SEUIL_ELIGIBILITE;
    }

    public function getPerdArgentSiAbandonAttribute(): bool
    {
        return $this->totalCotiseCfa < \App\Services\TontineCalcService::SEUIL_ABANDON;
    }

    public function getStatutLabelAttribute(): string
    {
        if ($this->a_abandonne) return 'ABANDONNÉ';
        return $this->is_active ? 'ACTIF' : 'INACTIF';
    }

    // ── Accessor : total distributions reçues ─────────────────

    /**
     * Somme de toutes les distributions versées à ce membre.
     */
    public function getTotalDistributionsRecuesAttribute(): int
    {
        return (int) $this->distributions()->sum('montant');
    }

    // ── Accessors dû / crédit ──────────────────────────────────

    /**
     * Ce que le membre doit À LA TONTINE.
     *
     * Cas : il a reçu une distribution anticipée supérieure à ce qu'il a cotisé.
     * Chaque semaine cotisée réduit ce dû automatiquement.
     * Formule : max(0, total_distributions_recues − total_cotise_cfa)
     *
     * Exemple :
     *   - Distribué : 500 000 CFA (semaine 26)
     *   - Cotisé à date : 260 000 CFA (26 × 10 000)
     *   - du_vers_tontine = 240 000 CFA
     *   - Semaine 27 cotisée → cotisé = 270 000 → du_vers_tontine = 230 000
     *   - À la semaine 50 cotisée → cotisé = 500 000 → du_vers_tontine = 0
     */
    public function getDuVersTontineAttribute(): int
    {
        return max(0, $this->totalDistributionsRecues - $this->totalCotiseCfa);
    }

    /**
     * Ce que la tontine doit encore AU MEMBRE (solde créditeur).
     *
     * Cas normal sans distribution anticipée, ou si le membre a cotisé
     * plus que ce qu'il a reçu.
     * Formule : max(0, total_cotise_cfa − total_distributions_recues)
     */
  public function getCreditVersMembreAttribute(): int
{
    return max(0, $this->totalCotiseCfa - $this->totalDistributionsRecues);
}

    /**
     * Solde net = total_cotise_cfa − total_distributions_recues
     *
     * Négatif → le membre doit à la tontine (|solde| = du_vers_tontine)
     * Positif → la tontine doit au membre (= credit_vers_membre)
     * Zéro    → équilibré
     */
    public function getSoldeNetAttribute(): int
    {
        return $this->totalCotiseCfa - $this->totalDistributionsRecues;
    }

    /**
     * Compatibilité ascendante : `du_membre` retourne le dû vers la tontine
     * (remplace l'ancien accesseur qui avait la sémantique inverse).
     *
     * @deprecated Préférer du_vers_tontine ou credit_vers_membre selon le sens.
     */
    public function getDuMembreAttribute(): int
    {
        return $this->getDuVersTontineAttribute();
    }

    /**
     * Vrai si le membre a cotisé les 52 semaines de l'année.
     */
    public function getATermineTontineAttribute(): bool
    {
        return $this->semainesCotisees >= \App\Services\TontineCalcService::TOTAL_SEMAINES;
    }

    // ── Helpers ───────────────────────────────────────────────

    public function aPayeSemaine(int $semaine, int $annee = null): bool
    {
        return $this->cotisations()
            ->where('num_semaine', $semaine)
            ->where('annee', $annee ?? (int) date('Y'))
            ->where('statut', 'paye')
            ->exists();
    }

    // ── Scopes ────────────────────────────────────────────────
    public function scopeActifs($q)
    {
        return $q->where('is_active', true)->where('a_abandonne', false);
    }

    public function scopeAbandonnes($q)
    {
        return $q->where('a_abandonne', true);
    }

    /**
     * Membres qui doivent de l'argent à la tontine
     * (ont reçu une avance non encore couverte par les cotisations).
     */
    public function scopeAvecDuVersTontine($q)
    {
        // Filtre en PHP après chargement car les accessors font des requêtes
        // Pour de la performance en production, voir note ci-dessous *
        return $q;
        // * En prod : utiliser une colonne calculée ou un sous-requête SQL
    }
}