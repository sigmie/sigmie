<?php

namespace App\Providers;

use App\Contracts\MailingList;
use App\Services\Mailchimp;
use App\Services\MailgunList;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

class MailingListServiceProvider extends ServiceProvider
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
    public function boot()
    {
        $this->app->bind(
            MailingList::class,
            fn ($app) => new Mailchimp($app->make(Client::class), ['key' => config('mailchimp.key'), 'data_center' => config('mailchimp.data_center')])
        );
    }
}
