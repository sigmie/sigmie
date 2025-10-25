<?php

declare(strict_types=1);

namespace Sigmie\Search\Formatters;

use ReflectionClass;
use Sigmie\Mappings\Properties;
use Sigmie\Search\Contracts\MultiSearchable;
use Sigmie\Search\Contracts\MultiSearchResponse;
use Sigmie\Search\NewSearch;

class SigmieMultiSearchResponse implements MultiSearchResponse
{
    protected array $multiSearchResponseRaw = [];

    /** @var MultiSearchable[] */
    protected array $searches = [];

    protected array $formattedResults = [];

    public function multiSearchResponseRaw(array $raw): static
    {
        $this->multiSearchResponseRaw = $raw;

        return $this;
    }

    public function searches(array $searches): static
    {
        $this->searches = $searches;

        return $this;
    }

    public function format(): array
    {
        if ($this->formattedResults !== []) {
            return $this->formattedResults;
        }

        $responses = $this->multiSearchResponseRaw['responses'] ?? [];
        $results = [];

        $responseIndex = 0;
        foreach ($this->searches as $searchIndex => $search) {
            if ($search instanceof NewSearch) {
                // NewSearch has 2 responses: search + facets
                $searchResponse = $responses[$responseIndex] ?? [];
                $facetsResponse = $responses[$responseIndex + 1] ?? [];

                // Use reflection to access protected properties property
                $reflection = new ReflectionClass($search);
                $propertiesProperty = $reflection->getProperty('properties');
                $propertiesProperty->setAccessible(true);
                $properties = $propertiesProperty->getValue($search) ?? new Properties;

                $searchContextProperty = $reflection->getProperty('searchContext');
                $searchContextProperty->setAccessible(true);
                $searchContext = $searchContextProperty->getValue($search);

                // Create a formatter similar to NewSearch::get()
                $formatter = new SigmieSearchResponse($properties);
                $formatter->queryResponseRaw($searchResponse)
                    ->facetsResponseRaw($facetsResponse)
                    ->context($searchContext)
                    ->errors([]);

                $results[$searchIndex] = $formatter->format();
                $responseIndex += 2;
            } else {
                // NewQuery has 1 response: just search
                $searchResponse = $responses[$responseIndex] ?? [];

                $results[$searchIndex] = [
                    'hits' => $searchResponse['hits']['hits'] ?? [],
                    'processing_time_ms' => $searchResponse['took'] ?? 0,
                    'total' => $searchResponse['hits']['total']['value'] ?? 0,
                ];
                $responseIndex += 1;
            }
        }

        $this->formattedResults = $results;

        return $results;
    }

    public function json(?string $key = null): array
    {
        $formatted = $this->format();

        if ($key === null) {
            return $formatted;
        }

        return (array) dot($formatted)->get($key);
    }

    public function getSearchResult(int $index): ?array
    {
        $formatted = $this->format();

        return $formatted[$index] ?? null;
    }

    public function getAllResults(): array
    {
        return $this->format();
    }
}
