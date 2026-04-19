<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Les commandes Artisan personnalisées
     */
    protected $commands = [
        \App\Console\Commands\GenererCotisations::class,
    ];

    /**
     * Planification (optionnel)
     */
    protected function schedule(Schedule $schedule): void
    {
        //
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