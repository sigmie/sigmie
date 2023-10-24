<?php

declare(strict_types=1);

namespace Sigmie\Mappings;

class Field
{
    public function __construct(
        protected string $name,
        protected string $type
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function values(): array
    {
        return [
            'type' => $this->type,
        ];
    }
}
