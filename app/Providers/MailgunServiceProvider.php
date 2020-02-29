<?php

namespace App\Providers;

use App\Services\Mailgun;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

class MailgunServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(Client $client)
    {
        $this->app->singleton(
            Mailgun::class,
            function () use ($client) {
                return new Mailgun(
                    $client,
                    [
                        'domain' => config('services.mailgun.domain'),
                        'secret' => config('services.mailgun.secret'),
                        'endpoint' => config('services.mailgun.endpoint')
                    ],
                );
            }
        );
    }
}
