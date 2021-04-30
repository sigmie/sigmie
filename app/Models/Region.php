<?php

declare(strict_types=1);

namespace App\Models;

use Sigmie\App\Core\Cloud\Regions\America;
use Sigmie\App\Core\Cloud\Regions\Asia;
use Sigmie\App\Core\Cloud\Regions\Australia;
use Sigmie\App\Core\Cloud\Regions\Europe;
use Sigmie\App\Core\Cloud\Regions\SouthAmerica;
use Sushi\Sushi;

class Region extends Model
{
    use Sushi;

    protected $rows = [
        [
            'id' => 1,
            'class' => Asia::class,
            'name' => 'Asia',
        ],
        [
            'id' => 2,
            'class' => Australia::class,
            'name' => 'Australia',
        ],
        [
            'id' => 3,
            'class' => America::class,
            'name' => 'America',
        ],
        [
            'id' => 4,
            'class' => SouthAmerica::class,
            'name' => 'South America',
        ],
        [
            'id' => 5,
            'class' => Europe::class,
            'name' => 'Europe',
        ],
    ];
}
