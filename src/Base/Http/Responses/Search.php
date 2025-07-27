<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Responses;

use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Document\Hit;

class Search extends ElasticsearchResponse
{
    public function total(): int
    {
        return $this->json('hits.total.value');
    }

    public function aggregation(string $dot): mixed
    {
        return $this->json("aggregations.{$dot}");
    }

    public function get(): array
    {
        return $this->json();
    }

    public function autocompletion(): array
    {
        return array_map(fn($value) => $value['text'], $this->json('suggest.autocompletion.0.options') ?? []);
    }

    public function hits(): array
    {
        return array_map(fn($value) => new Hit(
            $value['_source'],
            $value['_id'],
            $value['_score']
        ), $this->json('hits.hits') ?? []);
    }

    public function replaceHits(array $hits): array
    {
        $this->decoded->set('hits.hits', $hits);

        return $this->hits();
    }

    public function normalize()
    {
        $results = $this->json();

        $hits = [];

        // $facets = collect($properties->toArray())
        //     ->filter(fn(Type $type) => $type->isFacetable())
        //     ->filter(fn(Type $type) => in_array($type->name, $requestedFacets))
        //     ->mapWithKeys(fn(Type $type) => [$type->name => (object) $type->facets($response)])
        //     ->filter(fn($value) => !is_null($value))
        //     ->toArray();

        // $autocompletions = array_map(fn($value) => $value['text'], $response->json('suggest.autocompletion.0.options') ?? []);

        // $autocompletions = trim($query) === '' ? explode(';', $trending) : $autocompletions;

        return [
            'hits' => (object) $hits,
            'processing_time_ms' => $took,
            'total' => $total,
            // 'per_page' => $perPage,
            // 'page' => $page,
            // 'query' => $query,
            // // 'search' => $search,
            // 'autocomplete' => $autocompletions,
            // 'params' => $params,
            // 'index' => $index,
            // 'filters' => $filter,
            // 'facets' => $facets,
            // 'sort' => $sortString,
        ];
    }
}
