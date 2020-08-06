<?php

declare(strict_types=1);


namespace Sigma\Mapping\Types;

use Sigma\Mapping\Type;

/**
 * @Annotation
 */
class Keyword extends Type
{
    public function field(): string
    {
        return 'keyword';
    }
}
