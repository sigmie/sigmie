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
        return [
            'field' => $schema->string()->description('Facetable field to list values for (from the search tool field list)')->required(),
            'filters' => $schema->string()->description('Optional filter expression, same DSL as the search tool, to narrow values (e.g. "field:nav*")'),
            'limit' => $schema->integer()->description('Max values to return')->default(self::DEFAULT_LIMIT),
        ];
    }

    public function handle(Request $request): string
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

        return json_encode([
            'field' => $fieldName,
            'values' => $facets[$fieldName] ?? null,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
