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
    public function __construct(
        public string $name,
        protected int $dims = 384,
    ) {
        $props = new NewProperties();
        $props->type(
            new SigmieVector(
                name: 'vector',
                dims: $this->dims,
                strategy: VectorStrategy::ScriptScore,
            )
        );

        parent::__construct($name, $props);
    }

    public function dims(): int
    {
        return $this->dims;
    }

    public function queries(array|string $vector): array
    {
        $source = "1.0+cosineSimilarity(params.query_vector, '{$this->fullPath}.vector')";

        return  [
            new Nested(
                $this->fullPath,
                new FunctionScore(
                    query: new MatchAll(),
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
