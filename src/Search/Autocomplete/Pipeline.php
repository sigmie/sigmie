<?php

declare(strict_types=1);

namespace Sigmie\Search\Autocomplete;

use Sigmie\Base\APIs\API;
use Sigmie\Base\APIs\Ingest;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\ElasticsearchResponse;

class Pipeline
{
    use API;
    use Ingest;

    public function __construct(
        ElasticsearchConnection $elasticsearchConnection,
        public readonly string $name
    ) {
        $this->setElasticsearchConnection($elasticsearchConnection);
    }

    public function simulate(array $docs = []): ElasticsearchResponse
    {
        foreach ($docs as $position => $doc) {
            $source = ((array) $doc)['_source'] ?? null;
            if (is_array($source) && array_is_list($source)) {
                $docs[$position] = [...(array) $doc, '_source' => (object) $source];
            }
        }

        return $this->ingestAPICall($this->name, 'POST', ['docs' => array_values($docs)], '/_simulate');
    }
}
