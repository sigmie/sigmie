<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Contracts;

use Sigmie\Shared\Collection;

interface Vectorizable
{
    /**
     * Get vector fields associated with this field
     */
    public function vectorFields(): Collection;
}
