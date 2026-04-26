<?php
// app/Models/Membre.php
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
        'tontine_id', // ← NOUVEAU
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
        'montant_cotisation', // ← NOUVEAU
    ];

    // ── Relations ─────────────────────────────────────────
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

    /** ← NOUVEAU : tontine du membre */
    public function tontine(): BelongsTo
    {
        return $this->belongsTo(Tontine::class);
    }

    // ── Accessors ─────────────────────────────────────────

    /**
     * ← NOUVEAU : montant hebdomadaire propre à ce membre.
     * Fallback vers MONTANT_HEBDO si pas de tontine assignée.
     */
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

    /**
     * Somme réelle des montants encaissés (toutes années).
     * Utilise le montant DB — correct même si le membre a changé de tontine.
     */
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

    public function aPayeSemaine(int $semaine, int $annee = null): bool
    {
        return $this->cotisations()
            ->where('num_semaine', $semaine)
            ->where('annee', $annee ?? (int) date('Y'))
            ->where('statut', 'paye')
            ->exists();
    }

    // ── Scopes ────────────────────────────────────────────
    public function scopeActifs($q)
    {
        return $q->where('is_active', true)->where('a_abandonne', false);
    }

    public function scopeAbandonnes($q)
    {
        return $q->where('a_abandonne', true);
    }
}