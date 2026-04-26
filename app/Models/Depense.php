<?php
// app/Models/Depense.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Depense extends Model
{
    protected $fillable = [
        'motif', 'montant', 'date_depense', 'image_path', 'notes',
    ];

    protected $casts = [
        'date_depense' => 'date',
        'montant'      => 'integer',
    ];

    /** URL publique de l'image (null si pas d'image) */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path
            ? asset('storage/' . $this->image_path)
            : null;
    }
}