<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

use function Sigmie\Functions\sort_clauses;

class Sort extends Bucket
{
    public function __construct(
        protected string $name,
        protected array $sort,
        protected ?int $size = null,
        protected ?int $from = null,
    ) {
        parent::__construct($name);
    }

    protected function value(): array
    {
        $res = [
            'bucket_sort' => [
                'sort' => sort_clauses($this->sort),
            ],
        ];

        if ($this->size !== null) {
            $res['bucket_sort']['size'] = $this->size;
        }

        if ($this->from !== null) {
            $res['bucket_sort']['from'] = $this->from;
        }

        return $res;
    }
}
