<?php
// app/Http/Controllers/Api/TontineController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tontine;
use Illuminate\Http\JsonResponse;

class TontineController extends Controller
{
    /**
     * GET /tontines
     * Retourne toutes les tontines groupées par catégorie pour le picker Flutter.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'categories' => Tontine::groupeesParCategorie(),
        ]);
    }

    /**
     * GET /tontines/{tontine}
     * Détail d'une tontine + ses membres actifs.
     */
    public function show(Tontine $tontine): JsonResponse
    {
        $tontine->load(['membres' => fn($q) => $q->where('is_active', true)->where('a_abandonne', false)]);

        return response()->json([
            'tontine'       => $tontine,
            'nb_membres'    => $tontine->membres->count(),
        ]);
    }
}