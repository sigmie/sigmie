<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Tokenizers;

use Sigmie\Base\Contracts\TokenFilter;

class Length implements TokenFilter
{
    public function __construct(protected int $min, protected int $max)
    {
    }

    public function type(): string
    {
        return 'length';
    }

    public function value(): array
    {
        return [
            'min' => $this->min,
            'max' => $this->max
        ];
    }
}
