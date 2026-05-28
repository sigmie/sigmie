<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Sigmie\Mappings\Types\Boolean;
use Sigmie\Mappings\Types\CaseSensitiveKeyword;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\DateTime;
use Sigmie\Mappings\Types\GeoPoint;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\Price;
use Sigmie\Mappings\Types\Range;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type;
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
    public function __construct(
        protected SigmieIndex $index,
        protected string $baseFilters = '',
    ) {}

    /** Max distinct values listed per facetable field in the description. */
    private const FACET_VALUE_CAP = 20;

    /** Facet value hints keyed by field name, recomputed on each description() build. */
    private array $facetHints = [];

    public function description(): string
    {
        $properties = $this->index->properties()->get();

        $this->facetHints = $this->facetValueHints($properties->toArray());

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
            ."Sort: field:asc field:desc _score (space-separated)\n"
            ."Geo sort: field[lat,lon]:km:asc\n"
            ."Facets: field1 field2:20 (space-separated, optional :size for keywords or :interval for numbers)\n"
            .'Discovering valid values: to filter on a field whose values you do not know, first search with that field name in `facets` (optionally narrowed by `query` or another filter), then read the returned facet values and filter by one of them.');
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

        return json_encode($result, JSON_PRETTY_PRINT);
    }

    /**
     * One match-all facet query over all facetable top-level fields, returning a
     * "values: …" (or "range a..b") hint per field so the agent uses real values
     * instead of guessing. Best-effort: never throws — an empty index or a field
     * whose facets cannot be parsed is simply skipped.
     *
     * @param  array<int, Type>  $fields
     * @return array<string, string>
     */
    private function facetValueHints(array $fields): array
    {
        $facetable = array_values(array_filter(
            $fields,
            fn (Type $f): bool => ! $f instanceof Object_ && ! $f instanceof Nested && $f->isFacetable(),
        ));

        if ($facetable === []) {
            return [];
        }

        // Numbers/dates/ranges take an interval (not a term count) after ':', so omit the cap for them.
        $spec = implode(' ', array_map(
            fn (Type $f): string => $f->name().(
                $f instanceof Number || $f instanceof Price || $f instanceof Date || $f instanceof DateTime || $f instanceof Range
                    ? ''
                    : ':'.(self::FACET_VALUE_CAP + 1)
            ),
            $facetable,
        ));

        try {
            $aggregations = $this->index->newSearch()
                ->queryString('')
                ->facets($spec)
                ->size(0) // aggregations only — we never use the hits
                ->get()
                ->facetAggregations();
        } catch (\Throwable) {
            return [];
        }

        if ($aggregations === []) {
            return [];
        }

        $hints = [];

        foreach ($facetable as $field) {
            try {
                $parsed = $field->facets($aggregations);
            } catch (\Throwable) {
                continue;
            }

            if (! is_array($parsed) || $parsed === []) {
                continue;
            }

            $hint = $this->formatFacetValues($parsed);

            if ($hint !== '') {
                $hints[$field->name] = $hint;
            }
        }

        return $hints;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function formatFacetValues(array $parsed): string
    {
        if (array_key_exists('min', $parsed) && array_key_exists('max', $parsed)) {
            return sprintf('range %s..%s', $parsed['min'], $parsed['max']);
        }

        $values = array_keys($parsed);
        $shown = array_slice($values, 0, self::FACET_VALUE_CAP);
        $more = count($values) > self::FACET_VALUE_CAP
            ? sprintf(' (+%d more — request this field in `facets`)', count($values) - self::FACET_VALUE_CAP)
            : '';

        return 'values: '.implode(', ', array_map(static fn ($v): string => (string) $v, $shown)).$more;
    }

    private function collectFieldDescriptions(array $fields, string $prefix = ''): array
    {
        $descriptions = [];

        foreach ($fields as $field) {
            $name = $prefix !== '' ? sprintf('%s.%s', $prefix, $field->name) : $field->name;

            if ($field instanceof Object_) {
                $descriptions = [
                    ...$descriptions,
                    ...$this->collectFieldDescriptions(
                        $field->getProperties()->toArray(),
                        $name
                    ),
                ];

                continue;
            }

            $desc = $this->describeField($field, $name);

            if ($desc !== null) {
                $descriptions[] = $desc;
            }
        }

        return $descriptions;
    }

    private function describeField(Type $field, string $name): ?string
    {
        $type = $this->fieldTypeName($field);

        if ($type === null) {
            return null;
        }

        $capabilities = $this->fieldCapabilities($field);
        $filter = $this->filterExample($field, $name);

        $tags = $capabilities !== [] ? ' ('.implode(', ', $capabilities).')' : '';

        if ($field instanceof Nested) {
            return $this->describeNested($field, $name, $tags);
        }

        $line = sprintf('- %s [%s]%s: %s', $name, $type, $tags, $filter);

        $description = $field->getDescription();
        if (is_string($description) && $description !== '') {
            $line .= ' — '.$description;
        }

        $values = $this->facetHints[$name] ?? null;
        if (is_string($values) && $values !== '') {
            $line .= ' — '.$values;
        }

        return $line;
    }

    private function describeNested(Nested $field, string $name, string $tags): string
    {
        $subFields = array_filter(array_map(
            fn (Type $child): ?string => $this->describeField($child, $child->name),
            $field->getProperties()->toArray()
        ));

        $desc = sprintf("- %s [nested]%s: %s:{subfield:'value' AND other>10}", $name, $tags, $name);

        if ($subFields !== []) {
            $desc .= "\n  Sub-fields:\n".implode("\n", array_map(
                fn (string $line): string => '  '.$line,
                $subFields
            ));
        }

        return $desc;
    }

    private function fieldTypeName(Type $field): ?string
    {
        return match (true) {
            $field instanceof Keyword,
            $field instanceof CaseSensitiveKeyword => 'keyword',
            $field instanceof Number,
            $field instanceof Price => 'number',
            $field instanceof Boolean => 'boolean',
            $field instanceof Date,
            $field instanceof DateTime => 'date',
            $field instanceof GeoPoint => 'geo',
            $field instanceof Range => 'range',
            $field instanceof Nested => 'nested',
            $field instanceof Text => 'text',
            default => null,
        };
    }

    private function fieldCapabilities(Type $field): array
    {
        $capabilities = [];

        $isSortable = match (true) {
            $field instanceof Text => $field->isSortable(),
            $field instanceof Nested, $field instanceof Range => false,
            default => true,
        };

        if ($isSortable) {
            $capabilities[] = 'sortable';
        }

        if ($field->isFacetable()) {
            $capabilities[] = 'facetable';
        }

        return $capabilities;
    }

    private function filterExample(Type $field, string $name): string
    {
        return match (true) {
            $field instanceof Keyword,
            $field instanceof CaseSensitiveKeyword => sprintf("%s:'value' %s:['a','b'] %s:val*", $name, $name, $name),
            $field instanceof Number,
            $field instanceof Price => sprintf('%s>n %s<=n %s:min..max', $name, $name, $name),
            $field instanceof Boolean => sprintf('%s:true %s:false', $name, $name),
            $field instanceof Date,
            $field instanceof DateTime => sprintf("%s>'2024-01-01' %s<'2024-12-31'", $name, $name),
            $field instanceof GeoPoint => $name.':10km[lat,lon]',
            $field instanceof Range => sprintf('%s>n %s:min..max', $name, $name),
            $field instanceof Text => $field->isFilterable()
                ? sprintf("%s:'value' %s:['a','b']", $name, $name)
                : 'query only',
            default => '',
        };
    }
}
