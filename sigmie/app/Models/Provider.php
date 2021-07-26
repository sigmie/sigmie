<?php

declare(strict_types=1);

namespace App\Models;

use Sigmie\App\Core\Cloud\Providers\Google\Google;

class Provider extends StaticModel
{
    protected $rows = [
        [
            'name' => 'Google',
            'provider_class' => Google::class,
            'active' => true
        ],
        [
            'name' => 'AWS',
            'provider_class' => null,
            'active' => false
        ],
        [
            'name' => 'Digital Ocean',
            'provider_class' => null,
            'active' => false
        ]
    ];
}
