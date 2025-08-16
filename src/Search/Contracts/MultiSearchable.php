<?php

declare(strict_types=1);

namespace Sigmie\Search\Contracts;

interface MultiSearchable 
{
    public function toMultiSearch(): array;
    
    public function name(string $name): static;

    public function multisearchResCount(): int;
}
