<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\App;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //Commands\PullReport::class,
        // Commands\ArchiveOldWagers::class,
        // Commands\DeleteOldWagerBackups::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('app:pull-report-update-version')->everyMinute();

        //$schedule->command('make:pull-report')->everyFiveSeconds();
        // $schedule->command('archive:old-wagers')->everyThirtyMinutes();
        // $schedule->command('wagers:delete-old-backups')->cron('*/45 * * * *');
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
