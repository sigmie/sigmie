<?php

namespace Sigmie\Search\Formatters;

use Sigmie\Mappings\Properties;

class SigmieFormatWithArray extends AbstractFormatter
{
    public function __construct(protected Properties $properties) {}

    protected array $parameters = [];

    /**
     * @param array{
     *   queryStrings: array,
     *   filterString: string,
     *   sortString: string,
     *   facetString: string,
     *   size: int,
     *   from: int,
     *   index?: string,
     *   textScoreMultiplier?: float,
     *   semanticScoreMultiplier?: float
     * } $parameters
     */
    public function withParameters(array $parameters): static
    {
        $this->parameters = $parameters;
        return $this;
    }

    public function format(): array
    {
        return [
            'hits' => $this->formatHits(),
            'processing_time_ms' => $this->raw['took'] ?? 0,
            'total' => $this->raw['hits']['total']['value'] ?? 0,
            
            'query' => $this->parameters['queryStrings'] ?? [],
            'filters' => $this->parameters['filterString'] ?? '',
            'facets' => $this->parameters['facetString'] ?? '',
            'sort' => $this->parameters['sortString'] ?? '',
            'size' => $this->parameters['size'] ?? 20,
            'from' => $this->parameters['from'] ?? 0,
            
            'meta' => [
                'index' => $this->parameters['index'] ?? null,
                'text_score_multiplier' => $this->parameters['textScoreMultiplier'] ?? 1.0,
                'semantic_score_multiplier' => $this->parameters['semanticScoreMultiplier'] ?? 1.0,
            ]
        ];
    }

    protected function formatHits(): object
    {
        $hits = [];

        foreach ($this->raw['hits']['hits'] ?? [] as $hit) {
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
