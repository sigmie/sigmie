<?php declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\NewsletterSubscriptionWasCreated;
use App\Models\NewsletterSubscription;
use Tests\TestCase;

class NewsletterSubscribedTest extends TestCase
{
    private $event;

    private $newsletterSubscriptionMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->newsletterSubscriptionMock = $this->createMock(NewsletterSubscription::class);

        $this->event = new NewsletterSubscriptionWasCreated($this->newsletterSubscriptionMock);
    }

    /**
     * @test
     */
    public function newslettersubscription_returns_given_instance(): void
    {
        $this->assertEquals($this->event->newsletterSubscription, $this->newsletterSubscriptionMock);
    }
}
