<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Contracts;

interface Sortable
{
    /**
     * Get the field name to use for sorting
     */
    public function sortableName(): ?string;
}
