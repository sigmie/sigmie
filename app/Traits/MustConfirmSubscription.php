<?php

declare(strict_types=1);

namespace App\Traits;

use App\Notifications\Newsletter\ConfirmSubscription;

trait MustConfirmSubscription
{
    public function subscriptionConfirmed(): bool
    {
        return $this->getAttribute('confirmed');
    }

    public function confirmSubscription(): void
    {
        $this->forceFill(['confirmed' => true])->save();
    }

    public function sendConfirmationEmailNotification(): void
    {
        $this->notify(new ConfirmSubscription);
    }
}
