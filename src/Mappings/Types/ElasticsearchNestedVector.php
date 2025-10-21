<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Nested as TypesNested;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\Text\Nested;

class ElasticsearchNestedVector extends TypesNested implements Type
{
    public ?string $apiName = null;

    protected int $dims;

    public function __construct(
        string $name,
        int $dims = 384,
        ?string $apiName = null,
    ) {
        $props = new NewProperties();
        $props->type(
            new DenseVector(
                name: 'vector',
                dims: $dims,
            )
        );

        parent::__construct($name, $props);

        $this->dims = $dims;
        $this->apiName = $apiName;
    }

    public function dims(): int
    {
        return $this->dims;
    }

    public function vectorQueries(array $vector, int $k, array $filter = []): array
    {
        // Elasticsearch uses 'field' string syntax
        $source = "cosineSimilarity(params.query_vector, '_embeddings.{$this->fullPath}.vector') + 1.0";

        // For nested queries, don't apply root-level filters inside the nested query
        // Filters will be handled at the top level of the search
        $baseQuery = new MatchAll();

        return [
            new Nested(
                "_embeddings.{$this->fullPath}",
                new FunctionScore(
                    query: $baseQuery,
                    source: $source,
                    boostMode: 'replace',
                    params: [
                        'query_vector' => $vector
                    ]
                )
            )
        ];
    }
}
