<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\ElasticsearchMappingType;
use Sigmie\Mappings\Traits\HasFacets;
use Sigmie\Query\Aggs;

class Number extends Type
{
    use HasFacets;

    protected int|float|null $scalingFactor = null;

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

    public function scaledFloat(int|float $scalingFactor = 100): self
    {
        $this->type = ElasticsearchMappingType::SCALED_FLOAT->value;
        $this->scalingFactor = $scalingFactor;

        return $this;
    }

    public function toRaw(): array
    {
        $raw = parent::toRaw();

        if ($this->type === ElasticsearchMappingType::SCALED_FLOAT->value) {
            $raw[$this->name]['scaling_factor'] = $this->scalingFactor ?? 100;
        }

        return $raw;
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
        return $aggregation[$this->name()] ?? [];
    }

    public function validate(string $key, mixed $value): array
    {
        if (! $this->ignoresMalformedScalar($value) && ! self::fitsMapping($this->type, $value, (bool) ($this->storedMapping['coerce'] ?? true))) {
            return [false, sprintf('The field %s mapped as %s must be a number', $key, $this->typeName())];
        }

        return [true, ''];
    }

    public static function fitsMapping(string $type, mixed $value, bool $coerce = true): bool
    {
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            return false;
        }

        if (! $coerce && (is_string($value) || (is_float($value) && in_array($type, ['integer', 'long'], true) && floor($value) !== $value))) {
            return false;
        }

        return match ($type) {
            'integer' => $value >= -2147483648 && $value <= 2147483647,
            'float' => is_finite(unpack('f', pack('f', (float) $value))[1]),
            'long' => self::fitsLong($value),
            default => true,
        };
    }

    private static function fitsLong(int|float|string $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        if (is_string($value) && trim($value) !== $value) {
            return false;
        }

        $magnitude = abs((float) $value);
        if (! is_string($value) || $magnitude !== 2.0 ** 63) {
            return $magnitude <= 2.0 ** 63;
        }

        // Doubles round both limits to 2^63. Compare the 19 integral decimal digits.
        $mantissa = preg_split('/e/i', $value, 2)[0];
        $digits = ltrim(str_replace('.', '', $mantissa), '+-0');
        $integer = substr(str_pad($digits, 19, '0'), 0, 19);

        return strcmp($integer, $value[0] === '-' ? '9223372036854775808' : '9223372036854775807') <= 0;
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
