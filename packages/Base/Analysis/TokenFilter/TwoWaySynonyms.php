<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

use Sigmie\Base\Contracts\TokenFilter;

class TwoWaySynonyms implements TokenFilter
{
    protected string $name = 'two_way_synonyms';

    public function __construct(
        protected string $prefix,
        protected array $synonyms
    ) {
        $this->name = "{$prefix}_{$this->name}";
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return 'synonym';
    }

    public function value(): array
    {
        $synonyms = array_map(fn ($value) => implode(', ', $value), $this->synonyms);

        return [
            'synonyms' => $synonyms,
            'class' => static::class
        ];
    }
}
