<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\DateTime;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\Price;
use Sigmie\Mappings\Types\Range;
use Sigmie\Mappings\Types\Type;
use Sigmie\SigmieIndex;
use Throwable;

/**
 * Companion to {@see SigmieIndexTool}: lets an agent discover the valid filter values for a
 * facetable field at query time (instead of baking values into the search tool's description).
 *
 * Keyword/category fields return their distinct values (with counts); numeric/date fields return
 * min/max. An optional `query` narrows term values by prefix. Aggregation only (size 0).
 */
class SigmieFilterValuesTool implements Tool
{
    public function __construct(
        protected SigmieIndex $index,
        protected string $baseFilters = '',
    ) {}

    /** Max distinct values returned. */
    private const DEFAULT_LIMIT = 20;

    public function name(): string
    {
        return 'discover_filter_values';
    }

    public function description(): string
    {
        $fields = implode(', ', $this->facetableFieldNames());

        return sprintf(
            "List the valid filter values for a field of the '%s' index. Provide `field` (one of: %s) and an optional `query` to narrow by prefix. Keyword/category fields return distinct values; numeric/date fields return min and max. Call this before filtering when you do not know a field's values.",
            $this->index->name(),
            $fields !== '' ? $fields : '(no facetable fields)'
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'field' => $schema->string()->description('Field to list values for (one of the facetable fields named in the description)')->required(),
            'query' => $schema->string()->description('Optional prefix to narrow term values, e.g. "nav" matches "navy", "navy blue"'),
            'limit' => $schema->integer()->description('Max distinct values to return')->default(self::DEFAULT_LIMIT),
        ];
    }

    public function handle(Request $request): string
    {
        $fieldName = trim((string) ($request['field'] ?? ''));
        $limit = max(1, (int) ($request['limit'] ?? self::DEFAULT_LIMIT));
        $query = trim((string) ($request['query'] ?? ''));

        $field = $this->facetableField($fieldName);

        if (! $field instanceof Type) {
            return json_encode([
                'error' => sprintf("'%s' is not a facetable field of this index.", $fieldName),
                'available_fields' => $this->facetableFieldNames(),
            ], JSON_THROW_ON_ERROR);
        }

        $isNumeric = $field instanceof Number || $field instanceof Price
            || $field instanceof Date || $field instanceof DateTime || $field instanceof Range;

        $search = $this->index->newSearch()->queryString('')->size(0);

        $filterParts = [];
        if ($this->baseFilters !== '') {
            $filterParts[] = sprintf('(%s)', $this->baseFilters);
        }

        if (! $isNumeric && ($prefix = $this->safePrefix($query)) !== '') {
            $filterParts[] = sprintf('%s:%s*', $fieldName, $prefix);
        }

        if ($filterParts !== []) {
            $search->filters(implode(' AND ', $filterParts));
        }

        $search->facets($isNumeric ? $fieldName : sprintf('%s:%d', $fieldName, $limit));

        try {
            $parsed = $field->facets($search->get()->facetAggregations());
        } catch (Throwable) {
            $parsed = null;
        }

        if (! is_array($parsed)) {
            $parsed = [];
        }

        if ($isNumeric && array_key_exists('min', $parsed) && array_key_exists('max', $parsed)) {
            return json_encode([
                'field' => $fieldName,
                'min' => $parsed['min'],
                'max' => $parsed['max'],
            ], JSON_THROW_ON_ERROR);
        }

        $values = array_slice(array_keys($parsed), 0, $limit);

        return json_encode([
            'field' => $fieldName,
            'values' => $values,
            'counts' => array_intersect_key($parsed, array_flip($values)),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
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

    /** First token of the query, stripped to a wildcard-safe prefix. */
    private function safePrefix(string $query): string
    {
        $first = preg_split('/\s+/', trim($query))[0] ?? '';

        return (string) preg_replace('/[^\p{L}\p{N}_-]/u', '', $first);
    }
}
