<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

use Sigmie\Base\Contracts\TokenFilter;

class Stemmer implements TokenFilter
{
    public function __construct(
        protected string $name,
        protected array $stems
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return 'stemmer_override';
    }

    public function value(): array
    {
        $rules = array_map(fn ($value) => implode(', ', $value[0]) . '=>' . $value[1], $this->stems);

        return [
            "rules" => $rules
        ];
    }
}
