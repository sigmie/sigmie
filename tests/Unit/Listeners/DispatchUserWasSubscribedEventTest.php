<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\Subscription\UserWasSubscribed;
use App\Listeners\Subscription\DispatchUserWasSubscribedEvent;
use Illuminate\Support\Facades\Event;
use Laravel\Paddle\Events\WebhookHandled;
use Tests\TestCase;

class DispatchUserWasSubscribedEventTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @test
     */
    public function event_fired_on_subscription_created(): void
    {
        Event::fake();

        $eventMock = $this->createMock(WebhookHandled::class);
        $eventMock->payload = [
            'alert_name' => 'subscription_created',
            'passthrough' => '{"billable_id":"9"}'
        ];

        $listener = new DispatchUserWasSubscribedEvent();
        $listener->handle($eventMock);

        Event::assertDispatched(UserWasSubscribed::class, function ($e) {
            return $e->userId === 9;
        });
    }

    /**
     * @test
     */
    public function event_not_fired_on_other_events(): void
    {
        Event::fake();

        $eventMock = $this->createMock(WebhookHandled::class);
        $eventMock->payload = [
            'alert_name' => 'some_event',
            'passthrough' => '{"billable_id":"9"}'
        ];

        $listener = new DispatchUserWasSubscribedEvent();
        $listener->handle($eventMock);

        Event::assertNotDispatched(UserWasSubscribed::class);
    }
}
