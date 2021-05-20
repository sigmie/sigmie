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
            "synonyms" => $rules
        ];
    }
}
