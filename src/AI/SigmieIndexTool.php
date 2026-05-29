<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Sigmie\SigmieIndex;

/**
 * Exposes a Sigmie index as a Laravel AI SDK tool.
 *
 * Requires `laravel/ai` to be installed.
 *
 * Usage:
 *   new SigmieIndexTool(app(ProductIndex::class))
 *   new SigmieIndexTool(app(OrderIndex::class), baseFilters: "user_id:{$user->id}")
 *
 *   // In your agent:
 *   public function tools(): array
 *   {
 *       return [
 *           new SigmieIndexTool(app(ProductIndex::class)),
 *       ];
 *   }
 */
class SigmieIndexTool implements Tool
{
    use DescribesIndexFields;
    use HandlesToolErrors;

    public function __construct(
        protected SigmieIndex $index,
        protected string $baseFilters = '',
        protected bool $describeFields = true,
    ) {}

    public function description(): string
    {
        // Lean mode: omit the per-field list and operator docs from the always-present description
        // (keeps the system prompt small when many indices are registered) and point the agent to
        // describe_index for the schema on demand. Still lists field NAMES so it can decide to search.
        if (! $this->describeFields) {
            $names = array_column($this->fieldsSchema(), 'name');

            return sprintf(
                "Search the '%s' index. Fields: %s. Before building filters or sorts, call describe_index for each field's type and meaning and the exact filter/sort/facet syntax.",
                $this->index->name(),
                implode(', ', $names)
            );
        }

        $properties = $this->index->properties()->get();

        $fieldDescriptions = $this->collectFieldDescriptions($properties->toArray());

        $description = sprintf("Search the '%s' index.", $this->index->name());

        if ($fieldDescriptions !== []) {
            $description .= "\n\nAvailable fields:\n".implode("\n", $fieldDescriptions);
        }

        return $description.("\n\n"
            ."Filter operators: AND, OR, AND NOT\n"
            ."Negation: NOT field:'value'\n"
            ."Grouping: (field:'a' OR field:'b') AND other>10\n"
            ."Exists check: field:*\n"
            ."Sort: space-separated list of 'field:asc' or 'field:desc' — the direction goes after a COLON (e.g. 'price:asc name:desc'), plus '_score'. A space before the direction ('price asc') is INVALID.\n"
            ."Geo sort: field[lat,lon]:km:asc\n"
            ."Facets: field1 field2:20 (space-separated, optional :size for keywords or :interval for numbers)\n"
            ."Matching: equality filters on text/keyword fields are exact and CASE-SENSITIVE — if a filter returns 0 unexpectedly, call discover_filter_values to confirm the exact stored value.\n"
            ."Discovering valid values: if you do not know a field's valid values, call discover_filter_values with the field name (and optional query) before filtering.\n"
            .'Schema: call describe_index for the full structured field list, types and filter syntax.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Search query text')->required(),
            'filters' => $schema->string()->description('Filter expression'),
            'sort' => $schema->string()->description('Sort expression'),
            'facets' => $schema->string()->description('Space-separated facet fields'),
            'facet_filters' => $schema->string()->description('Active facet filter values'),
            'per_page' => $schema->integer()->description('Number of results per page')->default(10),
            'page' => $schema->integer()->description('Page number')->default(1),
        ];
    }

    public function handle(Request $request): string
    {
        return $this->guard(fn (): string => json_encode($this->result($request), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    /**
     * The search result as an array, for callers that want data instead of the JSON string handle() returns.
     */
    public function result(Request $request): array
    {
        $search = $this->index->newSearch()
            ->queryString((string) ($request['query'] ?? ''))
            ->page(
                $request->integer('page', 1),
                $request->integer('per_page', 10),
            );

        $filterParts = [];

        if ($this->baseFilters !== '') {
            $filterParts[] = sprintf('(%s)', $this->baseFilters);
        }

        if ($aiFilters = $request['filters'] ?? null) {
            $filterParts[] = sprintf('(%s)', $aiFilters);
        }

        if ($filterParts !== []) {
            $search->filters(implode(' AND ', $filterParts));
        }

        if ($sort = $request['sort'] ?? null) {
            $search->sort((string) $sort);
        }

        if ($facets = $request['facets'] ?? null) {
            $search->facets(
                (string) $facets,
                (string) ($request['facet_filters'] ?? ''),
            );
        }

        $response = $search->get();

        $hits = array_map(
            fn ($hit) => ['_id' => $hit->_id, ...$hit->_source],
            $response->hits()
        );

        $result = [
            'total' => $response->total(),
            'hits' => $hits,
        ];

        if ($facets) {
            $result['facets'] = $response->json('facets');
        }

        return $result;
    }
}
