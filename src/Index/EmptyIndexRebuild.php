<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Exception;

class EmptyIndexRebuild extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forAlias(string $alias): static
    {
        return new static(sprintf("Rebuild of '%s' produced no documents. The live index was kept.", $alias));
    }
}
