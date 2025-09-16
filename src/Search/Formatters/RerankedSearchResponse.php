<?php

declare(strict_types=1);

namespace Sigmie\Search\Formatters;

class RerankedSearchResponse
{
    public function __construct(
        protected array $rerankedHits,
        protected array $fields,
        protected string $query,
        protected int $topK,
    ) {}

    public function hits(): array
    {
        return $this->rerankedHits;
    }
}
