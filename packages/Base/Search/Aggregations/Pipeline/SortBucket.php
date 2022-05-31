<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Pipeline;

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
        $raw = [$this->name => [
            $this->type => [
                'sort' => [
                    $this->path => ['order' => $this->order]
                ]
            ]
        ]];

        return $raw;
    }
}
