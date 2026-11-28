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

class OpenSearchNestedVector extends TypesNested implements Type
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
            new KnnVector(
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
        $dotProduct = <<<'PAINLESS'
            float[] vector = doc[params.field].value;
            double product = 0;
            for (int i = 0; i < vector.length; i++) {
                product += vector[i] * params.query_vector[i];
            }
            PAINLESS;

        $innerProductScore = $dotProduct."\nreturn product < 0 ? 1 / (1 - product) : product + 1;";

        return match ($similarity) {
            VectorSimilarity::Cosine => 'cosineSimilarity(params.query_vector, doc[params.field]) + 1.0',
            VectorSimilarity::Euclidean => '1 / (1 + Math.sqrt(l2Squared(params.query_vector, doc[params.field])))',
            VectorSimilarity::DotProduct => $this->autoNormalizeVector
                ? $dotProduct."\nreturn Math.max(0.0, product + 1);"
                : $innerProductScore,
            VectorSimilarity::MaxInnerProduct => $innerProductScore,
        };
    }

    public function vectorQueries(array $vector, int $k, Boolean $filter): array
    {
        $path = '_embeddings.'.$this->fullPath();
        $field = $path.'.vector';
        $source = $this->mapSimilarityToScript($this->similarity);

        return [
            new Nested(
                $path,
                new FunctionScore(
                    query: new Exists($field),
                    source: $source,
                    // OpenSearch drops outer boosts in replace mode. The exists query scores the boost.
                    boostMode: 'multiply',
                    params: [
                        'query_vector' => array_values($vector),
                        'field' => $field,
                    ]
                )
            ),
        ];
    }
}
