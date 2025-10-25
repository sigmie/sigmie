<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Base\APIs\MSearch;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Query\NewQuery;
use Sigmie\Search\Contracts\MultiSearchable;
use Sigmie\Shared\UsesApis;

use function Sigmie\Functions\random_name;

class NewMultiSearch
{
    use MSearch;
    use UsesApis;

    /** @var array Ordered list of all queries (MultiSearchable objects and raw arrays) */
    protected array $queries = [];

    /** @var array Names associated with each query (by index) */
    protected array $names = [];

    public function __construct(
        protected ElasticsearchConnection $elasticsearchConnection
    ) {
    }

    public function newSearch(string $index, ?string $name = null): NewSearch
    {
        $search = (new NewSearch($this->elasticsearchConnection))
            ->index($index)
            ->apis($this->apis);

        $this->queries[] = $search;
        $this->names[count($this->queries) - 1] = $name ?? random_name('srch');

        return $search;
    }

    public function add(NewSearch $search, ?string $name = null): NewSearch
    {
        $this->queries[] = $search;
        $this->names[count($this->queries) - 1] = $name ?? random_name('srch');

        return $search;
    }

    public function newQuery(string $index, ?string $name = null): NewQuery
    {
        $query = new NewQuery($this->elasticsearchConnection);
        $query = $query->index($index);

        $this->queries[] = $query;
        $this->names[count($this->queries) - 1] = $name ?? random_name('srch');

        return $query;
    }

    public function raw(string $index, array $query, ?string $name = null): static
    {
        $this->queries[] = [
            ['index' => $index],
            $query
        ];
        $this->names[count($this->queries) - 1] = $name ?? random_name('srch');

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

    public function hits(): array
    {
        $results = $this->get();

        $allHits = array_map(function ($result) {
            // Handle raw query results (arrays) vs formatted results (objects with hits() method)
            if (is_array($result)) {
                return $result['hits']['hits'] ?? [];
            }

            return $result->hits();
        }, $results);

        return array_merge(...$allHits);
    }

    /**
     * Execute search and get hits grouped by name
     */
    public function groupedHits(): array
    {
        $results = $this->get();
        $grouped = [];

        foreach ($results as $index => $result) {
            $name = $this->names[$index] ?? random_name('srch');
            $hits = is_array($result)
                ? ($result['hits']['hits'] ?? [])
                : $result->hits();

            $grouped[$name] = $hits;
        }

        return $grouped;
    }
}
