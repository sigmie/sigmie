<?php

declare(strict_types=1);


namespace Sigma\Mapping;

use Doctrine\Common\Annotations\Annotation;
use Sigma\Contract\Type as TypeInterface;


abstract class Type implements TypeInterface
{
    /**
     * Default parameters for all types
     *
     * @return array
     */
    public function parameters(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    abstract public function field(): string;
}
