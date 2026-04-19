<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sanction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SanctionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sanctions = Sanction::with([
                // ⚠️ withTrashed() : si le membre est supprimé (soft delete),
                // son nom reste visible dans l'historique des sanctions.
                'membre' => fn($q) => $q->withTrashed(),
            ])
            ->when($request->statut,    fn($q, $s)  => $q->where('statut', $s))
            ->when($request->membre_id, fn($q, $id) => $q->where('membre_id', $id))
            ->orderByDesc('date_sanction')
            ->paginate(20);

        // Formate les dates pour Flutter
        $sanctions->getCollection()->transform(fn($s) => $this->formatSanction($s));

        return response()->json($sanctions);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'membre_id'     => 'required|exists:membres,id',
            'motif'         => 'required|in:retard_reunion,absence_non_justifiee,retard_cotisation,non_respect_statuts,autre',
            'date_sanction' => 'sometimes|date',
            'notes'         => 'nullable|string',
        ]);

        $data['montant']       = Sanction::montantPourMotif($data['motif']);
        $data['date_sanction'] = $data['date_sanction'] ?? now()->toDateString();

        $sanction = Sanction::create($data);
        $sanction->load(['membre' => fn($q) => $q->withTrashed()]);

        return response()->json($this->formatSanction($sanction), 201);
    }

    public function show(Sanction $sanction): JsonResponse
    {
        $sanction->load(['membre' => fn($q) => $q->withTrashed()]);
        return response()->json($this->formatSanction($sanction));
    }

    public function destroy(Sanction $sanction): JsonResponse
    {
        $sanction->delete();
        return response()->json(['message' => 'Sanction supprimée.']);
    }

    public function marquerPaye(Sanction $sanction): JsonResponse
    {
        if ($sanction->statut === 'paye') {
            return response()->json(['message' => 'Déjà marquée payée.'], 422);
        }

        $sanction->update([
            'statut'        => 'paye',
            'date_paiement' => now()->toDateString(),
        ]);

        $sanction->load(['membre' => fn($q) => $q->withTrashed()]);
        return response()->json($this->formatSanction($sanction));
    }

    // ── Helper ─────────────────────────────────────────────────────

    private function formatSanction(Sanction $s): array
    {
        return [
            'id'            => $s->id,
            'motif'         => $s->motif,
            'montant'       => $s->montant,
            'statut'        => $s->statut,
            'notes'         => $s->notes,
            // Date dd/MM/yyyy pour Flutter
            'date_sanction' => $s->date_sanction
                ? Carbon::parse($s->date_sanction)->format('d/m/Y')
                : '',
            // Membre avec son nom même si supprimé
            'membre' => $s->membre ? [
                'id'  => $s->membre->id,
                'nom' => $s->membre->nom,
            ] : ['id' => null, 'nom' => '—'],
        ];
    }
}