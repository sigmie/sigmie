<?php

declare(strict_types=1);

namespace Sigmie\Rag;

use Sigmie\AI\Contracts\RerankApi;
use Sigmie\Document\Hit;
use Sigmie\Document\RerankedHit;
use Sigmie\Search\Formatters\SigmieSearchResponse;
use Symfony\Component\Yaml\Yaml;

class NewRerank
{
    protected array $fields = [];

    protected int $topK = 10;

    protected ?string $query = null;

    public function __construct(protected RerankApi $reranker) {}

    public function query(string $query): self
    {
        $this->query = $query;
        return $this;
    }

    public function topK(int $topK): self
    {
        $this->topK = $topK;
        return $this;
    }

    public function fields(array $fields): self
    {
        $this->fields = $fields;
        return $this;
    }

    public function rerank(array $hits): array
    {
        $query = $this->query;

        if (empty($hits) || !$query) {
            return $hits;
        }

        // Format hits for reranking
        $textDocuments = $this->payload($hits);

        // Perform reranking
        $res = $this->reranker->rerank($textDocuments, $query, $this->topK) ?? [];

        return array_map(function ($index) use ($hits) {
            return new RerankedHit($hits[$index['index']], $index['score']);
        }, $res);
    }

    public function payload(array $hits): array
    {
        $documents = [];

        /** @var Hit $hit */
        foreach ($hits as $hit) {
            // Filter to specific fields and format as inline YAML string
            $filteredData = [];
            foreach ($this->fields as $field) {
                if (isset($hit->_source[$field])) {
                    $filteredData[$field] = $hit->_source[$field];
                }
            }
            // Format as inline YAML (e.g., "name: Value\ndescription: Another value")
            $documents[] = Yaml::dump($filteredData, inline: 1);
        }

        return $documents;
    }
}
