<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

use Sigmie\Query\Shared\Missing;

class Terms extends Bucket
{
    use Missing;

    protected int $size;

    protected array $order = [];

    public function __construct(
        protected string $name,
        protected string $field,
    ) {}

    public function size(int $size)
    {
        $this->size = $size;
    }

    public function order(string $subaggregation, string $direction): self
    {
        $this->order = ['order' => [
            $subaggregation => $direction,
        ]];

        return $this;
    }

    public function value(): array
    {
        $value = [
            'terms' => [
                'field' => $this->field,
                ...$this->order,
            ],
        ];

        if ($this->size ?? false) {
            $value['terms']['size'] = $this->size;
        }

        if (isset($this->missing)) {
            $value['terms']['missing'] = $this->missing;
        }

        return $value;
    }
}
