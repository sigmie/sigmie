<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Properties as MappingsProperties;
use Sigmie\Mappings\Types\Text;
use Sigmie\Query\Aggs as FacetAggs;
use Sigmie\Query\Contracts\Aggs;
use Sigmie\Query\Contracts\FuzzyQuery;
use Sigmie\Query\Contracts\QueryClause;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Search;
use Sigmie\Search\Contracts\SearchBuilder;
use Sigmie\Shared\Collection;
use function Sigmie\Functions\object_map;

abstract class AbstractSearchBuilder implements SearchBuilder
{
    // Elasticsearch bounds both k and num_candidates at 10,000.
    protected const VECTOR_PAGE_WINDOW = 10000;

    protected Properties $properties;

    protected string $highlightSuffix;

    protected string $highlightPrefix;

    protected bool $typoTolerance = false;

    protected array $sorts = ['_score'];

    protected bool $autocompletion = true;

    protected ?array $fields = null;

    protected ?array $retrieve = null;

    protected array $typoTolerantAttributes = [];

    protected int $size = 20;

    protected int $autocompleteSize = 5;

    protected int $from = 0;

    protected ?float $minScore = null;

    protected bool $filterable = true;

    protected bool $sortable = true;

    protected int $minCharsForOneTypo = 3;

    protected int $minCharsForTwoTypo = 6;

    protected int $autocompleteFuzzyMinLength = 3;

    protected int $autocompleteFuzzyPrefixLength = 1;

    protected array $weight = [];

    protected array $highlight = [];

    protected bool $noResultsOnEmptySearch = false;

    protected bool $noKeywordSearch = false;

    protected bool $semanticSearch = false;

    protected Boolean $filters;

    protected Boolean $globalFilters;

    protected array $filterQueries = [];

    protected Boolean $facetFilters;

    protected Aggs $facets;

    public function __construct(
        public readonly ElasticsearchConnection $elasticsearchConnection,
    ) {
        $this->properties = new MappingsProperties;

        $this->facets = new FacetAggs;

        $this->facets->global('all');

        $this->filters = new Boolean;
        $this->filters->must()->matchAll();

        $this->facetFilters = new Boolean;
        $this->facetFilters->must()->matchAll();

        $this->globalFilters = new Boolean;
        $this->globalFilters->must()->matchAll();
    }

    public function properties(Properties|NewProperties $properties): static
    {
        $this->properties = $properties instanceof NewProperties ? $properties->get() : $properties;

        return $this;
    }

    public function filterQuery(QueryClause $query): static
    {
        $this->filterQueries[] = $query;

        return $this;
    }

    public function typoTolerance(int $oneTypoChars = 3, int $twoTypoChars = 6): static
    {
        $this->typoTolerance = true;
        $this->minCharsForOneTypo = $oneTypoChars;
        $this->minCharsForTwoTypo = $twoTypoChars;

        return $this;
    }

    public function size(int $size = 20): static
    {
        $this->size = $size;

        return $this;
    }

    public function autocompleteSize(int $size = 5): static
    {
        $this->autocompleteSize = $size;

        return $this;
    }

    public function noResultsOnEmptySearch(bool $value = true): static
    {
        $this->noResultsOnEmptySearch = $value;

        return $this;
    }

    public function disableKeywordSearch(bool $value = true): static
    {
        $this->noKeywordSearch = $value;

        return $this;
    }

    public function minScore(float $score): static
    {
        $this->minScore = $score;

        return $this;
    }

    public function semantic(
        bool $value = true,
    ): static {
        $this->semanticSearch = $value;

        return $this;
    }

    public function autocomplete(
        bool $enabled = true,
        int $minLength = 3,
        int $prefixLength = 1,
    ): self {

        $this->autocompletion = $enabled;
        $this->autocompleteFuzzyMinLength = $minLength;
        $this->autocompleteFuzzyPrefixLength = $prefixLength;

        return $this;
    }

    public function from(int $from = 0): static
    {
        $this->from = $from;

        return $this;
    }

    public function minCharsForOneTypo(int $chars): static
    {
        $this->typoTolerance = true;
        $this->minCharsForOneTypo = $chars;

        return $this;
    }

    public function minCharsForTwoTypo(int $chars): static
    {
        $this->typoTolerance = true;
        $this->minCharsForTwoTypo = $chars;

        return $this;
    }

    public function weight(array $weight): static
    {
        $this->weight = $weight;

        return $this;
    }

    public function retrieve(array $attributes): static
    {
        $this->retrieve = $attributes;

        return $this;
    }

    protected function handleBoostField(QueryClause $query): QueryClause
    {
        if ($this->properties->boostField ?? false) {
            return new FunctionScore(
                $query,
                source: $this->properties->boostField->scriptScoreSource(),
                boostMode: $this->properties->boostField->scriptScoreBoostMode(),
                params: ['field' => $this->properties->boostField->fullPath()]
            );
        }

        return $query;
    }

    protected function handleHighlight(Search $search): void
    {
        if ($this->highlight === []) {
            return;
        }

        $search->highlight($this->highlightOptions());
    }

    protected function highlightOptions(): array
    {
        $fields = [];
        // Elasticsearch's plain highlighter can fail while rewriting a kNN query.
        $type = $this->semanticSearch && $this->elasticsearchConnection->driver()->engine() === SearchEngineType::Elasticsearch
            ? 'unified'
            : 'plain';

        foreach ($this->highlight as $fieldName) {
            $field = $this->properties->get($fieldName);
            $prefixField = $field instanceof Text ? $field->indexedPrefixName() : null;
            $names = $field?->names() ?? [$fieldName];

            foreach ($names as $name) {
                $fields[$name] = [
                    'type' => $type,
                    'pre_tags' => [$this->highlightPrefix],
                    'post_tags' => [$this->highlightSuffix],
                    'fragment_size' => 150,
                    'number_of_fragments' => 3,
                    'no_match_size' => 150,
                ];

                if ($prefixField !== null && $name === $field->fullPath()) {
                    $fields[$name]['type'] = 'unified';
                    $fields[$name]['matched_fields'] = [$prefixField, ...array_diff($names, [$name])];
                    $fields[$name]['require_field_match'] = true;
                }
            }
        }

        return [
            'require_field_match' => false,
            'no_match_size' => 100,
            'fields' => object_map($fields),
        ];
    }

    public function highlighting(array $attributes, string $prefix, string $suffix): static
    {
        $this->highlight = $attributes;
        $this->highlightPrefix = $prefix;
        $this->highlightSuffix = $suffix;

        return $this;
    }

    public function typoTolerantAttributes(array $attributes): static
    {
        $this->typoTolerantAttributes = $attributes;

        return $this;
    }

    protected function weightedFieldQuery(QueryClause $clause, string $field, float $weight): QueryClause
    {
        if ($clause instanceof FuzzyQuery && in_array($field, $this->typoTolerantAttributes, true)) {
            $clause = (clone $clause)->fuzziness(sprintf('AUTO:%d,%d', $this->minCharsForOneTypo, $this->minCharsForTwoTypo));
        }

        if ($weight === 1.0) {
            return $clause;
        }

        $weighted = new Boolean;
        $weighted->must()->query($clause);

        return $weighted->boost($weight);
    }

    protected function semanticFields(?array $scopedFields = null): Collection
    {
        $names = array_map(
            fn ($name): ?string => $this->properties->get((string) $name)?->fullPath(),
            $scopedFields ?? $this->searchFields(),
        );

        return $this->properties->nestedSemanticFields()
            ->filter(fn (Text $field): bool => in_array($field->fullPath(), $names, true));
    }

    protected function searchFields(): array
    {
        return $this->fields ?? $this->properties->fieldNames();
    }

    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }
}
