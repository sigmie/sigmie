<?php

declare(strict_types=1);

namespace Sigmie\Support\Contracts;

use Doctrine\Common\Collections\Collection as DoctrineCollection;

interface Collection extends DoctrineCollection
{
    public function flatten(int $depth = INF): self;

    public function flattenWithKeys(int $depth = 1): self;

    public function mapWithKeys(callable $callback): self;
}
