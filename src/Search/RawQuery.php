<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Closure;
use Generator;
use InvalidArgumentException;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\PointInTimeRequests;
use Sigmie\Document\Hit;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Query\Search;
use Sigmie\Search\Contracts\LazyIterableQuery;
use Sigmie\Search\Contracts\MultiSearchable;
use stdClass;

class RawQuery implements LazyIterableQuery, MultiSearchable
{
    private int $pitIterationChunkSize = 500;

    public function __construct(
        private ElasticsearchConnection $httpConnection,
        private string $index,
        private array $body,
    ) {
        $this->body = Search::normalizeBody($body);
    }

    public function toMultiSearch(): array
    {
        return [
            isset($this->body['pit']) ? new stdClass : ['index' => $this->index],
            $this->body,
        ];
    }

    public function multisearchResCount(): int
    {
        return 1;
    }

    public function formatResponses(...$responses): mixed
    {
        return $responses[0] ?? [];
    }

    public function chunk(int $size = 500): static
    {
        $this->pitIterationChunkSize = $size;

        return $this;
    }

    /**
     * @return Generator<int, Hit>
     */
    public function lazy(): Generator
    {
        yield from $this->iterateHits();
    }

    public function each(Closure $fn): void
    {
        foreach ($this->iterateHits() as $hit) {
            $fn($hit);
        }
    }

    /**
     * @return Generator<int, Hit>
     */
    private function iterateHits(): Generator
    {
        $pit = new PointInTimeRequests($this->httpConnection);
        $isOpenSearch = $this->httpConnection->driver()->engine() === SearchEngineType::OpenSearch;

        $body = $this->body;

        $hasCollapse = array_key_exists('collapse', $this->body);
        $sort = $this->body['sort'] ?? null;
        $userSort = match (true) {
            is_string($sort) && $sort !== '' => [$sort],
            $sort instanceof stdClass => [$sort],
            is_array($sort) => $sort,
            default => [],
        };

        unset(
            $body['from'],
            $body['size'],
            $body['aggs'],
            $body['suggest'],
            $body['track_total_hits'],
            $body['sort'],
        );

        $body['size'] = $this->pitIterationChunkSize;
        $body['sort'] = PitSortPlanner::plan($userSort, $isOpenSearch, $hasCollapse, preserveSingleSort: true);

        $ownsSnapshot = ! isset($body['pit']);
        if ($ownsSnapshot) {
            $keepAlive = '1m';
            $pitId = PointInTimeIterator::pitIdFromOpenResponse($pit->open($this->index, $keepAlive), $isOpenSearch);
        } else {
            $snapshot = (array) $body['pit'];
            $keepAlive = $snapshot['keep_alive'] ?? null;
            $pitId = $snapshot['id'] ?? throw new InvalidArgumentException('A supplied point in time requires an id.');
        }

        yield from PointInTimeIterator::iterate(
            $pitId,
            $keepAlive,
            $body,
            fn (array $requestBody): ElasticsearchResponse => $pit->search($requestBody),
            function (string $id) use ($pit, $ownsSnapshot): void {
                if ($ownsSnapshot) {
                    $pit->close($id);
                }
            },
            Hit::fromRaw(...),
        );
    }
}
