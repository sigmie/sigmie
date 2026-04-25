<?php

declare(strict_types=1);

namespace Sigmie\Search\Contracts;

use Closure;
use Generator;
use Sigmie\Document\Hit;

interface LazyIterableQuery
{
    public function chunk(int $size): static;

    /**
     * @return Generator<int, Hit>
     */
    public function lazy(): Generator;

    public function each(Closure $fn): void;
}
