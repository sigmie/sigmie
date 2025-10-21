<?php

declare(strict_types=1);

namespace Sigmie\Search\Contracts;

interface MultiSearchable
{
    public function toMultiSearch(): array;

    public function multisearchResCount(): int;

    public function formatResponses(...$responses): mixed;
}
