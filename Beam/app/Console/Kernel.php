<?php

namespace App\Console;

use App\Console\Commands\AggregateArticlesViews;
use App\Console\Commands\CreateAuthorsSegments;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Schema;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        if (!Schema::hasTable("migrations")) {
            return;
        }

         $schedule->command('aggregate:pageview-load')
             ->hourlyAt(5)
             ->withoutOverlapping();

        $schedule->command('aggregate:pageview-timespent')
            ->hourlyAt(5)
            ->withoutOverlapping();

        $schedule->command(AggregateArticlesViews::COMMAND)
            ->dailyAt('00:10')
            ->withoutOverlapping()
            ->after(function() {
                $this->artisan->run(CreateAuthorsSegments::COMMAND);
            });
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        require base_path('routes/console.php');
        $this->load(__DIR__.'/Commands');
    }
}
