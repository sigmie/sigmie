<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use PHPUnit\Framework\TestCase;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Enums\VectorStrategy;
use Sigmie\Search\VectorPool;
use Sigmie\Support\VectorMath;
use UnexpectedValueException;

class VectorStrategyNormalizationTest extends TestCase
{
    public function test_unrequested_batch_results_cannot_replace_cached_queries(): void
    {
        $api = $this->createStub(EmbeddingsApi::class);
        $api->method('maxBatchSize')->willReturn(2);
        $api->method('batchEmbed')->willReturn([
            ['text' => 'new', 'dims' => 2, 'vector' => [1, 0]],
            ['text' => 'cached', 'dims' => 2, 'vector' => [1, 0]],
        ]);
        $pool = (new VectorPool($api))->setPool(['cached' => [2 => [0, 1]]]);

        try {
            $pool->getMany([['text' => 'new', 'dims' => 2]]);
            $this->fail('Expected an invalid embedding batch to be rejected.');
        } catch (UnexpectedValueException) {
            $this->assertSame([0, 1], $pool->get('cached', 2));
            $this->assertFalse($pool->has('new', 2));
        }
    }

    public function test_average_rejects_inconsistent_example_widths(): void
    {
        $this->expectException(UnexpectedValueException::class);

        VectorMath::average([[1, 1], [1]]);
    }

    public function test_average_keeps_small_components_after_cancellation(): void
    {
        $vectors = [[1e16, 0.0], [3.0, 3.0], [-1e16, 0.0]];

        $this->assertSame([1.0, 1.0], VectorMath::average($vectors));
    }

    public function test_cosine_is_exact_for_identical_mixed_numeric_types(): void
    {
        $this->assertSame(1.0, VectorMath::cosineSimilarity([1, 2], [1.0, 2.0]));
    }

    public function test_stored_vector_components_are_json_lists(): void
    {
        foreach (VectorStrategy::cases() as $strategy) {
            foreach ([false, true] as $normalize) {
                $value = $strategy->format([[2 => 3.0, 5 => 4.0]], $normalize);
                $vector = $strategy === VectorStrategy::ScriptScore ? $value[0]['vector'] : $value;

                $this->assertSame($normalize ? [0.6, 0.8] : [3.0, 4.0], $vector);
            }
        }
    }

    public function test_preloaded_pool_respects_normalization(): void
    {
        $api = $this->createStub(EmbeddingsApi::class);
        foreach ([false, true] as $normalize) {
            $pool = (new VectorPool($api, $normalize))->setPool(['query' => [2 => [3.0, 4.0]]]);
            $this->assertSame($normalize ? [0.6, 0.8] : [3.0, 4.0], $pool->get('query', 2));
        }
    }

    /** @test */
    public function cosine_similarity_stays_in_its_documented_range(): void
    {
        $this->assertLessThanOrEqual(1.0, VectorMath::cosineSimilarity([1.0, 0.01], [2.0, 0.02]));
        $this->assertGreaterThanOrEqual(-1.0, VectorMath::cosineSimilarity([1.0, 0.01], [-2.0, -0.02]));
    }

    /**
     * @test
     */
    public function average_strategy_produces_normalized_vectors(): void
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

        $magnitude = sqrt(array_sum(array_map(fn ($v): int|float => $v * $v, $result)));

        // This will fail with current implementation
        $this->assertEqualsWithDelta(1.0, $magnitude, 0.01,
            'Average strategy should normalize the result vector. Got magnitude: '.$magnitude);
    }

    /**
     * @test
     */
    public function concatenate_strategy_preserves_normalization(): void
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
    public function average_strategy_with_three_vectors(): void
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

        $magnitude = sqrt(array_sum(array_map(fn ($v): int|float => $v * $v, $result)));

        $this->assertEqualsWithDelta(1.0, $magnitude, 0.01,
            'Average of 3 orthogonal unit vectors should be normalized. Got: '.$magnitude);
    }

    /**
     * @test
     */
    public function concatenate_and_script_score_normalize_when_requested(): void
    {
        $embeddings = [[3.0, 4.0]];

        foreach ([VectorStrategy::Average, VectorStrategy::Concatenate] as $strategy) {
            $vector = $strategy->format($embeddings, true);

            $this->assertEqualsWithDelta(0.6, $vector[0], 0.0001);
            $this->assertEqualsWithDelta(0.8, $vector[1], 0.0001);
        }

        $script = VectorStrategy::ScriptScore->format($embeddings, true);

        $this->assertEqualsWithDelta(0.6, $script[0]['vector'][0], 0.0001);
        $this->assertEqualsWithDelta(0.8, $script[0]['vector'][1], 0.0001);

        $this->assertSame([3.0, 4.0], VectorStrategy::Concatenate->format($embeddings, false));
        $this->assertSame([['vector' => [3.0, 4.0]]], VectorStrategy::ScriptScore->format($embeddings, false));
    }

    /**
     * @test
     */
    public function average_format_reads_the_only_embedding_whatever_its_key(): void
    {
        $this->assertSame([3.0, 4.0], VectorStrategy::Average->format([1 => [3.0, 4.0]], false));
        $this->assertSame([], VectorStrategy::Average->format([], false));
    }

    /**
     * @test
     */
    public function centroid_averages_vectors_that_are_not_a_list(): void
    {
        $centroid = VectorMath::centroid([1 => [1.0, 0.0], 2 => [0.0, 1.0]]);

        $this->assertEqualsWithDelta(0.7071067811865475, $centroid[0], 0.000000000001);
        $this->assertEqualsWithDelta(0.7071067811865475, $centroid[1], 0.000000000001);
    }

    /**
     * @test
     */
    public function an_exact_unit_vector_is_normalized_at_zero_tolerance(): void
    {
        $this->assertTrue(VectorMath::isNormalized([1.0, 0.0], 0.0));
    }

    /**
     * @test
     */
    public function a_vector_keeps_its_components_when_the_keys_are_not_packed(): void
    {
        $centroid = VectorMath::centroid([[1 => 3.0, 2 => 4.0]]);

        $this->assertCount(2, $centroid);
        $this->assertEqualsWithDelta(0.6, $centroid[0], 0.000000000001);
        $this->assertEqualsWithDelta(0.8, $centroid[1], 0.000000000001);
    }

    /**
     * @test
     */
    public function a_cosine_pairs_components_in_order(): void
    {
        $score = VectorMath::cosineSimilarity([0 => 1.0, 2 => 0.0], [1.0, 1.0]);

        $this->assertEqualsWithDelta(1 / sqrt(2), $score, 0.000000000001);
    }

    /**
     * @test
     */
    public function identical_finite_vectors_have_cosine_one(): void
    {
        $vector = [1.0e200, 1.0e200];

        $this->assertEqualsWithDelta(1.0, VectorMath::cosineSimilarity($vector, $vector), 0.000000000001);

        $normalized = VectorMath::normalize($vector);

        $this->assertEqualsWithDelta(1 / sqrt(2), $normalized[0], 0.000000000001);
        $this->assertEqualsWithDelta(1 / sqrt(2), $normalized[1], 0.000000000001);
        $this->assertTrue(VectorMath::isNormalized($normalized));
    }

    /** @dataProvider finiteExtremes */
    public function test_normalization_at_float_limits(float $component): void
    {
        $vector = VectorMath::normalize([$component, $component]);
        $this->assertEqualsWithDelta([sqrt(0.5), sqrt(0.5)], $vector, 1e-12);
    }

    public static function finiteExtremes(): array
    {
        return [[PHP_FLOAT_MAX], [PHP_FLOAT_MIN * PHP_FLOAT_EPSILON]];
    }

    public function test_centroid_of_repeated_maximum_components(): void
    {
        $vectors = array_fill(0, 3, [PHP_FLOAT_MAX, 0.0]);
        $this->assertSame([1.0, 0.0], VectorMath::centroid($vectors));
    }

    public function test_average_strategy_preserves_a_finite_mean(): void
    {
        $vectors = array_fill(0, 3, [PHP_FLOAT_MAX, 3.0]);
        $this->assertSame([PHP_FLOAT_MAX, 3.0], VectorStrategy::Average->format($vectors, false));
    }

    public function test_script_score_emits_a_list_for_keyed_embeddings(): void
    {
        $result = VectorStrategy::ScriptScore->format([7 => [3, 4], 9 => [5, 6]], false);

        $this->assertSame('[{"vector":[3,4]},{"vector":[5,6]}]', json_encode($result));
    }

    public function test_concatenate_reads_a_keyed_embedding(): void
    {
        $this->assertSame([3, 4], VectorStrategy::Concatenate->format([7 => [3, 4]], false));
    }

    public function test_pool_batches_unique_missing_text_dimension_pairs(): void
    {
        $api = $this->createMock(EmbeddingsApi::class);
        $api->method('maxBatchSize')->willReturn(2);
        $batches = [];
        $api->method('batchEmbed')->willReturnCallback(function (array $batch) use (&$batches): array {
            $batches[] = $batch;

            return array_map(fn ($item) => [...$item, 'vector' => array_fill(0, $item['dims'], 1.0)], $batch);
        });

        $pool = (new VectorPool($api, false))->setPool(['cached' => [1 => [0.5]]]);
        $a = ['text' => 'a', 'dims' => 1];
        $b = ['text' => 'a', 'dims' => 2];
        $c = ['text' => 'c', 'dims' => 1];
        $items = [['text' => 'cached', 'dims' => 1], $a, $a, $b, $c];
        $pool->getMany($items)->getMany($items);

        $this->assertSame([[$a, $b], [$c]], $batches);
        $this->assertSame([1.0, 1.0], $pool->get('a', 2));
    }
}
