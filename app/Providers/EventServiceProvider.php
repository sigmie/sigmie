<?php

namespace App\Providers;

use App\Events\ClusterCreated;
use App\Events\ClusterHasFailed;
use App\Events\ClusterIsRunning;
use App\Events\ClusterWasCreated;
use App\Events\NewsletterSubscribed;
use App\Listeners\SendEmailConfirmationNotification;
use App\Listeners\AwaitElasticsearchBoot;
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
        ClusterWasCreated::class => [
            AwaitElasticsearchBoot::class
        ],
        ClusterIsRunning::class => [],
        ClusterHasFailed::class => []
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
