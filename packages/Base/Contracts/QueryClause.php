<?php declare(strict_types=1);

namespace Sigmie\Base\Contracts;

interface QueryClause extends ToRaw
{
    public function toRaw(): array;
}