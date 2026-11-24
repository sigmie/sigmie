<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Sigmie\Mappings\DateBound;
use Sigmie\Mappings\ElasticsearchMappingType;
use Sigmie\Mappings\Traits\HasFacets;
use Sigmie\Query\Aggs;
use stdClass;

use function Sigmie\Functions\format_date_value;
use function Sigmie\Functions\packed_ip_address;

class Range extends Type
{
    use HasFacets;

    public function __construct(string $name, protected ?string $dateFormat = null)
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

    public function toRaw(): array
    {
        $raw = parent::toRaw();

        if ($this->type === ElasticsearchMappingType::DATE_RANGE->value && $this->dateFormat !== null) {
            $raw[$this->name]['format'] = $this->dateFormat;
        }

        return $raw;
    }

    public function formatDateBound(DateTimeInterface $value): int|string
    {
        return format_date_value($value, explode('||', $this->dateFormat ?? ''), 'Y-m-d');
    }

    public function aggregation(Aggs $aggs, string $param): void
    {
        // Range fields typically don't support standard aggregations
        // but we can add range aggregations if needed
    }

    public function sortableName(): ?string
    {
        return null;
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
        if ($this->type === ElasticsearchMappingType::IP_RANGE->value && is_string($value)) {
            [$address, $prefix] = array_pad(explode('/', $value, 2), 2, '');
            if (packed_ip_address($address) !== null && ctype_digit($prefix)
                && (int) $prefix <= (str_contains($address, ':') ? 128 : 32)) {
                return [true, ''];
            }
        }

        if ($value instanceof stdClass) {
            $value = (array) $value;
        }
        if (! is_array($value)) {
            return [false, sprintf("The field %s mapped as %s must be an array with 'gte', 'gt', 'lte', or 'lt' keys", $key, $this->typeName())];
        }

        $validKeys = ['gte', 'gt', 'lte', 'lt'];

        $keys = array_keys($value);
        if (array_intersect($keys, $validKeys) === []) {
            return [false, sprintf('The field %s mapped as %s must contain at least one of: ', $key, $this->typeName()).implode(', ', $validKeys)];
        }

        if (array_diff($keys, $validKeys) !== []) {
            return [false, sprintf('The field %s mapped as %s must contain only bounds named: ', $key, $this->typeName()).implode(', ', $validKeys)];
        }

        $type = substr($this->type, 0, -strlen('_range'));
        foreach ($value as $bound) {
            if ($bound === null) {
                continue;
            }

            $valid = match ($type) {
                'ip' => packed_ip_address($bound) !== null,
                'date' => DateBound::accepts($bound, $this->dateFormat ?? 'strict_date_optional_time||epoch_millis'),
                default => Number::fitsMapping($type, $bound, (bool) ($this->storedMapping['coerce'] ?? true)),
            };
            if (! $valid) {
                return [false, sprintf('The field %s has an invalid %s bound', $key, $this->typeName())];
            }
        }

        $hasInterval = match ($type) {
            'ip' => $this->hasIpInterval($value),
            'date' => $this->hasDateInterval($value),
            default => $this->hasNumericInterval($type, $value),
        };

        return $hasInterval ? [true, ''] : [false, sprintf('The field %s has an empty %s interval', $key, $this->typeName())];
    }

    private function hasDateInterval(array $bounds): bool
    {
        if ($this->dateFormat !== null && $this->dateFormat !== 'strict_date_optional_time||epoch_millis') {
            return true;
        }
        $milliseconds = [];
        foreach ($bounds as $operator => $bound) {
            if ($bound === null) {
                continue;
            }
            $text = is_float($bound) ? json_encode($bound) : (string) $bound;
            if (preg_match('/^-?\d{4}-\d{2}-\d{2}\z/', $text)) {
                $start = (new DateTimeImmutable($text, new DateTimeZone('UTC')))->getTimestamp() * 1000;
                // Elasticsearch rounds inclusive upper and exclusive lower calendar bounds up.
                $milliseconds[$operator] = in_array($operator, ['gt', 'lte'], true) ? $start + 86399999 : $start;
            } elseif (! preg_match('/^-?\d{4}\z/', $text) && preg_match('/^(-?\d+)(?:\.(\d*))?\z/', $text, $parts)) {
                $negativeFraction = str_starts_with($text, '-') && trim($parts[2] ?? '', '0') !== '';
                $milliseconds[$operator] = (int) $parts[1] - ($negativeFraction ? 1 : 0);
            } else {
                // Date math and partial ISO timestamps have engine-specific rounding.
                return true;
            }
        }

        return $this->hasNumericInterval('long', $milliseconds);
    }

    private function hasIpInterval(array $bounds): bool
    {
        $lower = str_repeat("\0", 16);
        $upper = str_repeat("\xff", 16);
        foreach ($bounds as $operator => $value) {
            if ($value === null) {
                continue;
            }
            $address = packed_ip_address($value);
            // Lucene sorts IPv4 addresses in IPv4-mapped IPv6 space.
            if (strlen($address) === 4) {
                $address = str_repeat("\0", 10)."\xff\xff".$address;
            }
            if ($operator === 'gt' || $operator === 'lt') {
                $step = $operator === 'gt' ? 1 : -1;
                for ($i = 15; $i >= 0; $i--) {
                    $next = ord($address[$i]) + $step;
                    $address[$i] = chr($next & 255);
                    if ($next >= 0 && $next <= 255) {
                        break;
                    }
                }
                if ($i < 0) {
                    return false;
                }
            }
            if ($operator === 'gte' || $operator === 'gt') {
                $lower = $address;
            } else {
                $upper = $address;
            }
        }

        return strcmp($lower, $upper) <= 0;
    }

    private function hasNumericInterval(string $type, array $bounds): bool
    {
        $lower = $upper = null;
        $lowerOpen = $upperOpen = false;
        $discrete = in_array($type, ['integer', 'long'], true);
        foreach ($bounds as $operator => $bound) {
            if ($bound === null) {
                continue;
            }
            $number = match ($type) {
                'integer', 'long' => (int) $bound,
                'float' => unpack('f', pack('f', (float) $bound))[1],
                default => (float) $bound,
            };
            if ($operator === 'gt' || $operator === 'gte') {
                $lower = $number;
                $lowerOpen = $operator === 'gt';
            } else {
                $upper = $number;
                $upperOpen = $operator === 'lt';
            }
        }

        if ($lower === null || $upper === null) {
            return true;
        }
        if ($lower === $upper) {
            return ! $lowerOpen && ! $upperOpen;
        }

        if ($lower >= $upper || ! ($lowerOpen && $upperOpen)) {
            return $lower < $upper;
        }
        if ($discrete) {
            return $lower + 1 !== $upper;
        }

        // IEEE-754 neighbors have consecutive bit patterns, reversed for negative values.
        [$format, $integer] = $type === 'float' ? ['G', 'N'] : ['E', 'J'];
        $bits = unpack($integer, pack($format, $lower))[1];
        $next = $lower == 0.0 ? 1 : $bits + ($lower < 0 ? -1 : 1);

        return unpack($format, pack($integer, $next))[1] < $upper;
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
