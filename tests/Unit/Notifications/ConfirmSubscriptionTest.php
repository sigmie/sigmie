<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Notifications\Newsletter\ConfirmSubscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Tests\Helpers\WithNotifiableMock;
use Tests\TestCase;

class ConfirmSubscriptionTest extends TestCase
{
    use WithNotifiableMock;

    /**
     * @var ConfirmSubscription
     */
    private $notification;

    public function setUp(): void
    {
        parent::setUp();

        $this->notification = new ConfirmSubscription();
    }

    /**
     * @test
     */
    public function notification_via_mail()
    {
        $this->assertEquals(['mail'], $this->notification->via($this->withNotifiableMock()));
    }

    /**
     * @test
     */
    public function to_mail()
    {
        $this->withNotifiableMock();

        URL::shouldReceive('temporarySignedRoute')->andReturn('some-link');

        $expected = (new MailMessage())
            ->subject('Confirm your subscription')
            ->line('Please click the button below to confirm your newsletter subscription.')
            ->action('Confirm newsletter subscription', 'some-link');

        $this->assertEquals($expected, $this->notification->toMail($this->notifiableMock));
    }
}
