<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Enums\VectorSimilarity;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\OpenSearchKnn;

class KnnVector extends DenseVector
{
    public string $type = 'knn_vector';

    public function __construct(
        string $name,
        int $dims = 384,
        bool $index = true,
        VectorSimilarity $similarity = VectorSimilarity::Cosine,
        ?int $m = 64,
        ?int $efConstruction = 300,
    ) {
        parent::__construct($name, $dims, $index, $similarity, 'hnsw', $m, $efConstruction);
    }

    public static function fromMapping(string $name, array $mapping): self
    {
        $method = (array) ($mapping['method'] ?? []);
        $space = $method['space_type'] ?? $mapping['space_type'] ?? 'l2';
        $parameters = (array) ($method['parameters'] ?? []);
        $field = new self(
            name: $name,
            dims: $mapping['dimension'] ?? 384,
            similarity: match ($space) {
                'l2' => VectorSimilarity::Euclidean,
                'innerproduct' => VectorSimilarity::DotProduct,
                default => VectorSimilarity::Cosine,
            },
            m: $parameters['m'] ?? 64,
            efConstruction: $parameters['ef_construction'] ?? 300,
        );
        $field->indexType = $method['name'] ?? 'hnsw';

        // JSON decoding turns empty parameter objects into PHP lists.
        $encoder = (array) ($parameters['encoder'] ?? []);
        if (($encoder['parameters'] ?? null) === []) {
            $encoder['parameters'] = (object) [];
            $parameters['encoder'] = $encoder;
            $method['parameters'] = $parameters;
            $mapping['method'] = $method;
        }

        if (($method['parameters'] ?? null) === []) {
            $method['parameters'] = (object) [];
            $mapping['method'] = $method;
        }

        $field->restoreMapping($mapping);

        return $field;
    }

    protected function vectorMapping(): array
    {
        $mapping = [
            'type' => $this->type,
            'dimension' => $this->dims,
        ];

        if ($this->index) {
            $mapping['method'] = [
                'name' => 'hnsw',
                'space_type' => $this->mapSimilarity($this->similarity),
                'engine' => 'lucene',  // Changed from 'nmslib' to 'lucene' for OpenSearch 3.0+
            ];

            $parameters = array_filter([
                'm' => $this->m,
                'ef_construction' => $this->efConstruction,
            ], static fn (?int $value): bool => $value !== null);
            if ($parameters !== []) {
                $mapping['method']['parameters'] = $parameters;
            }
        }

        return $mapping;
    }

    protected function mapSimilarity(VectorSimilarity $similarity): string
    {
        return match ($similarity) {
            VectorSimilarity::Cosine => 'cosinesimil',
            VectorSimilarity::DotProduct => 'innerproduct',
            VectorSimilarity::Euclidean => 'l2',
            VectorSimilarity::MaxInnerProduct => 'innerproduct',
        };
    }

    public function vectorQueries(array $vector, int $k, Boolean $filter): array
    {
        return [
            new OpenSearchKnn(
                field: '_embeddings.'.$this->fullPath(),
                queryVector: $vector,
                k: min(10000, $k),
                filter: $filter->toRaw(),
                boost: 1.0
            ),
        ];
    }
}
