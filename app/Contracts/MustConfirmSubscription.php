<?php

namespace App\Contracts;

interface MustConfirmSubscription
{
    public function subscriptionConfirmed(): bool;

    public function sendConfirmationEmailNotification(): void;

    public function confirmSubscription(): void;
}
