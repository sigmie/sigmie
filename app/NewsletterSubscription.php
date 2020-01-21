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

    /**
     * Attribute default types
     *
     * @var array
     */
    protected $casts = [
        'confirmed' => 'boolean'
    ];

    /**
     * Attribute defaults
     *
     * @var array
     */
    protected $attributes = [
        'confirmed' => false
    ];
}
