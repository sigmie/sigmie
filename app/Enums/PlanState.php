<?php

declare(strict_types=1);

namespace App\Enums;

use MyCLabs\Enum\Enum;

class PlanState extends Enum
{
    public const NONE = 'none';

    public const RUNNING = 'running';
}
