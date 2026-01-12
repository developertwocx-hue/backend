<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Generate compliance alerts daily at 8:00 AM
        $schedule->job(new \App\Jobs\GenerateComplianceAlerts())
            ->dailyAt('08:00')
            ->name('generate-compliance-alerts')
            ->withoutOverlapping();

        // Send compliance alerts every hour
        $schedule->job(new \App\Jobs\SendComplianceAlerts())
            ->hourly()
            ->name('send-compliance-alerts')
            ->withoutOverlapping();

        // Recalculate vehicle compliance scores daily at 9:00 AM
        $schedule->command('vehicles:recalculate-compliance')
            ->dailyAt('09:00')
            ->name('recalculate-compliance-scores')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
