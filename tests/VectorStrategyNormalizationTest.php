<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Enums\VectorStrategy;
use Sigmie\Support\VectorMath;
use Sigmie\Testing\TestCase;

class VectorStrategyNormalizationTest extends TestCase
{
    /**
     * @test
     */
    public function average_strategy_produces_normalized_vectors()
    {
        $strategy = VectorStrategy::Average;

        // Two normalized vectors
        $vec1 = [0.6, 0.8, 0.0];
        $vec2 = [0.8, 0.6, 0.0];

        // Verify inputs are normalized
        $this->assertTrue(VectorMath::isNormalized($vec1));
        $this->assertTrue(VectorMath::isNormalized($vec2));

        // Apply average strategy
        $result = $strategy->format([$vec1, $vec2]);

        // The average of two normalized vectors is NOT necessarily normalized
        // [0.6, 0.8, 0] + [0.8, 0.6, 0] = [1.4, 1.4, 0]
        // Average: [0.7, 0.7, 0]
        // Magnitude: sqrt(0.7^2 + 0.7^2) = sqrt(0.98) ≈ 0.99 (NOT 1.0!)

        $magnitude = sqrt(array_sum(array_map(fn($v) => $v * $v, $result)));

        // This will fail with current implementation
        $this->assertEqualsWithDelta(1.0, $magnitude, 0.01,
            'Average strategy should normalize the result vector. Got magnitude: ' . $magnitude);
    }

    /**
     * @test
     */
    public function concatenate_strategy_preserves_normalization()
    {
        $strategy = VectorStrategy::Concatenate;

        // Single normalized vector
        $vec1 = [0.6, 0.8, 0.0];

        $this->assertTrue(VectorMath::isNormalized($vec1));

        // Apply concatenate strategy (just returns first vector)
        $result = $strategy->format([$vec1]);

        // Should remain normalized
        $this->assertTrue(VectorMath::isNormalized($result));
    }

    /**
     * @test
     */
    public function average_strategy_with_three_vectors()
    {
        $strategy = VectorStrategy::Average;

        // Three normalized vectors pointing in different directions
        $vec1 = [1.0, 0.0, 0.0];
        $vec2 = [0.0, 1.0, 0.0];
        $vec3 = [0.0, 0.0, 1.0];

        // Apply average strategy
        $result = $strategy->format([$vec1, $vec2, $vec3]);

        // Average: [1/3, 1/3, 1/3]
        // Magnitude: sqrt(3 * (1/3)^2) = sqrt(1/3) ≈ 0.577 (NOT normalized!)

        $magnitude = sqrt(array_sum(array_map(fn($v) => $v * $v, $result)));

        $this->assertEqualsWithDelta(1.0, $magnitude, 0.01,
            'Average of 3 orthogonal unit vectors should be normalized. Got: ' . $magnitude);
    }
}
