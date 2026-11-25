<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\TokenFilter;

use function Sigmie\Functions\name_configs;

class TokenLimit extends TokenFilter
{
    public function __construct(
        string $name,
        int $count
    ) {
        parent::__construct($name, ['max_token_count' => $count]);
    }

    public function type(): string
    {
        return 'limit';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);

        $filter = new static($name, (int) ($configs['max_token_count'] ?? 1));
        $filter->settings($filter->settings + $configs);

        return $filter;
    }

    protected function getValues(): array
    {
        return $this->settings;
    }
}
