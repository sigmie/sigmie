<?php

declare(strict_types=1);

namespace Sigmie\Support\Contracts;

use Closure;
use Doctrine\Common\Collections\Collection as DoctrineCollection;

interface Collection extends DoctrineCollection
{
    public function flatten(int $depth = INF): self;

    public function flattenWithKeys(int $depth = 1): self;

    public function mapWithKeys(callable $callback): self;

    public function mapToDictionary(callable $callback): self;

    public function sortByKeys(): self;

    /**
     * @return self
     */
    public function map(Closure $func);

    /**
     * @return self
     */
    public function filter(Closure $p);
}
