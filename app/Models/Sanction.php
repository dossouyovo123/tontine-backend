<?php 
// ──────────────────────────────────────────────────────────────
// app/Models/Sanction.php
// ──────────────────────────────────────────────────────────────
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
 
class Sanction extends Model
{
    // Montants fixes par motif (règle métier)
    const MONTANTS = [
        'retard_reunion'        => 300,
        'retard_cotisation'     => 300,
        'non_respect_statuts'   => 300,
        'absence_non_justifiee' => 500,
        'autre'                 => 300,
    ];
 
    protected $fillable = [
        'membre_id', 'motif', 'montant',
        'date_sanction', 'statut', 'date_paiement', 'notes',
    ];
 
    protected $casts = [
        'date_sanction'  => 'date',
        'date_paiement'  => 'date',
    ];
 
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }
 
    /** Déduit le montant fixe du motif */
    public static function montantPourMotif(string $motif): int
    {
        return self::MONTANTS[$motif] ?? 300;
    }
}
?>