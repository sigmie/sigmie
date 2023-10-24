<?php

declare(strict_types=1);

namespace Sigmie\Query\Contracts;

use Sigmie\Shared\Contracts\ToRaw;

interface Aggs extends ToRaw
{
    public function min(string $name, string $field);

    public function max(string $name, string $field);

    public function avg(string $name, string $field);
}
