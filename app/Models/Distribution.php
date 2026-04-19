<?php 
// ──────────────────────────────────────────────────────────────
// app/Models/Distribution.php
// ──────────────────────────────────────────────────────────────
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
 
class Distribution extends Model
{
    protected $fillable = [
        'membre_id', 'montant', 'date_distribution', 'note',
    ];
 
    protected $casts = [
        'date_distribution' => 'date',
        'montant'           => 'integer',
    ];
 
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }
}?>