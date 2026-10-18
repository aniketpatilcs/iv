<?php

namespace App\Console;

use App\Modules\Submissions\Models\Upload;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Prune audit entries older than ACTIVITY_LOG_RETENTION_DAYS.
        $schedule->command('activitylog:clean --force')->dailyAt('02:30')->onOneServer();

        // Delete files uploaded for forms that were never submitted.
        $schedule->command('model:prune', ['--model' => [Upload::class]])->hourly()->onOneServer();

        // Keep the failed_jobs table from growing without bound.
        $schedule->command('queue:prune-failed --hours=720')->daily()->onOneServer();
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
