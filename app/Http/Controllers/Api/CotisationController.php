<?php

// ──────────────────────────────────────────────────────────────
// app/Http/Controllers/Api/CotisationController.php
// ──────────────────────────────────────────────────────────────
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Cotisation, Membre};
use App\Services\{TontineCalcService, CotisationService};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CotisationController extends Controller
{
    public function __construct(
        private TontineCalcService $calc,
        private CotisationService  $cotisationService
    ) {}

    // ──────────────────────────────────────────────────────────
    // GET /cotisations?annee=2026
    // Liste globale — TOUTES les cotisations (payées ET impayées)
    // Génère les semaines manquantes avant de lire
    // ──────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $this->cotisationService->genererSemainesManquantes();

        $annee = (int) ($request->annee ?? now()->year);

        $cotisations = Cotisation::with('membre')
            ->where('annee', $annee)
            ->orderBy('membre_id')
            ->orderBy('num_semaine')
            ->get();

        return response()->json([
            'annee'       => $annee,
            'cotisations' => $cotisations,
            'total'       => $cotisations->count(),
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // GET /cotisations/semaine/{semaine?}?annee=2026
    // Statut de TOUS les membres actifs pour une semaine donnée
    // ──────────────────────────────────────────────────────────
    public function semaine(Request $request, int $semaine = null): JsonResponse
    {
        $this->cotisationService->genererSemainesManquantes();

        $semaine = $semaine ?? $this->calc->semaineCourante();
        $annee   = (int) ($request->annee ?? now()->year);

        $membres = Membre::where('is_active', true)
            ->where('a_abandonne', false)
            ->get();

        // Indexer les cotisations par membre_id pour un accès O(1)
        $cotisations = Cotisation::where('num_semaine', $semaine)
            ->where('annee', $annee)
            ->get()
            ->keyBy('membre_id');

        $liste = $membres->map(function ($m) use ($cotisations) {
            $cot = $cotisations->get($m->id);

            return [
                'membre_id'     => $m->id,
                'nom'           => $m->nom,
                'telephone'     => $m->telephone,
                'cotisation_id' => $cot?->id,                     // nécessaire pour l'annulation Flutter
                'statut'        => $cot?->statut ?? 'impaye',
                'paye'          => $cot?->statut === 'paye',
            ];
        });

        return response()->json([
            'semaine'          => $semaine,
            'annee'            => $annee,
            'semaine_courante' => $this->calc->semaineCourante(),
            'membres'          => $liste,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // POST /cotisations/encaisser
    // Encaisse une semaine pour un membre.
    //
    // LOGIQUE :
    // - La ligne existe déjà (générée en 'impaye') → updateOrCreate la passe à 'paye'
    // - La ligne n'existe pas encore → updateOrCreate la crée en 'paye'
    // - Contrainte unique (membre_id, num_semaine, annee) → jamais de doublon
    // ──────────────────────────────────────────────────────────
    public function encaisser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'membre_id'   => 'required|exists:membres,id',
            'num_semaine' => 'required|integer|between:1,52',
            'annee'       => 'sometimes|integer|min:2020|max:2100',
        ]);

        $annee      = (int) ($data['annee'] ?? now()->year);
        $dateSamedi = $this->calc->dateSamedi($data['num_semaine'], $annee)->toDateString();

        $cotisation = Cotisation::updateOrCreate(
            [
                // Clé de recherche unique — trouve la ligne existante quelle que soit son statut
                'membre_id'   => $data['membre_id'],
                'num_semaine' => $data['num_semaine'],
                'annee'       => $annee,
            ],
            [
                // Valeurs à appliquer (création ou mise à jour)
                'date_samedi' => $dateSamedi,
                'montant'     => TontineCalcService::MONTANT_HEBDO,
                'statut'      => 'paye',
            ]
        );

        $membre = Membre::find($data['membre_id']);

        return response()->json([
            'id'          => $cotisation->id,
            'num_semaine' => $cotisation->num_semaine,
            'annee'       => $cotisation->annee,
            'date_samedi' => $cotisation->date_samedi->format('d/m/Y'),
            'montant'     => $cotisation->montant,
            'statut'      => $cotisation->statut,
            'paye'        => true,
            'membre'      => [
                'id'               => $membre->id,
                'nom'              => $membre->nom,
                'semaines_cotisees'=> $membre->semaines_cotisees,   // recalculé depuis la DB
                'total_cotise_cfa' => $membre->total_cotise_cfa,
                'est_eligible_moto'=> $membre->est_eligible_moto,
            ],
        ], 201);
    }

    // ──────────────────────────────────────────────────────────
    // PUT /cotisations/{cotisation}/annuler
    // Annule un encaissement → statut 'impaye', montant 0
    // La ligne reste en DB (jamais supprimée)
    // ──────────────────────────────────────────────────────────
    public function annuler(Cotisation $cotisation): JsonResponse
    {
        $cotisation->marquerImpaye(); // via le helper du modèle

        return response()->json([
            'message'     => 'Cotisation remise en impayé',
            'id'          => $cotisation->id,
            'num_semaine' => $cotisation->num_semaine,
            'annee'       => $cotisation->annee,
            'statut'      => 'impaye',
            'paye'        => false,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // GET /membres/{membre}/cotisations
    // Toutes les cotisations d'un membre (payées ET impayées)
    //
    // FLUX :
    // 1. genererSemainesManquantes() assure que chaque semaine
    //    de 1 à semaine courante existe en DB avec statut 'impaye'
    // 2. On lit toutes les lignes depuis la DB — plus besoin de reconstruction côté Flutter
    // ──────────────────────────────────────────────────────────
    public function parMembre(Membre $membre): JsonResponse
    {
        // Génère les semaines manquantes AVANT de lire
        $this->cotisationService->genererSemainesManquantes(now()->year, $membre->id);

        $annee = (int) now()->year;

        $cotisations = $membre->cotisations()
            ->select(['id', 'membre_id', 'num_semaine', 'annee', 'montant', 'statut', 'date_samedi'])
            ->where('annee', $annee)
            ->orderByDesc('num_semaine')
            ->get()
            ->map(fn($c) => [
                'id'          => $c->id,
                'num_semaine' => (int) $c->num_semaine,
                'annee'       => (int) $c->annee,
                'montant'     => (int) $c->montant,
                'statut'      => $c->statut,
                'paye'        => $c->statut === 'paye',
                'date_samedi' => $c->date_samedi
                    ? \Carbon\Carbon::parse($c->date_samedi)->format('d/m/Y')
                    : null,
            ]);

        $payees = $cotisations->where('paye', true);

        return response()->json([
            'membre'      => $membre->only(['id', 'nom', 'num_registre']),
            'cotisations' => $cotisations->values(), // toutes semaines (payées + impayées)
            'total_paye'  => $payees->sum('montant'),
            'nb_semaines' => $payees->count(),
        ]);
    }
}