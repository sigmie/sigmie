<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Notifications\Newsletter\ConfirmSubscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Tests\Helpers\NeedsNotifiable;
use Tests\TestCase;

class ConfirmSubscriptionTest extends TestCase
{
    use NeedsNotifiable;

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
        $this->assertEquals(['mail'], $this->notification->via($this->notifiable()));
    }

    /**
     * @test
     */
    public function to_mail()
    {
        URL::shouldReceive('temporarySignedRoute')->andReturn('some-link');

        $expected = (new MailMessage())
            ->subject('Confirm your subscription')
            ->line('Please click the button below to confirm your newsletter subscription.')
            ->action('Confirm newsletter subscription', 'some-link');

        $this->assertEquals($expected, $this->notification->toMail($this->notifiable()));
    }
}
