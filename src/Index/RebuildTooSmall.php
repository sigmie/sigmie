<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Exception;

class RebuildTooSmall extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forAlias(string $alias, int $count, int $minDocuments): static
    {
        return new static(sprintf(
            "Rebuild of '%s' produced %d documents, fewer than the required %d. The live index was kept.",
            $alias,
            $count,
            $minDocuments,
        ));
    }
}
