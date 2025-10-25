<?php

declare(strict_types=1);

namespace Sigmie\Index\Alias;

use Exception;

class MultipleIndicesForAlias extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forAlias(string $alias): static
    {
        return new static(sprintf('Multiple indices found for alias %s.', $alias));
    }
}
