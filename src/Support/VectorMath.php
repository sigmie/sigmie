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
        $magnitude = sqrt(array_sum(array_map(fn($v): int|float => $v * $v, $vector)));

        // Avoid division by zero
        if ($magnitude == 0) {
            return $vector;
        }

        return array_map(fn($v): float => $v / $magnitude, $vector);
    }

    /**
     * Check if a vector is already normalized (within tolerance)
     */
    public static function isNormalized(array $vector, float $tolerance = 0.0001): bool
    {
        $magnitude = sqrt(array_sum(array_map(fn($v): int|float => $v * $v, $vector)));

        return abs($magnitude - 1.0) < $tolerance;
    }

    /**
     * Calculate the centroid (average) of multiple vectors
     * Returns a normalized centroid vector
     */
    public static function centroid(array $vectors): array
    {
        if ($vectors === []) {
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
        $centroid = array_map(fn($sum): float => $sum / $numVectors, $centroid);

        // Normalize the centroid
        return self::normalize($centroid);
    }

    /**
     * Scale a vector by a factor
     */
    public static function scale(array $vector, float $factor): array
    {
        return array_map(fn($v): float => $v * $factor, $vector);
    }

    /**
     * Calculate cosine similarity between two vectors
     * Returns value between -1 and 1 (1 = identical, 0 = orthogonal, -1 = opposite)
     */
    public static function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        if ($vectorA === [] || $vectorB === [] || count($vectorA) !== count($vectorB)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        foreach ($vectorA as $i => $valueA) {
            $valueB = $vectorB[$i];
            $dotProduct += $valueA * $valueB;
            $magnitudeA += $valueA * $valueA;
            $magnitudeB += $valueB * $valueB;
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        // Avoid division by zero
        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }
}
