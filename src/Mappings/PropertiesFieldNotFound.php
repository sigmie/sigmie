<?php

declare(strict_types=1);

namespace Sigmie\Mappings;

use RuntimeException;

class PropertiesFieldNotFound extends RuntimeException
{
    public function __construct(string $field)
    {
        parent::__construct(sprintf("The field '%s' was not found in properties.", $field));
    }
}
