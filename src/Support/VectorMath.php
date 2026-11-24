<?php

declare(strict_types=1);

namespace Sigmie\Support;

use UnexpectedValueException;

class VectorMath
{
    /**
     * Normalize a vector to unit length (L2 normalization)
     */
    public static function normalize(array $vector): array
    {
        $peak = self::peak($vector);
        if ($peak == 0.0) {
            return $vector;
        }

        $scaled = array_map(fn ($value): float => $value / $peak, $vector);
        $length = sqrt(array_sum(array_map(fn ($value): float => $value * $value, $scaled)));

        return array_map(fn ($value): float => $value / $length, $scaled);
    }

    /**
     * Check if a vector is already normalized (within tolerance)
     */
    public static function isNormalized(array $vector, float $tolerance = 0.0001): bool
    {
        $magnitude = sqrt(array_sum(array_map(fn ($v): int|float => $v * $v, $vector)));

        return abs($magnitude - 1.0) <= $tolerance;
    }

    /**
     * Calculate the centroid (average) of multiple vectors
     * Returns a normalized centroid vector
     */
    public static function centroid(array $vectors): array
    {
        return self::normalize(self::average($vectors));
    }

    public static function average(array $vectors): array
    {
        if ($vectors === []) {
            return [];
        }

        $count = count($vectors);
        $mean = array_fill(0, count(reset($vectors)), 0.0);
        $corrections = $mean;
        foreach ($vectors as $vector) {
            if (count($vector) !== count($mean)) {
                throw new UnexpectedValueException('Cannot average vectors of different dimensions.');
            }

            foreach (array_values($vector) as $i => $value) {
                $sum = $mean[$i];
                $next = $sum + $value;
                $mean[$i] = $next;

                if (is_finite($next)) {
                    // Keep the part lost to rounding when adding different magnitudes.
                    $corrections[$i] += abs($sum) >= abs($value)
                        ? ($sum - $next) + $value
                        : ($value - $next) + $sum;
                }
            }
        }

        foreach ($mean as $i => $sum) {
            $sum += $corrections[$i];
            if (is_finite($sum)) {
                $mean[$i] = $sum / $count;
                continue;
            }

            // The mean fits even when its intermediate sum overflows.
            $components = array_map(fn ($vector) => array_values($vector)[$i], $vectors);
            $peak = self::peak($components);
            $scaled = array_map(fn ($value): float => $value / $peak, $components);
            $mean[$i] = (array_sum($scaled) / $count) * $peak;
        }

        return $mean;
    }

    /**
     * Scale a vector by a factor
     */
    public static function scale(array $vector, float $factor): array
    {
        return array_map(fn ($v): float => $v * $factor, $vector);
    }

    /**
     * Calculate cosine similarity between two vectors
     * Returns value between -1 and 1 (1 = identical, 0 = orthogonal, -1 = opposite)
     */
    public static function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        $vectorA = array_values($vectorA);
        $vectorB = array_values($vectorB);

        if ($vectorA === [] || $vectorB === [] || count($vectorA) !== count($vectorB)) {
            return 0.0;
        }

        $peakA = self::peak($vectorA);
        $peakB = self::peak($vectorB);

        // Avoid division by zero
        if ($peakA == 0.0 || $peakB == 0.0) {
            return 0.0;
        }

        // Identical finite vectors are 1. Scaling still leaves a double rounding short of 1.
        if ($vectorA == $vectorB && is_finite($peakA) && is_finite($peakB)) {
            return 1.0;
        }

        $dotProduct = 0.0;
        $sumA = 0.0;
        $sumB = 0.0;

        foreach ($vectorA as $i => $valueA) {
            $scaledA = $valueA / $peakA;
            $scaledB = $vectorB[$i] / $peakB;
            $dotProduct += $scaledA * $scaledB;
            $sumA += $scaledA * $scaledA;
            $sumB += $scaledB * $scaledB;
        }

        $similarity = $dotProduct / (sqrt($sumA) * sqrt($sumB));

        return max(-1.0, min(1.0, $similarity));
    }

    /**
     * @param  array<int|string, int|float>  $vector
     */
    private static function peak(array $vector): float
    {
        $peak = 0.0;

        foreach ($vector as $value) {
            $peak = max($peak, abs((float) $value));
        }

        return $peak;
    }
}
