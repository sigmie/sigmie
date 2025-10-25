<?php

declare(strict_types=1);

namespace Sigmie\Languages\Greek\Filter;

use Sigmie\Index\Analysis\TokenFilter\TokenFilter;

use function Sigmie\Functions\name_configs;

class Lowercase extends TokenFilter
{
    public function __construct(string $name = 'greek_lowercase')
    {
        parent::__construct($name);
    }

    public function type(): string
    {
        return 'lowercase';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static($name);
    }

    protected function getValues(): array
    {
        return [
            'language' => 'greek',
        ];
    }
}
