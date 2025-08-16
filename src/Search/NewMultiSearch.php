<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Base\APIs\MSearch;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Query\NewQuery;
use Sigmie\Search\MultiSearchResponse;

class NewMultiSearch
{
    use MSearch;

    /** @var MultiSearchable[] */
    protected array $searches = [];

    protected array $rawQueries = [];

    public function __construct(
        protected ElasticsearchConnection $elasticsearchConnection
    ) {}

    public function newSearch(string $name): NewSearch
    {
        $search = new NewSearch($this->elasticsearchConnection);
        $search->index($name);

        $this->searches[$name] = $search;

        return $search;
    }

    public function newQuery(string $index): NewQuery
    {
        $query = new NewQuery($this->elasticsearchConnection, $index);

        $this->searches[] = $query;

        return $query;
    }

    public function raw(string $name, string $index, array $query) {

        $this->rawQueries[$name] = [
            ['index' => $index],
            $query
        ];

        return $this;
    }

    public function query(string $index): NewQuery
    {
        return $this->newQuery($index);
    }

    public function get(): array
    {
        $body = [];

        foreach ($this->rawQueries as $name => $query) {
            $body = [
                ...$body,
                ...$query
            ];
        }

        foreach ($this->searches as $index => $search) {
            $body = [
                ...$body,
                ...$search->toMultiSearch()
            ];
        }

        $response = $this->msearchAPICall($body);

        if ($response->failed()) {
            throw new ElasticsearchException($response->json(), $response->code());
        }

        $responses = $response->json()['responses'] ?? [];
        $results = [];
        $responseIndex = 0;

        // Handle raw queries first
        foreach ($this->rawQueries as $name => $query) {
            $results[$name] = $responses[$responseIndex] ?? [];
            $responseIndex += 1;
        }

        // Handle MultiSearchable objects
        foreach ($this->searches as $index => $search) {
            $searchResponses = array_slice($responses, $responseIndex, $search->multisearchResCount());
            $results[$search->searchName] = $search->sliceMultiSearchResponse($searchResponses);
            $responseIndex += $search->multisearchResCount();
        }

        return $results;
    }
}
