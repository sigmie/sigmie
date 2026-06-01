<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Sigmie\SigmieIndex;

/**
 * Fetches specific documents from the index by their `_id`. Use after search_index or
 * sample_documents surface an `_id` the agent wants to read in full.
 *
 * Reuses the index's `collect()->getMany()` multi-get (Elasticsearch `_mget`). IDs that do not
 * exist are simply absent from the returned documents.
 */
class SigmieGetDocumentsTool implements Tool
{
    use HandlesToolErrors;

    public function __construct(
        protected SigmieIndex $index,
    ) {}

    public function name(): string
    {
        return 'get_documents';
    }

    public function description(): string
    {
        return sprintf(
            "Retrieve specific documents from the '%s' index by their `_id`. Pass the ids you already learned from search_index or sample_documents. Ids that do not exist are omitted from the result.",
            $this->index->name()
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'ids' => $schema->array()->items($schema->string())
                ->description('Document ids to retrieve (1-100)')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        return $this->guard(fn (): string => json_encode($this->result($request), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * The retrieved documents as an array, for callers that want data instead of the JSON string handle() returns.
     */
    public function result(Request $request): array
    {
        $ids = array_slice(array_values(array_filter(
            (array) ($request['ids'] ?? []),
            fn ($id): bool => is_string($id) && $id !== '',
        )), 0, 100);

        return $this->index->collect()->getMany($ids);
    }
}
