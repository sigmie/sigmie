<?php

declare(strict_types=1);

namespace Sigmie\AI\Rerankers;

use Sigmie\AI\Contracts\Reranker;

class NoopReranker implements Reranker
{
    public function rerank(array $documents, string $queryString, ?int $topK = null): array
    {
        return $documents;
    }
}
