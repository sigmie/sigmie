<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Enums\VectorSimilarity;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Nested as TypesNested;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\Text\Nested;

class OpenSearchNestedVector extends TypesNested implements Type
{
    protected int $dims;

    protected VectorSimilarity $similarity;

    public function __construct(
        string $name,
        int $dims = 384,
        VectorSimilarity $similarity = VectorSimilarity::Cosine,
        ?string $fullPath = '',
    ) {
        $props = new NewProperties;
        $props->type(
            new KnnVector(
                name: 'vector',
                dims: $dims,
                similarity: $similarity,
                fullPath: $fullPath ? $fullPath.'.vector' : 'vector',
            )
        );

        parent::__construct($name, $props, $fullPath);

        $this->dims = $dims;
        $this->similarity = $similarity;
    }

    public function dims(): int
    {
        return $this->dims;
    }

    protected function mapSimilarityToScript(VectorSimilarity $similarity): string
    {
        return match ($similarity) {
            VectorSimilarity::Cosine => sprintf("cosineSimilarity(params.query_vector, doc['_embeddings.%s.vector']) + 1.0", $this->fullPath),
            VectorSimilarity::DotProduct => sprintf("dotProduct(params.query_vector, doc['_embeddings.%s.vector'])", $this->fullPath),
            VectorSimilarity::Euclidean => sprintf("1 / (1 + l2norm(params.query_vector, doc['_embeddings.%s.vector']))", $this->fullPath),
            VectorSimilarity::MaxInnerProduct => sprintf("dotProduct(params.query_vector, doc['_embeddings.%s.vector'])", $this->fullPath),
        };
    }

    public function vectorQueries(array $vector, int $k, Boolean $filter): array
    {
        $source = $this->mapSimilarityToScript($this->similarity);

        // Note: We use MatchAll() here instead of $filter because nested queries operate
        // in a different scope. The $filter parameter contains parent-level filters
        // (e.g., "active:true") which cannot be applied inside a nested query context.
        // Parent-level filters are applied by the outer query structure in NewSearch
        // that wraps these nested vector queries.
        return [
            new Nested(
                '_embeddings.'.$this->fullPath,
                new FunctionScore(
                    query: new MatchAll,
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
