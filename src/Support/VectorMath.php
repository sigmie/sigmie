<?php

declare(strict_types=1);

namespace Sigmie\Support;

class VectorMath
{
    /**
     * Normalize a vector to unit length (L2 normalization)
     */
    public static function normalize(array $vector): array
    {
        $magnitude = sqrt(array_sum(array_map(fn($v) => $v * $v, $vector)));

        // Avoid division by zero
        if ($magnitude == 0) {
            return $vector;
        }

        return array_map(fn($v) => $v / $magnitude, $vector);
    }

    /**
     * Check if a vector is already normalized (within tolerance)
     */
    public static function isNormalized(array $vector, float $tolerance = 0.0001): bool
    {
        $magnitude = sqrt(array_sum(array_map(fn($v) => $v * $v, $vector)));

        return abs($magnitude - 1.0) < $tolerance;
    }

    /**
     * Calculate the centroid (average) of multiple vectors
     * Returns a normalized centroid vector
     */
    public static function centroid(array $vectors): array
    {
        if (empty($vectors)) {
            return [];
        }

        $numVectors = count($vectors);
        $dimensions = count($vectors[0]);

        $centroid = array_fill(0, $dimensions, 0.0);

        foreach ($vectors as $vector) {
            foreach ($vector as $i => $value) {
                $centroid[$i] += $value;
            }
        }

        // Average each dimension
        $centroid = array_map(fn($sum) => $sum / $numVectors, $centroid);

        // Normalize the centroid
        return self::normalize($centroid);
    }
}
