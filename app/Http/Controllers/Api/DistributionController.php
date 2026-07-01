<?php
// ──────────────────────────────────────────────────────────────
// app/Http/Controllers/Api/DistributionController.php
//
// Logique métier :
//   • Le membre reçoit une distribution anticipée (avance).
//   • du_vers_tontine = distributions_recues − total_cotise  (si > 0)
//   • credit_vers_membre = total_cotise − distributions_recues  (si > 0)
//   • Le dû diminue AUTOMATIQUEMENT à chaque cotisation — aucune
//     saisie manuelle de remboursement n'est nécessaire ou permise.
//   • On ne peut distribuer que si le membre est actif.
//   • Pas de plafond de montant lié au "dû" : on verse ce qu'on veut
//     (le dû résultant sera calculé automatiquement).
// ──────────────────────────────────────────────────────────────
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Distribution, Membre};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DistributionController extends Controller
{
    // ── GET /distributions ─────────────────────────────────────
 public function index(Request $request): JsonResponse
{
    // Accepte per_page depuis le client, plafonné à 5000 pour éviter les abus
    $perPage = min((int) ($request->per_page ?? 20), 5000);

    $distributions = Distribution::with([
            'membre' => fn($q) => $q->withTrashed()->with('tontine'),
        ])
        ->when($request->membre_id, fn($q, $id) => $q->where('membre_id', $id))
        ->orderByDesc('date_distribution')
        ->paginate($perPage);

    $distributions->getCollection()->transform(fn($d) => $this->formatDistribution($d));

    return response()->json($distributions);
}
    // ── GET /distributions/soldes ──────────────────────────────
    /**
     * Retourne pour chaque membre actif :
     *   - du_vers_tontine  : ce que le membre doit à la tontine (avance reçue non couverte)
     *   - credit_vers_membre : ce que la tontine doit encore au membre
     *   - solde_net        : total_cotise − distributions_recues (peut être négatif)
     *
     * Trié par du_vers_tontine décroissant (membres les plus endettés en premier).
     */

public function soldes(): JsonResponse
{
    // withoutGlobalScopes() + fresh() garantit pas de cache de modèle
    $membres = Membre::actifs()
        ->with('tontine')
        ->get()
        ->map(function ($m) {
            // Forcer le recalcul des relations agrégées
            $totalCotise   = (int) $m->cotisations()->where('statut', 'paye')->sum('montant');
            $totalDistrib  = (int) $m->distributions()->sum('montant');
            $duVersTontine = max(0, $totalDistrib - $totalCotise);
            $creditMembre  = max(0, $totalCotise  - $totalDistrib);

            return [
                'id'                          => $m->id,
                'num_registre'                => $m->num_registre,
                'nom'                         => $m->nom,
                'telephone'                   => $m->telephone ?? '',
                'profession'                  => $m->profession ?? '',
                'semaines_cotisees'           => $m->semaines_cotisees,
                'total_cotise_cfa'            => $totalCotise,
                'total_distributions_recues'  => $totalDistrib,
                'du_vers_tontine'             => $duVersTontine,
                'credit_vers_membre'          => $creditMembre,
                'solde_net'                   => $totalCotise - $totalDistrib,
                'du_membre'                   => $duVersTontine,        // alias legacy
                'a_termine_tontine'           => $m->a_termine_tontine,
                'montant_cotisation'          => $m->montant_cotisation,
                'tontine' => $m->tontine ? [
                    'id'        => $m->tontine->id,
                    'nom'       => $m->tontine->nom,
                    'categorie' => $m->tontine->categorie,
                    'montant'   => $m->tontine->montant,
                ] : null,
            ];
        })
        ->sortByDesc('du_vers_tontine')
        ->values();

    return response()->json([
        'membres'               => $membres,
        'total_du_vers_tontine' => $membres->sum('du_vers_tontine'),
        'total_credit_vers_mbr' => $membres->sum('credit_vers_membre'),
        'nb_avec_du'            => $membres->where('du_vers_tontine', '>', 0)->count(),
        'nb_termines'           => $membres->where('a_termine_tontine', true)->count(),
        'total_du'              => $membres->sum('du_vers_tontine'),   // legacy
    ]);
}
    // ── POST /distributions ────────────────────────────────────
    /**
     * Enregistre une distribution (versement d'une avance au membre).
     *
     * Pas de plafond — on peut verser n'importe quel montant à n'importe quel moment.
     * Le dû résultant sera calculé automatiquement par les accessors du modèle.
     *
     * Seule contrainte : le membre doit être actif (non abandonné).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'membre_id'         => 'required|exists:membres,id',
            'montant'           => 'required|integer|min:1',
            'date_distribution' => 'required|date',
        ]);

        $membre = Membre::with('tontine')->findOrFail($data['membre_id']);
        $data['note'] = "Je soussigné {$membre->nom}, reconnait avoir ramassé en tranche. "
              . "Je m'engage à respecter les articles 13 et 14 du règlement de l'association. "
              . "Le présent document lui est délivré pour servir et valoir ce que de droit.";

        // Seule garde : le membre doit être actif
        if (! $membre->is_active || $membre->a_abandonne) {
            return response()->json([
                'message' => 'Impossible de distribuer à un membre inactif ou abandonné.',
            ], 422);
        }

        $distribution = Distribution::create($data);
        $distribution->load(['membre' => fn($q) => $q->withTrashed()->with('tontine')]);

        return response()->json($this->formatDistribution($distribution), 201);
    }

// ── PUT /distributions/{id} ────────────────────────────────
public function update(Request $request, Distribution $distribution): JsonResponse
{
    // Validation
    $validated = $request->validate([
        'montant'           => 'sometimes|integer|min:1',
        'date_distribution' => 'sometimes|date',
        // 🔥 note volontairement ignorée (auto générée)
    ]);

    // 🔥 On ne garde que les champs réellement envoyés
    $data = [];
    
    if ($request->has('montant')) {
        $data['montant'] = $validated['montant'];
    }

    if ($request->has('date_distribution')) {
        $data['date_distribution'] = $validated['date_distribution'];
    }

    // 🔥 update sécurisé
    $distribution->update($data);

    // Reload relation
    $distribution->load([
        'membre' => fn($q) => $q->withTrashed()->with('tontine')
    ]);

    return response()->json($this->formatDistribution($distribution));
}
    // ── GET /distributions/{id} ────────────────────────────────
    public function show(Distribution $distribution): JsonResponse
    {
        $distribution->load(['membre' => fn($q) => $q->withTrashed()->with('tontine')]);
        return response()->json($this->formatDistribution($distribution));
    }

    // ── DELETE /distributions/{id} ─────────────────────────────
    public function destroy(Distribution $distribution): JsonResponse
    {
        $distribution->delete();
        return response()->json(['message' => 'Distribution supprimée.']);
    }

    // ── GET /distributions/{id}/pdf ────────────────────────────
    public function exportPdf(Distribution $distribution): \Illuminate\Http\Response
    {
        $distribution->load(['membre' => fn($q) => $q->withTrashed()]);
        $pdf = app('dompdf.wrapper')->loadView('pdf.distribution', [
            'distribution'    => $distribution,
            'date_generation' => now()->format('d/m/Y à H:i'),
        ]);
        $ref = 'DIST-' . str_pad($distribution->membre?->num_registre ?? 0, 3, '0', STR_PAD_LEFT);
        return $pdf->download("recu_{$ref}_{$distribution->id}.pdf");
    }

    // ── Helper ─────────────────────────────────────────────────

    private function formatDistribution(Distribution $d): array
    {
        $m = $d->membre;

        return [
            'id'                => $d->id,
            'reference'         => 'DIST-#' . str_pad($m?->num_registre ?? 0, 3, '0', STR_PAD_LEFT),
            'montant'           => $d->montant,
            'note'              => $d->note,
            'date_distribution' => $d->date_distribution
                ? Carbon::parse($d->date_distribution)->format('d/m/Y')
                : '',
            'membre' => $m ? [
                'id'                          => $m->id,
                'num_registre'                => $m->num_registre,
                'nom'                         => $m->nom,
                'telephone'                   => $m->telephone ?? '',
                'profession'                  => $m->profession ?? '',
                'semaines_cotisees'           => $m->semaines_cotisees,
                'total_cotise_cfa'            => $m->total_cotise_cfa,
                'total_distributions_recues'  => $m->total_distributions_recues,
                // Nouvelle sémantique
                'du_vers_tontine'             => $m->du_vers_tontine,
                'credit_vers_membre'          => $m->credit_vers_membre,
                'solde_net'                   => $m->solde_net,
                // Alias legacy
                'du_membre'                   => $m->du_vers_tontine,
                'a_termine_tontine'           => $m->a_termine_tontine,
                'montant_cotisation'          => $m->montant_cotisation,
                'tontine' => $m->tontine ? [
                    'id'        => $m->tontine->id,
                    'nom'       => $m->tontine->nom,
                    'categorie' => $m->tontine->categorie,
                    'montant'   => $m->tontine->montant,
                ] : null,
            ] : ['id' => null, 'nom' => '—', 'num_registre' => 0],
        ];
    }
}