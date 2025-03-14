<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\Type as AbstractType;

class DenseVector extends AbstractType implements Type
{
    public function __construct(
        public string $name,
        protected int $dims = 384
    ) {
        $this->type = 'dense_vector';
    }

    public function toRaw(): array
    {
        return [
            $this->name => [
                'type' => $this->type,
                'dims' => $this->dims,
            ]
        ];
    }
}
