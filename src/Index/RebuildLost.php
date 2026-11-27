<?php

declare(strict_types=1);

namespace Sigmie\Index;

use RuntimeException;

class RebuildLost extends RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forAlias(string $alias, string $index): static
    {
        return new static(sprintf(
            "Alias '%s' no longer points to '%s'.",
            $alias,
            $index,
        ));
    }
}
