<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('userPlans:expire')->dailyAt('00:05');
        $schedule->command('appointments:markCompletedMissed')->dailyAt('00:05');
        $schedule->command('touristAppointments:markCompletedMissed')->dailyAt('00:05');
        $schedule->command('subscription:expiry-reminder')->daily();
        $schedule->command('queue:work --stop-when-empty')->everyMinute();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
