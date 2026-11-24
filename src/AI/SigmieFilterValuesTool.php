<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Sigmie\Mappings\Types\Price;
use Sigmie\Parse\ParseException;
use Sigmie\SigmieIndex;

/**
 * Companion to {@see SigmieIndexTool}: discover the valid values of a facetable field at query
 * time, so the agent can filter accurately instead of guessing.
 *
 * Thin by design — it requests a facet on the field and returns the engine's own parsed result
 * (value counts for keyword/category, min/max for numeric/date, plus a histogram for price). Field validity and
 * per-type parsing are handled by the search/facet layer; an unknown or non-facetable field
 * simply yields no values.
 */
class SigmieFilterValuesTool implements Tool
{
    use GuardsPrivateFields;
    use HandlesToolErrors;
    use NamesIndexTools;
    use ScopesToBaseFilters;

    public function __construct(
        protected SigmieIndex $index,
        protected string $baseFilters = '',
    ) {}

    private const DEFAULT_LIMIT = 20;

    public function name(): string
    {
        return $this->toolName('discover_filter_values');
    }

    public function description(): string
    {
        return sprintf(
            "List the valid values of a facetable field of the '%s' index so you can filter accurately — call this when you do not know a field's values. Provide `field` (a facetable field from the search tool's field list) and optional `filters` (same DSL as the search tool, e.g. \"field:nav*\" to prefix-match, or filter another field to narrow). Keyword/category fields return value counts; numeric/date fields return min/max. `truncated: true` means more values exist than `limit`; `other_documents` counts the documents in the omitted values. Raise `limit` or narrow with `filters` before treating the list as complete.",
            $this->index->name()
        ).$this->privateFieldsNote();
    }

    public function schema(JsonSchema $schema): array
    {
        // Optional params are `nullable()->required()` so the schema is also valid under
        // OpenAI's strict function-calling. Callers that don't want to scope or change the
        // limit simply pass null.
        return [
            'field' => $schema->string()->description('Facetable field to list values for (from the search tool field list)')->required(),
            'filters' => $schema->string()->description('Filter expression to narrow values (pass null for none, same DSL as the search tool, e.g. "field:nav*")')->nullable()->required(),
            'limit' => $schema->integer()->min(0)->max(2147483647)->description('Max values to return (default '.self::DEFAULT_LIMIT.')')->default(self::DEFAULT_LIMIT)->nullable()->required(),
        ];
    }

    public function handle(Request $request): string
    {
        return $this->guard(fn (): string => json_encode(
            $this->encodeValues($this->result($request)),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        ));
    }

    /**
     * The discovered values as an array, for callers that want data instead of the JSON string handle() returns.
     */
    public function result(Request $request): array
    {
        $fieldName = trim((string) ($request['field'] ?? ''));
        $rawLimit = $request['limit'] ?? null;
        $limit = $rawLimit === null || $rawLimit === '' ? self::DEFAULT_LIMIT : (int) $rawLimit;

        $this->refusePrivateFields($fieldName);

        $mapped = $this->index->properties()->get()->get($fieldName)
            ?? throw new ParseException(sprintf('Field %s does not exist.', $fieldName));
        $facet = $mapped instanceof Price ? $fieldName : sprintf('%s:%d', $fieldName, max(1, $limit));

        $search = $this->index->newSearch()
            ->queryString('')
            ->facets($facet, throwOnError: true)
            ->size(0);

        $this->applyBaseFilters($search);

        if (($filters = trim((string) ($request['filters'] ?? ''))) !== '') {
            $search->filters($filters, throwOnError: true);
        }

        if ($limit < 1) {
            return [
                'field' => $fieldName,
                'values' => [],
                'truncated' => false,
                'other_documents' => 0,
            ];
        }

        $response = $search->get();
        $facets = (array) ($response->json('facets') ?? []);

        // Terms facets return only the top `limit` buckets. Elasticsearch counts the documents in
        // the omitted buckets as `sum_other_doc_count`; surface it so the agent knows the list is
        // partial. Field types nest the terms aggregation at different depths under the field name.
        $aggregationName = $mapped->name();
        $aggregation = $response->facetAggregations()[$aggregationName] ?? [];
        if ($mapped->nestedPath() !== null) {
            while (isset($aggregation['_nested'])) {
                $aggregation = $aggregation['_nested'];
            }
        }

        while (! is_int($aggregation['sum_other_doc_count'] ?? null) && is_array($aggregation[$aggregationName] ?? null)) {
            $aggregation = $aggregation[$aggregationName];
        }

        $otherDocuments = (int) ($aggregation['sum_other_doc_count'] ?? 0);

        return [
            'field' => $fieldName,
            'values' => $facets[$fieldName] ?? null,
            'truncated' => $otherDocuments > 0,
            'other_documents' => $otherDocuments,
        ];
    }

    /**
     * A one-key map whose key is "0" is a PHP list. json_encode then drops the key.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function encodeValues(array $result): array
    {
        $values = $result['values'] ?? null;

        if (is_array($values) && $values !== [] && array_is_list($values)) {
            $result['values'] = (object) $values;
        }

        return $result;
    }
}
