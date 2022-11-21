<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\Blueprint;

class Nested extends Type
{
    public function __construct(
        string $name,
        protected Blueprint $blueprint = new Blueprint
    ) {
        parent::__construct($name);
    }

    public function toRaw(): array
    {
        return [$this->name => [
            'type' => 'nested',
            'properties' => ($this->blueprint)()->toRaw(),
        ]];
    }

    public function properties(callable $callable): static
    {
        $this->blueprint = new Blueprint();

        $callable($this->blueprint);

        return $this;
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        return $queries;
    }
}
