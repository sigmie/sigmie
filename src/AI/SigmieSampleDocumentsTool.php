<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Sigmie\SigmieIndex;

/**
 * Companion to {@see SigmieIndexTool}: returns a few example documents so an agent can see the
 * real data shape and field formats before searching or filtering.
 */
class SigmieSampleDocumentsTool implements Tool
{
    public function __construct(
        protected SigmieIndex $index,
        protected string $baseFilters = '',
    ) {}

    public function name(): string
    {
        return 'sample_documents';
    }

    public function description(): string
    {
        return sprintf(
            "Return a few example documents from the '%s' index so you can see real field values and the data shape. Use a small limit.",
            $this->index->name()
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Number of example documents to return (1-20)')->default(5),
        ];
    }

    public function handle(Request $request): string
    {
        $limit = max(1, min(20, (int) ($request['limit'] ?? 5)));

        $search = $this->index->newSearch()->queryString('')->size($limit);

        if ($this->baseFilters !== '') {
            $search->filters($this->baseFilters);
        }

        $documents = array_map(
            fn ($hit): array => ['_id' => $hit->_id, ...$hit->_source],
            $search->get()->hits()
        );

        return json_encode(['documents' => $documents], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
