<?php

declare(strict_types=1);

namespace App\Enums;

use MyCLabs\Enum\Enum;

class ActivityTypes extends Enum
{
    private const DISPATCH = 'dispatch';

    private const ERROR = 'error';
}
