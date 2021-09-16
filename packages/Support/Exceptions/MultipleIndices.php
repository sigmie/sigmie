<?php

declare(strict_types=1);

namespace Sigmie\Support\Exceptions;

use Exception;

class MultipleIndices extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forAlias(string $alias): static
    {
        return new static("Multiple indices found for alias {$alias}.");
    }
}
