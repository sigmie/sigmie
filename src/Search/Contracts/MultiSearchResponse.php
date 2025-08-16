<?php

declare(strict_types=1);

namespace Sigmie\Search\Contracts;

interface MultiSearchResponse
{
    public function multiSearchResponseRaw(array $raw): static;
    
    public function searches(array $searches): static;
    
    public function format(): array;
    
    public function json(?string $key = null): array;
    
    public function getSearchResult(int $index): ?array;
    
    public function getAllResults(): array;
}