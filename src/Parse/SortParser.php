<?php

declare(strict_types=1);

namespace Sigmie\Parse;

use Sigmie\Mappings\Types\GeoPoint;

use function Sigmie\Functions\keyed;

class SortParser extends Parser
{
    public function parse(string $string): array
    {
        $this->errors = [];

        $sorts = preg_split('/\s+/u', trim($string), flags: PREG_SPLIT_NO_EMPTY);

        if ($sorts === false) {
            $this->handleError('Sort expression contains invalid UTF-8.');

            return [];
        }

        if ($sorts === []) {
            return ['_score'];
        }

        $sort = [];

        foreach ($sorts as $match) {
            if (in_array($match, ['_score', '_doc'])) {
                $sort[] = $match;

                continue;
            }

            // A bare "asc"/"desc" token means the direction was written with a space
            // (SQL-style "field asc") instead of a colon. Point to the correct form so
            // the caller (or an agent) can fix it rather than seeing "Field asc does not exist".
            if (in_array($match, ['asc', 'desc'], true) && ! $this->fieldExists($match)) {
                $this->handleError(sprintf(
                    "Invalid sort '%s': attach the direction to its field with a colon, e.g. 'field:%s' (not 'field %s'). Use 'field:asc' or 'field:desc', and separate multiple sorts with spaces, e.g. 'price:asc name:desc'.",
                    $string,
                    $match,
                    $match
                ), ['token' => $match]);

                continue;
            }

            // Handle _score with direction
            if (str_starts_with($match, '_score:')) {
                $direction = substr($match, 7); // Remove '_score:'

                if ($direction === 'desc') {
                    $sort[] = ['_score' => 'desc'];

                    continue;
                }

                if ($direction === 'asc') {
                    $this->handleError("_score cannot be sorted in ascending order. Use '_score:desc' or '_score' instead.", [
                        'field' => '_score',
                        'direction' => 'asc',
                    ]);

                    continue;
                }

                $this->handleError(sprintf("Invalid direction '%s' for _score. Use 'desc' or omit direction.", $direction), [
                    'field' => '_score',
                    'direction' => $direction,
                ]);

                continue;
            }

            if (preg_match(
                '/\A(?P<field>'.self::FIELD_PATH.')\[(?P<latitude>[+-]?'.self::DECIMAL.'),(?P<longitude>[+-]?'.self::DECIMAL.')\]:(?P<unit>\w+):(?P<order>\w+)\z/u',
                $match,
                $matches
            )) {
                $fieldType = $this->properties->get($matches['field']);
                if (! $fieldType instanceof GeoPoint) {

                    $this->handleError(sprintf('Field %s is not a geo point.', $matches['field']), [
                        'field' => $matches['field'],
                    ]);

                    continue;
                }

                $field = $fieldType->fullPath();
                $unit = $matches['unit'];
                $order = $matches['order'];
                $latitude = $matches['latitude'];
                $longitude = $matches['longitude'];
                if (! in_array($unit, ['km', 'm', 'cm', 'mm', 'mi', 'yd', 'ft', 'in', 'nmi'])) {
                    $this->handleError(sprintf("Invalid unit '%s' for geo distance sort.", $unit), [
                        'unit' => $unit,
                    ]);

                    continue;
                }

                if (! in_array($order, ['asc', 'desc'])) {
                    $this->handleError(sprintf("Invalid order '%s' for geo distance sort.", $order), [
                        'order' => $order,
                    ]);

                    continue;
                }

                if (! is_numeric($latitude) || ! is_numeric($longitude) || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                    $this->handleError('Invalid latitude or longitude for geo distance sort.', [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                    ]);

                    continue;
                }

                $latitude = (float) $latitude;
                $longitude = (float) $longitude;
                if (($field === 'order' && $order !== 'asc') || ($field === 'unit' && $unit !== 'm')) {
                    $sort[] = $this->geoScriptSort($field, $latitude, $longitude, $unit, $order);

                    continue;
                }

                // An object under "nested" is parsed as nested-sort options.
                $geoSort = [
                    $field => $field === 'nested' ? [$longitude, $latitude] : ['lat' => $latitude, 'lon' => $longitude],
                ] + ['order' => $order, 'unit' => $unit];

                if (($nestedPath = $fieldType->nestedPath()) !== null) {
                    $geoSort['nested'] = ['path' => $nestedPath];
                }

                $sort[] = ['_geo_distance' => $geoSort];

                continue;
            }

            [$field, $direction] = array_pad(explode(':', $match, 2), 2, 'asc');

            if (! in_array($direction, ['asc', 'desc'], true)) {
                $this->handleError(sprintf("Invalid direction '%s' for field '%s'. Use 'asc' or 'desc'.", $direction, $field), [
                    'field' => $field,
                    'direction' => $direction,
                ]);

                continue;
            }

            if ($field === '_doc') {
                $sort[] = ['_doc' => $direction];

                continue;
            }

            $type = $this->properties->get($field);

            $sortableName = $this->handleSortableFieldName($field);

            // Field isn't sortable
            if (is_null($sortableName)) {
                continue;
            }

            if (($nestedPath = $type->nestedPath()) !== null) {
                $sort[] = [
                    $sortableName => [
                        'nested' => [
                            'path' => $nestedPath,
                        ],
                        'order' => $direction,
                    ],
                ];
            } else {
                $sort[] = keyed($sortableName, $direction);
            }
        }

        return $sort;
    }

    // A top-level field named "order" or "unit" cannot share its JSON key
    // with the sort option. Match native arc distance and multi-value mode.
    private function geoScriptSort(string $field, float $lat, float $lon, string $unit, string $order): array
    {
        $meters = match ($unit) {
            'km' => 1000, 'm' => 1, 'cm' => 0.01, 'mm' => 0.001,
            'mi' => 1609.344, 'yd' => 0.9144, 'ft' => 0.3048,
            'in' => 0.0254, 'nmi' => 1852,
        };

        return ['_script' => [
            'type' => 'number',
            'order' => $order,
            'script' => [
                'source' => <<<'PAINLESS'
                    def points = doc[params.field];
                    if (points.size() == 0) return Double.POSITIVE_INFINITY;
                    double best = params.ascending ? Double.POSITIVE_INFINITY : 0.0;
                    for (def point : points) {
                        double a = Math.sin(Math.toRadians(point.lat - params.lat) / 2);
                        double b = Math.sin(Math.toRadians(point.lon - params.lon) / 2);
                        double h = a*a + Math.cos(Math.toRadians(params.lat))*Math.cos(Math.toRadians(point.lat))*b*b;
                        double distance = 12742017.5428 * Math.asin(Math.min(1.0, Math.sqrt(h)));
                        best = params.ascending ? Math.min(best, distance) : Math.max(best, distance);
                    }
                    return best / params.meters;
                    PAINLESS,
                'params' => ['field' => $field, 'lat' => $lat, 'lon' => $lon, 'ascending' => $order === 'asc', 'meters' => $meters],
            ],
        ]];
    }
}
