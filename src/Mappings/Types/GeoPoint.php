<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use stdClass;

class GeoPoint extends Type
{
    protected string $type = 'geo_point';

    public function sortableName(): ?string
    {
        return null;
    }

    public function validate(string $key, mixed $value): array
    {
        if (($this->storedMapping['ignore_malformed'] ?? false) === true) {
            if (is_array($value) && array_is_list($value) && (is_int($value[0] ?? null) || is_float($value[0] ?? null))) {
                // A leading number commits the parser to a coordinate tuple before normalization.
                if (! is_numeric($value[1] ?? null) || (array_key_exists(2, $value) && ! is_int($value[2]) && ! is_float($value[2]))) {
                    return [false, sprintf('The field %s has invalid numeric coordinates.', $key)];
                }
            }
            return [true, ''];
        }

        $points = is_array($value) && array_is_list($value) && ! is_int($value[0] ?? null) && ! is_float($value[0] ?? null) ? $value : [$value];

        foreach ($points as $point) {
            if ($point !== null && ! $this->hasValidCoordinates($point)) {
                return [false, sprintf('The field %s mapped as %s must have valid lat and lon coordinates.', $key, $this->typeName())];
            }
        }

        return [true, ''];
    }

    private function hasValidCoordinates(mixed $point): bool
    {
        if ($point instanceof stdClass) {
            $point = (array) $point;
        }
        if (is_string($point)) {
            if (str_contains($point, ',')) {
                $coords = array_map('trim', explode(',', $point));
                return $this->validTuple($coords, latitudeFirst: true);
            }
            if (preg_match('/^point(?:\s+(z))?\s*\(([^()]*)\)\z/i', trim($point), $match)) {
                $coordinates = preg_split('/\s+/', trim($match[2]));

                return ($match[1] === '' || count($coordinates) === 3) && $this->validTuple($coordinates);
            }

            return strcasecmp(trim($point), 'POINT EMPTY') === 0
                || preg_match('/^[0123456789bcdefghjkmnpqrstuvwxyz]+\z/', substr($point, 0, 12)) === 1;
        }

        if (! is_array($point)) {
            return false;
        }
        if (array_is_list($point)) {
            return (is_int($point[0] ?? null) || is_float($point[0] ?? null))
                && (count($point) !== 3 || is_float($point[2]) || is_int($point[2]))
                && $this->validTuple($point);
        }
        if (isset($point['type'], $point['coordinates'])) {
            return count($point) === 2 && is_string($point['type']) && strcasecmp($point['type'], 'point') === 0
                && is_array($point['coordinates']) && array_is_list($point['coordinates'])
                && $this->validTuple($point['coordinates']);
        }
        if (! isset($point['lat'], $point['lon']) || array_diff_key($point, ['lat' => true, 'lon' => true, 'z' => true]) !== []) {
            return false;
        }

        $coords = [$point['lon'], $point['lat']];
        if (array_key_exists('z', $point)) {
            $coords[] = $point['z'];
        }
        return $this->validTuple($coords);
    }

    private function validTuple(array $coordinates, bool $latitudeFirst = false): bool
    {
        if (count($coordinates) !== 2 && (count($coordinates) !== 3 || ! is_numeric($coordinates[2])
            || ($this->storedMapping['ignore_z_value'] ?? true) === false)) {
            return false;
        }
        [$lon, $lat] = $latitudeFirst ? [$coordinates[1], $coordinates[0]] : $coordinates;

        return is_numeric($lat) && is_numeric($lon)
            && $lat >= -90 && $lat <= 90
            && $lon >= -180 && $lon <= 180;
    }
}
