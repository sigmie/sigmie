<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Mappings\ElasticsearchMappingType;
use Sigmie\Query\Aggs;

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

    public function double(): self
    {
        $this->type = ElasticsearchMappingType::DOUBLE->value;

        return $this;
    }

    public function queries(array|string $queryString): array
    {
        $queries = [];

        // $queries[] = new Term($this->name, $queryString);

        return $queries;
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
        $originalBuckets = $aggregation[$this->name()][$this->name()] ?? $aggregation[$this->name()] ?? [];

        return $originalBuckets;
    }

    public function validate(string $key, mixed $value): array
    {
        if (! is_numeric($value)) {
            return [false, "The field {$key} mapped as {$this->typeName()} must be a number"];
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
