<?php

declare(strict_types=1);

namespace Sigmie\Mappings;

use Exception;
use Sigmie\Enums\VectorSimilarity;
use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\Types\BaseVector;
use Sigmie\Mappings\Types\NestedVector;

class NewSemanticField
{
    protected VectorSimilarity $similarity = VectorSimilarity::Cosine;

    protected bool $similarityChosen = false;

    protected int $dims = 256;

    protected ?int $efConstruction = null;

    protected ?int $m = null;

    protected ?int $accuracyLevel = null;

    protected string $indexType = 'hnsw';

    protected bool $index = true;

    protected VectorStrategy $strategy = VectorStrategy::Concatenate;

    protected ?string $apiName = null;

    protected ?string $queryApiName = null;

    protected ?string $boostedBy = null;

    protected bool $autoNormalizeVector = true;

    protected string $fieldType = 'text';

    public function __construct(public string $name) {}

    public function cosineSimilarity(): static
    {
        return $this->similarity(VectorSimilarity::Cosine);
    }

    public function euclideanSimilarity(): static
    {
        return $this->similarity(VectorSimilarity::Euclidean);
    }

    public function dotProductSimilarity(): static
    {
        return $this->similarity(VectorSimilarity::DotProduct);
    }

    public function maxInnerProductSimilarity(): static
    {
        return $this->similarity(VectorSimilarity::MaxInnerProduct);
    }

    public function similarity(VectorSimilarity $similarity): static
    {
        $this->similarity = $similarity;
        $this->similarityChosen = true;

        return $this;
    }

    public function accuracy(int $level, ?int $dimensions = null): static
    {
        $dimensions ??= $this->dims;

        if ($dimensions < 1) {
            throw new Exception('Dimensions must be positive');
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
        $this->accuracyLevel = $level;
        $this->dims = $dimensions;
        $this->m = null;
        $this->efConstruction = null;

        return $this;
    }

    public function efConstruction(int $efConstruction): static
    {
        $this->efConstruction = $efConstruction;

        return $this;
    }

    public function m(int $m): static
    {
        $this->m = $m;

        return $this;
    }

    /**
     * Elasticsearch dense_vector index_options.type. Quantized types
     * (int8, int4, bbq) keep a compressed copy in memory; flat types
     * skip the HNSW graph and ignore m and ef_construction.
     */
    public function indexType(string $type): static
    {
        if (! in_array($type, [
            'hnsw',
            'int8_hnsw',
            'int4_hnsw',
            'bbq_hnsw',
            'flat',
            'int8_flat',
            'int4_flat',
            'bbq_flat',
        ])) {
            throw new Exception('Index type must be one of: hnsw, int8_hnsw, int4_hnsw, bbq_hnsw, flat, int8_flat, int4_flat, bbq_flat');
        }

        $this->indexType = $type;

        return $this;
    }

    public function dimensions(int $dims): static
    {
        $this->dims = $dims;

        return $this;
    }

    public function api(?string $apiName): static
    {
        $this->apiName = $apiName;

        return $this;
    }

    public function searchApi(?string $queryApiName): static
    {
        $this->queryApiName = $queryApiName;

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

        return $this;
    }

    public function fieldType(string $type): static
    {
        $this->fieldType = $type;

        return $this;
    }

    public function definition(): array
    {
        return [
            'accuracy' => $this->accuracyLevel,
            'dimensions' => $this->dims,
            'similarity' => $this->similarityChosen ? $this->similarity->value : null,
            'index_type' => $this->indexType,
            'm' => $this->m,
            'ef_construction' => $this->efConstruction,
            'api' => $this->apiName,
            'search_api' => $this->queryApiName,
            'boosted_by' => $this->boostedBy,
            'normalize' => $this->autoNormalizeVector,
            'field_type' => $this->fieldType,
        ];
    }

    public function restoreDefinition(array $definition): void
    {
        $this->dimensions($definition['dimensions']);
        if (isset($definition['accuracy'])) {
            $this->accuracy($definition['accuracy']);
        }

        if (isset($definition['similarity'])) {
            $this->similarity(VectorSimilarity::from($definition['similarity']));
        }

        if (isset($definition['m'])) {
            $this->m($definition['m']);
        }

        if (isset($definition['ef_construction'])) {
            $this->efConstruction($definition['ef_construction']);
        }

        if (isset($definition['boosted_by'])) {
            $this->boostedBy($definition['boosted_by']);
        }

        $this->indexType($definition['index_type'])
            ->api($definition['api'])
            ->searchApi($definition['search_api'])
            ->normalizeVector($definition['normalize'])
            ->fieldType($definition['field_type']);
    }

    public function make(): BaseVector|NestedVector
    {
        $similarity = match (true) {
            $this->similarityChosen => $this->similarity,
            $this->boostedBy !== null => VectorSimilarity::DotProduct,
            $this->fieldType === 'image' => VectorSimilarity::Euclidean,
            default => VectorSimilarity::Cosine,
        };

        [$m, $efConstruction] = match ($this->accuracyLevel) {
            1 => [12, 60],
            2 => [16, 100],
            3 => [24, 150],
            4 => [32, 200],
            5 => [40, 300],
            6 => [48, 400],
            default => [64, 300],
        };

        if ($this->index && $this->accuracyLevel !== null) {
            $scale = sqrt($this->dims / 256);
            $m = min((int) round($m * $scale), 64);
            $efConstruction = min((int) round($efConstruction * $scale), 500);
        }

        $m = $this->m ?? $m;
        $efConstruction = $this->efConstruction ?? $efConstruction;

        // The name is the field path, so each index type gets its own field.
        // Plain hnsw keeps the unprefixed name of existing indices.
        $name = match (true) {
            ! $this->index => 'exact_dims'.$this->dims,
            $this->indexType === 'hnsw' => 'm'.$m.'_efc'.$efConstruction.'_dims'.$this->dims,
            str_ends_with($this->indexType, 'flat') => $this->indexType.'_dims'.$this->dims,
            default => $this->indexType.'_m'.$m.'_efc'.$efConstruction.'_dims'.$this->dims,
        }.'_'.$similarity->value.'_'.$this->strategy->suffix();

        if (! $this->index) {
            return new NestedVector(
                $name,
                $this->dims,
                $this->apiName,
                $this->strategy,
                $similarity,
                $this->queryApiName,
                $this->boostedBy,
                $this->autoNormalizeVector
            );
        }

        return new BaseVector(
            name: $name,
            dims: $this->dims,
            index: $this->index,
            similarity: $similarity,
            strategy: $this->strategy,
            indexType: $this->indexType,
            m: $m,
            efConstruction: $efConstruction,
            apiName: $this->apiName,
            boostedByField: $this->boostedBy,
            autoNormalizeVector: $this->autoNormalizeVector,
            queryApiName: $this->queryApiName,
        );
    }
}
