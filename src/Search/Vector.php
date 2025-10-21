<?php

declare(strict_types=1);

namespace Sigmie\Search;

class Vector
{
    public function __construct(
        public readonly int $dimension,
        public readonly array $vector
    ) {
        $this->dimension = $dimension;
        $this->vector = $vector;
    }
}
