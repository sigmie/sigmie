<?php

declare(strict_types=1);

namespace App\Models;

use Sigmie\App\Core\Cloud\Providers\Google\Google;
use Sigmie\App\Core\Cloud\Regions\America;
use Sigmie\App\Core\Cloud\Regions\Asia;
use Sigmie\App\Core\Cloud\Regions\Europe;
use Sushi\Sushi;

class CloudResourcePricing extends Model
{
    use Sushi;

    protected $rows = [
        [
            'provider' => Google::class,
            'region' => Asia::class,
            'cpu' => 19,
            'memory' => 2.50,
            'disk' => 0.1
        ],
        [
            'provider' => Google::class,
            'region' => 'australia',
            'cpu' => 23,
            'memory' => 3.50,
            'disk' => 0.15
        ],
        [
            'provider' => Google::class,
            'region' => America::class,
            'cpu' => 16,
            'memory' => 2.50,
            'disk' => 0.1
        ],
        [
            'provider' => Google::class,
            'region' => 'SouthAmerica',
            'cpu' => 26,
            'memory' => 3.5,
            'disk' => 0.15
        ],
        [
            'provider' => Google::class,
            'region' => Europe::class,
            'cpu' => 18,
            'memory' => 2.5,
            'disk' => 0.1
        ],
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class, 'provider', 'provider');
    }
}
