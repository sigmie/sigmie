<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Aggregations\Bucket;

use Sigmie\Base\Shared\Missing;

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
