<?php

declare(strict_types=1);

namespace Sigmie\Document;

use Closure;
use Iterator;
use Sigmie\Base\APIs\Count;
use Sigmie\Document\Actions as DocumentActions;

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
        $page = 1;
        $total = (int) $this->countAPICall($this->name)->json('count');

        // Initial scroll request
        $body = [
            'size' => $this->chunk,
            // Return documents in the order they are stored internally in the index, per shard.
            // It is the most efficient sort, especially for large scans or scrolls.
            'sort' => [['_doc' => 'asc']],
            'query' => ['match_all' => (object) []],
        ];

        if ($this->only || $this->except) {

            $body['_source'] = [];

            if ($this->only) {
                $body['_source']['includes'] = $this->only;
            }

            if ($this->except) {
                $body['_source']['excludes'] = $this->except;
            }
        }

        $response = $this->searchAPICall(index: $this->name, query: $body, scroll: '1m');

        $scrollId = $response->json('_scroll_id');

        foreach ($response->json('hits')['hits'] as $data) {
            yield $data['_id'] => new Document($data['_source'], $data['_id']);
        }

        while ($this->chunk * $page < $total) {
            $page++;

            yield from $this->pageGenerator($scrollId);
        }
    }

    protected function pageGenerator(string $scrollId): Iterator
    {
        // Continue scrolling with scroll_id
        $response = $this->scrollAPICall($scrollId, '1m');

        $values = $response->json('hits')['hits'];

        foreach ($values as $data) {
            yield $data['_id'] => new Document($data['_source'], $data['_id']);
        }
    }
}
