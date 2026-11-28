<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\AI\APIs\ApiResponse;
use Sigmie\AI\Contracts\RerankApi;
use Sigmie\Document\Hit;
use Sigmie\Document\RerankedHit;
use Symfony\Component\Yaml\Yaml;

use function Sigmie\Functions\select_source_fields;
use function Sigmie\Functions\trim_text;

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

        if ($hits === [] || $query === null || trim_text($query) === '') {
            return $hits;
        }

        $limit = min($this->topK, count($hits));
        if ($limit <= 0) {
            return [];
        }

        // The provider index is an offset into the rebuilt payload.
        $hits = array_values($hits);
        $textDocuments = $this->payload($hits);

        $res = $this->reranker->rerank($textDocuments, $query, $limit);
        ApiResponse::validateRanking($res, count($hits));
        usort($res, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(function (array $index) use ($hits): RerankedHit {
            $hit = $hits[$index['index']];

            if (is_array($hit)) {
                $hit = Hit::fromRaw($hit);
            }

            return new RerankedHit($hit, $index['score']);
        }, array_slice($res, 0, $limit));
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
            $source = (array) (is_array($hit) ? ($hit['_source'] ?? []) : $hit->_source);
            // No selection sends the whole source, like NewContextComposer.
            $filteredData = $fields === [] ? $source : select_source_fields($source, $fields);

            $data = $filteredData !== [] && array_is_list($filteredData) ? (object) $filteredData : $filteredData;
            $documents[] = Yaml::dump($data, inline: 1, flags: Yaml::DUMP_OBJECT_AS_MAP);
        }

        return $documents;
    }
}
