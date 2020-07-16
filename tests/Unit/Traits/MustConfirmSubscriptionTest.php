<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Contracts\MustConfirmSubscription;
use App\Notifications\ConfirmSubscription;
use App\Traits\MustConfirmSubscription as MustConfirmSubscriptionTrait;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class MustConfirmSubscriptionTest extends TestCase
{
    /**
     * @var MustConfirmSubscription|MockObject
     */
    private $mustConfirmSubscription;

    public function setUp(): void
    {
        parent::setUp();

        $this->mustConfirmSubscription = $this->getMockBuilder(MustConfirmSubscriptionTrait::class)->addMethods([
            'forceFill', 'notify', 'save', 'getAttribute'
        ])->getMockForTrait();
    }

    /**
     * @test
     */
    public function subscription_confirmed_returns_prop_value()
    {
        $this->mustConfirmSubscription->method('forceFill')->willReturnSelf();
        $this->mustConfirmSubscription->method('getAttribute')->willReturnMap([['confirmed', false]]);

        $this->assertFalse($this->mustConfirmSubscription->subscriptionConfirmed());
    }

    /**
     * @test
     */
    public function confirm_subscription_force_fills_confirmed_value_to_true_and_saves()
    {
        $this->mustConfirmSubscription->method('forceFill')->willReturnSelf();
        $this->mustConfirmSubscription->expects($this->once())->method('forceFill')->with(['confirmed' => true]);
        $this->mustConfirmSubscription->expects($this->once())->method('save');

        $this->mustConfirmSubscription->confirmSubscription();
    }

    /**
     * @test
     */
    public function send_confirmation_email_calls_notify_with_notification()
    {
        $this->mustConfirmSubscription->method('forceFill')->willReturnSelf();
        $this->mustConfirmSubscription->expects($this->once())->method('notify')->with(new ConfirmSubscription);

        $this->mustConfirmSubscription->sendConfirmationEmailNotification();
    }
}
