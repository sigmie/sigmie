<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Closure;
use Generator;
use Sigmie\Base\APIs\MSearch;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Document\Hit;
use Sigmie\Query\NewQuery;
use Sigmie\Search\Contracts\LazyIterableQuery;
use Sigmie\Search\Contracts\MultiSearchable;
use Sigmie\Shared\UsesApis;

use function Sigmie\Functions\random_name;

class NewMultiSearch
{
    use MSearch;
    use UsesApis;

    /** @var list<MultiSearchable> */
    protected array $queries = [];

    /** @var array Names associated with each query (by index) */
    protected array $names = [];

    /** @var int HTTP response code from the last _msearch call */
    protected int $responseCode = 200;

    public function __construct(
        protected ElasticsearchConnection $elasticsearchConnection
    ) {}

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

    public function raw(string $index, array $query, ?string $name = null): RawQuery
    {
        $raw = new RawQuery($this->elasticsearchConnection, $index, $query);
        $this->queries[] = $raw;
        $this->names[count($this->queries) - 1] = $name ?? random_name('srch');

        return $raw;
    }

    public function get(): array
    {
        $body = [];

        foreach ($this->queries as $query) {
            $body = [
                ...$body,
                ...$query->toMultiSearch(),
            ];
        }

        $response = $this->msearchAPICall($body);

        if ($response->failed()) {
            throw new ElasticsearchException($response->json(), $response->code());
        }

        $this->responseCode = $response->code();

        $responses = $response->json('responses') ?? [];
        $results = [];
        $responseIndex = 0;

        foreach ($this->queries as $query) {
            $searchResponses = array_slice($responses, $responseIndex, $query->multisearchResCount());

            $result = $query->formatResponses(...$searchResponses, httpCode: $this->responseCode);

            $results[] = $result;
            $responseIndex += $query->multisearchResCount();
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

    /**
     * Get the HTTP response code from the last _msearch call
     */
    public function responseCode(): int
    {
        return $this->responseCode;
    }

    /**
     * @return Generator<int, Hit>
     */
    public function lazy(): Generator
    {
        foreach ($this->queries as $query) {
            if (! $query instanceof LazyIterableQuery) {
                continue;
            }

            foreach ($query->lazy() as $hit) {
                yield $hit;
            }
        }
    }

    public function each(Closure $fn): void
    {
        foreach ($this->queries as $query) {
            if (! $query instanceof LazyIterableQuery) {
                continue;
            }

            foreach ($query->lazy() as $hit) {
                $fn($hit);
            }
        }
    }
}
