<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\ElasticsearchMappingType;

class Number extends Type
{
    public function integer(): self
    {
        $this->type = ElasticsearchMappingType::INTEGER->value;

        return $this;
    }

    public function float(): self
    {
        $this->type = ElasticsearchMappingType::FLOAT->value;

        return $this;
    }

    public function long(): self
    {
        $this->type = ElasticsearchMappingType::LONG->value;

        return $this;
    }

    public function toRaw(): array
    {
        return [$this->name => [
            'type' => $this->type,
        ]];
    }
}
