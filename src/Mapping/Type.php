<?php

namespace Sigma\Mapping;

use Sigma\Contract\Type as TypeInterface;

abstract class Type implements TypeInterface
{
    /**
     * Default parameters for all types
     *
     * @return array
     */
    protected function parameters(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    abstract public function field(): string;
}
