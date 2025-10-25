<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Pipeline;

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
        return [$this->name => [
            $this->type => [
                'sort' => [
                    $this->path => ['order' => $this->order],
                ],
            ],
        ]];
    }
}
