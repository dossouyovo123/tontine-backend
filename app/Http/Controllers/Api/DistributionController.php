<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Distribution;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DistributionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $distributions = Distribution::with([
                // ⚠️ withTrashed() : nom du membre conservé même après sa suppression
                'membre' => fn($q) => $q->withTrashed(),
            ])
            ->when($request->membre_id, fn($q, $id) => $q->where('membre_id', $id))
            ->orderByDesc('date_distribution')
            ->paginate(20);

        $distributions->getCollection()->transform(fn($d) => $this->formatDistribution($d));

        return response()->json($distributions);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'membre_id'         => 'required|exists:membres,id',
            'montant'           => 'required|integer|min:1',
            'date_distribution' => 'required|date',
            'note'              => 'nullable|string',
        ]);

        $distribution = Distribution::create($data);
        $distribution->load(['membre' => fn($q) => $q->withTrashed()]);

        return response()->json($this->formatDistribution($distribution), 201);
    }

    public function show(Distribution $distribution): JsonResponse
    {
        $distribution->load(['membre' => fn($q) => $q->withTrashed()]);
        return response()->json($this->formatDistribution($distribution));
    }

    public function destroy(Distribution $distribution): JsonResponse
    {
        $distribution->delete();
        return response()->json(['message' => 'Distribution supprimée.']);
    }

    public function exportPdf(Distribution $distribution): \Illuminate\Http\Response
    {
        $distribution->load(['membre' => fn($q) => $q->withTrashed()]);
        $pdf = app('dompdf.wrapper')->loadView('pdf.distribution', [
            'distribution'    => $distribution,
            'date_generation' => now()->format('d/m/Y à H:i'),
        ]);
        return $pdf->download("recu_distribution_{$distribution->id}.pdf");
    }

    // ── Helper ─────────────────────────────────────────────────────

    private function formatDistribution(Distribution $d): array
    {
        return [
            'id'                => $d->id,
            'montant'           => $d->montant,
            'note'              => $d->note,
            // Date dd/MM/yyyy pour Flutter
            'date_distribution' => $d->date_distribution
                ? Carbon::parse($d->date_distribution)->format('d/m/Y')
                : '',
            // Membre avec son nom même si supprimé
            'membre' => $d->membre ? [
                'id'  => $d->membre->id,
                'nom' => $d->membre->nom,
            ] : ['id' => null, 'nom' => '—'],
        ];
    }
}