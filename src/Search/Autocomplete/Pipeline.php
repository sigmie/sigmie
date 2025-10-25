<?php

declare(strict_types=1);

namespace Sigmie\Search\Autocomplete;

use Sigmie\Base\APIs\API;
use Sigmie\Base\APIs\Ingest;
use Sigmie\Base\Contracts\ElasticsearchResponse;

class Pipeline
{
    use API;
    use Ingest;

    public function __construct(
        public readonly string $name
    ) {}

    public function simulate(array $docs = []): ElasticsearchResponse
    {
        return $this->ingestAPICall($this->name.'/_simulate', 'POST', ['docs' => $docs]);
    }
}
