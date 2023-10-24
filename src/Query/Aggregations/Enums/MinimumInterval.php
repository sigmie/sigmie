<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Enums;

enum MinimumInterval: string
{
    case Second = 'second';

    case Minute = 'minute';

    case Hour = 'hour';

    case Day = 'day';

    case Month = 'month';

    case Year = 'year';
}
