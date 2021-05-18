<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

use Sigmie\Base\Contracts\TokenFilter;

class OneWaySynonyms implements TokenFilter
{
    public function __construct(
        protected string $name,
        protected array $synonyms
    ) {
    }

    public function name(): string
    {
        return $this->name . '_one_way_synonyms';
    }

    public function type(): string
    {
        return 'synonym';
    }

    public function value(): array
    {
        $synonyms = array_map(fn ($value) => implode(',', $value[0]) . '=>' . implode(',', $value[1]), $this->synonyms);

        return [
            "synonyms" => $synonyms
        ];
    }
}
