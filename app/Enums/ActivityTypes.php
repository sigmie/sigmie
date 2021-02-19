<?php

declare(strict_types=1);

namespace App\Enums;

use MyCLabs\Enum\Enum;

class ActivityTypes extends Enum
{
    private const INFO = 'info';

    private const WARNING = 'info';

    private const ERROR = 'error';

    public static function INFO(): ActivityTypes
    {
        return new ActivityTypes(self::INFO);
    }

    public static function WARNING(): ActivityTypes
    {
        return new ActivityTypes(self::WARNING);
    }

    public static function ERROR(): ActivityTypes
    {
        return new ActivityTypes(self::ERROR);
    }
}
