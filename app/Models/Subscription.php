<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Paddle\Subscription as PaddleSubscription;

class Subscription extends PaddleSubscription
{
    use HasFactory;
}
