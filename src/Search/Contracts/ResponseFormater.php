<?php

declare(strict_types=1);

namespace Sigmie\Search\Contracts;

use Sigmie\AI\Contracts\RerankApi;
use Sigmie\Search\SearchContext;

interface ResponseFormater
{
    public function queryResponseRaw(array $raw): static;

    public function facetsResponseRaw(array $raw): static;

    public function format(): array;

    public function errors(array $errors): static;

    public function context(SearchContext $context): static;

    /**
     * @param  array<string, mixed>  $apis
     */
    public function apis(array $apis): static;

    /**
     * @param  array<int, string>  $fields
     * @return array<int, \Sigmie\Document\RerankedHit>
     */
    public function rerank(
        RerankApi|string $reranker,
        array $fields,
        ?string $query = null,
        ?int $topK = null,
    ): array;
}
