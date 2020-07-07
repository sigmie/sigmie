<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Notifications\ConfirmSubscription;
use App\Traits\MustConfirmSubscription;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class MustConfirmSubscriptionTest extends TestCase
{
    /** @var MustConfirmSubscription|MockObject */
    private $trait;

    public function setUp(): void
    {
        parent::setUp();

        $this->trait = $this->getMockBuilder(MustConfirmSubscription::class)
            ->setMethods([
                'forceFill',
                'save',
                'notify',
            ])->getMockForTrait();

        $this->trait->method('forceFill')->willReturnSelf();
    }

    /**
     * @test
     */
    public function subscription_confirmed_returns_prop_value()
    {
        $this->trait->confirmed = false;

        $this->assertFalse($this->trait->subscriptionConfirmed());
    }

    /**
     * @test
     */
    public function confirm_subscription_force_fills_confirmed_value_to_true_and_saves()
    {
        $this->trait->expects($this->once())->method('forceFill')->with(['confirmed' => true]);
        $this->trait->expects($this->once())->method('save');

        $this->trait->confirmSubscription();
    }

    /**
    * @test
    */
    public function send_confirmation_email_calls_notify_with_notification()
    {
        $this->trait->expects($this->once())->method('notify')->with(new ConfirmSubscription);

        $this->trait->sendConfirmationEmailNotification();
    }
}
