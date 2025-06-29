<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Enums\VectorSimilarity;
use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\Type as AbstractType;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\NearestNeighbors;
use Sigmie\Query\Queries\Text\Nested;

class SigmieVector extends DenseVector 
{
    public ?string $textFieldName = null;

    public function __construct(
        public string $name,
        protected int $dims = 384,
        protected bool $index = true,
        protected VectorSimilarity $similarity = VectorSimilarity::Cosine,
        protected VectorStrategy $strategy = VectorStrategy::Concatenate,
        protected string $indexType = 'hnsw',
        protected ?int $m = 64,
        protected ?int $efConstruction = 300,
        protected ?float $confidenceInterval = null,
        protected ?int $oversample = null,
    ) {
        $this->type = 'dense_vector';
    }

    public function toRaw(): array
    {
        $raw = [
            $this->name => [
                'type' => $this->type,
                'dims' => $this->dims,
                'index' => $this->index,
            ]
        ];

        if ($this->index) {
            $raw[$this->name]['similarity'] = $this->similarity->value;
            $raw[$this->name]['index_options'] = [
                'type' => $this->indexType,
                'm' => $this->m,
                'ef_construction' => $this->efConstruction,
            ];
        }

        if ($this->confidenceInterval !== null) {
            $raw['index_options']['confidence_interval'] = $this->confidenceInterval;
        }

        if ($this->oversample !== null) {
            $raw['index_options']['rescore_vector'] = [
                'oversample' => $this->oversample,
            ];
        }

        return $raw;
    }

    public function strategy(): VectorStrategy
    {
        return $this->strategy;
    }

    public function dims(): int
    {
        return $this->dims;
    }

    public function createSuffix(): string
    {
        if (!$this->index) {
            return 'exact';
        }

        $suffix = 'm' . $this->m . '_efc' . $this->efConstruction . '_dims' . $this->dims . '_' . $this->similarity->value;

        return $suffix;
    }

    public function textFieldName(string $name): static
    {
        $this->textFieldName = $name;

        return $this;
    }

    public function embeddingsName(): string
    {
        return "{$this->textFieldName}.{$this->name}";
    }

    public function queries(array|string $vector): array
    {
        if ($this->index) {
            return [
                new NearestNeighbors(
                    $this->fullPath,
                    $vector,
                    // // k: $this->dims,
                    numCandidates: $this->efConstruction * 2
                )
            ];
        }

        // $source = "1.0+cosineSimilarity(params.query_vector, '{$this->fullPath}')";

        // $query = [
        //     new Nested(
        //         $this->fullPath,
        //         new FunctionScore(
        //             query: new MatchAll(),
        //             source: $source,
        //             boostMode: 'replace',
        //             params: [
        //                 'query_vector' => $vector
        //             ]
        //         )
        //     )
        // ];

        return [];

        return $query;
    }
}
