<?php

declare(strict_types=1);

namespace Sigmie\Document;

use Closure;
use Iterator;
use Sigmie\Base\APIs\Count;
use Sigmie\Document\Actions as DocumentActions;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Search\PointInTimeIterator;

trait LazyEach
{
    use Count;
    use DocumentActions;

    protected int $chunk = 500;

    public function chunk(int $size): self
    {
        $this->chunk = $size;

        return $this;
    }

    public function each(Closure $fn): self
    {
        foreach ($this->indexGenerator() as $key => $value) {
            $fn($value, $key);
        }

        return $this;
    }

    protected function indexGenerator(): Iterator
    {
        $isOpenSearch = $this->elasticsearchConnection->driver()->engine() === SearchEngineType::OpenSearch;

        $sort = $isOpenSearch ? [['_id' => 'asc']] : [['_shard_doc' => 'asc']];

        $baseBody = [
            'size' => $this->chunk,
            'sort' => $sort,
            'query' => ['match_all' => (object) []],
        ];

        if ($this->only || $this->except) {
            $baseBody['_source'] = [];

            if ($this->only) {
                $baseBody['_source']['includes'] = $this->only;
            }

            if ($this->except) {
                $baseBody['_source']['excludes'] = $this->except;
            }
        }

        $keepAlive = '1m';

        $open = $this->openPointInTimeAPICall($this->name, $keepAlive);
        $pitId = PointInTimeIterator::pitIdFromOpenResponse($open, $isOpenSearch);

        foreach (PointInTimeIterator::iterate(
            $pitId,
            $keepAlive,
            $baseBody,
            fn (array $body) => $this->pitSearchAPICall($body),
            function (string $id): void {
                $this->closePointInTimeAPICall($id);
            },
            fn (array $data): Hit => new Hit(
                $data['_source'] ?? [],
                $data['_id'],
                isset($data['_score']) ? (float) $data['_score'] : null,
                $data['_index'] ?? null,
                $data['sort'] ?? null,
            ),
        ) as $hit) {
            yield $hit->_id => $hit;
        }
    }
}
