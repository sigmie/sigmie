<?php

declare(strict_types=1);

namespace Sigmie\Parse;

use Sigmie\Mappings\Types\GeoPoint;
use Sigmie\Mappings\Types\Nested;

class SortParser extends Parser
{
    public function parse(string $string): array
    {
        $this->errors = [];

        $string = trim($string);

        if ($string === '') {
            return ['_score'];
        }

        $sorts = explode(' ', $string);
        $sort = [];
        $hasGeoDistance = false;

        foreach ($sorts as $match) {
            if (in_array($match, ['_score', '_doc'])) {
                $sort[] = $match;

                continue;
            }

            if (preg_match(
                '/(?P<field>\w+(\.\w+)*(\.\w+)*)\[(?P<latitude>-?\d+(\.\d+)?),(?P<longitude>-?\d+(\.\d+)?)\]:(?P<unit>\w+):(?P<order>\w+)/',
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

                $field = $matches['field'];
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

                $hasGeoDistance = true;
                if ($fieldType->parentPath && $fieldType->parentType === Nested::class) {
                    $sort[] = [
                        '_geo_distance' => [
                            'nested' => [
                                'path' => $fieldType->parentPath,
                            ],
                            $field => [
                                'lat' => (float) $latitude,
                                'lon' => (float) $longitude,
                            ],
                            'order' => $order,
                            'unit' => $unit,
                        ],
                    ];
                } else {
                    $sort[] = [
                        '_geo_distance' => [
                            $field => [
                                'lat' => (float) $latitude,
                                'lon' => (float) $longitude,
                            ],
                            'order' => $order,
                            'unit' => $unit,
                        ],
                    ];
                }

                continue;
            }

            if (str_contains($match, ':')) {
                [$field, $direction] = explode(':', $match);
            } else {

                $field = $match;
                $direction = 'asc';
            }

            $type = $this->properties->get($field);

            $sortableName = $this->handleSortableFieldName($field);

            // Field isn't sortable
            if (is_null($sortableName)) {
                continue;
            }

            if ($type->parentPath && $type->parentType === Nested::class) {
                $sort[] = [
                    $sortableName => [
                        'nested' => [
                            'path' => $type->parentPath,
                        ],
                        'order' => $direction,
                    ],
                ];
            } else {
                $sort[] = [$sortableName => $direction];
            }
        }

        return $sort;
    }
}
