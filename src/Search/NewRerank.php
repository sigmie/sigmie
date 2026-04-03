<?php

declare(strict_types=1);

namespace Sigmie\Search;

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

        $textDocuments = $this->payload($hits);

        $res = $this->reranker->rerank($textDocuments, $query, $this->topK) ?? [];

        return array_map(function (array $index) use ($hits): RerankedHit {
            $hit = $hits[$index['index']];

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
        return self::documentPayloads($hits, $this->fields);
    }

    /**
     * YAML document strings for reranking (same shape as {@see payload()}).
     *
     * @param  array<int, Hit|array<string, mixed>>  $hits
     * @return array<int, string>
     */
    public static function documentPayloads(array $hits, array $fields): array
    {
        $documents = [];

        foreach ($hits as $hit) {
            $filteredData = [];
            $source = is_array($hit) ? ($hit['_source'] ?? []) : $hit->_source;

            foreach ($fields as $field) {
                if (isset($source[$field])) {
                    $filteredData[$field] = $source[$field];
                }
            }

            $documents[] = Yaml::dump($filteredData, inline: 1);
        }

        return $documents;
    }
}
