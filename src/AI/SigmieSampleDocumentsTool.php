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
    use HandlesToolErrors;

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
        // `limit` is `nullable()->required()` so the schema is valid under OpenAI's strict
        // function-calling. Callers that want the default simply pass null.
        return [
            'limit' => $schema->integer()->description('Number of example documents to return (1-20, default 5)')->default(5)->nullable()->required(),
        ];
    }

    public function handle(Request $request): string
    {
        return $this->guard(fn (): string => json_encode($this->result($request), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * The sample documents as an array, for callers that want data instead of the JSON string handle() returns.
     */
    public function result(Request $request): array
    {
        $limit = max(1, min(20, (int) ($request['limit'] ?? 5)));

        // Documents serialise to {_id, _source} via their JsonSerializable.
        return $this->index->collect()->random($limit)->toArray();
    }
}
