<?php

declare(strict_types=1);

namespace App\Models;

use Sushi\Sushi;

class Keyword extends Model
{
    use Sushi;

    protected $rows = [
        [
            'id' => 1,
            'word' => 'mice',
        ],
    ];
}
