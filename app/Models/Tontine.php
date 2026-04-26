<?php
// app/Models/Tontine.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tontine extends Model
{
    protected $fillable = ['nom', 'categorie', 'montant', 'description', 'is_active'];

    protected $casts = [
        'montant'   => 'integer',
        'is_active' => 'boolean',
    ];

    public function membres(): HasMany
    {
        return $this->hasMany(Membre::class);
    }

    public function getCategorieLibelleAttribute(): string
    {
        return match($this->categorie) {
            'petits'  => 'Petits montants',
            'moyens'  => 'Moyens montants',
            'grands'  => 'Grands montants',
            'premium' => 'Premium',
            default   => $this->categorie,
        };
    }

    /** Toutes les tontines actives, groupées par catégorie — pour l'API Flutter */
    public static function groupeesParCategorie(): array
    {
        $ordre = ['petits', 'moyens', 'grands', 'premium'];

        return static::where('is_active', true)
            ->orderByRaw("FIELD(categorie, 'petits','moyens','grands','premium')")
            ->orderBy('montant')
            ->get()
            ->groupBy('categorie')
            ->sortBy(fn($_, $key) => array_search($key, $ordre))
            ->map(fn($items, $cat) => [
                'categorie' => $cat,
                'libelle'   => $items->first()->categorieLibelle,
                'tontines'  => $items->values(),
            ])
            ->values()
            ->all();
    }
}