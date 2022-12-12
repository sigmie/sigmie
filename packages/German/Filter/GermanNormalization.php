<?php

declare(strict_types=1);

namespace Sigmie\German\Filter;

use Sigmie\Index\Analysis\TokenFilter\Lowercase as BaseLowercase;
use Sigmie\Index\Contracts\NormalizerFilter;

class GermanNormalization implements NormalizerFilter
{
    public function type(): string
    {
        return 'german_normalization';
    }
}
