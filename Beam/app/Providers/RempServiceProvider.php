<?php

namespace App\Providers;

use App\Contracts\JournalContract;
use App\Contracts\Mailer\Mailer;
use App\Contracts\Mailer\MailerContract;
use App\Contracts\Remp\Journal;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

class RempServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(JournalContract::class, function ($app) {
            $client = new Client([
                'base_uri' => $app['config']->get('services.remp.beam.segments_addr'),
            ]);
            return new Journal($client);
        });

        $this->app->bind(MailerContract::class, function ($app) {
            $client = new Client([
                'base_uri' => $app['config']->get('services.remp.mailer.web_addr'),
            ]);
            return new Mailer($client);
        });
    }

    public function provides()
    {
        return [JournalContract::class, MailerContract::class];
    }
}
