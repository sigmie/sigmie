<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\Contracts\Type;
use Sigmie\Query\Queries\Elastiknn\NearestNeighbors;
use Sigmie\Search\Contracts\EmbeddingsQueries;

class DenseVector implements EmbeddingsQueries, Type
{
    // protected string $type = 'dense_vector';
    protected string $type = 'elastiknn_dense_float_vector';

    public function __construct(
        public string $name,
        protected int $dims = 384
    ) {}

    public function type(): string
    {
        return $this->type;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function meta(array $meta): void {}

    public function toRaw(): array
    {
        return [
            $this->name => [
                'type' => $this->type,
                'elastiknn' => [
                    // 'type' => $this->type,
                    'dims' => $this->dims,
                    'model' => 'exact',
                ]
            ]
        ];
    }

    public function queries(array $embeddings): array
    {
        return [
            new NearestNeighbors(
                $this->name,
                $embeddings
            )
        ];
    }
}
