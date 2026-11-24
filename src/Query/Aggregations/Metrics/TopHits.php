<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Metrics;

use Sigmie\Query\Aggregations\Bucket\Bucket;
use Sigmie\Query\Shared\Missing;

use function Sigmie\Functions\sort_clauses;

class TopHits extends Bucket
{
    use Missing;

    public function __construct(
        protected string $name,
        protected ?array $sort = null,
        protected ?array $_sources = null,
        protected int $size = 1,
        protected array $sourceExcludes = [],
    ) {
        parent::__construct($name);
    }

    protected function value(): array
    {
        $topHits = [
            'size' => $this->size,
        ];

        if ($this->_sources !== null) {
            $topHits['_source'] = [
                'includes' => array_values($this->_sources),
            ];
        }

        if ($this->sourceExcludes !== []) {
            $topHits['_source']['excludes'] = array_values($this->sourceExcludes);
        }

        if ($this->sort !== null) {
            $topHits['sort'] = sort_clauses($this->sort);
        }

        return [
            'top_hits' => $topHits,
        ];
    }
}
