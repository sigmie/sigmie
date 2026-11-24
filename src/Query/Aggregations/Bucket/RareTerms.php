<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

use Sigmie\Query\Shared\Missing;

class RareTerms extends Bucket
{
    use Missing;

    protected int $maxDocCount;

    public function __construct(
        protected string $name,
        protected string $field,
    ) {}

    public function maxDocCount(int $count): static
    {
        $this->maxDocCount = $count;

        return $this;
    }

    protected function value(): array
    {
        $value = [
            'rare_terms' => [
                'field' => $this->field,
            ],
        ];

        if (isset($this->maxDocCount)) {
            $value['rare_terms']['max_doc_count'] = $this->maxDocCount;
        }

        if (isset($this->missing)) {
            $value['rare_terms']['missing'] = $this->missing;
        }

        return $value;
    }
}
