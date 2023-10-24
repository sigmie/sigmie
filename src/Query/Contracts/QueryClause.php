<?php

declare(strict_types=1);

namespace Sigmie\Query\Contracts;

use Sigmie\Shared\Contracts\ToRaw;

interface QueryClause extends ToRaw
{
    public function boost(float $boost = 1): self;

    public function toRaw(): array;
}
