<?php

namespace App\Providers;

use App\Events\ClusterCreated;
use App\Events\NewsletterSubscribed;
use App\Listeners\SendEmailConfirmationNotification;
use App\Listeners\StoreClusterData;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        NewsletterSubscribed::class => [
            SendEmailConfirmationNotification::class
        ],
        ClusterCreated::class => [
            StoreClusterData::class
        ]
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }
}
