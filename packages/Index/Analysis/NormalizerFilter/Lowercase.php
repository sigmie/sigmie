<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\NormalizerFilter;

use Sigmie\Index\Contracts\NormalizerFilter;

class Lowercase implements NormalizerFilter
{
    public function type(): string
    {
        return 'lowercase';
    }
}
