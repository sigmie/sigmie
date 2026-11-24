<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Sigmie\SigmieIndex;

/**
 * Companion to {@see SigmieIndexTool}: returns the queryable schema of an index as structured data —
 * every field with its type, whether it is filterable / sortable / facetable, what it means, and an
 * example of how to filter on it — plus the filter / sort / facet syntax.
 *
 * Lets an agent learn the exact field names and operators on demand, so the search tool's own
 * description can stay lean (useful when many indices are registered).
 */
class SigmieIndexSchemaTool implements Tool
{
    use DescribesIndexFields;
    use HandlesToolErrors;
    use NamesIndexTools;

    public function __construct(
        protected SigmieIndex $index,
    ) {}

    public function name(): string
    {
        return $this->toolName('describe_index');
    }

    public function description(): string
    {
        return sprintf(
            "Return the queryable schema of the '%s' index: every field with its type, whether it is filterable / sortable / facetable, what it means, and an example of how to filter on it — plus the filter, sort and facet syntax. Call this to learn the exact field names and operators before searching.",
            $this->index->name()
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        return $this->guard(fn (): string => json_encode($this->result($request), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * The schema as an array, for callers that want data instead of the JSON string handle() returns.
     */
    public function result(Request $request): array
    {
        return [
            'index' => $this->index->name(),
            'fields' => $this->fieldsSchema(),
            'filter_syntax' => [
                'operators' => 'AND, OR, AND NOT',
                'negation' => "NOT field:'value'",
                'grouping' => "(field:'a' OR field:'b') AND other>10",
                'exists' => 'field:*',
                'sort' => "space-separated 'field:asc' or 'field:desc' — direction after a COLON (e.g. 'price:asc name:desc'), plus '_score'. 'price asc' (a space) is INVALID.",
                'geo_sort' => 'field[lat,lon]:km:asc',
                'facets' => 'field1 field2:20 (space-separated; :size for term fields, :interval for price; number, date, and datetime take no parameter)',
            ],
            'notes' => [
                "Equality on keyword() is case-insensitive because its normalizer lowercases. Equality on a text field's keyword subfield and on caseSensitiveKeyword() is case-sensitive. If a filter returns 0 unexpectedly, call {$this->toolName('discover_filter_values')} on that field to confirm the exact stored values (including casing).",
                "Use {$this->toolName('discover_filter_values')} to list a field's valid values; use {$this->toolName('sample_documents')} to see example records.",
            ],
        ];
    }
}
