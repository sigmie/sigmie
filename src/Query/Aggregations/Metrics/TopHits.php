<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Metrics;

use Sigmie\Query\Aggregations\Bucket\Bucket;
use Sigmie\Query\Shared\Missing;

class TopHits extends Bucket
{
    use Missing;

    public function __construct(
        protected string $name,
        protected ?array $sort = null,
        protected ?array $_sources = null,
        protected int $size = 1
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
                'includes' => $this->_sources,
            ];
        }

        if ($this->sort !== null) {
            $topHits['sort'] = $this->sort;
        }

        return [
            'top_hits' => $topHits,
        ];
    }
}
