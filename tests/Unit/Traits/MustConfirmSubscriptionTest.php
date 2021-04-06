<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Contracts\MustConfirmSubscription;
use App\Models\NewsletterSubscription;
use App\Models\Subscription;
use App\Notifications\Newsletter\ConfirmSubscription;
use App\Traits\MustConfirmSubscription as MustConfirmSubscriptionTrait;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Feature\NewsletterSubscriptionControllerTest;
use Tests\TestCase;

class MustConfirmSubscriptionTest extends TestCase
{
    /**
     * @var NewsletterSubscription
     */
    private $subscription;

    public function setUp(): void
    {
        parent::setUp();

        $this->subscription = new NewsletterSubscription([
            'email' => 'foo@bar.com'
        ]);
    }

    /**
     * @test
     */
    public function subscription_confirmed_returns_prop_value()
    {
        $this->assertFalse($this->subscription->subscriptionConfirmed());
    }

    /**
     * @test
     */
    public function confirm_subscription_force_fills_confirmed_value_to_true_and_saves()
    {
        $this->subscription->confirmSubscription();

        $this->assertTrue($this->subscription->subscriptionConfirmed());
    }

    /**
     * @test
     */
    public function send_confirmation_email_calls_notify_with_notification()
    {
        Notification::fake();

        $this->subscription->sendConfirmationEmailNotification();

        Notification::assertSentTo(
            [$this->subscription],
            ConfirmSubscription::class
        );
    }
}
