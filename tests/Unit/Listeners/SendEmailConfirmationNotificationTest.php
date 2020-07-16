<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\NewsletterSubscriptionWasCreated;
use App\Listeners\SendEmailConfirmationNotification;
use App\Models\NewsletterSubscription;
use App\Contracts\MustConfirmSubscription;
use Mockery\Mock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SendEmailConfirmationNotificationTest extends TestCase
{
    /**
     * @var SendEmailConfirmationNotification
     */
    private $listener;

    /**
     * @var MustConfirmSubscription|MockObject
     */
    private $subscriptionMock;

    /**
     * @var NewsletterSubscriptionWasCreated|MockObject
     */
    private $eventMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->subscriptionMock = $this->createMock(NewsletterSubscription::class);

        $this->eventMock = $this->createMock(NewsletterSubscriptionWasCreated::class);
        $this->eventMock->newsletterSubscription  = $this->subscriptionMock;

        $this->listener = new SendEmailConfirmationNotification;
    }

    /**
     * @test
     */
    public function handle_sends_notification()
    {
        $this->subscriptionMock->method('subscriptionConfirmed')->willReturn(false);

        $this->subscriptionMock->expects($this->once())->method('sendConfirmationEmailNotification');

        $this->listener->handle($this->eventMock);
    }

    /**
     * @test
     */
    public function handle_doesnt_sends_notification_if_subscription_is_confirmed()
    {
        $this->subscriptionMock->method('subscriptionConfirmed')->willReturn(true);

        $this->subscriptionMock->expects($this->exactly(0))->method('sendConfirmationEmailNotification');

        $this->listener->handle($this->eventMock);
    }
}
