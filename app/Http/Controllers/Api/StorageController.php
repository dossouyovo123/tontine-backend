<?php
// app/Http/Controllers/Api/StorageController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageController extends Controller
{
    /**
     * Sert un fichier depuis storage/app/public/
     * Contournement quand storage:link est indisponible (exec() désactivé).
     *
     * GET /api/v1/storage/depenses/monimage.jpg
     * →  storage/app/public/depenses/monimage.jpg
     */
    public function serve(string $path): StreamedResponse|\Illuminate\Http\Response
    {
        // Sécurité : empêche la traversée de répertoire
        $path = ltrim($path, '/');
        if (str_contains($path, '..')) {
            abort(403);
        }

        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $mime = Storage::disk('public')->mimeType($path);
        $size = Storage::disk('public')->size($path);

        return response()->stream(
            fn() => fpassthru(Storage::disk('public')->readStream($path)),
            200,
            [
                'Content-Type'   => $mime,
                'Content-Length' => $size,
                'Cache-Control'  => 'public, max-age=86400',
            ]
        );
    }
}