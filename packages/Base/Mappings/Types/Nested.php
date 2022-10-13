<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings\Types;

use Sigmie\Base\Mappings\Blueprint;
use Sigmie\Base\Mappings\PropertyType;

class Nested extends PropertyType
{
    public function __construct(
        protected string $name,
        protected Blueprint $blueprint = new Blueprint
    ) {
        parent::__construct($name);
    }

    public function toRaw(): array
    {
        return [$this->name => [
            'type' => 'nested',
            'properties' => ($this->blueprint)()->toRaw()
        ]];
    }

    public function properties(callable $callable): static
    {
        $this->blueprint = new Blueprint();

        $callable($this->blueprint);

        return $this;
    }
}
