<?php 
// ──────────────────────────────────────────────────────────────
// app/Models/Complement.php
// ──────────────────────────────────────────────────────────────
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
 
class Complement extends Model
{
    protected $fillable = [
        'reference', 'membre_id',
        'semaines_cotisees_snapshot', 'montant_cotise_total', 'montant_moto_estime',
        'description_moto', 'statut',
        'date_demande', 'date_attribution_moto', 'notes_admin',
    ];
 
    protected $casts = [
        'date_demande'          => 'date',
        'date_attribution_moto' => 'date',
    ];
 
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }
 
    public function getMontantComplementAttribute(): int
    {
        return $this->montant_moto_estime - $this->montant_cotise_total;
    }
 
    /** Génère la prochaine référence CMP00X */
    public static function prochainerReference(): string
    {
        $count = self::count() + 1;
        return 'CMP' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }
}?>