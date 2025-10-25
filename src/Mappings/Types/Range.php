<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\ElasticsearchMappingType;
use Sigmie\Query\Aggs;

class Range extends Type
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->type = ElasticsearchMappingType::INTEGER_RANGE->value;
    }

    public function integer(): self
    {
        $this->type = ElasticsearchMappingType::INTEGER_RANGE->value;

        return $this;
    }

    public function float(): self
    {
        $this->type = ElasticsearchMappingType::FLOAT_RANGE->value;

        return $this;
    }

    public function long(): self
    {
        $this->type = ElasticsearchMappingType::LONG_RANGE->value;

        return $this;
    }

    public function double(): self
    {
        $this->type = ElasticsearchMappingType::DOUBLE_RANGE->value;

        return $this;
    }

    public function date(): self
    {
        $this->type = ElasticsearchMappingType::DATE_RANGE->value;

        return $this;
    }

    public function ip(): self
    {
        $this->type = ElasticsearchMappingType::IP_RANGE->value;

        return $this;
    }

    public function aggregation(Aggs $aggs, string $param): void
    {
        // Range fields typically don't support standard aggregations
        // but we can add range aggregations if needed
    }

    public function isFacetable(): bool
    {
        return false;
    }

    public function facets(array $aggregation): ?array
    {
        return null;
    }

    public function validate(string $key, mixed $value): array
    {
        if (!is_array($value)) {
            return [false, sprintf("The field %s mapped as %s must be an array with 'gte', 'gt', 'lte', or 'lt' keys", $key, $this->typeName())];
        }

        $validKeys = ['gte', 'gt', 'lte', 'lt'];
        $hasValidKeys = false;

        foreach ($validKeys as $validKey) {
            if (array_key_exists($validKey, $value)) {
                $hasValidKeys = true;
                break;
            }
        }

        if (!$hasValidKeys) {
            return [false, sprintf('The field %s mapped as %s must contain at least one of: ', $key, $this->typeName()) . implode(', ', $validKeys)];
        }

        return [true, ''];
    }

    public function typeName(): string
    {
        return match ($this->type) {
            ElasticsearchMappingType::INTEGER_RANGE->value => 'integer_range',
            ElasticsearchMappingType::FLOAT_RANGE->value => 'float_range',
            ElasticsearchMappingType::LONG_RANGE->value => 'long_range',
            ElasticsearchMappingType::DOUBLE_RANGE->value => 'double_range',
            ElasticsearchMappingType::DATE_RANGE->value => 'date_range',
            ElasticsearchMappingType::IP_RANGE->value => 'ip_range',
            default => 'range',
        };
    }
}
