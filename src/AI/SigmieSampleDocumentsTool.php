<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Sigmie\SigmieIndex;

/**
 * Companion to {@see SigmieIndexTool}: returns a few random example documents so an agent can see
 * the real data shape and field values before searching or filtering.
 *
 * Reuses the index's `collect()->random()` sampler and the collection's `toJson()`.
 */
class SigmieSampleDocumentsTool implements Tool
{
    public function __construct(
        protected SigmieIndex $index,
    ) {}

    public function name(): string
    {
        return 'sample_documents';
    }

    public function description(): string
    {
        return sprintf(
            "Return a few RANDOM example documents from the '%s' index so you can see real field values and the data shape. Each call returns a different random sample, so you can call it again for more examples.",
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

        return $this->index->collect()->random($limit)->toJson(JSON_UNESCAPED_UNICODE);
    }
}
