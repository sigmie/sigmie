<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Paddle\Receipt as PaddleReceipt;

class Receipt extends PaddleReceipt
{
    use HasFactory;
}
