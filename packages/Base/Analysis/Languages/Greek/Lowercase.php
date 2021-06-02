<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Languages\Greek;

use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Base\Priority;

class Lowercase implements TokenFilter
{
    use Priority;

    protected string $name = 'greek_lowercase';

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return 'lowercase';
    }

    public function value(): array
    {
        return [
            'language' => 'greek',
            'class' => static::class
        ];
    }
}
