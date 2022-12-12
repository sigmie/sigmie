<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\NormalizerFilter;

use Sigmie\Index\Contracts\NormalizerFilter;

use function Sigmie\Functions\name_configs;

class DecimalDigit implements NormalizerFilter
{
    public function type(): string
    {
        return 'decimal_digit';
    }
}
