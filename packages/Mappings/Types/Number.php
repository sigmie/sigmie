<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\ElasticsearchMappingType;
use Sigmie\Query\Queries\Term\Term;

class Number extends Type
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->type = ElasticsearchMappingType::INTEGER->value;
    }

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

    public function scaledFloat(): self
    {
        $this->type = ElasticsearchMappingType::SCALED_FLOAT->value;

        return $this;
    }

    public function long(): self
    {
        $this->type = ElasticsearchMappingType::LONG->value;

        return $this;
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        $queries[] = new Term($this->name, $queryString);

        return $queries;
    }
}
