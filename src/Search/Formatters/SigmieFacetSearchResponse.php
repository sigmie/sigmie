<?php

namespace Sigmie\Search\Formatters;

use Sigmie\Mappings\Properties;

class SigmieFacetSearchResponse extends SigmieSearchResponse 
{
    public function __construct(protected Properties $properties) {}

    public function format(): array
    {
        dump('Response', $this->queryResponseRaw);

        return [
            'hits' => $this->formatHits(),
            'processing_time_ms' => $this->queryResponseRaw['took'] ?? 0,
            'total' => $this->queryResponseRaw['hits']['total']['value'] ?? 0,
            'query_strings' => $this->search->queryStrings ?? [],
            'filter_string' => $this->search->filterString ?? '',
            'sort_string' => $this->search->sortString ?? '',
            'page' => $this->search->size > 0 ? intval($this->search->from / $this->search->size) + 1 : 1,
            'per_page' => $this->search->size,
            'total_pages' => $this->search->size > 0 ? intval($this->queryResponseRaw['hits']['total']['value'] / $this->search->size) : 1,
        ];
    }

    protected function formatHits(): object
    {
        $hits = [];

        foreach ($this->queryResponseRaw['hits']['hits'] ?? [] as $hit) {
            $only = array_intersect_key($hit, array_flip(['_source', '_id', 'highlight', '_score']));

            $hits[(string) $hit['_id']] = [
                ...($only['_source'] ?? []),
                '_score' => $only['_score'] ?? null,
            ];
        }

        return (object) $hits;
    }
}
