<?php

declare(strict_types=1);

namespace App\Models;

use Sushi\Sushi;

class Synonym extends Model
{
    use Sushi;

    protected $rows = [
        [
            'id' => 1,
            'synonym' => ['google', 'goog'],
        ],
        [
            'id' => 2,
            'synonym' => ['subnet', 'subnetwork'],
        ],
        [
            'id' => 3,
            'synonym' => [],
        ],
    ];
}
