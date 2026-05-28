<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\Type;
use Sigmie\SigmieIndex;

/**
 * Companion to {@see SigmieIndexTool}: discover the valid values of a facetable field at query
 * time, so the agent can filter accurately instead of guessing (and so values aren't baked into
 * the search tool's description).
 *
 * Thin by design — it delegates to the engine's own building blocks:
 *  - `newSearch()` + the filter DSL for scoping/narrowing,
 *  - the field's own facet parser (`$field->facets()`), which already returns the right shape per
 *    type (value counts for keyword/category, min/max/histogram for numeric/date).
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
        $fields = implode(', ', $this->facetableFieldNames());

        return sprintf(
            "List the valid values of a facetable field of the '%s' index so you can filter accurately — call this when you do not know a field's values. Provide `field` (one of: %s) and optional `filters` (same DSL as the search tool, e.g. \"field:nav*\" to prefix-match, or filter another field to narrow). Keyword/category fields return value counts; numeric/date fields return min/max.",
            $this->index->name(),
            $fields !== '' ? $fields : '(no facetable fields)'
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'field' => $schema->string()->description('Facetable field to list values for (one named in the description)')->required(),
            'filters' => $schema->string()->description('Optional filter expression, same DSL as the search tool, to narrow values (e.g. "field:nav*")'),
            'limit' => $schema->integer()->description('Max values to return')->default(self::DEFAULT_LIMIT),
        ];
    }

    public function handle(Request $request): string
    {
        $fieldName = trim((string) ($request['field'] ?? ''));
        $limit = max(1, (int) ($request['limit'] ?? self::DEFAULT_LIMIT));

        $field = $this->facetableField($fieldName);

        if (! $field instanceof Type) {
            return json_encode([
                'error' => sprintf("'%s' is not a facetable field of this index.", $fieldName),
                'available_fields' => $this->facetableFieldNames(),
            ], JSON_THROW_ON_ERROR);
        }

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

        $values = $field->facets($search->get()->facetAggregations()) ?? [];

        return json_encode(['field' => $fieldName, 'values' => $values], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function facetableField(string $name): ?Type
    {
        foreach ($this->index->properties()->get()->toArray() as $field) {
            if ($field->name === $name && $this->isFacetableLeaf($field)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function facetableFieldNames(): array
    {
        $names = [];

        foreach ($this->index->properties()->get()->toArray() as $field) {
            if ($this->isFacetableLeaf($field)) {
                $names[] = $field->name;
            }
        }

        return $names;
    }

    private function isFacetableLeaf(Type $field): bool
    {
        return ! $field instanceof Object_ && ! $field instanceof Nested && $field->isFacetable();
    }
}
