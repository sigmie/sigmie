<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\ElasticsearchMappingType;
use Sigmie\Mappings\Traits\HasFacets;
use Sigmie\Query\Aggs;

class Number extends Type
{
    use HasFacets;
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

    public function double(): self
    {
        $this->type = ElasticsearchMappingType::DOUBLE->value;

        return $this;
    }

    public function aggregation(Aggs $aggs, string $param): void
    {
        $aggs->stats($this->name(), $this->name());
    }

    public function isFacetable(): bool
    {
        return true;
    }

    public function facets(array $aggregation): ?array
    {
        return $aggregation[$this->name()][$this->name()] ?? $aggregation[$this->name()] ?? [];
    }

    public function validate(string $key, mixed $value): array
    {
        if (! is_numeric($value)) {
            return [false, sprintf('The field %s mapped as %s must be a number', $key, $this->typeName())];
        }

        return [true, ''];
    }

    public function typeName(): string
    {
        return match ($this->type) {
            ElasticsearchMappingType::INTEGER->value => 'integer',
            ElasticsearchMappingType::FLOAT->value => 'float',
            ElasticsearchMappingType::SCALED_FLOAT->value => 'scaled_float',
            ElasticsearchMappingType::LONG->value => 'long',
            ElasticsearchMappingType::DOUBLE->value => 'double',
            default => 'number',
        };
    }
}
