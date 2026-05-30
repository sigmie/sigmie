<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Sigmie\SigmieIndex;

/**
 * Companion to {@see SigmieIndexTool}: discover the valid values of a facetable field at query
 * time, so the agent can filter accurately instead of guessing.
 *
 * Thin by design — it requests a facet on the field and returns the engine's own parsed result
 * (value counts for keyword/category, min/max/histogram for numeric/date). Field validity and
 * per-type parsing are handled by the search/facet layer; an unknown or non-facetable field
 * simply yields no values.
 */
class SigmieFilterValuesTool implements Tool
{
    use HandlesToolErrors;

    public function __construct(
        protected SigmieIndex $index,
        protected string $baseFilters = '',
    ) {}

    private const DEFAULT_LIMIT = 20;

    public function name(): string
    {
        return 'discover_filter_values';
    }

    public function description(): string
    {
        return sprintf(
            "List the valid values of a facetable field of the '%s' index so you can filter accurately — call this when you do not know a field's values. Provide `field` (a facetable field from the search tool's field list) and optional `filters` (same DSL as the search tool, e.g. \"field:nav*\" to prefix-match, or filter another field to narrow). Keyword/category fields return value counts; numeric/date fields return min/max.",
            $this->index->name()
        );
    }

    public function schema(JsonSchema $schema): array
    {
        // Optional params are `nullable()->required()` so the schema is also valid under
        // OpenAI's strict function-calling. Callers that don't want to scope or change the
        // limit simply pass null.
        return [
            'field' => $schema->string()->description('Facetable field to list values for (from the search tool field list)')->required(),
            'filters' => $schema->string()->description('Filter expression to narrow values (pass null for none, same DSL as the search tool, e.g. "field:nav*")')->nullable()->required(),
            'limit' => $schema->integer()->description('Max values to return (default '.self::DEFAULT_LIMIT.')')->default(self::DEFAULT_LIMIT)->nullable()->required(),
        ];
    }

    public function handle(Request $request): string
    {
        return $this->guard(fn (): string => json_encode($this->result($request), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * The discovered values as an array, for callers that want data instead of the JSON string handle() returns.
     */
    public function result(Request $request): array
    {
        $fieldName = trim((string) ($request['field'] ?? ''));
        $limit = max(1, (int) ($request['limit'] ?? self::DEFAULT_LIMIT));

        $search = $this->index->newSearch()
            ->queryString('')
            ->facets(sprintf('%s:%d', $fieldName, $limit))
            ->size(0);

        $filters = array_values(array_filter([
            $this->baseFilters,
            trim((string) ($request['filters'] ?? '')),
        ], static fn (string $f): bool => $f !== ''));

        if ($filters !== []) {
            $search->filters(implode(' AND ', array_map(static fn (string $f): string => sprintf('(%s)', $f), $filters)));
        }

        $facets = (array) ($search->get()->json('facets') ?? []);

        return [
            'field' => $fieldName,
            'values' => $facets[$fieldName] ?? null,
        ];
    }
}
