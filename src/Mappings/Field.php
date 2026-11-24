<?php

declare(strict_types=1);

namespace Sigmie\Mappings;

use Sigmie\Mappings\Types\FlatObject;
use Sigmie\Mappings\Types\Type;
use stdClass;

use function Sigmie\Functions\mapping_field_maps;
use function Sigmie\Functions\merge_named;
use function Sigmie\Functions\packed_ip_address;

class Field extends Type
{
    public function __construct(
        string $name,
        protected string $type,
        protected array $options = [],
    ) {
        parent::__construct($name);
        $this->meta((array) ($options['meta'] ?? []));
    }

    public function validate(string $key, mixed $value): array
    {
        if ($this->type === 'ip' && packed_ip_address($value) === null
            && ! (($this->options['ignore_malformed'] ?? false) && is_scalar($value))) {
            return [false, sprintf('The field %s must be an IP address', $key)];
        }

        if ($this->type === 'geo_shape' && ! ($this->options['ignore_malformed'] ?? false) && ! $this->hasGeometry($value)) {
            return [false, sprintf('The field %s must contain a GeoJSON or WKT geometry', $key)];
        }

        if ($this->type === 'flattened' && ! FlatObject::acceptsFlattenedValue($value, $this->options)) {
            return [false, sprintf('The field %s does not fit its flattened mapping', $key)];
        }

        return parent::validate($key, $value);
    }

    private function hasGeometry(mixed $value): bool
    {
        if (is_string($value)) {
            if (preg_match('/^\s*point\b/i', $value)) {
                if (preg_match('/^\s*point\s+empty\s*\z/i', $value)) {
                    return true;
                }
                if (! preg_match('/^\s*point(?:\s+(z))?\s*\(([^()]*)\)\s*\z/i', $value, $parts)) {
                    return false;
                }
                $coordinates = preg_split('/\s+/', trim($parts[2]));
                return ($parts[1] === '' || count($coordinates) === 3)
                    && count(array_filter($coordinates, is_numeric(...))) === count($coordinates)
                    && $this->hasCoordinates(array_map(floatval(...), $coordinates), 0);
            }
            return preg_match('/^\s*(?:multipoint|linestring|multilinestring|polygon|multipolygon|bbox|geometrycollection)\s*(?:z\s*)?(?:empty|\(.*\))\s*\z/is', $value) === 1;
        }
        if (! is_array($value) && ! $value instanceof stdClass) {
            return false;
        }
        $shape = (array) $value;
        $type = $shape['type'] ?? null;
        if (! is_string($type)) {
            return false;
        }

        return match (strtolower($type)) {
            'geometrycollection' => is_array($shape['geometries'] ?? null) && $shape['geometries'] !== []
                && array_is_list($shape['geometries']) && array_filter($shape['geometries'], $this->hasGeometry(...)) === $shape['geometries'],
            'point' => $this->hasCoordinates($shape['coordinates'] ?? null, 0),
            'envelope' => count((array) ($shape['coordinates'] ?? [])) === 2 && $this->hasCoordinates($shape['coordinates'], 1),
            'linestring' => count((array) ($shape['coordinates'] ?? [])) >= 2 && $this->hasCoordinates($shape['coordinates'], 1),
            'multipoint' => $this->hasCoordinates($shape['coordinates'] ?? null, 1),
            'multilinestring', 'polygon' => $this->hasCoordinates($shape['coordinates'] ?? null, 2),
            'multipolygon' => $this->hasCoordinates($shape['coordinates'] ?? null, 3),
            default => false,
        };
    }

    private function hasCoordinates(mixed $coordinates, int $depth): bool
    {
        if (! is_array($coordinates) || ! array_is_list($coordinates) || $coordinates === []) {
            return false;
        }
        if ($depth === 0 && (count($coordinates) < 2 || count($coordinates) > (($this->options['ignore_z_value'] ?? true) ? 3 : 2))) {
            return false;
        }
        foreach ($coordinates as $coordinate) {
            if ($depth === 0 ? (! is_int($coordinate) && ! is_float($coordinate)) : ! $this->hasCoordinates($coordinate, $depth - 1)) {
                return false;
            }
        }

        return true;
    }

    public function queries(array|string $queryString): array
    {
        return [];
    }

    public function toRaw(): array
    {
        $mapping = merge_named(['type' => $this->type], $this->options);

        if ($this->meta !== [] || array_key_exists('meta', $mapping)) {
            $mapping['meta'] = array_is_list($this->meta) ? (object) $this->meta : $this->meta;
        }

        if ($this->copyTo !== null) {
            $mapping['copy_to'] = $this->copyTo;
        }

        return [$this->name => mapping_field_maps($mapping)];
    }
}
