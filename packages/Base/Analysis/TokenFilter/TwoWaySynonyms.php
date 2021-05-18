<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

use Sigmie\Base\Contracts\TokenFilter;

class TwoWaySynonyms implements TokenFilter
{
    public function __construct(
        protected string $name,
        protected array $synonyms
    ) {
    }

    public function name(): string
    {
        return $this->name . '_two_way_synonyms';
    }

    public function type(): string
    {
        return 'synonym';
    }

    public function value(): array
    {
        $synonyms = array_map(fn ($value) => implode(',', $value), $this->synonyms);

        return [
            "synonyms" => $synonyms
        ];
    }
}
