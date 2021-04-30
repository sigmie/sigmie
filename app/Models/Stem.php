<?php

declare(strict_types=1);

namespace App\Models;

use Sushi\Sushi;

class Stem extends Model
{
    use Sushi;

    protected $rows = [
        [
            'id' => 1,
            'word' => 'mice',
            'stemmed' => 'mouse',
        ],
        [
            'id' => 2,
            'word' => 'skies',
            'stemmed' => 'sky',
        ],
        [
            'id' => 3,
            'word' => 'feet',
            'stemmed' => 'foot',
        ]
    ];
}
