<?php

namespace Sigmie\Rag;

use Sigmie\AI\Contracts\Reranker;
use Sigmie\Search\Formatters\RerankedSearchResponse;
use Sigmie\Search\Formatters\SigmieSearchResponse;

class NewRerank
{
    public function __construct(protected Reranker $reranker) {}

    public function query(string $query): self {}

    public function topK(int $topK): self {}

    public function fields(array $fields): self {}

    public function rerank(SigmieSearchResponse $response): RerankedSearchResponse
    {
        return new RerankedSearchResponse($response);
    }
}
