<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Contracts;

use Sigmie\Mappings\Properties;

interface FieldContainer
{
    /**
     * Get the Properties instance containing the child fields
     */
    public function getProperties(): Properties;

    /**
     * Check if this container has child fields
     */
    public function hasFields(): bool;
}
