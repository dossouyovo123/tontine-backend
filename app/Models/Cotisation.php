<?php

// ──────────────────────────────────────────────────────────────
// app/Models/Cotisation.php
// ──────────────────────────────────────────────────────────────
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cotisation extends Model
{
    protected $fillable = [
        'membre_id', 'num_semaine', 'annee', 'date_samedi', 'montant', 'statut',
    ];

    protected $casts = [
        'date_samedi' => 'date',
        'montant'     => 'integer',
        'num_semaine' => 'integer',
        'annee'       => 'integer',
    ];

    // ── Scopes utiles ─────────────────────────────────────────
    public function scopePayees($query)
    {
        return $query->where('statut', 'paye');
    }

    public function scopeImpayes($query)
    {
        return $query->where('statut', 'impaye');
    }

    // ── Helpers ───────────────────────────────────────────────
    public function estPaye(): bool
    {
        return $this->statut === 'paye';
    }

    /**
     * Marque la cotisation comme payée.
     * Met à jour montant + statut en une seule requête.
     */
    public function marquerPaye(): void
    {
        $this->update([
            'statut'  => 'paye',
            'montant' => \App\Services\TontineCalcService::MONTANT_HEBDO,
        ]);
    }

    /**
     * Annule un encaissement → statut 'impaye', montant remis à 0.
     * La ligne reste en base (jamais supprimée).
     */
    public function marquerImpaye(): void
    {
        $this->update([
            'statut'  => 'impaye',
            'montant' => 0,
        ]);
    }

    // ── Relation ──────────────────────────────────────────────
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }
}