<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\MailingList;
use App\Services\MailchimpList;
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
            fn ($app) => new MailchimpList(
                $app->make(Client::class),
                [
                    'key' => config('services.mailchimp.key'),
                    'data_center' => config('services.mailchimp.data_center')
                ]
            )
        );
    }
}
