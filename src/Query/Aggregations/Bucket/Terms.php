<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

use Sigmie\Query\Shared\Missing;
use Sigmie\Query\Shared\OrdersBuckets;

class Terms extends Bucket
{
    use Missing;
    use OrdersBuckets;

    protected int $size;

    protected ?int $minDocCount = null;

    protected array|string|null $exclude = null;

    public function __construct(
        protected string $name,
        protected string $field,
    ) {}

    public function size(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function minDocCount(int $minDocCount): static
    {
        $this->minDocCount = $minDocCount;

        return $this;
    }

    public function exclude(array|string $terms): self
    {
        $this->exclude = is_array($terms) ? array_values($terms) : $terms;

        return $this;
    }

    protected function value(): array
    {
        $value = [
            'terms' => [
                'field' => $this->field,
                ...$this->order,
            ],
        ];

        if (isset($this->size)) {
            $value['terms']['size'] = $this->size;
        }

        if ($this->minDocCount !== null) {
            $value['terms']['min_doc_count'] = $this->minDocCount;
        }

        if (isset($this->missing)) {
            $value['terms']['missing'] = $this->missing;
        }

        if ($this->exclude !== null && $this->exclude !== []) {
            $value['terms']['exclude'] = $this->exclude;
        }

        return $value;
    }
}
