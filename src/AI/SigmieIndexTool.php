<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Sigmie\Parse\Parser;
use Sigmie\Search\InnerHits;
use Sigmie\SigmieIndex;

use function Sigmie\Functions\merge_named;

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
    use NamesIndexTools;
    use ScopesToBaseFilters;
    use SelectsOutputFields;

    public function __construct(
        protected SigmieIndex $index,
        protected string $baseFilters = '',
    ) {}

    public function name(): string
    {
        return $this->toolName('search_index');
    }

    public function description(): string
    {
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
            ."Facets: field1 field2:20 (space-separated; :size for term fields, :interval for price; number, date, and datetime take no parameter)\n"
            ."Matching: equality on keyword() is case-insensitive because its normalizer lowercases. Equality on a text field's keyword subfield and on caseSensitiveKeyword() is case-sensitive. If a filter returns 0 unexpectedly, call {$this->toolName('discover_filter_values')} to confirm the exact stored value.\n"
            ."Output: each hit holds `_id` plus the source fields in `fields` (null uses the index default).\n"
            ."Matches: to see WHICH items of a nested field matched the query or a nested filter, list nested paths in `matches` (e.g. 'sections' for whole items, 'sections.text' for one field of them), each optionally with ':size' to cap its items (1-".InnerHits::MAX_SIZE.', default '.InnerHits::MAX_SIZE."), e.g. 'sections.text:5 comments'. Each hit then holds `_matches` with, per path, `total` (all matching items) and `items` (each with `_offset`, its position in that array), e.g. {\"_matches\": {\"sections\": {\"total\": 2, \"items\": [{\"_offset\": 4, \"text\": \"...\"}]}}}. Combine with `fields` that leave the nested field out to get only the matching items instead of the whole array. `total` above the number of items means the list is capped.\n"
            ."Discovering valid values: if you do not know a field's valid values, call {$this->toolName('discover_filter_values')} with the field name and optional `filters` before filtering.\n"
            ."Schema: call {$this->toolName('describe_index')} for the full structured field list, types and filter syntax.");
    }

    public function schema(JsonSchema $schema): array
    {
        // Every parameter is declared `nullable()->required()` so the schema works under
        // OpenAI's strict function-calling (which demands every property appear in `required`)
        // AND keeps the same UX for callers that simply omit a value (pass null instead).
        return [
            'query' => $schema->string()->description('Search query text')->required(),
            'filters' => $schema->string()->description('Filter expression (pass null when not filtering)')->nullable()->required(),
            'sort' => $schema->string()->description('Sort expression (pass null for relevance)')->nullable()->required(),
            'facets' => $schema->string()->description('Space-separated facet fields (pass null when not faceting)')->nullable()->required(),
            'facet_filters' => $schema->string()->description('Active facet filter values (pass null when no facet filters)')->nullable()->required(),
            'per_page' => $schema->integer()->min(0)->max(2147483647)->description('Number of results per page (default 10)')->default(10)->nullable()->required(),
            'page' => $schema->integer()->min(1)->max(2147483647)->description('Page number (default 1)')->default(1)->nullable()->required(),
            'fields' => $this->outputFieldsSchema($schema),
            'matches' => $schema->string()->description("Nested paths whose matching items you want back in `_matches`, separated by spaces or commas, each optionally 'path:size' (size 1-".InnerHits::MAX_SIZE.', default '.InnerHits::MAX_SIZE."), e.g. 'sections.text:5 comments' (pass null for none). Use with `fields` to keep the rest small.")->nullable()->required(),
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
        $sort = $this->textArgument($request['sort'] ?? null);
        $facets = $this->textArgument($request['facets'] ?? null);
        $facetFilters = $this->textArgument($request['facet_filters'] ?? null) ?? '';
        $filters = $this->textArgument($request['filters'] ?? null);

        $this->refusePrivateFields(
            ...$this->expressionFields($sort),
            ...$this->expressionFields($facets),
        );

        $search = $this->index->newSearch()
            ->queryString((string) ($request['query'] ?? ''))
            ->retrieve($this->outputFields($request['fields'] ?? null))
            ->except($this->index->exceptFromTools())
            ->page(
                $request->integer('page', 1),
                $request->integer('per_page', 10),
            );

        $this->applyBaseFilters($search);

        foreach ($this->matchRequests($request['matches'] ?? null) as [$path, $size]) {
            $search->innerHits($path, $size);
        }

        if ($filters !== null && $filters !== '') {
            $search->filters($filters, throwOnError: true);
        }

        if ($sort !== null && $sort !== '') {
            $search->sort($sort);
        }

        if (($facets ?? '') !== '' || $facetFilters !== '') {
            $search->facets($facets ?? '', $facetFilters, throwOnError: true);
        }

        $response = $search->get();

        $hits = array_map(function (array $hit): array {
            $source = $hit['_source'] ?? [];
            unset($source['_id'], $source['_matches']);

            $row = merge_named(['_id' => $hit['_id']], $source);

            if (isset($hit['_matches'])) {
                $row['_matches'] = $hit['_matches'];
            }

            return $row;
        }, $response->json('hits'));

        $result = [
            'total' => $response->total(),
            'hits' => $hits,
        ];

        if ($facets !== null && $facets !== '') {
            $result['facets'] = $response->json('facets');
        }

        return $result;
    }

    /**
     * The `matches` argument as [path, size] pairs, e.g. "sections.text:5 comments" or
     * "sections.text:5, comments" -> [['sections.text', 5], ['comments', 100]].
     *
     * @return list<array{0: string, 1: int}>
     *
     * @throws InvalidArgumentException when a size is missing, not a number, or outside 1-100
     */
    protected function matchRequests(mixed $matches): array
    {
        $tokens = preg_split('/[\s,]+/u', trim((string) $matches), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(function (string $token): array {
            if (preg_match('/^('.Parser::FIELD_PATH.')(?::(\d+))?$/u', $token, $parts) !== 1
                || ($size = (int) ($parts[2] ?? InnerHits::MAX_SIZE)) < 1
                || $size > InnerHits::MAX_SIZE) {
                throw new InvalidArgumentException(sprintf(
                    "Invalid matches entry '%s'. Use a nested path, optionally with ':size' from 1 to %d, e.g. 'sections.text:5'.",
                    $token,
                    InnerHits::MAX_SIZE,
                ));
            }

            return [$parts[1], $size];
        }, $tokens);
    }

    /**
     * Tool arguments arrive as JSON values. 0 and false are data, not a missing string.
     */
    private function textArgument(mixed $value): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }
}
