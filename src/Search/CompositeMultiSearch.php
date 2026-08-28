<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Closure;
use InvalidArgumentException;
use Sigmie\Base\APIs\MSearch;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Search\Contracts\MultiSearchable;
use Sigmie\Shared\UsesApis;

class CompositeMultiSearch
{
    use MSearch;
    use UsesApis;

    /**
     * @var array<string, MultiSearchable>
     */
    protected array $searches = [];

    public function __construct(
        protected ElasticsearchConnection $elasticsearchConnection,
        protected string $aggregation,
        protected array $sources,
        protected int $size = 10000,
    ) {}

    public function search(string $name, MultiSearchable $search): static
    {
        $this->searches[$name] = $search;

        return $this;
    }

    /**
     * @return array<string, list<array>>
     */
    public function buckets(): array
    {
        $buckets = [];
        $afterKeys = [];

        foreach (array_keys($this->searches) as $name) {
            $buckets[$name] = [];
            $afterKeys[$name] = null;
        }

        $activeNames = array_keys($this->searches);

        do {
            $responses = $this->responses($activeNames, $afterKeys);

            foreach ($activeNames as $index => $name) {
                $aggregation = $responses[$index]['aggregations'][$this->aggregation] ?? [];
                $buckets[$name] = [
                    ...$buckets[$name],
                    ...($aggregation['buckets'] ?? []),
                ];
                $afterKeys[$name] = $aggregation['after_key'] ?? null;
            }

            $activeNames = array_values(array_filter(
                $activeNames,
                fn (string $name): bool => $afterKeys[$name] !== null,
            ));
        } while ($activeNames !== []);

        return $buckets;
    }

    public function each(Closure $callback): void
    {
        foreach ($this->buckets() as $name => $buckets) {
            foreach ($buckets as $bucket) {
                $callback($bucket, $name);
            }
        }
    }

    /**
     * @param  list<string>  $activeNames
     * @param  array<string, array|null>  $afterKeys
     * @return list<array>
     */
    protected function responses(array $activeNames, array $afterKeys): array
    {
        $body = [];

        foreach ($activeNames as $name) {
            $body = [
                ...$body,
                ...$this->toCompositeMultiSearch($this->searches[$name], $afterKeys[$name]),
            ];
        }

        $response = $this->msearchAPICall($body);

        // @codeCoverageIgnoreStart
        if ($response->failed()) {
            throw new ElasticsearchException($response->json(), $response->code());
        }

        // @codeCoverageIgnoreEnd

        return $response->json('responses') ?? [];
    }

    /**
     * @return array{0: array, 1: array}
     */
    protected function toCompositeMultiSearch(MultiSearchable $search, ?array $after): array
    {
        if ($search->multisearchResCount() !== 1) {
            throw new InvalidArgumentException('Composite multi-search only supports searches with one response.');
        }

        $multiSearch = $search->toMultiSearch();
        $header = $multiSearch[0] ?? throw new InvalidArgumentException('Composite multi-search is missing a search header.');
        $body = $multiSearch[1] ?? throw new InvalidArgumentException('Composite multi-search is missing a search body.');

        $body['aggs'][$this->aggregation] = [
            'composite' => [
                'sources' => $this->sources,
                'size' => $this->size,
            ],
        ];

        if ($after !== null) {
            $body['aggs'][$this->aggregation]['composite']['after'] = (object) $after;
        }

        return [$header, $body];
    }
}
