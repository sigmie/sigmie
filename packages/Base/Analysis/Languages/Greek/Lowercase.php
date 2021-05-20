<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Languages\Greek;

use Sigmie\Base\Contracts\TokenFilter;

class Lowercase implements TokenFilter
{
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
        return ['language' => 'greek'];
    }
}
