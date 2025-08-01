<?php

namespace Sigmie\Search\Formatters;

use Sigmie\Mappings\Properties;

class SigmieSearchResponse extends AbstractFormatter
{
    public function __construct(protected Properties $properties) {}

    public function json(?string $key = null): array {
        return (array) dot($this->format())->get($key);
    }

    public function format(): array
    {
        return [
            'hits' => $this->formatHits(),
            'processing_time_ms' => $this->queryResponseRaw['took'] ?? 0,
            'total' => $this->queryResponseRaw['hits']['total']['value'] ?? 0,

            'query_strings' => $this->search->queryStrings ?? [],
            'filter_string' => $this->search->filterString ?? '',
            'facets_string' => $this->search->facetString ?? '',
            'sort_string' => $this->search->sortString ?? '',
            'page' => $this->search->size > 0 ? intval($this->search->from / $this->search->size) + 1 : 1,
            'per_page' => $this->search->size,
            'total_pages' => $this->search->size > 0 ? intval($this->queryResponseRaw['hits']['total']['value'] / $this->search->size) : 1,

            'facets' => $this->formatFacets(),

            // 'autocomplete' => $this->context->autocomplete ?? [],
            // 'params' => $this->context->params ?? [],
        ];
    }

    public function formatFacets(): object
    {
        $facets = [];
        foreach ($this->properties->toArray() as $type) {
            if ($type->isFacetable() && in_array($type->name, $this->search->facetFields)) {
                $facetData = $type->facets($this->facetAggregations());
                if (!is_null($facetData)) {
                    $facets[$type->name] = (object) $facetData;
                }
            }
        }

        return (object) $facets;
    }

    protected function formatHits(): object
    {
        $hits = [];

        foreach ($this->queryResponseRaw['hits']['hits'] ?? [] as $hit) {
            $only = array_intersect_key($hit, array_flip(['_source', '_id', 'highlight', '_score']));

            $hits[(string) $hit['_id']] = [
                ...($only['_source'] ?? []),
                '_id' => $only['_id'] ?? null,
                '_score' => $only['_score'] ?? null,
                '_highlight' => $only['highlight'] ?? [],
            ];
        }

        return (object) $hits;
    }
}
