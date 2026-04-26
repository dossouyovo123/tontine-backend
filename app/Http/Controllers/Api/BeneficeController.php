<?php
// app/Http/Controllers/Api/BeneficeController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Benefice, Membre};
use App\Services\TontineCalcService;
use Carbon\Carbon;
use Illuminate\Http\{Request, JsonResponse};

class BeneficeController extends Controller
{
    public function __construct(private TontineCalcService $calc) {}

    // ──────────────────────────────────────────────────────────
    // GET /benefices?annee=2026
    // Liste tous les bénéfices + résumé global
    // ──────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $annee = (int) ($request->annee ?? now()->year);

        $benefices = Benefice::with(['membre' => fn($q) => $q->withTrashed()])
            ->where('annee', $annee)
            ->orderBy('date_prelevement', 'desc')
            ->get()
            ->map(fn($b) => $this->formatBenefice($b));

        return response()->json([
            'annee'              => $annee,
            'total_benefices'    => $benefices->sum('montant_benefice'),
            'nb_membres'         => $benefices->count(),
            'benefices'          => $benefices,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // POST /benefices/calculer
    // Calcule et enregistre les bénéfices pour tous les membres
    // après la fin des 52 semaines.
    //
    // LOGIQUE :
    // - Pour chaque membre avec une tontine
    // - bénéfice = montant de sa tontine (fixe, peu importe le nb semaines payées)
    // - total_cotise = somme des cotisations payées sur l'année
    // - insertOrIgnore → idempotent (on peut relancer sans doublon)
    // ──────────────────────────────────────────────────────────
    public function calculer(Request $request): JsonResponse
    {
        $annee = (int) ($request->annee ?? now()->year);

        // Vérifie qu'on est bien à la semaine 52 (ou après)
        $semaineCourante = $this->calc->semaineCourante($annee);
        if ($semaineCourante < TontineCalcService::TOTAL_SEMAINES) {
            return response()->json([
                'message' => "Impossible de calculer : semaine courante $semaineCourante / 52. Attendez la fin des 52 semaines.",
                'semaine_courante' => $semaineCourante,
            ], 422);
        }

        // Membres avec une tontine assignée (non supprimés)
        $membres = Membre::withoutTrashed()
            ->with('tontine')
            ->whereNotNull('tontine_id')
            ->get();

        $calcules = 0;
        $ignores  = 0;
        $datePrelevement = now()->toDateString();

        foreach ($membres as $membre) {
            // Total cotisé sur les 52 semaines de l'année
            $totalCotise = (int) $membre->cotisations()
                ->where('annee', $annee)
                ->where('statut', 'paye')
                ->sum('montant');

            // Bénéfice = montant de la tontine (fixe)
            $montantBenefice = $membre->montant_cotisation;

            // insertOrIgnore = si déjà calculé pour ce membre/année, on skip
            $inserted = \DB::table('benefices')->insertOrIgnore([[
                'membre_id'        => $membre->id,
                'annee'            => $annee,
                'total_cotise'     => $totalCotise,
                'montant_benefice' => $montantBenefice,
                'date_prelevement' => $datePrelevement,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]]);

            $inserted ? $calcules++ : $ignores++;
        }

        return response()->json([
            'message'   => "Bénéfices calculés pour $calcules membres ($ignores déjà existants).",
            'calcules'  => $calcules,
            'ignores'   => $ignores,
            'annee'     => $annee,
            'total_benefices' => Benefice::where('annee', $annee)->sum('montant_benefice'),
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // GET /benefices/stats
    // Résumé rapide pour le dashboard
    // ──────────────────────────────────────────────────────────
    public function stats(): JsonResponse
    {
        $annee = now()->year;

        return response()->json([
            'annee'           => $annee,
            'total_benefices' => (int) Benefice::where('annee', $annee)->sum('montant_benefice'),
            'nb_preleves'     => (int) Benefice::where('annee', $annee)->count(),
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // DELETE /benefices/{benefice}
    // Suppression (correction d'une erreur admin)
    // ──────────────────────────────────────────────────────────
    public function destroy(Benefice $benefice): JsonResponse
    {
        $benefice->delete();
        return response()->json(['message' => 'Bénéfice supprimé.']);
    }

    // ── Helper ────────────────────────────────────────────────
    private function formatBenefice(Benefice $b): array
    {
        return [
            'id'               => $b->id,
            'annee'            => $b->annee,
            'total_cotise'     => $b->total_cotise,
            'montant_benefice' => $b->montant_benefice,
            'benefice_net'     => $b->benefice_net,
            'date_prelevement' => Carbon::parse($b->date_prelevement)->format('d/m/Y'),
            'notes'            => $b->notes,
            'membre' => $b->membre ? [
                'id'           => $b->membre->id,
                'nom'          => $b->membre->nom,
                'num_registre' => $b->membre->num_registre,
                'tontine'      => $b->membre->tontine ? [
                    'nom'    => $b->membre->tontine->nom,
                    'montant'=> $b->membre->tontine->montant,
                ] : null,
            ] : null,
        ];
    }
}