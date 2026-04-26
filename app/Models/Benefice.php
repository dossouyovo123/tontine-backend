<?php
// app/Models/Benefice.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Benefice extends Model
{
    protected $fillable = [
        'membre_id', 'annee', 'total_cotise',
        'montant_benefice', 'date_prelevement', 'notes',
    ];

    protected $casts = [
        'date_prelevement' => 'date',
        'total_cotise'     => 'integer',
        'montant_benefice' => 'integer',
        'annee'            => 'integer',
    ];

    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    /** Bénéfice net = total cotisé - prélèvement */
    public function getBeneficeNetAttribute(): int
    {
        return $this->total_cotise - $this->montant_benefice;
    }
}