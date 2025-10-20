<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Nested as TypesNested;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\Text\Nested;

class NestedVector extends TypesNested
{
    public ?string $apiName = null;

    protected int $dims;

    public function __construct(
        string $name,
        NewProperties $properties,
        int $dims = 384,
        ?string $apiName = null,
    ) {
        parent::__construct($name, $properties);

        $this->dims = $dims;
        $this->apiName = $apiName;
    }

    public function dims(): int
    {
        return $this->dims;
    }

    public function queries(array|string $vector, ?\Sigmie\Base\Contracts\SearchEngineDriver $driver = null, array $filter = []): array
    {
        // OpenSearch uses doc['field'] syntax, Elasticsearch uses 'field' string syntax
        if ($driver && $driver->engine() === \Sigmie\Enums\SearchEngine::OpenSearch) {
            $source = "cosineSimilarity(params.query_vector, doc['embeddings.{$this->fullPath}.vector']) + 1.0";
        } else {
            $source = "cosineSimilarity(params.query_vector, 'embeddings.{$this->fullPath}.vector') + 1.0";
        }

        // For nested queries, don't apply root-level filters inside the nested query
        // Filters will be handled at the top level of the search
        $baseQuery = new MatchAll();

        return  [
            new Nested(
                "embeddings.{$this->fullPath}",
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
