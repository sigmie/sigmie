<?php

declare(strict_types=1);

namespace Sigmie\Support\Exceptions;

use Exception;

class MissingMapping extends Exception
{
    public static function forIndex(string $index)
    {
        return new static("Index mapping is missing for index name {$index}.");
    }

    public static function forAlias(string $alias)
    {
        return new static("Index mapping is missing for index alias {$alias}.");
    }
}
