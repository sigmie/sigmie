<?php

declare(strict_types=1);

namespace Sigmie\Support\Exception;

use Exception;

class MultipleIndices extends Exception
{
    public static function forAlias(string $alias)
    {
        return new static("Multiple indices found for alias {$alias}.");
    }
}
