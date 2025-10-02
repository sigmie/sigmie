<?php

declare(strict_types=1);

namespace Sigmie\Support;

class VectorNormalizer
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
}
