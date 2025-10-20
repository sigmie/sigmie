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

class NestedDenseVector extends AbstractType implements Type
{
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

    public function queries(array|string $vector): array
    {
        if ($this->index) {
            return [
                new NearestNeighbors(
                    "_embeddings.name.{$this->name}",
                    $vector,
                    // // k: $this->dims,
                    numCandidates: $this->efConstruction * 2
                )
            ];
        }

        $source = "";
        $source .= "double maxSim = 0;";
        $source .= "for (int i = 0; i < doc['_embeddings.name.{$this->name}'].length; i++) {";
        $source .= "  double sim = cosineSimilarity(params.query_vector, doc['_embeddings.name.{$this->name}'][i]);";
        $source .= "  if (sim > maxSim) maxSim = sim;";
        $source .= "}";
        $source .= "return maxSim;";

        return [
            new FunctionScore(
                query: new MatchAll(),
                source: $source,
                boostMode: 'replace', // Doesn't matter, because of match all 
                params: [
                    'query_vector' => $vector
                ]
            )
        ];
    }
}
