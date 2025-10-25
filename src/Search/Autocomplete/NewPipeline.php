<?php

declare(strict_types=1);

namespace Sigmie\Search\Autocomplete;

use Sigmie\Base\APIs\API;
use Sigmie\Base\APIs\Ingest;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Shared\Contracts\ToRaw;

class NewPipeline implements ToRaw
{
    use API;
    use Ingest;

    protected string $description;

    protected array $processors = [];

    public function __construct(
        ElasticsearchConnection $elasticsearchConnection,
        protected string $name
    ) {
        $this->setElasticsearchConnection($elasticsearchConnection);
    }

    public function toRaw(): array
    {
        $res = [
            'processors' => array_map(fn (Processor $processor): array => $processor->toRaw(), $this->processors),
        ];

        if ($this->description ?? false) {
            $res['description'] = $this->description;
        }

        return $res;
    }

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function addPocessor(Processor $processor): static
    {
        $this->processors[] = $processor;

        return $this;
    }

    public function create(): Pipeline
    {
        $this->ingestAPICall($this->name, 'PUT', $this->toRaw());

        return new Pipeline($this->name);
    }
}
