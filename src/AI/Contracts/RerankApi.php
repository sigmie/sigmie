<?php

declare(strict_types=1);

namespace Sigmie\AI\Contracts;

interface RerankApi
{
    /**
     * Rerank documents based on a query
     */
    public function rerank(array $documents, string $query, array $options = []): array;
}