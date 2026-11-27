<?php

declare(strict_types=1);

namespace Sigmie\Search\Formatters;

use Sigmie\Query\NewQuery;
use Sigmie\Search\Contracts\MultiSearchable;
use Sigmie\Search\Contracts\ResponseFormater;
use Sigmie\Search\RawQuery;

use function Sigmie\Functions\response_value;
use function Sigmie\Functions\search_hits;
use function Sigmie\Functions\search_summary;

class SigmieMultiSearchResponse
{
    protected array $multiSearchResponseRaw = [];

    /** @var MultiSearchable[] */
    protected array $searches = [];

    protected array $formattedResults = [];

    public function multiSearchResponseRaw(array $raw): static
    {
        $this->multiSearchResponseRaw = $raw;
        $this->formattedResults = [];

        return $this;
    }

    public function searches(array $searches): static
    {
        $this->searches = $searches;
        $this->formattedResults = [];

        return $this;
    }

    public function format(): array
    {
        if ($this->formattedResults !== []) {
            return $this->formattedResults;
        }

        search_hits($this->multiSearchResponseRaw);
        $responses = $this->multiSearchResponseRaw['responses'] ?? [];
        $results = [];

        $responseIndex = 0;
        foreach ($this->searches as $searchIndex => $search) {
            $count = $search->multisearchResCount();
            $slice = array_slice($responses, $responseIndex, $count);
            $responseIndex += $count;

            if ($search instanceof NewQuery || $search instanceof RawQuery) {
                $result = search_summary($slice[0] ?? []);
            } else {
                foreach ($slice as $response) {
                    search_hits($response);
                }

                $result = $search->formatResponses(...$slice);
                if ($result instanceof ResponseFormater) {
                    $result = $result->format();
                }
            }

            $results[$searchIndex] = $result;
        }

        $this->formattedResults = $results;

        return $results;
    }

    public function json(?string $key = null): mixed
    {
        return response_value($this->format(), $key);
    }

    public function getSearchResult(int $index): mixed
    {
        $formatted = $this->format();

        return $formatted[$index] ?? null;
    }

    public function getAllResults(): array
    {
        return $this->format();
    }
}
