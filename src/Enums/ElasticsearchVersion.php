<?php

declare(strict_types=1);

namespace Sigmie\Enums;

enum ElasticsearchVersion: string
{
    case v7 = '7.x';
    case v8 = '8.x';
}
