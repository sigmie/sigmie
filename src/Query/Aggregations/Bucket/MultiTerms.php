<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

class MultiTerms extends Bucket
{
    protected int $size;

    protected array $order = [];

    /**
     * @param  list<string>  $fields
     */
    public function __construct(
        protected string $name,
        protected array $fields,
    ) {}

    public function size(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function order(string $subaggregation, string $direction): self
    {
        $this->order = ['order' => [
            $subaggregation => $direction,
        ]];

        return $this;
    }

    protected function value(): array
    {
        $value = [
            'multi_terms' => [
                'terms' => array_map(
                    fn (string $field): array => ['field' => $field],
                    $this->fields,
                ),
                ...$this->order,
            ],
        ];

        if ($this->size ?? false) {
            $value['multi_terms']['size'] = $this->size;
        }

        return $value;
    }
}
