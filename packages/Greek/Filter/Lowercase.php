<?php

declare(strict_types=1);

namespace Sigmie\Greek\Filter;

use Sigmie\Base\Analysis\TokenFilter\TokenFilter;

use function Sigmie\Helpers\name_configs;

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
