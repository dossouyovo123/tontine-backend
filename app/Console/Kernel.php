<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\SanctionnerRetardCotisation;

class Kernel extends ConsoleKernel
{
    /**
     * Les commandes Artisan personnalisées
     */
    protected $commands = [
        \App\Console\Commands\GenererCotisations::class,
    ];

    /**
     * Planification ()
     */
protected function schedule(Schedule $schedule): void
{
    $schedule->command(SanctionnerRetardCotisation::class)
        ->weeklyOn(0, '08:00')
        ->timezone('Africa/Porto-Novo')   // ← ajouter cette ligne
        ->withoutOverlapping()
        ->runInBackground()
        ->appendOutputTo(storage_path('logs/sanctions-auto.log'));
}
    /**
     * Chargement des commandes
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}