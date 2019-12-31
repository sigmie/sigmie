<?php

namespace App;

use App\Contracts\MustConfirmSubscription as MustConfirmSubscriptionInterface;
use App\Model;
use Illuminate\Notifications\Notifiable;
use App\Traits\MustConfirmSubscription;

class NewsletterSubscription extends Model implements MustConfirmSubscriptionInterface
{
    use Notifiable;
    use MustConfirmSubscription;

    protected $casts = [
        'confirmed' => 'boolean'
    ];

    protected $attributes = [
        'confirmed' => false
    ];
}
