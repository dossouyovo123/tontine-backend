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

 public function getImageUrlAttribute(): ?string
{
    return $this->image_path
        ? url('api/v1/storage/' . $this->image_path)
        : null;
}
}