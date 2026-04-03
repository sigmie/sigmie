<?php

namespace Sigmie\Search\Formatters;

use LogicException;
use Sigmie\AI\Contracts\RerankApi;
use Sigmie\Document\RerankedHit;
use Sigmie\Search\Contracts\ResponseFormater;
use Sigmie\Search\SearchContext;

abstract class AbstractFormatter implements ResponseFormater
{
    /**
     * Registered embeddings / LLM / rerank APIs from the search builder (for response helpers).
     *
     * @var array<string, mixed>
     */
    protected array $apis = [];

    protected array $queryResponseRaw = [];

    protected array $facetsResponseRaw = [];

    protected array $facets = [];

    protected array $errors;

    protected SearchContext $search;

    protected int $responseCode = 200;

    abstract public function format(): array;

    public function queryResponseRaw(array $raw): static
    {
        $this->queryResponseRaw = $raw;

        return $this;
    }

    public function facetsResponseRaw(array $raw): static
    {
        $this->facetsResponseRaw = $raw;

        return $this;
    }

    public function errors(array $errors): static
    {
        $this->errors = $errors;

        return $this;
    }

    public function context(SearchContext $context): static
    {
        $this->search = $context;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $apis
     */
    public function apis(array $apis): static
    {
        $this->apis = $apis;

        return $this;
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<int, RerankedHit>
     */
    public function rerank(
        RerankApi|string $reranker,
        array $fields,
        ?string $query = null,
        ?int $topK = null,
    ): array {
        throw new LogicException('Reranking is only available on SigmieSearchResponse.');
    }

    public function responseCode(int $code): static
    {
        $this->responseCode = $code;

        return $this;
    }

    public function code(): int
    {
        return $this->responseCode;
    }

    public function facetAggregations(): array
    {
        return $this->facetsResponseRaw['aggregations'] ?? [];
    }
}
