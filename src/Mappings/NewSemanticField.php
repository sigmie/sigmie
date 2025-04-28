<?php

declare(strict_types=1);

namespace Sigmie\Mappings;

use Exception;
use Sigmie\Mappings\Types\DenseVector;

class NewSemanticField
{
    protected string $similarity = 'cosine';

    protected int $dims = 256;

    protected int $efConstruction = 300;

    protected int $m = 64;

    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function cosineSimilarity()
    {
        $this->similarity = 'cosine';

        return $this;
    }

    public function euclideanSimilarity()
    {
        $this->similarity = 'l2_norm';

        return $this;
    }

    public function dotProductSimilarity()
    {
        $this->similarity = 'dot_product';

        return $this;
    }

    public function maxInnerProductSimilarity()
    {
        $this->similarity = 'max_inner_product';

        return $this;
    }

    public function accuracy(int $level, ?int $dimensions = null)
    {
        $dimensions = $dimensions ?? $this->dims;

        if ($level < 1 || $level > 6) {
            throw new Exception('Accuracy level must be between 1 and 6');
        }

        // Base values for 256 dimensions
        $base = [
            1 => [16, 80],
            2 => [24, 128],
            3 => [32, 200],
            4 => [48, 300],
            5 => [64, 400],
            6 => [80, 512],
        ];

        [$baseM, $baseEf] = $base[$level];

        // Scale factor relative to 256 dims
        $scale = $dimensions / 256;

        // Scale with upper bounds to avoid unbounded growth
        $this->m = min((int) round($baseM * $scale), 128);
        $this->efConstruction = min((int) round($baseEf * $scale), 1000);
        $this->dims = $dimensions;

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

    public function make(): DenseVector
    {
        return new DenseVector(
            name: $this->name,
            dims: $this->dims,
            similarity: $this->similarity,
            efConstruction: $this->efConstruction,
            m: $this->m,
        );
    }
}
