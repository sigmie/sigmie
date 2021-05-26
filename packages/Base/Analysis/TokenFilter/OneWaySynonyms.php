<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

use Sigmie\Base\Contracts\TokenFilter;

class OneWaySynonyms implements TokenFilter
{
    protected string $name = 'one_way_synonyms';

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
        $rules = [];
        foreach ($this->synonyms as $to => $from) {
            $from = implode(', ', $from);
            $rules[] = "{$from} => {$to}";
        }

        return [
            'synonyms' => $rules,
            'class' => static::class
        ];
    }
}
