<?php

// ──────────────────────────────────────────────────────────────
// app/Http/Controllers/Api/MembreController.php
// ──────────────────────────────────────────────────────────────
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membre;
use App\Services\{TontineCalcService, CotisationService};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MembreController extends Controller
{
    public function __construct(
        private TontineCalcService $calc,
        private CotisationService  $cotisationService
    ) {}

    // ──────────────────────────────────────────────────────────
    // GET /membres
    // ──────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = Membre::withCount(['cotisations', 'sanctions', 'distributions']);

        if ($request->filter === 'actifs')     $query->actifs();
        if ($request->filter === 'abandonnes') $query->abandonnes();

        if ($request->search) {
            $q = $request->search;
            $query->where(fn($q2) => $q2
                ->where('nom', 'like', "%$q%")
                ->orWhere('num_registre', $q)
            );
        }

        $paginated = $query->orderBy('num_registre')->paginate(20);
        $paginated->getCollection()->transform(fn($m) => $this->formatMembre($m));

        return response()->json($paginated);
    }

    // ──────────────────────────────────────────────────────────
    // POST /membres
    //
    // CORRECTION :
    // 1. On force is_active=true et a_abandonne=false dans $data
    //    pour que la DB ait bien ces valeurs dès le create()
    //    (ne pas dépendre du default de la migration)
    // 2. On appelle genererPourMembre() APRÈS save pour que
    //    le membre existe bien en DB
    // 3. genererPourMembre() ne filtre plus sur is_active →
    //    le nouveau membre est toujours traité
    // ──────────────────────────────────────────────────────────
   public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom'        => 'required|string|max:255',
            'telephone'  => 'required|string',
            'adresse'    => 'nullable|string',
            'profession' => 'required|string',
            'tontine_id' => 'required|exists:tontines,id', // ← NOUVEAU
        ]);

        $data['num_registre']     = (Membre::withTrashed()->max('num_registre') ?? 0) + 1;
        $data['date_inscription'] = now()->toDateString();
        $data['is_active']        = true;
        $data['a_abandonne']      = false;

        $membre = Membre::create($data);
        $membre->load('tontine'); // ← précharger pour formatMembre

        $this->cotisationService->genererPourMembre($membre->id);

        return response()->json($this->formatMembre($membre->fresh()->load('tontine')), 201);
    }

    // ──────────────────────────────────────────────────────────
    // GET /membres/{membre}
    // ──────────────────────────────────────────────────────────
 public function show(Membre $membre): JsonResponse
    {
        $this->cotisationService->genererPourMembre($membre->id);

        $membre->load([
            'tontine',   // ← NOUVEAU
            'cotisations'   => fn($q) => $q->where('annee', now()->year)->orderBy('num_semaine', 'desc'),
            'sanctions'     => fn($q) => $q->orderBy('date_sanction', 'desc'),
            'distributions' => fn($q) => $q->orderBy('date_distribution', 'desc'),
            'complements',
        ]);

        $cotisations = $membre->cotisations->map(fn($c) => [
            'id'          => $c->id,
            'num_semaine' => (int) $c->num_semaine,
            'date_samedi' => $c->date_samedi
                ? Carbon::parse($c->date_samedi)->format('d/m/Y')
                : null,
            'montant'     => (int) $c->montant,
            'annee'       => (int) $c->annee,
            'statut'      => $c->statut,
            'paye'        => $c->statut === 'paye',
        ]);

        $sanctions = $membre->sanctions->map(fn($s) => [
            'id'            => $s->id,
            'motif'         => $s->motif,
            'montant'       => (int) $s->montant,
            'statut'        => $s->statut,
            'notes'         => $s->notes,
            'date_sanction' => Carbon::parse($s->date_sanction)->format('d/m/Y'),
        ]);

        $distributions = $membre->distributions->map(fn($d) => [
            'id'                => $d->id,
            'montant'           => (int) $d->montant,
            'note'              => $d->note,
            'date_distribution' => Carbon::parse($d->date_distribution)->format('d/m/Y'),
        ]);

        return response()->json([
            'membre' => array_merge($this->formatMembre($membre), [
                'cotisations'   => $cotisations,
                'sanctions'     => $sanctions,
                'distributions' => $distributions,
            ]),
            'semaines_payees'  => $membre->semaines_cotisees,
            'total_cotise'     => $membre->total_cotise_cfa,
            'eligible_moto'    => $membre->est_eligible_moto,
            'perd_argent'      => $membre->perd_argent_si_abandon,
            'semaine_courante' => $this->calc->semaineCourante(),
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // PUT /membres/{membre}
    // ──────────────────────────────────────────────────────────
    public function update(Request $request, Membre $membre): JsonResponse
    {
        $data = $request->validate([
            'nom'        => 'sometimes|string|max:255',
            'telephone'  => 'sometimes|string',
            'adresse'    => 'nullable|string',
            'profession' => 'sometimes|string',
            'is_active'  => 'sometimes|boolean',
        ]);

        $membre->update($data);
        return response()->json($this->formatMembre($membre->fresh()));
    }

    // ──────────────────────────────────────────────────────────
    // DELETE /membres/{membre}
    // SoftDelete — le nom reste dans l'historique
    // ──────────────────────────────────────────────────────────
    public function destroy(Membre $membre): JsonResponse
    {
        $membre->delete();
        return response()->json(['message' => 'Membre supprimé.']);
    }

    // ──────────────────────────────────────────────────────────
    // POST /membres/{membre}/abandonner
    // ──────────────────────────────────────────────────────────
    public function abandonner(Membre $membre): JsonResponse
    {
        if ($membre->a_abandonne) {
            return response()->json(['message' => 'Déjà marqué abandonné.'], 422);
        }

        $membre->update(['a_abandonne' => true, 'is_active' => false]);
        $membre->refresh();

        return response()->json([
            'membre'              => $this->formatMembre($membre),
            'perd_argent'         => $membre->perd_argent_si_abandon,
            'total_cotise'        => $membre->total_cotise_cfa,
            'seuil_abandonnement' => TontineCalcService::SEUIL_ABANDON,
            'message'             => $membre->perd_argent_si_abandon
                ? 'Membre marqué abandonné. Perd son argent (< 50 000 CFA cotisés).'
                : 'Membre marqué abandonné. Peut récupérer son dû.',
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // POST /membres/{membre}/reactiver
    // ──────────────────────────────────────────────────────────
    public function reactiver(Membre $membre): JsonResponse
    {
        $membre->update(['a_abandonne' => false, 'is_active' => true]);

        // Génère les semaines manquantes maintenant qu'il est réactivé
        // (sans filtre is_active — le membre vient d'être mis à jour)
        $this->cotisationService->genererPourMembre($membre->id);

        return response()->json($this->formatMembre($membre->fresh()));
    }

    // ──────────────────────────────────────────────────────────
    // GET /membres/{membre}/historique
    // ──────────────────────────────────────────────────────────
    public function historique(Membre $membre): JsonResponse
    {
        $this->cotisationService->genererPourMembre($membre->id);

        $semaineCourante = $this->calc->semaineCourante();
        $annee           = (int) now()->year;

        $cotisationsDB = $membre->cotisations()
            ->where('annee', $annee)
            ->whereBetween('num_semaine', [1, $semaineCourante])
            ->get()
            ->keyBy('num_semaine');

        $historique = collect(range(1, $semaineCourante))
            ->map(function ($sem) use ($cotisationsDB, $annee) {
                $cot  = $cotisationsDB->get($sem);
                $paye = $cot?->statut === 'paye';

                return [
                    'semaine'       => $sem,
                    'date_samedi'   => $this->calc->dateSamedi($sem, $annee)->format('d/m/Y'),
                    'paye'          => $paye,
                    'montant'       => $paye ? (int) ($cot?->montant ?? TontineCalcService::MONTANT_HEBDO) : 0,
                    'statut'        => $paye ? 'paye' : 'impaye',
                    'cotisation_id' => $cot?->id,
                ];
            })
            ->sortByDesc('semaine')
            ->values();

        $nbPayees = $historique->where('paye', true)->count();

        return response()->json([
            'membre'           => $membre->only(['id', 'nom', 'telephone', 'num_registre']),
            'semaine_debut'    => $this->calc->premierSamediJanvier()->format('d/m/Y'),
            'semaine_courante' => $semaineCourante,
            'semaines_payees'  => $nbPayees,
            'total_paye'       => $nbPayees * TontineCalcService::MONTANT_HEBDO,
            'historique'       => $historique,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // GET /membres/{membre}/pdf
    // ──────────────────────────────────────────────────────────
    public function exportPdf(Membre $membre): \Illuminate\Http\Response
    {
        $membre->load(['cotisations', 'sanctions', 'distributions']);

        $pdf = app('dompdf.wrapper')->loadView('pdf.membre', [
            'membre'          => $membre,
            'semaines_payees' => $membre->semaines_cotisees,
            'total_cotise'    => $membre->total_cotise_cfa,
            'date_generation' => now()->format('d/m/Y à H:i'),
        ]);

       $filename = "membre-{$membre->num_registre}-{$membre->nom}-" . now()->format('dmY') . ".pdf";
       return $pdf->download($filename);
    }

    // ──────────────────────────────────────────────────────────
    // Helper : format uniforme pour Flutter (dates en dd/MM/yyyy)
    // ──────────────────────────────────────────────────────────
    private function formatMembre(Membre $m): array
    {
        return [
            'id'                     => $m->id,
            'num_registre'           => $m->num_registre,
            'nom'                    => $m->nom,
            'telephone'              => $m->telephone ?? '',
            'adresse'                => $m->adresse   ?? '',
            'profession'             => $m->profession ?? '',
            'is_active'              => (bool) $m->is_active,
            'a_abandonne'            => (bool) $m->a_abandonne,
            'date_inscription'       => $m->date_inscription
                ? Carbon::parse($m->date_inscription)->format('d/m/Y')
                : '',
            'semaines_cotisees'      => $m->semaines_cotisees,
            'total_cotise_cfa'       => $m->total_cotise_cfa,
            'est_eligible_moto'      => $m->est_eligible_moto,
            'perd_argent_si_abandon' => $m->perd_argent_si_abandon,

            // ── NOUVEAU : infos tontine ────────────────────
            'montant_cotisation'     => $m->montant_cotisation,
            'tontine_id'             => $m->tontine_id,
            'tontine' => $m->tontine ? [
                'id'        => $m->tontine->id,
                'nom'       => $m->tontine->nom,
                'categorie' => $m->tontine->categorie,
                'montant'   => $m->tontine->montant,
            ] : null,
        ];
    }
}