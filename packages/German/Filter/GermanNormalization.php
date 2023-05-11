<?php

declare(strict_types=1);

namespace Sigmie\German\Filter;

use Sigmie\Index\Analysis\TokenFilter\TokenFilter;
use Sigmie\Index\Contracts\NormalizerFilter;

class GermanNormalization extends TokenFilter implements NormalizerFilter
{
    protected function getValues(): array
    {
        return [];
    }

    public function type(): string
    {
        return 'german_normalization';
    }
}
