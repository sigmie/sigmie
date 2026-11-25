<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

use Sigmie\Query\Shared\OrdersBuckets;

class MultiTerms extends Bucket
{
    use OrdersBuckets;

    protected int $size;

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

    protected function value(): array
    {
        $value = [
            'multi_terms' => [
                'terms' => array_map(
                    fn (string $field): array => ['field' => $field],
                    array_values($this->fields),
                ),
                ...$this->order,
            ],
        ];

        if (isset($this->size)) {
            $value['multi_terms']['size'] = $this->size;
        }

        return $value;
    }
}
