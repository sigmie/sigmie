<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\MustConfirmSubscription as MustConfirmSubscriptionInterface;
use App\Traits\MustConfirmSubscription;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Nova\Actions\Actionable;

class NewsletterSubscription extends Model implements MustConfirmSubscriptionInterface
{
    use Actionable;
    use Notifiable;
    use MustConfirmSubscription;
    use HasFactory;

    protected $casts = [
        'confirmed' => 'boolean'
    ];

    protected $attributes = [
        'confirmed' => false
    ];
}
