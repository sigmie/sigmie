<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Enums;

enum CalendarInterval: string
{
    case Minute = '1m';

    case Hour = '1h';

    case Day = '1d';

    case Week = '1w';

    case Month = '1M';

    case Quarter = '1q';

    case Year = '1y';
}
