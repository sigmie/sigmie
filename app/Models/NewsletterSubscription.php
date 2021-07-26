<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\MustConfirmSubscription as MustConfirmSubscriptionInterface;
use App\Notifications\Newsletter\ConfirmSubscription;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Nova\Actions\Actionable;

class NewsletterSubscription extends Model implements MustConfirmSubscriptionInterface
{
    use Actionable;
    use Notifiable;
    use HasFactory;

    protected $casts = [
        'confirmed' => 'boolean'
    ];

    protected $attributes = [
        'confirmed' => false
    ];

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
        $this->notify(new ConfirmSubscription());
    }
}
