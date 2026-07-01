<?php
// app/Http/Controllers/Api/DepenseController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Depense;
use Carbon\Carbon;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\Storage;

class DepenseController extends Controller
{
    // ──────────────────────────────────────────────────────────
    // GET /depenses
    // ──────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $depenses = Depense::orderByDesc('date_depense')
            ->orderByDesc('id')
            ->get()
            ->map(fn($d) => $this->formatDepense($d));

        return response()->json([
            'depenses'     => $depenses,
            'total'        => $depenses->sum('montant'),
            'nb_depenses'  => $depenses->count(),
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // POST /depenses
    // Accepte multipart/form-data pour l'upload image
    // ──────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'motif'        => 'required|string|max:255',
            'montant'      => 'required|integer|min:1',
            'date_depense' => 'required|date',
            'notes'        => 'nullable|string',
            'image'        => 'nullable|image|max:5120', // max 5 MB
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')
                ->store('depenses', 'public');
        }

        $depense = Depense::create([
            'motif'        => $data['motif'],
            'montant'      => $data['montant'],
            'date_depense' => $data['date_depense'],
            'notes'        => $data['notes'] ?? null,
            'image_path'   => $imagePath,
        ]);

        return response()->json($this->formatDepense($depense), 201);
    }

    // ──────────────────────────────────────────────────────────
    // GET /depenses/{depense}
    // ──────────────────────────────────────────────────────────
    public function show(Depense $depense): JsonResponse
    {
        return response()->json($this->formatDepense($depense));
    }

    // ──────────────────────────────────────────────────────────
    // POST /depenses/{depense}/update
    // (POST au lieu de PUT pour supporter multipart/form-data + image)
    // ──────────────────────────────────────────────────────────
    public function update(Request $request, Depense $depense): JsonResponse
    {
        $data = $request->validate([
            'motif'        => 'sometimes|string|max:255',
            'montant'      => 'sometimes|integer|min:1',
            'date_depense' => 'sometimes|date',
            'notes'        => 'nullable|string',
            'image'        => 'nullable|image|max:5120',
            'supprimer_image' => 'nullable|boolean',
        ]);

        // Nouvelle image → supprime l'ancienne
        if ($request->hasFile('image')) {
            if ($depense->image_path) {
                Storage::disk('public')->delete($depense->image_path);
            }
            $data['image_path'] = $request->file('image')
                ->store('depenses', 'public');
        }

        // Suppression explicite de l'image sans remplacement
        if ($request->boolean('supprimer_image') && $depense->image_path) {
            Storage::disk('public')->delete($depense->image_path);
            $data['image_path'] = null;
        }

        $depense->update(array_filter($data, fn($k) => in_array($k, [
            'motif', 'montant', 'date_depense', 'notes', 'image_path'
        ]), ARRAY_FILTER_USE_KEY));

        return response()->json($this->formatDepense($depense->fresh()));
    }

    // ──────────────────────────────────────────────────────────
    // DELETE /depenses/{depense}
    // ──────────────────────────────────────────────────────────
    public function destroy(Depense $depense): JsonResponse
    {
        if ($depense->image_path) {
            Storage::disk('public')->delete($depense->image_path);
        }
        $depense->delete();
        return response()->json(['message' => 'Dépense supprimée.']);
    }

    // ── Helper ────────────────────────────────────────────────
    private function formatDepense(Depense $d): array
    {
        return [
            'id'           => $d->id,
            'motif'        => $d->motif,
            'montant'      => $d->montant,
            'date_depense' => $d->date_depense
                ? Carbon::parse($d->date_depense)->format('d/m/Y')
                : '',
            'notes'        => $d->notes,
'image_url' => $d->image_path
    ? url('api/v1/storage/' . $d->image_path)
    : null,            'has_image'    => !is_null($d->image_path),
        ];
    }
}