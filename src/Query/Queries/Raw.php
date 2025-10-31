<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries;

class Raw extends Query
{
    public function __construct(protected string|array $raw) {}

    public function toRaw(): array
    {
        if (is_array($this->raw)) {
            return $this->raw;
        }

        return [$this->raw];
    }
}
