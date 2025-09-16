<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\AI\Contracts\Embedder;
use Sigmie\Base\APIs\MSearch;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Query\NewQuery;
use Sigmie\Search\Contracts\MultiSearchable;
use Sigmie\Search\MultiSearchResponse;

class NewMultiSearch
{
    use MSearch;

    /** @var array Ordered list of all queries (MultiSearchable objects and raw arrays) */
    protected array $queries = [];

    public function __construct(
        protected ElasticsearchConnection $elasticsearchConnection,
        protected ?Embedder $embedder = null
    ) {}

    public function newSearch(string $name): NewSearch
    {
        $search = new NewSearch($this->elasticsearchConnection, $this->embedder);
        $search->index($name);

        $this->queries[] = $search;

        return $search;
    }

    public function newQuery(string $index): NewQuery
    {
        $query = new NewQuery($this->elasticsearchConnection);
        $query = $query->index($index);

        $this->queries[] = $query;

        return $query;
    }

    public function raw(string $index, array $query)
    {
        $this->queries[] = [
            ['index' => $index],
            $query
        ];

        return $this;
    }

    public function get(): array
    {
        $body = [];

        // Build body in the order queries were added
        foreach ($this->queries as $query) {
            if ($query instanceof MultiSearchable) {
                $body = [
                    ...$body,
                    ...$query->toMultiSearch()
                ];
            } else {
                // Raw query (array format: [header, body])
                $body[] = $query[0]; // header
                $body[] = $query[1]; // body
            }
        }

        $response = $this->msearchAPICall($body);

        if ($response->failed()) {
            throw new ElasticsearchException($response->json(), $response->code());
        }

        $responses = $response->json('responses') ?? [];
        $results = [];
        $responseIndex = 0;

        // Process responses in the same order
        foreach ($this->queries as $query) {
            if ($query instanceof MultiSearchable) {
                $searchResponses = array_slice($responses, $responseIndex, $query->multisearchResCount());
                $results[] = $query->formatResponses(...$searchResponses);
                $responseIndex += $query->multisearchResCount();
            } else {
                // Raw query
                $results[] = $responses[$responseIndex] ?? [];
                $responseIndex += 1;
            }
        }

        return $results;
    }

    public function hits() {

        $results = $this->get();

        return array_map(fn($result) => $result->hits(), $results);
    }
}
