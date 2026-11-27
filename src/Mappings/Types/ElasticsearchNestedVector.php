<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Enums\VectorSimilarity;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Nested as TypesNested;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\Term\Exists;
use Sigmie\Query\Queries\Text\Nested;

class ElasticsearchNestedVector extends TypesNested implements Type
{
    protected int $dims;

    protected VectorSimilarity $similarity;

    public function __construct(
        string $name,
        int $dims = 384,
        VectorSimilarity $similarity = VectorSimilarity::Cosine,
        protected bool $autoNormalizeVector = true,
    ) {
        $props = new NewProperties;
        $props->type(
            new DenseVector(
                name: 'vector',
                dims: $dims,
                index: false,
                similarity: $similarity,
            )
        );

        parent::__construct($name, $props);

        $this->dims = $dims;
        $this->similarity = $similarity;
    }

    public function dims(): int
    {
        return $this->dims;
    }

    protected function mapSimilarityToScript(VectorSimilarity $similarity): string
    {
        $fullPath = addcslashes($this->fullPath(), "'\\");
        $dotProduct = sprintf("dotProduct(params.query_vector, '_embeddings.%s.vector')", $fullPath);
        $innerProductScore = sprintf('double value = %s; return value < 0 ? 1 / (1 + -value) : value + 1;', $dotProduct);

        return match ($similarity) {
            VectorSimilarity::Cosine => sprintf("Math.max(0.0, cosineSimilarity(params.query_vector, '_embeddings.%s.vector') + 1.0)", $fullPath),
            VectorSimilarity::Euclidean => sprintf("1 / (1 + l2norm(params.query_vector, '_embeddings.%s.vector'))", $fullPath),
            VectorSimilarity::DotProduct => $this->autoNormalizeVector
                ? sprintf('Math.max(0.0, %s + 1.0)', $dotProduct)
                : $innerProductScore,
            VectorSimilarity::MaxInnerProduct => $innerProductScore,
        };
    }

    public function vectorQueries(array $vector, int $k, Boolean $filter): array
    {
        $path = '_embeddings.'.$this->fullPath();
        $source = $this->mapSimilarityToScript($this->similarity);

        return [
            new Nested(
                $path,
                new FunctionScore(
                    query: new Exists($path.'.vector'),
                    source: $source,
                    params: [
                        'query_vector' => array_values($vector),
                    ]
                )
            ),
        ];
    }
}
