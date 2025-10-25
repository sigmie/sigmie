<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\TokenFilter;

use function Sigmie\Functions\name_configs;

class TokenLimit extends TokenFilter
{
    public function __construct(
        string $name,
        protected int $count
    ) {
        parent::__construct($name, []);
    }

    public function type(): string
    {
        return 'limit';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);

        return new static($name,(int) $configs['max_token_count']);
    }

    protected function getValues(): array
    {
        return [
            'max_token_count' => $this->count,
        ];
    }
}
