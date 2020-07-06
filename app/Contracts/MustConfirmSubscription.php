<?php

declare(strict_types=1);

namespace App\Contracts;

interface MustConfirmSubscription
{
    public function subscriptionConfirmed(): bool;

    public function sendConfirmationEmailNotification(): void;

    public function confirmSubscription(): void;
}
