<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MembreController;
use App\Http\Controllers\Api\CotisationController;
use App\Http\Controllers\Api\SanctionController;
use App\Http\Controllers\Api\DistributionController;
use App\Http\Controllers\Api\ComplementController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\TontineController; // ← NOUVEAU
use App\Http\Controllers\Api\{BeneficeController, DepenseController};

/*
|--------------------------------------------------------------------------
| API Routes — MaTontine  /api/v1
|
| ⚠️  RÈGLE CRITIQUE ANTI-404 :
|     Les routes avec un SEGMENT FIXE doivent toujours être déclarées
|     AVANT les routes avec un paramètre dynamique {id}.
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── AUTH PUBLIQUES ────────────────────────────────────────────────────
    Route::post('login',           [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('verify-otp',      [AuthController::class, 'verifyOtp']);
    Route::post('reset-password',  [AuthController::class, 'resetPassword']);

    // ── ROUTES PROTÉGÉES ──────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get ('me',     [AuthController::class, 'me']);
        Route::put ('me',     [AuthController::class, 'updateProfile']);

        // Dashboard / Stats
        Route::get('dashboard',         [DashboardController::class, 'index']);
        Route::get('stats/cotisations', [DashboardController::class, 'statsCotisations']);
        Route::get('stats/sanctions',   [DashboardController::class, 'statsSanctions']);
        Route::get('stats/membres',     [DashboardController::class, 'statsMembres']);

        // ── TONTINES ← NOUVEAU ────────────────────────────────────────────
        // Segment fixe {tontine}/show avant apiResource
        Route::get('tontines/{tontine}', [TontineController::class, 'show']);
        Route::get('tontines',           [TontineController::class, 'index']);

        // ── MEMBRES ───────────────────────────────────────────────────────
        Route::post('membres/{membre}/abandonner',  [MembreController::class,     'abandonner']);
        Route::post('membres/{membre}/reactiver',   [MembreController::class,     'reactiver']);
        Route::get ('membres/{membre}/historique',  [MembreController::class,     'historique']);
        Route::get ('membres/{membre}/pdf',         [MembreController::class,     'exportPdf']);
        Route::get ('membres/{membre}/cotisations', [CotisationController::class, 'parMembre']);
        Route::apiResource('membres', MembreController::class);

        // ── COTISATIONS ───────────────────────────────────────────────────
        Route::prefix('cotisations')->group(function () {
            Route::post('encaisser',            [CotisationController::class, 'encaisser']);
            Route::get ('semaine/{semaine?}',   [CotisationController::class, 'semaine']);
            Route::get ('/',                    [CotisationController::class, 'index']);
            Route::put ('{cotisation}/annuler', [CotisationController::class, 'annuler']);
        });

        // ── SANCTIONS ─────────────────────────────────────────────────────
        Route::post('sanctions/{sanction}/marquer-paye', [SanctionController::class, 'marquerPaye']);
        Route::apiResource('sanctions', SanctionController::class)->except(['update']);

        // ── DISTRIBUTIONS ─────────────────────────────────────────────────
        Route::get   ('distributions/{distribution}/pdf', [DistributionController::class, 'exportPdf']);
        Route::delete('distributions/{distribution}',     [DistributionController::class, 'destroy']);
        Route::apiResource('distributions', DistributionController::class)->except(['update', 'destroy']);

        // ── COMPLÉMENTS MOTOS ─────────────────────────────────────────────
        Route::post('complements/{complement}/approuver', [ComplementController::class, 'approuver']);
        Route::post('complements/{complement}/refuser',   [ComplementController::class, 'refuser']);
        Route::post('complements/{complement}/attribuer', [ComplementController::class, 'attribuerMoto']);
        Route::get ('complements/{complement}/pdf',       [ComplementController::class, 'exportPdf']);
        Route::apiResource('complements', ComplementController::class)->except(['update']);

        // ── BÉNÉFICES ─────────────────────────────────────────────────
// Segment fixe 'calculer' et 'stats' AVANT {benefice}
Route::post('benefices/calculer',    [BeneficeController::class, 'calculer']);
Route::get ('benefices/stats',       [BeneficeController::class, 'stats']);
Route::get ('benefices',             [BeneficeController::class, 'index']);
Route::delete('benefices/{benefice}',[BeneficeController::class, 'destroy']);

// ── DÉPENSES ──────────────────────────────────────────────────
// POST pour update (multipart/form-data + image)
Route::post('depenses/{depense}/update', [DepenseController::class, 'update']);
Route::apiResource('depenses', DepenseController::class)->except(['update']);
    });
});