<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use App\Console\Commands\SanctionnerRetardCotisation;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command(SanctionnerRetardCotisation::class)
            ->weeklyOn(0, '08:00')
            ->timezone('Africa/Porto-Novo')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/sanctions-auto.log'));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(fn () => response()->json([
            'message' => 'Non authentifié. Token invalide ou expiré.'
        ], 401));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();