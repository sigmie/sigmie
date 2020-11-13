<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Cluster\ClusterHasFailed;
use App\Events\Cluster\ClusterWasBooted;
use App\Events\Cluster\ClusterWasCreated;
use App\Events\Cluster\ClusterWasDestroyed;
use App\Events\Newsletter\NewsletterSubscriptionWasCreated;
use App\Listeners\Cluster\PollClusterState;
use App\Listeners\Cluster\UpdateClusterStateToError;
use App\Listeners\Notifications\SendClusterDestroyedNotification;
use App\Listeners\Notifications\SendClusterRunningNotification;
use App\Listeners\Notifications\SendEmailConfirmationNotification;
use App\Listeners\Subscription\DispatchUserWasSubscribedEvent;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Laravel\Paddle\Events\WebhookHandled;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     */
    protected $listen = [
        ClusterWasCreated::class => [
            PollClusterState::class
        ],
        ClusterWasDestroyed::class => [
            SendClusterDestroyedNotification::class,
        ],
        ClusterWasBooted::class => [
            SendClusterRunningNotification::class
        ],
        NewsletterSubscriptionWasCreated::class => [
            SendEmailConfirmationNotification::class
        ],
        WebhookHandled::class => [
            DispatchUserWasSubscribedEvent::class
        ],
        ClusterHasFailed::class => [
            UpdateClusterStateToError::class
        ]
    ];

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }
}
