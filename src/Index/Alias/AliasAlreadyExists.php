<?php

declare(strict_types=1);

namespace Sigmie\Index\Alias;

use Exception;

class AliasAlreadyExists extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forAlias(string $alias): static
    {
        return new static("An index with alias '{$alias}' already exists.");
    }
}
