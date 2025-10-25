<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Enums\VectorSimilarity;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Nested as TypesNested;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\Text\Nested;

class ElasticsearchNestedVector extends TypesNested implements Type
{
    public ?string $apiName = null;

    protected int $dims;

    protected VectorSimilarity $similarity;

    public function __construct(
        string $name,
        int $dims = 384,
        ?string $apiName = null,
        VectorSimilarity $similarity = VectorSimilarity::Cosine,
    ) {
        $props = new NewProperties;
        $props->type(
            new DenseVector(
                name: 'vector',
                dims: $dims,
                similarity: $similarity,
            )
        );

        parent::__construct($name, $props);

        $this->dims = $dims;
        $this->apiName = $apiName;
        $this->similarity = $similarity;
    }

    public function dims(): int
    {
        return $this->dims;
    }

    protected function mapSimilarityToScript(VectorSimilarity $similarity): string
    {
        return match ($similarity) {
            VectorSimilarity::Cosine => "cosineSimilarity(params.query_vector, '_embeddings.{$this->fullPath}.vector') + 1.0",
            VectorSimilarity::DotProduct => "dotProduct(params.query_vector, '_embeddings.{$this->fullPath}.vector')",
            VectorSimilarity::Euclidean => "1 / (1 + l2norm(params.query_vector, '_embeddings.{$this->fullPath}.vector'))",
            VectorSimilarity::MaxInnerProduct => "dotProduct(params.query_vector, '_embeddings.{$this->fullPath}.vector')",
        };
    }

    public function vectorQueries(array $vector, int $k, Boolean $filter): array
    {
        $source = $this->mapSimilarityToScript($this->similarity);

        return [
            new Nested(
                "_embeddings.{$this->fullPath}",
                new FunctionScore(
                    query: $filter,
                    source: $source,
                    boostMode: 'replace',
                    params: [
                        'query_vector' => $vector,
                    ]
                )
            ),
        ];
    }
}
