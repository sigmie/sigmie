<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\Type as AbstractType;

class DenseVector extends AbstractType implements Type
{
    public function __construct(
        public string $name,
        protected int $dims = 384,
        protected string $similarity = 'cosine',
        protected string $indexType = 'hnsw',
        protected int $m = 64,
        protected int $efConstruction = 300,
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
                'index' => true,
                'similarity' => $this->similarity,
                'index_options' => [
                    'type' => $this->indexType,
                    'm' => $this->m,
                    'ef_construction' => $this->efConstruction,
                ],
            ]
        ];

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
}
