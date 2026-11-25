<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Pipeline;

use function Sigmie\Functions\object_map;

class SortBucket extends Pipeline
{
    public function __construct(
        protected string $name,
        protected string $path,
        protected string $order
    ) {
        parent::__construct($this->name, 'bucket_sort', $this->path);
    }

    public function toRaw(): array
    {
        $sort = [
            $this->path => ['order' => $this->order],
        ];

        $raw = [$this->name => [
            $this->type => [
                'sort' => object_map($sort),
            ],
        ]];

        if ($this->meta !== []) {
            $raw[$this->name]['meta'] = object_map($this->meta);
        }

        return $raw;
    }
}
