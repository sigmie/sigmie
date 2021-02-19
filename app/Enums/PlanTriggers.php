<?php

declare(strict_types=1);

namespace App\Enums;

use MyCLabs\Enum\Enum;

class PlanTriggers extends Enum
{
    private const PING = 'ping';

    private const MANUAL = 'manual';

    private const SCHEDULED = 'scheduled';

    public static function SCHEDULED(): PlanTriggers
    {
        return new PlanTriggers(self::SCHEDULED);
    }

    public static function PING(): PlanTriggers
    {
        return new PlanTriggers(self::PING);
    }

    public static function MANUAL(): PlanTriggers
    {
        return new PlanTriggers(self::MANUAL);
    }
}
