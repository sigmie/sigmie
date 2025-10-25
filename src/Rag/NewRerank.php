<?php

declare(strict_types=1);

namespace Sigmie\Rag;

use Sigmie\AI\Contracts\RerankApi;
use Sigmie\Document\Hit;
use Sigmie\Document\RerankedHit;
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

        if ($hits === [] || ! $query) {
            return $hits;
        }

        // Format hits for reranking
        $textDocuments = $this->payload($hits);

        // Perform reranking
        $res = $this->reranker->rerank($textDocuments, $query, $this->topK) ?? [];

        return array_map(function (array $index) use ($hits): RerankedHit {
            $hit = $hits[$index['index']];

            // Convert array to Hit object if needed
            if (is_array($hit)) {
                $hit = new Hit(
                    $hit['_source'] ?? [],
                    $hit['_id'] ?? '',
                    $hit['_score'] ?? null,
                    $hit['_index'] ?? null,
                    $hit['sort'] ?? null
                );
            }

            return new RerankedHit($hit, $index['score']);
        }, $res);
    }

    public function payload(array $hits): array
    {
        $documents = [];

        foreach ($hits as $hit) {
            // Filter to specific fields and format as inline YAML string
            $filteredData = [];
            $source = is_array($hit) ? ($hit['_source'] ?? []) : $hit->_source;

            foreach ($this->fields as $field) {
                if (isset($source[$field])) {
                    $filteredData[$field] = $source[$field];
                }
            }

            // Format as inline YAML (e.g., "name: Value\ndescription: Another value")
            $documents[] = Yaml::dump($filteredData, inline: 1);
        }

        return $documents;
    }
}
