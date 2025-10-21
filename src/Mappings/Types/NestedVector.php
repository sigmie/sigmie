<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Nested as TypesNested;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\Text\Nested;

/**
 * Utility helper for vector fields in nested structures
 * Not instantiated directly - use ElasticsearchNestedVector or OpenSearchNestedVector instead
 */
class NestedVector extends TypesNested
{
    public function __construct(
        string $name,
        public readonly int $dims,
        public readonly string $apiName,
        public readonly VectorStrategy $strategy = VectorStrategy::Concatenate,
    ) {
        $props = new NewProperties();
        $props->type(
            new BaseVector(
                name: 'vector',
                dims: $dims,
                strategy: $strategy,
            )
        );

        parent::__construct($name, $props);
    }

    public function dims(): int
    {
        return $this->dims;
    }

    public function queries(array|string $vector, ?\Sigmie\Base\Contracts\SearchEngine $driver = null, array $filter = []): array
    {
        // OpenSearch uses doc['field'] syntax, Elasticsearch uses 'field' string syntax
        if ($driver && $driver->engine() === \Sigmie\Enums\SearchEngineType::OpenSearch) {
            $source = "cosineSimilarity(params.query_vector, doc['_embeddings.{$this->fullPath}.vector']) + 1.0";
        } else {
            $source = "cosineSimilarity(params.query_vector, '_embeddings.{$this->fullPath}.vector') + 1.0";
        }

        // For nested queries, don't apply root-level filters inside the nested query
        // Filters will be handled at the top level of the search
        $baseQuery = new MatchAll();

        return  [
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
