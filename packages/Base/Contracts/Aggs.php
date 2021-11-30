<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

interface Aggs extends ToRaw
{
    public function min(string $name, string $field);

    public function max(string $name, string $field);

    public function avg(string $name, string $field);
}
