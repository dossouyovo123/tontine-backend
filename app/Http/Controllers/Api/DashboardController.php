<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Membre, Cotisation, Sanction, Distribution, Complement};
use App\Services\TontineCalcService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private TontineCalcService $calc) {}

    public function index(): JsonResponse
    {
        $semaine    = $this->calc->semaineCourante();
        $annee      = now()->year;
        $dateSamedi = $this->calc->dateSamedi($semaine, $annee)->format('d/m/Y');

        // ── CORRECTION CRITIQUE ──────────────────────────────────
        // On récupère UNE SEULE FOIS le compte des actifs pour éviter
        // toute désynchronisation entre les deux utilisations.
        $totalActifs = (int) Membre::actifs()->count();

        // Nombre de membres qui ont DÉJÀ payé cette semaine
        $nbPayes = (int) Cotisation::where('num_semaine', $semaine)
                                   ->where('annee', $annee)
                                   ->where('statut', 'paye')
                                   ->count();

        // Membres actifs qui N'ONT PAS encore payé → jamais négatif
        $nbRetard = max(0, $totalActifs - $nbPayes);

        // Montant collecté cette semaine
        $collecte = $nbPayes * TontineCalcService::MONTANT_HEBDO;

        // ── Sanctions décomposées ─────────────────────────────────
        $sanctTotal     = (int) Sanction::sum('montant');
        $sanctEncaisse  = (int) Sanction::where('statut', 'paye')->sum('montant');
        $sanctEnAttente = $sanctTotal - $sanctEncaisse; // plus fiable que != 'paye'

        return response()->json([
            'semaine_courante' => $semaine,
            'date_samedi'      => $dateSamedi,

            'membres' => [
                'total'     => (int) Membre::count(),
                'actifs'    => $totalActifs,
                'inactifs'  => (int) Membre::where('is_active', false)
                                           ->where('a_abandonne', false)->count(),
                'abandonnes'=> (int) Membre::abandonnes()->count(),
            ],

            // ── CLÉS EXPLICITES AVEC TYPES INT ────────────────────
            // Flutter fait safeInt(cot['payes']), cot['retard'], cot['collecte']
            // On s'assure que chaque valeur est bien un entier (pas un string)
            'cotisations_semaine' => [
                'payes'    => $nbPayes,    // int
                'retard'   => $nbRetard,   // int, max(0, actifs - payés)
                'collecte' => $collecte,   // int
            ],

            'totaux' => [
                'cotisations'          => (int) Cotisation::sum('montant'),
                'distributions'        => (int) Distribution::sum('montant'),
                'sanctions'            => $sanctTotal,
                'sanctions_encaisse'   => $sanctEncaisse,
                'sanctions_en_attente' => $sanctEnAttente,
            ],

            'complements' => [
                'en_attente'    => (int) Complement::where('statut', 'en_attente')->count(),
                'approuves'     => (int) Complement::where('statut', 'approuve')->count(),
                'moto_attribuee'=> (int) Complement::where('statut', 'moto_attribuee')->count(),
            ],
        ]);
    }

    public function statsCotisations(): JsonResponse
    {
        $semaine    = $this->calc->semaineCourante();
        $parSemaine = Cotisation::where('annee', now()->year)
            ->where('statut', 'paye')
            ->selectRaw('num_semaine, COUNT(*) as nb_membres, SUM(montant) as total')
            ->groupBy('num_semaine')
            ->orderBy('num_semaine')
            ->get()
            ->map(fn($r) => [
                'num_semaine' => (int) $r->num_semaine,
                'nb_membres'  => (int) $r->nb_membres,
                'total'       => (int) $r->total,
                'date_samedi' => $this->calc->dateSamedi($r->num_semaine)->format('d/m/Y'),
            ]);

        return response()->json([
            'semaine_courante' => $semaine,
            'par_semaine'      => $parSemaine,
            'total_annee'      => (int) Cotisation::where('annee', now()->year)->sum('montant'),
        ]);
    }

    public function statsSanctions(): JsonResponse
    {
        $total     = (int) Sanction::sum('montant');
        $encaisse  = (int) Sanction::where('statut', 'paye')->sum('montant');
        return response()->json([
            'total'      => $total,
            'encaisse'   => $encaisse,
            'en_attente' => $total - $encaisse,
            'par_motif'  => Sanction::selectRaw('motif, COUNT(*) as nb, SUM(montant) as total')
                ->groupBy('motif')->get(),
        ]);
    }

    public function statsMembres(): JsonResponse
    {
        return response()->json([
            'total'          => (int) Membre::count(),
            'actifs'         => (int) Membre::actifs()->count(),
            'eligibles_moto' => (int) Membre::actifs()->get()
                ->filter(fn($m) => $m->semaines_cotisees >= TontineCalcService::SEUIL_ELIGIBILITE)
                ->count(),
        ]);
    }
}