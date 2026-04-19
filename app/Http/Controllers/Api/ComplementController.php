<?php 
// ──────────────────────────────────────────────────────────────
// ComplementController.php
// ──────────────────────────────────────────────────────────────
namespace App\Http\Controllers\Api;
 
use App\Http\Controllers\Controller;
use App\Models\{Complement, Membre};
use App\Services\TontineCalcService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
 
class ComplementController extends Controller
{
    public function __construct(private TontineCalcService $calc) {}
 
    public function index(Request $request): JsonResponse
    {
        $complements = Complement::with('membre')
            ->when($request->statut, fn($q, $s) => $q->where('statut', $s))
            ->orderByDesc('date_demande')
            ->paginate(20);
 
        return response()->json($complements);
    }
 
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'membre_id'           => 'required|exists:membres,id',
            'montant_moto_estime' => 'required|integer|min:1',
            'description_moto'    => 'nullable|string',
        ]);
 
        $membre = Membre::findOrFail($data['membre_id']);
 
        if (! $this->calc->estEligibleMoto($membre->semainesCotisees)) {
            return response()->json([
                'message'         => 'Membre non éligible. Minimum 12 semaines requis.',
                'semaines_payees' => $membre->semainesCotisees,
            ], 422);
        }
 
        $complement = Complement::create([
            'reference'                  => Complement::prochainerReference(),
            'membre_id'                  => $data['membre_id'],
            'semaines_cotisees_snapshot' => $membre->semainesCotisees,
            'montant_cotise_total'       => $membre->totalCotiseCfa,
            'montant_moto_estime'        => $data['montant_moto_estime'],
            'description_moto'           => $data['description_moto'] ?? null,
            'date_demande'               => now()->toDateString(),
        ]);
 
        return response()->json($complement->load('membre'), 201);
    }
 
    public function show(Complement $complement): JsonResponse
    {
        return response()->json($complement->load('membre'));
    }
 
    public function destroy(Complement $complement): JsonResponse
    {
        if ($complement->statut !== 'en_attente') {
            return response()->json(['message' => 'Seules les demandes en attente peuvent être supprimées.'], 422);
        }
        $complement->delete();
        return response()->json(['message' => 'Demande supprimée.']);
    }
 
    public function approuver(Request $request, Complement $complement): JsonResponse
    {
        $data = $request->validate(['notes_admin' => 'nullable|string']);
        $complement->update(['statut' => 'approuve', 'notes_admin' => $data['notes_admin'] ?? null]);
        return response()->json($complement->load('membre'));
    }
 
    public function refuser(Request $request, Complement $complement): JsonResponse
    {
        $data = $request->validate(['notes_admin' => 'nullable|string']);
        $complement->update(['statut' => 'refuse', 'notes_admin' => $data['notes_admin'] ?? null]);
        return response()->json($complement->load('membre'));
    }
 
    public function attribuerMoto(Complement $complement): JsonResponse
    {
        $complement->update([
            'statut'                => 'moto_attribuee',
            'date_attribution_moto' => now()->toDateString(),
        ]);
        return response()->json($complement->load('membre'));
    }
 
    public function exportPdf(Complement $complement): \Illuminate\Http\Response
    {
        $complement->load('membre');
        $pdf = app('dompdf.wrapper')->loadView('pdf.complement', [
            'complement'      => $complement,
            'date_generation' => now()->format('d/m/Y à H:i'),
        ]);
        $filename = "complement_{$complement->reference}_{$complement->membre->nom}.pdf";
        return $pdf->download($filename);
    }
}
?>