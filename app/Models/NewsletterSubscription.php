<?php

namespace App\Models;

use App\Contracts\MustConfirmSubscription as MustConfirmSubscriptionInterface;
use Illuminate\Notifications\Notifiable;
use App\Traits\MustConfirmSubscription;
use Laravel\Nova\Actions\Actionable;

class NewsletterSubscription extends Model implements MustConfirmSubscriptionInterface
{
    use Actionable, Notifiable, MustConfirmSubscription;

    protected array $casts = [
        'confirmed' => 'boolean'
    ];

    protected $attributes = [
        'confirmed' => false
    ];
}
