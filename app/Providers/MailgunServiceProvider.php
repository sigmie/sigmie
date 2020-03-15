<?php

namespace App\Providers;

use App\Services\MailgunList;
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
            MailgunList::class,
            function () use ($client) {
                return new MailgunList(
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
