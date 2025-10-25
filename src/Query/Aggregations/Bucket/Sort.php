<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

class Sort extends Bucket
{
    public function __construct(
        protected string $name,
        protected array $sort,
        protected ?int $size = null,
        protected ?int $form = null,
    ) {
        parent::__construct($name);
    }

    protected function value(): array
    {
        $res = [
            'bucket_sort' => [
                'sort' => $this->sort,
            ],
        ];

        if ($this->size) {
            $res['bucket_sort']['size'] = $this->size;
        }

        if ($this->form) {
            $res['bucket_sort']['from'] = $this->form;
        }

        return $res;
    }
}
