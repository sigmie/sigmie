<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Support\VectorMath;
use Sigmie\Testing\TestCase;

class VectorNormalizationTest extends TestCase
{
    /**
     * @test
     */
    public function normalizes_vector_to_unit_length()
    {
        $vector = [3.0, 4.0, 0.0];
        $normalized = VectorMath::normalize($vector);

        // Magnitude should be 1.0
        $magnitude = sqrt(array_sum(array_map(fn($v) => $v * $v, $normalized)));
        $this->assertEqualsWithDelta(1.0, $magnitude, 0.0001);

        // Values should be scaled correctly
        $this->assertEqualsWithDelta(0.6, $normalized[0], 0.0001); // 3/5
        $this->assertEqualsWithDelta(0.8, $normalized[1], 0.0001); // 4/5
        $this->assertEqualsWithDelta(0.0, $normalized[2], 0.0001);
    }

    /**
     * @test
     */
    public function handles_already_normalized_vector()
    {
        $vector = [0.6, 0.8, 0.0];
        $normalized = VectorMath::normalize($vector);

        // Should remain the same
        $this->assertEqualsWithDelta(0.6, $normalized[0], 0.0001);
        $this->assertEqualsWithDelta(0.8, $normalized[1], 0.0001);
    }

    /**
     * @test
     */
    public function handles_zero_vector()
    {
        $vector = [0.0, 0.0, 0.0];
        $normalized = VectorMath::normalize($vector);

        // Should return zero vector unchanged
        $this->assertEquals([0.0, 0.0, 0.0], $normalized);
    }

    /**
     * @test
     */
    public function detects_normalized_vectors()
    {
        $normalized = [0.6, 0.8, 0.0];
        $this->assertTrue(VectorMath::isNormalized($normalized));

        $notNormalized = [3.0, 4.0, 0.0];
        $this->assertFalse(VectorMath::isNormalized($notNormalized));
    }

    /**
     * @test
     */
    public function normalizes_high_dimensional_vector()
    {
        // Create a 256-dimensional vector
        $vector = array_fill(0, 256, 1.0);
        $normalized = VectorMath::normalize($vector);

        // Magnitude should be 1.0
        $magnitude = sqrt(array_sum(array_map(fn($v) => $v * $v, $normalized)));
        $this->assertEqualsWithDelta(1.0, $magnitude, 0.0001);

        // Each component should be 1/sqrt(256) = 1/16 = 0.0625
        foreach ($normalized as $component) {
            $this->assertEqualsWithDelta(0.0625, $component, 0.0001);
        }
    }
}
