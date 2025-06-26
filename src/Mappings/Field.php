<?php

declare(strict_types=1);

namespace Sigmie\Mappings;

use Sigmie\Mappings\Types\Type;

class Field extends Type
{
    public function __construct(
        string $name,
        protected string $type,
        protected array $options = [],
    ) {
        parent::__construct($name);
    }

    public function queries(array|string $queryString): array
    {
        return [];
    }

    public function name(): string
    {
        return $this->name;
    }

    public function toRaw(): array
    {
        return [$this->name => [
            'type' => $this->type,
            ...$this->options,
        ]];
    }
}
