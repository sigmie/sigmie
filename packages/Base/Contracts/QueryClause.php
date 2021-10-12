<?php

namespace Sigmie\Base\Contracts;

interface QueryClause extends ToRaw
{
    public function toRaw(): array;
}
