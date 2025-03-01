<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\Type as AbstractType;
use Sigmie\Sigmie;
use Sigmie\Enums\ElasticsearchVersion as Version;

class DenseVector extends AbstractType implements Type
{
    public function __construct(
        public string $name,
        protected int $dims = 384
    ) {
        $this->type = match (Sigmie::$version) {
            Version::v7 => 'elastiknn_dense_float_vector',
            Version::v8 => 'dense_vector',
        };
    }

    public function queries(string $queryString): array
    {
        return [];
    }

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
        return match (Sigmie::$version) {
            Version::v7 => [
                $this->name => [
                    'type' => $this->type,
                    'elastiknn' => [
                        'dims' => $this->dims,
                        'model' => 'exact',
                    ]
                ]
            ],
            Version::v8 => [
                $this->name => [
                    'type' => $this->type,
                ]
            ]
        };
    }
}
