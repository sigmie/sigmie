<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Closure;
use Generator;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\PointInTimeRequests;
use Sigmie\Document\Hit;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Search\Contracts\LazyIterableQuery;
use Sigmie\Search\Contracts\MultiSearchable;

final class RawQuery implements LazyIterableQuery, MultiSearchable
{
    private int $pitIterationChunkSize = 500;

    public function __construct(
        private ElasticsearchConnection $httpConnection,
        private string $index,
        private array $body,
    ) {}

    public function toMultiSearch(): array
    {
        return [
            ['index' => $this->index],
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

        $userSort = is_array($this->body['sort'] ?? null) ? $this->body['sort'] : [];

        unset(
            $body['from'],
            $body['size'],
            $body['aggs'],
            $body['highlight'],
            $body['suggest'],
            $body['track_total_hits'],
            $body['sort'],
            $body['post_filter'],
        );

        $body['size'] = $this->pitIterationChunkSize;
        $body['sort'] = PitSortPlanner::plan($userSort, $isOpenSearch);

        $keepAlive = '1m';
        $open = $pit->open($this->index, $keepAlive);
        $pitId = PointInTimeIterator::pitIdFromOpenResponse($open, $isOpenSearch);

        yield from PointInTimeIterator::iterate(
            $pitId,
            $keepAlive,
            $body,
            fn (array $requestBody): ElasticsearchResponse => $pit->search($requestBody),
            function (string $id) use ($pit): void {
                $pit->close($id);
            },
            fn (array $data): Hit => new Hit(
                $data['_source'] ?? [],
                $data['_id'],
                isset($data['_score']) ? (float) $data['_score'] : null,
                $data['_index'] ?? null,
                $data['sort'] ?? null,
            ),
        );
    }
}
