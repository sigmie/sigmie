<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class ConfirmSubscription extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Confirm your subscription')
            ->line('Please click the button below to confirm your newsletter subscription.')
            ->action('Confirm newsletter subscription', $verificationUrl);
    }

    protected function verificationUrl($notifiable)
    {
        return URL::temporarySignedRoute(
            'newsletter.subscription.confirmation',
            Carbon::now()->addMinutes(120),
            ['newsletterSubscription' => $notifiable->getKey()]
        );
    }
}
