<?php

declare(strict_types=1);

namespace Sigmie\Parse;

use Sigmie\Mappings\Types\GeoPoint;

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
                if ($nestedPath = $fieldType->nestedPath()) {
                    $sort[] = [
                        '_geo_distance' => [
                            'nested' => [
                                'path' => $nestedPath,
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

            if ($nestedPath = $type->nestedPath()) {
                $sort[] = [
                    $sortableName => [
                        'nested' => [
                            'path' => $nestedPath,
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
