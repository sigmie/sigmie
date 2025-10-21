<?php

declare(strict_types=1);

namespace Sigmie\AI\Contracts;

use Sigmie\Document\Hit;

interface Reranker
{
    public function rerank(array $documents, string $queryString): array;
}
