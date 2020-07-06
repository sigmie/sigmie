<?php declare(strict_types=1);

namespace App\Models;

use App\Contracts\MustConfirmSubscription as MustConfirmSubscriptionInterface;
use App\Traits\MustConfirmSubscription;
use Illuminate\Notifications\Notifiable;
use Laravel\Nova\Actions\Actionable;

class NewsletterSubscription extends Model implements MustConfirmSubscriptionInterface
{
    use Actionable, Notifiable, MustConfirmSubscription;

    protected $casts = [
        'confirmed' => 'boolean'
    ];

    protected $attributes = [
        'confirmed' => false
    ];
}
