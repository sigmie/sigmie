<?php

declare(strict_types=1);

namespace Sigmie\Mappings;

use Exception;
use Sigmie\Enums\VectorSimilarity;
use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\NestedVector;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\SigmieVector;

class NewSemanticField
{
    protected VectorSimilarity $similarity = VectorSimilarity::Cosine;

    protected int $dims = 256;

    protected ?int $efConstruction = 300;

    protected ?int $m = 64;

    protected bool $index = true;

    protected VectorStrategy $strategy = VectorStrategy::Concatenate;

    protected ?string $apiName = null;

    protected ?string $boostedBy = null;

    protected bool $autoNormalizeVector = true;

    protected string $fieldType = 'text';

    protected ?Type $createdVector = null;

    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function cosineSimilarity()
    {
        $this->similarity = VectorSimilarity::Cosine;

        return $this;
    }

    public function euclideanSimilarity()
    {
        $this->similarity = VectorSimilarity::Euclidean;

        return $this;
    }

    public function dotProductSimilarity()
    {
        $this->similarity = VectorSimilarity::DotProduct;

        return $this;
    }

    public function maxInnerProductSimilarity()
    {
        $this->similarity = VectorSimilarity::MaxInnerProduct;

        return $this;
    }

    public function similarity(VectorSimilarity $similarity)
    {
        $this->similarity = $similarity;

        return $this;
    }

    public function accuracy(int $level, ?int $dimensions = null)
    {
        $dimensions = $dimensions ?? $this->dims;

        if (!in_array($dimensions, [
            128,
            256,
            384,
            512,
            1024,
            1536,
            2048,
            3072,
        ])) {
            throw new Exception('Dimensions must be one of: 128, 256, 384, 512, 1024, 1536, 2048, 3072');
        }

        if ($level < 1 || $level > 7) {
            throw new Exception('Accuracy level must be between 1 and 7');
        }

        $this->strategy = match ($level) {
            //   Concatenate (accuracy 1) is fastest and cheapest because:
            //   - Single embedding call - It concatenates all text chunks into one string and generates ONE embedding.
            //   - Good for short texts - When you have short content that fits within the model's token limit, concatenating everything
            //     into a single embedding is efficient.
            //   - Lower precision - Since it's treating all the text as one blob, you lose granularity.
            //   But for accuracy level 1, that's acceptable - you're prioritizing speed/cost over precision.
            1 => VectorStrategy::Concatenate,
            //   Average (accuracy 2-6) generates multiple embeddings (one per text chunk) and then averages them. This:
            //   - Costs more (multiple API calls)
            //   - Handles longer texts better (splits content)
            //   - Preserves more semantic information through averaging
            2 => VectorStrategy::Average,
            3 => VectorStrategy::Average,
            4 => VectorStrategy::Average,
            5 => VectorStrategy::Average,
            6 => VectorStrategy::Average,
            //  ScriptScore (accuracy 7) is the most accurate
            //  - it searches across all individual embeddings without averaging, but it's
            //    also the slowest and most expensive.
            7 => VectorStrategy::ScriptScore,
        };

        $this->index = $level < 7;

        // Base values for 256 dimensions
        // These are calibrated to balance search quality vs indexing speed
        $base = [
            1 => [12, 60],   // Minimal quality, maximum speed
            2 => [16, 100],  // Low quality, good for small datasets
            3 => [24, 150],  // Balanced for most use cases
            4 => [32, 200],  // Good quality, moderate speed
            5 => [40, 300],  // High quality, slower indexing
            6 => [48, 400],  // Very high quality, significant indexing cost
        ];

        $this->dims = $dimensions;

        // Sublinear scaling: relationship between dimensions and optimal m is logarithmic, not linear
        // This prevents extreme values at high dimensions (e.g., 1536, 3072)
        if ($level < 7) {
            [$baseM, $baseEf] = $base[$level];
            $scale = sqrt($dimensions / 256);

            $this->m = min((int) round($baseM * $scale), 64);
            $this->efConstruction = min((int) round($baseEf * $scale), 500);
        } else {
            $this->m = null;
            $this->efConstruction = null;
        }

        return $this;
    }

    public function efConstruction(int $efConstruction)
    {
        $this->efConstruction = $efConstruction;

        return $this;
    }

    public function m(int $m)
    {
        $this->m = $m;

        return $this;
    }

    public function dimensions(int $dims)
    {
        $this->dims = $dims;

        return $this;
    }

    public function api(?string $apiName)
    {
        $this->apiName = $apiName;

        return $this;
    }

    public function boostedBy(string $fieldName): static
    {
        $this->boostedBy = $fieldName;

        return $this;
    }

    public function normalizeVector(bool $value): static
    {
        $this->autoNormalizeVector = $value;

        // Update the created vector if it exists
        if ($this->createdVector instanceof SigmieVector) {
            // We need to recreate the vector with the new setting
            // Store the reference so Text can update its vectors array
            $this->createdVector = null;
        }

        return $this;
    }

    public function fieldType(string $type): static
    {
        $this->fieldType = $type;

        return $this;
    }

    public function make(): Type
    {
        // If we already created a vector and it's not invalidated, return it
        if ($this->createdVector !== null) {
            return $this->createdVector;
        }

        // Auto-determine similarity if not explicitly set
        if ($this->similarity === VectorSimilarity::Cosine) {
            if ($this->boostedBy !== null) {
                $this->similarity = VectorSimilarity::DotProduct;
            } elseif ($this->fieldType === 'image') {
                $this->similarity = VectorSimilarity::Euclidean;
            }
        }

        $name = match ($this->index) {
            true => 'm' . $this->m . '_efc' . $this->efConstruction . '_dims' . $this->dims . '_' . $this->similarity->value . '_' . $this->strategy->suffix(),
            false => 'exact_dims' . $this->dims . '_' . $this->similarity->value . '_' . $this->strategy->suffix(),
        };

        if (!$this->index) {
            // Create a nested vector with proper properties for brute-force search
            $props = new NewProperties();
            $props->type(
                new DenseVector(
                    name: 'vector',
                    dims: $this->dims,
                    strategy: VectorStrategy::ScriptScore,
                    apiName: $this->apiName,
                )
            );
            $this->createdVector = new NestedVector($name, $props, $this->dims, $this->apiName);
            return $this->createdVector;
        }

        $type = new DenseVector(
            name: $name,
            dims: $this->dims,
            strategy: $this->strategy,
            index: $this->index,
            similarity: $this->similarity,
            efConstruction: $this->efConstruction,
            m: $this->m,
            apiName: $this->apiName,
            boostedByField: $this->boostedBy,
            autoNormalizeVector: $this->autoNormalizeVector,
        );

        $this->createdVector = $type;

        return $type;
    }
}
