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
            throw new Exception('Dimensions must be one of: 16, 24, 32, 48, 64, 80, 128, 200, 256, 300, 384, 400, 512, 1024, 1536, 2048, 3072');
        }

        if ($level < 1 || $level > 7) {
            throw new Exception('Accuracy level must be between 1 and 7');
        }

        $this->strategy = match ($level) {
            1 => VectorStrategy::Concatenate,
            2 => VectorStrategy::Concatenate,
            3 => VectorStrategy::Average,
            4 => VectorStrategy::Average,
            5 => VectorStrategy::Average,
            6 => VectorStrategy::Average,
            7 => VectorStrategy::ScriptScore,
        };

        $this->index = $level < 7;

        // Base values for 256 dimensions
        $base = [
            1 => [16, 80],
            2 => [24, 128],
            3 => [32, 200],
            4 => [48, 300],
            5 => [64, 400],
            6 => [80, 512],
        ];

        $this->dims = $dimensions;

        // Scale factor relative to 256 dims

        if ($level < 7) {
            [$baseM, $baseEf] = $base[$level];
            $scale = $dimensions / 256;

            $this->m = min((int) round($baseM * $scale), 128);
            $this->efConstruction = min((int) round($baseEf * $scale), 1000);
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

    public function make(): Type
    {
        $name = match ($this->index) {
            true => 'm' . $this->m . '_efc' . $this->efConstruction . '_dims' . $this->dims . '_' . $this->similarity->value . '_' . $this->strategy->suffix(),
            false => 'exact_dims' . $this->dims . '_' . $this->similarity->value . '_' . $this->strategy->suffix(),
        };

        if (!$this->index) {
            return new NestedVector($name, $this->dims);
        }

        $type = new SigmieVector(
            name: $name,
            dims: $this->dims,
            strategy: $this->strategy,
            index: $this->index,
            similarity: $this->similarity,
            efConstruction: $this->efConstruction,
            m: $this->m,
        );

        return $type;
    }
}
