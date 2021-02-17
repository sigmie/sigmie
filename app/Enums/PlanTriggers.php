<?php

declare(strict_types=1);

namespace App\Enums;

use MyCLabs\Enum\Enum;

class PlanTriggers extends Enum
{
    private const PING = 'ping';

    private const MANUAL = 'manual';

    private const SCHEDULED = 'scheduled';
}
