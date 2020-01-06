<?php

namespace Tests\Unit\Events;

use App\Events\NewsletterSubscribed;
use App\NewsletterSubscription;
use Tests\TestCase;

class NewsletterSubscribedTest extends TestCase
{
    private $event;

    private $newsletterSubscriptionMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->newsletterSubscriptionMock = $this->createMock(NewsletterSubscription::class);

        $this->event = new NewsletterSubscribed($this->newsletterSubscriptionMock);
    }

    /**
     * @test
     */
    public function newslettersubscription_returns_given_instance(): void
    {
        $this->assertEquals($this->event->newsletterSubscription(),  $this->newsletterSubscriptionMock);
    }
}
