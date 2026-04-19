<?php

// ──────────────────────────────────────────────────────────────
// app/Models/Membre.php
// ──────────────────────────────────────────────────────────────
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Membre extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'num_registre', 'nom', 'telephone', 'adresse',
        'profession', 'is_active', 'a_abandonne', 'date_inscription',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'a_abandonne'      => 'boolean',
        'date_inscription' => 'date',
    ];

    /**
     * Ces attributs sont toujours inclus dans la sérialisation JSON.
     * Flutter peut ainsi accéder directement à ces valeurs calculées
     * sans appel supplémentaire.
     */
    protected $appends = [
        'semaines_cotisees',
        'total_cotise_cfa',
        'est_eligible_moto',
        'perd_argent_si_abandon',
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

    // ── Accessors calculés ────────────────────────────────────

    /**
     * Nombre de semaines PAYÉES pour l'année courante uniquement.
     * Utilisé dans Flutter pour la progression annuelle et l'éligibilité moto.
     */
    public function getSemainesCotiseesAttribute(): int
    {
        return $this->cotisations()
            ->where('annee', (int) date('Y'))
            ->where('statut', 'paye')
            ->count();
    }

    /**
     * Total CFA versé (toutes années confondues, semaines payées uniquement).
     * Sert au calcul de la règle abandon (< 50 000 CFA → perd son argent).
     */
    public function getTotalCotiseCfaAttribute(): int
    {
        return (int) $this->cotisations()
            ->where('statut', 'paye')
            ->sum('montant');
    }

    /**
     * Éligible au complément moto si ≥ 12 semaines payées (année courante).
     */
    public function getEstEligibleMotoAttribute(): bool
    {
        return $this->semainesCotisees >= \App\Services\TontineCalcService::SEUIL_ELIGIBILITE;
    }

    /**
     * Perd son argent en cas d'abandon si total cotisé < 50 000 CFA.
     */
    public function getPerdArgentSiAbandonAttribute(): bool
    {
        return $this->totalCotiseCfa < \App\Services\TontineCalcService::SEUIL_ABANDON;
    }

    /**
     * Label lisible du statut : ACTIF / INACTIF / ABANDONNÉ.
     */
    public function getStatutLabelAttribute(): string
    {
        if ($this->a_abandonne) return 'ABANDONNÉ';
        return $this->is_active ? 'ACTIF' : 'INACTIF';
    }

    /**
     * Vérifie si le membre a payé une semaine précise.
     * N'utilise pas les semaines auto-générées (impayé) — vérifie seulement le statut 'paye'.
     */
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
}