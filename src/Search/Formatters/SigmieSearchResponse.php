<?php

namespace Sigmie\Search\Formatters;

use Sigmie\Document\Hit;
use Sigmie\Mappings\Properties;
use Sigmie\Search\SearchContext;

class SigmieSearchResponse extends AbstractFormatter
{
    public function __construct(
        protected Properties $properties,
        protected bool $semantic = false
    ) {}

    public function json(?string $key = null): array
    {
        return (array) dot($this->format())->get($key);
    }

    public function format(): array
    {
        return [
            'semantic' => $this->semantic,
            'hits' => $this->queryResponseRaw['hits']['hits'] ?? [],
            'processing_time_ms' => $this->queryResponseRaw['took'] ?? 0,
            'total' => $this->queryResponseRaw['hits']['total']['value'] ?? 0,
            'query_strings' => array_map(fn ($qs): string => (string) $qs, $this->search->queryStrings ?? []),
            'filter_string' => $this->search->filterString ?? '',
            'facets_string' => $this->search->facetString ?? '',
            'sort_string' => $this->search->sortString ?? '',
            'page' => $this->search->size > 0 ? intval($this->search->from / $this->search->size) + 1 : 1,
            'per_page' => $this->search->size,
            'total_pages' => $this->search->size > 0 ? intval(($this->queryResponseRaw['hits']['total']['value'] ?? 0) / $this->search->size) : 1,

            'facets' => $this->formatFacets(),
            'errors' => $this->errors,

            'autocomplete' => $this->queryResponseRaw['suggest']['autocompletion'] ?? [],
            // 'params' => $this->context->params ?? [],
        ];
    }

    public function autocompletion()
    {
        return $this->queryResponseRaw['suggest']['autocompletion'] ?? [];
    }

    public function hits(): array
    {
        return array_map(fn (array $hit): Hit => new Hit(
            $hit['_source'],
            $hit['_id'],
            $hit['_score'],
            $hit['_index'],
            $hit['sort'] ?? null
        ), $this->queryResponseRaw['hits']['hits'] ?? []);
    }

    public function total()
    {
        return $this->queryResponseRaw['hits']['total']['value'] ?? 0;
    }

    public function getContext(): ?SearchContext
    {
        return $this->search ?? null;
    }

    public function formatFacets(): object
    {
        $facets = [];
        foreach ($this->properties->toArray() as $type) {
            if ($type->isFacetable() && in_array($type->name, $this->search->facetFields)) {
                $facetData = $type->facets($this->facetAggregations());
                if (! is_null($facetData)) {
                    $facets[$type->name] = (object) $facetData;
                }
            }
        }

        return (object) $facets;
    }
}
