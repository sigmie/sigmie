<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Queries;

class Raw extends Query
{
    public function __construct(protected string $raw)
    {
    }
    public function toRaw(): array
    {
        return [$this->raw];
    }
}
