<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

use Sigmie\Query\Shared\Missing;

class RareTerms extends Bucket
{
    use Missing;

    protected int $size;

    public function __construct(
        protected string $name,
        protected string $field,
    ) {
    }

    public function size(int $size)
    {
        $this->size = $size;
    }

    public function value(): array
    {
        $value = [
            'rare_terms' => [
                'field' => $this->field,
            ],
        ];

        if (isset($this->missing)) {
            $value['rare_terms']['missing'] = $this->missing;
        }

        return $value;
    }
}
