<?php

declare(strict_types=1);

namespace Sigmie\Search\Formatters;

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
                $searchResponse = $responses[$responseIndex] ?? [];

                $properties = $search->getProperties() ?? new Properties;
                $searchContext = $search->searchContext;

                $formatter = new SigmieSearchResponse($properties);
                $formatter->queryResponseRaw($searchResponse)
                    ->facetsResponseRaw($searchResponse)
                    ->context($searchContext)
                    ->errors([]);

                $results[$searchIndex] = $formatter->format();
                $responseIndex += 1;
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
