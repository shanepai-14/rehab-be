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
        // Send appointment reminders every day at 8 AM
        $schedule->command('appointments:send-reminders')
                 ->everyMinute()
                 ->withoutOverlapping();

        // Clean up old notifications (optional)
        $schedule->command('notifications:cleanup')
                 ->weekly()
                 ->sundays()
                 ->at('01:00');
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