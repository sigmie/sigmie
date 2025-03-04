<?php

declare(strict_types=1);

namespace Sigmie\Plugins\Elastiknn;

use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\Type as AbstractType;

class DenseFloatVector extends AbstractType implements Type
{
    public function __construct(
        public string $name,
        protected int $dims = 384
    ) {
        $this->type = 'elastiknn_dense_float_vector';
    }

    public function toRaw(): array
    {
        return [
            $this->name => [
                'type' => $this->type,
                'elastiknn' => [
                    'dims' => $this->dims,
                    'model' => 'exact',
                ]
            ]
        ];
    }
}
