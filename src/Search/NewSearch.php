<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Closure;
use Generator;
use Http\Promise\Promise;
use InvalidArgumentException;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\PointInTimeRequests;
use Sigmie\Document\Hit;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Enums\VectorSimilarity;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\PropertiesFieldNotFound;
use Sigmie\Mappings\Types\BaseVector;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\NestedVector;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type;
use Sigmie\Parse\FacetParser;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\SortParser;
use Sigmie\Query\Aggs;
use Sigmie\Query\BooleanQueryBuilder;
use Sigmie\Query\Contracts\QueryClause as Query;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\ElasticsearchKnn;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\MatchNone;
use Sigmie\Query\Queries\OpenSearchKnn;
use Sigmie\Query\Queries\Raw;
use Sigmie\Query\Queries\Text\Nested;
use Sigmie\Query\Search;
use Sigmie\Query\Suggest;
use Sigmie\Search\Contracts\LazyIterableQuery;
use Sigmie\Search\Contracts\MultiSearchable;
use Sigmie\Search\Contracts\ResponseFormater;
use Sigmie\Search\Contracts\SearchQueryBuilder as SearchQueryBuilderInterface;
use Sigmie\Search\Formatters\SigmieSearchResponse;
use Sigmie\Shared\Collection;
use Sigmie\Shared\UsesApis;
use Sigmie\Support\VectorMath;

use function Sigmie\Functions\search_hits;
use function Sigmie\Functions\trim_text;

class NewSearch extends AbstractSearchBuilder implements LazyIterableQuery, MultiSearchable, SearchQueryBuilderInterface
{
    use UsesApis {
        apis as private registerApis;
    }

    protected array $sort = ['_score'];

    protected array $embeddings = [];

    protected array $knn = [];

    protected array $semanticQueries = [];

    protected string $index;

    protected float $textScoreMultiplier = 1.0;

    protected float $semanticScoreMultiplier = 1.0;

    protected array $facetFilterErrors = [];

    private array $facetAggregationAliases = [];

    protected string $facetFilterInput = '';

    protected bool $throwOnFacetError = false;

    protected bool $retrieveEmbeddingsField = false;

    protected array $except = [];

    protected ResponseFormater $formatter;

    public SearchContext $searchContext;

    public FilterParser $filterParser;

    public FacetParser $facetParser;

    public SortParser $sortParser;

    protected array $vectorPools = [];

    protected int $pitIterationChunkSize = 500;

    protected int $collapseTop = 0;

    protected Aggs $aggregations;

    public function __construct(
        ElasticsearchConnection $elasticsearchConnection
    ) {

        parent::__construct($elasticsearchConnection);

        $this->searchContext = new SearchContext;
        $this->filterParser = (new FilterParser($this->properties, false))->innerHits($this->searchContext->innerHits);
        $this->facetParser = new FacetParser($this->properties, false);
        $this->sortParser = new SortParser($this->properties, false);
        $this->aggregations = new Aggs;
    }

    /**
     * A clone owns its state. The parsed filters name their nested matches in the inner hits
     * registry of the search they were parsed for, so the clone parses its strings again.
     */
    public function __clone(): void
    {
        $this->searchContext = clone $this->searchContext;
        $this->filterParser = (clone $this->filterParser)->innerHits($this->searchContext->innerHits);
        $this->facetParser = clone $this->facetParser;
        $this->sortParser = clone $this->sortParser;
        $this->aggregations = clone $this->aggregations;

        $this->parseStrings();
    }

    public function page(int $page, int $perPage = 20): static
    {
        $from = ($page - 1) * $perPage;

        // PHP turns an int overflow into a float.
        return is_int($from)
            ? $this->from($from)->size($perPage)
            : throw new InvalidArgumentException(sprintf('Page %d of %d results is out of range.', $page, $perPage));
    }

    public function retrieveEmbeddingsField(bool $retrieve = true): static
    {
        $this->retrieveEmbeddingsField = $retrieve;

        return $this;
    }

    /**
     * Source fields to drop from every hit, even when retrieve() includes a parent field.
     */
    public function except(array $fields): static
    {
        $this->except = $fields;
        $this->searchContext->innerHits->except($fields);

        return $this;
    }

    /**
     * Return, per hit, the items of a nested path that matched the query or a nested filter.
     * Name a nested path for whole items (`reviews`) or a field inside it (`reviews.comment`);
     * an array names several that share the size. Each hit gets `_matches` with the `total` and
     * `items` per path.
     *
     * Calls add up, one per path: `->innerHits('reviews', size: 10)->innerHits('answers')`.
     * Calling again for the same path merges its fields and the later size wins.
     *
     * @param  string|list<string>  $fields
     * @param  int  $size  items per path; Elasticsearch caps it at `index.max_inner_result_window`
     */
    public function innerHits(string|array $fields, int $size = InnerHits::MAX_SIZE): static
    {
        $this->searchContext->innerHits->request((array) $fields, $size);

        return $this;
    }

    public function queryString(
        string $query,
        float $weight = 1.0,
        ?array $fields = null,
        ?int $dimension = null,
        ?array $vector = null,
        ?string $vectorField = null,
    ): static {
        $this->searchContext->queryStrings[] = new QueryString(
            text: $query,
            weight: $weight,
            dimension: $dimension,
            vector: $vector,
            fields: $fields,
            vectorField: $vectorField,
        );

        return $this;
    }

    public function queryImage(
        string $imageUrl,
        float $weight = 1.0,
        ?array $fields = null,
        ?int $dimension = null,
        ?array $vector = null,
    ): static {
        $this->searchContext->queryImages[] = new QueryImage(
            imageSource: $imageUrl,
            weight: $weight,
            dimension: $dimension,
            vector: $vector,
            fields: $fields,
        );

        return $this;
    }

    public function autocompletePrefix(string $prefix): static
    {
        $this->searchContext->autocompletePrefixStrings = [$prefix];

        return $this;
    }

    public function index(string $index): static
    {
        $this->index = $index;

        return $this;
    }

    public function properties(Properties|NewProperties $properties): static
    {
        parent::properties($properties);

        $this->filterParser->properties($properties);
        $this->facetParser->properties($properties);
        $this->sortParser->properties($properties);

        $this->parseStrings();

        return $this;
    }

    private function parseStrings(): void
    {
        if ($this->searchContext->filterString !== '') {
            $this->globalFilters = $this->filterParser->parse($this->searchContext->filterString);
        }

        if ($this->searchContext->sortString !== '') {
            $this->sort = $this->sortParser->parse($this->searchContext->sortString);
        }

        if ($this->searchContext->facetString !== '' || $this->facetFilterInput !== '') {
            $this->facets($this->searchContext->facetString, $this->facetFilterInput, $this->throwOnFacetError);
        }
    }

    public function filters(string $filters, bool $throwOnError = false): static
    {
        $this->searchContext->filterString = $filters;

        $this->globalFilters = $this->filterParser
            ->throwOnError($throwOnError)
            ->parse($this->searchContext->filterString);

        return $this;
    }

    // Compose the parsed string filter with any raw queries added via
    // filterQuery() as sibling clauses in a single filter context, so they
    // are ANDed together without disturbing the parsed query's own
    // should/must_not semantics (it stays nested as one clause).
    protected function appliedFilters(): Boolean
    {
        if ($this->filterQueries === []) {
            return $this->globalFilters;
        }

        $boolean = new Boolean;
        $filter = $boolean->filter()->query($this->globalFilters);

        foreach ($this->filterQueries as $query) {
            $filter->query($query);
        }

        return $boolean;
    }

    public function textScoreMultiplier(float $multiplier = 1.0): static
    {
        $this->textScoreMultiplier = $multiplier;

        return $this;
    }

    public function semanticScoreMultiplier(float $multiplier = 1.0): static
    {
        $this->semanticScoreMultiplier = $multiplier;

        return $this;
    }

    public function facets(
        string $facets,
        string $filters = '',
        bool $throwOnError = false,
    ): static {

        $this->facetFilterInput = $filters;
        $this->throwOnFacetError = $throwOnError;
        $this->facetParser->throwOnError($throwOnError);

        $facetFilterString = $this->facetParser->parseFilterString($filters);
        $this->facetFilterErrors = $this->facetParser->errors();

        $this->searchContext->facetString = $facets;
        $this->searchContext->facetFilterString = $facetFilterString;

        if ($facetFilterString !== '') {
            $facetFilters = (new FilterParser($this->properties, $throwOnError))
                ->innerHits($this->searchContext->innerHits);
            $this->facetFilters = $facetFilters->parse($facetFilterString);
            $this->facetFilterErrors = [...$this->facetFilterErrors, ...$facetFilters->errors()];
        }

        $this->facets = $this->facetParser->parse($facets, $facetFilterString);
        $this->searchContext->facetFields = $this->facetParser->parsedFields();

        return $this;
    }

    public function sort(string $sort = '_score', bool $throwOnError = true): static
    {
        $this->searchContext->sortString = $sort;
        $this->sort = $this->sortParser
            ->throwOnError($throwOnError)
            ->parse($sort);

        return $this;
    }

    public function uniqueBy(string $field, int $top = 0): static
    {
        $this->searchContext->collapseField = $field;
        $this->collapseTop = $top;

        return $this;
    }

    public function highlighting(array $attributes, string $prefix, string $suffix): static
    {
        $this->searchContext->highlightTags = [$prefix, $suffix];

        return parent::highlighting($attributes, $prefix, $suffix);
    }

    public function size(int $size = 20): static
    {
        $this->searchContext->size = $size;

        return $this;
    }

    public function from(int $from = 0): static
    {
        $this->searchContext->from = $from;
        $this->searchContext->searchAfter = null;

        return $this;
    }

    public function searchAfter(array $sort): static
    {
        $this->from();
        $this->searchContext->searchAfter = $sort === [] ? null : array_values($sort);

        return $this;
    }

    protected function handleRetrievableFields(Search $search)
    {
        $search->fields($this->retrievableFields())->except($this->except);
    }

    private function retrievableFields(): array|false
    {
        if ($this->retrieve === []) {
            return false;
        }

        $fields = $this->retrieve ?? $this->properties->fieldNames();
        if ($fields !== [] && $this->retrieveEmbeddingsField && ! in_array('_embeddings', $fields, true)) {
            $fields[] = '_embeddings';
        }

        return array_values($fields);
    }

    protected function handleSize(Search $search)
    {
        $search->size($this->searchContext->size);
    }

    protected function handleFrom(Search $search)
    {
        $search->from($this->searchContext->from);
    }

    protected function handleSearchAfter(Search $search): void
    {
        if ($this->searchContext->searchAfter === null) {
            return;
        }

        $collapsedSort = $this->openSearchCollapsedSort();
        if ($collapsedSort instanceof CollapsedSort && count($this->searchContext->searchAfter) === 1) {
            // OpenSearch 3.2 rejects native search_after with collapse.
            $postFilter = $this->searchContext->facetFilterString !== '' ? $this->facetFilters->toRaw() : null;
            $search->postFilter(new Raw($collapsedSort->postFilter($this->searchContext->searchAfter[0], $postFilter)));

            return;
        }

        $search->addRaw('search_after', $this->searchContext->searchAfter);
    }

    private function openSearchCollapsedSort(): ?CollapsedSort
    {
        if ($this->elasticsearchConnection->driver()->engine() !== SearchEngineType::OpenSearch) {
            return null;
        }

        return CollapsedSort::fromSort($this->collapseField(), $this->sort);
    }

    /**
     * Elasticsearch collapses on doc values. A text field collapses on the sub-field that sort()
     * uses, because search_after with collapse needs the sort and the collapse on one field.
     */
    private function collapseField(): ?string
    {
        $field = $this->searchContext->collapseField;
        $type = $field === null ? null : $this->properties->get($field);

        return $type?->sortableName() ?? $type?->filterableName() ?? $field;
    }

    protected function handleKnn(Search $search)
    {
        if ($this->knn !== []) {
            $search->knn($this->knn);
        }
    }

    protected function handleAggs(Search $search)
    {
        $facets = $this->facets->toRaw();
        $custom = $this->aggregations->toRaw();
        $aggs = $facets;
        $this->facetAggregationAliases = [];

        foreach ($custom as $name => $aggregation) {
            if (isset($facets[$name])) {
                $alias = '_facet_'.$name;
                while (isset($aggs[$alias]) || isset($custom[$alias])) {
                    $alias = '_'.$alias;
                }

                $aggs[$alias] = $facets[$name];
                $this->facetAggregationAliases[$name] = $alias;
            }

            $aggs[$name] = $aggregation;
        }

        // Array spread renumbers a name "1" to 0. Names "0" and "1" are then
        // a PHP list, and json_encode drops them.
        if ($aggs !== [] && array_is_list($aggs)) {
            $aggs = (object) $aggs;
        }

        $search->addRaw('aggs', $aggs);
    }

    public function aggregate(callable $callable): static
    {
        $callable($this->aggregations);

        return $this;
    }

    protected function handleSort(Search $search)
    {
        $search->sort($this->sort);
    }

    protected function handleCollapse(Search $search): void
    {
        if ($this->searchContext->collapseField === null) {
            return;
        }

        $collapse = ['field' => $this->collapseField()];

        if ($this->collapseTop > 0) {
            $collapse['inner_hits'] = [
                'name' => 'top',
                'from' => 1,
                'size' => $this->collapseTop,
                'sort' => array_map(
                    fn (mixed $clause): mixed => is_array($clause) && array_is_list($clause) ? (object) $clause : $clause,
                    $this->sort,
                ),
            ];

            if ($this->highlight !== []) {
                $collapse['inner_hits']['highlight'] = $this->highlightOptions();
            }

            $fields = $this->retrievableFields();
            if ($fields === false) {
                $collapse['inner_hits']['_source'] = false;
            }

            if ($fields) {
                $collapse['inner_hits']['_source']['includes'] = $fields;
            }

            if ($fields !== false && $this->except !== []) {
                $collapse['inner_hits']['_source']['excludes'] = array_values($this->except);
            }
        }

        $search->addRaw('collapse', $collapse);
    }

    protected function handleMinScore(Search $search)
    {
        $search->minScore($this->minScore ?? 0.0);
    }

    protected function handleFiltersQuery(Boolean $boolean)
    {
        $boolean->must()->bool(
            fn (Boolean $boolean): BooleanQueryBuilder => $boolean->filter()->query(
                $this->appliedFilters()
            )
        );
    }

    protected function handlePostFilter(Search $search): void
    {
        if ($this->searchContext->facetFilterString !== '') {
            $search->postFilter(new Raw($this->facetFilters->toRaw()));
        }
    }

    protected function handleQueryStrings(Boolean $boolean): void
    {
        $boolean->must()->bool(function (Boolean $boolean): void {

            $queryStrings = (new Collection($this->searchContext->queryStrings))
                ->filter(fn (QueryString $query): bool => trim_text($query->text()) !== '');

            $queryStrings->each(function (QueryString $queryString) use ($boolean): void {

                $query = $this->createStringQueries($queryString->text(), $queryString->weight(), $queryString->fields());

                $boolean->should()->query($query);
            });

            if ($queryStrings->isEmpty()) {
                if ($this->knn !== [] || $this->semanticQueries !== [] || $this->searchContext->queryImages !== []
                    || array_filter($this->searchContext->queryStrings, fn (QueryString $query): bool => $query->hasVector())) {
                    $boolean->should()->query(new MatchNone);
                } elseif ($this->searchContext->queryStrings !== [] || $this->noKeywordSearch || $this->noResultsOnEmptySearch) {
                    $boolean->should()->query($this->onEmptyQueryString());
                }
            }

            foreach ($this->semanticQueries as $semanticQuery) {
                $boolean->should()->query($semanticQuery);
            }
        });
    }

    protected function handleSuggest(Search $search): void
    {
        $prefix = $this->searchContext->autocompletePrefixStrings[0] ?? null;

        if (! $this->autocompletion || $prefix === null || $prefix === '') {
            return;
        }

        $autocompleteField = $this->properties->completionFields()->first();

        if ($autocompleteField) {
            $suggest = new Suggest;

            $suggest->completion(name: 'autocompletion')
                ->field($autocompleteField->name())
                ->size($this->autocompleteSize)
                ->fuzzyMinLegth($this->autocompleteFuzzyMinLength)
                ->fuzzyPrefixLenght($this->autocompleteFuzzyPrefixLength)
                ->fuzzy()
                ->prefix($prefix);

            $search->suggest($suggest);
        }
    }

    protected function handleTrackTotalHits(Search $search)
    {
        $search->trackTotalHits();
    }

    public function makeFacetSearch(): Search
    {
        $facets = clone $this;
        $facets->facetFilters = new Boolean;

        return $facets->makeSearch()->from(0)->size(0);
    }

    public function makeSearch(): Search
    {
        $search = $this->buildSearch(self::VECTOR_PAGE_WINDOW);
        $this->handleSearchAfter($search);

        return $search;
    }

    private function buildSearch(int $knnWindow): Search
    {
        $search = new Search($this->elasticsearchConnection);

        $search->index($this->index)->filterParserProperties($this->properties);

        $this->searchContext->innerHits->resolve($this->properties);

        $this->handleHighlight($search);
        $this->handleRetrievableFields($search);
        $this->handleSort($search);
        $this->handleAggs($search);
        $this->handleSize($search);
        $this->handleFrom($search);
        $this->handleMinScore($search);
        $this->handleSuggest($search);
        $this->handleTrackTotalHits($search);

        $boolean = new Boolean;

        $this->knn = [];
        $this->semanticQueries = [];

        if ($this->semanticSearch && $this->hasSemanticFields()) {
            $this->populateVectorPool();
            $this->buildKnnQueries(max(1, $knnWindow, $this->collapseTop + 1));
            $this->handleKnn($search);
        }

        $this->buildMainQuery($boolean);

        $query = $this->handleBoostField($boolean);

        if ($this->collapseTop > 0 && $this->minScore > 0 && $this->knn === []) {
            // Collapse expansion copies the query, but drops the request-level minimum score.
            $query = new FunctionScore($query, minScore: $this->minScore);
        }

        $search->query(new Raw($query->toRaw()));

        $this->handlePostFilter($search);

        $this->handleCollapse($search);

        return $search;
    }

    protected function buildMainQuery(Boolean $boolean): void
    {
        $this->handleFiltersQuery($boolean);
        $this->handleQueryStrings($boolean);
    }

    protected function hasSemanticFields(): bool
    {
        foreach ([...$this->searchContext->queryStrings, ...$this->searchContext->queryImages] as $query) {
            if ($this->getVectorFields($query)->isNotEmpty()) {
                return true;
            }
        }

        return false;
    }

    protected function populateVectorPool(): void
    {
        $textItems = [];

        foreach ([...$this->searchContext->queryStrings, ...$this->searchContext->queryImages] as $query) {
            if ($query->hasVector()) {
                continue;
            }

            if (trim_text((string) $query) === '') {
                continue;
            }

            foreach ($this->getVectorFields($query) as $field) {
                $apiName = $field->queryApiName ?? $field->apiName ?? null;

                if ($apiName === null) {
                    continue;
                }

                if (! isset($this->vectorPools[$apiName])) {
                    $api = $this->getApi($apiName);
                    if (! $api instanceof EmbeddingsApi) {
                        continue;
                    }

                    $this->vectorPools[$apiName] = new VectorPool($api, ensureNormalized: false);
                }

                if ($query instanceof QueryImage) {
                    $this->vectorPools[$apiName]->get($query->imageSource(), $field->dims());

                    continue;
                }

                $textItems[$apiName][] = ['text' => $query->text(), 'dims' => $field->dims()];
            }
        }

        foreach ($textItems as $apiName => $items) {
            $this->vectorPools[$apiName]->getMany($items);
        }
    }

    protected function onEmptyQueryString(): Query
    {
        if ($this->noKeywordSearch || $this->noResultsOnEmptySearch) {
            return new MatchNone;
        }

        return new MatchAll;
    }

    protected function queryBoost(Type $field, float $queryWeight): float
    {
        $fieldWeight = $this->weight[$field->fullPath()] ?? 1;

        return array_key_exists($field->fullPath(), $this->weight) ? $fieldWeight * $queryWeight : $queryWeight;
    }

    protected function createStringQueries(string $queryString, float $queryBoost = 1.0, ?array $scopedFields = null): Query
    {
        $keywordQuery = $this->buildKeywordQuery($queryString, $queryBoost, $scopedFields);

        $boolean = new Boolean;
        $boolean->should()->query($keywordQuery);

        return $boolean;
    }

    protected function buildKnnQueries(int $k): void
    {
        $allKnnQueries = [];
        $allSemanticQueries = [];

        foreach ([...$this->searchContext->queryStrings, ...$this->searchContext->queryImages] as $query) {
            if (trim_text((string) $query) === '' && ! $query->hasVector()) {
                continue;
            }

            $result = $this->createVectorQuery($query, $k);
            $allKnnQueries = array_merge($allKnnQueries, $result['knn']);
            $allSemanticQueries = array_merge($allSemanticQueries, $result['semantic']);
        }

        $this->knn = $allKnnQueries;
        $this->semanticQueries = $allSemanticQueries;
    }

    protected function buildKeywordQuery(string $queryString, float $queryBoost, ?array $scopedFields = null): Query
    {
        if ($this->noKeywordSearch) {
            return new MatchNone;
        }

        return $this->createTextQuery($queryString, $queryBoost, $scopedFields);
    }

    protected function createVectorQuery(QueryString|QueryImage $input, int $k): array
    {
        $knn = [];
        $semantic = [];
        $driver = $this->elasticsearchConnection->driver();
        $filter = $this->appliedFilters();
        if ($this->searchContext->facetFilterString !== '') {
            $combined = new Boolean;
            $combined->filter()->query($filter)->query($this->facetFilters);
            $filter = $combined;
        }

        // Document boosts and collapse expansion need vector scores inside the main query.
        $useTopLevelKnn = $driver->engine() === SearchEngineType::Elasticsearch
            && ! isset($this->properties->boostField)
            && $this->collapseTop === 0;

        foreach ($this->semanticFields($input->fields()) as $text) {
            $boost = $this->queryBoost($text, $input->weight()) * $this->semanticScoreMultiplier;
            foreach ($text->vectorFields() as $field) {
                if (! $this->matchesVectorField($input, $field)) {
                    continue;
                }

                $dims = $field->dims();
                if ($input->hasVector()) {
                    $vector = $this->callerVector($input, $dims);
                } else {
                    $apiName = $field->queryApiName ?? $field->apiName;
                    $pool = $this->vectorPools[$apiName ?? 'default'] ?? null;
                    $vector = $pool?->get((string) $input, $dims);
                }

                if ($vector === null) {
                    continue;
                }

                $normalize = ! $input->hasVector() || $field->similarity === VectorSimilarity::DotProduct;
                if ($field->autoNormalizeVector && $normalize && ! VectorMath::isNormalized($vector)) {
                    $vector = VectorMath::normalize($vector);
                }

                $mapped = $field instanceof NestedVector ? $driver->nestedVectorField($field) : $driver->vectorField($field);
                foreach ($mapped->vectorQueries($vector, $k, $filter) as $query) {
                    $query->boost($boost);
                    $raw = $useTopLevelKnn ? $query->toRaw() : [];
                    if (isset($raw['knn'])) {
                        $knn[] = $raw['knn'];
                    } else {
                        $semantic[] = $query;
                    }
                }
            }
        }

        return ['knn' => $knn, 'semantic' => $semantic];
    }

    private function matchesVectorField(QueryString|QueryImage $query, BaseVector|NestedVector $field): bool
    {
        return (! $query->hasDimension() || $query->dimension() === $field->dims())
            && (! $query instanceof QueryString || $query->vectorField === null || $query->vectorField === $field->name);
    }

    protected function getVectorFields(QueryString|QueryImage|null $query = null): Collection
    {
        $fields = $this->semanticFields($query?->fields())
            ->map(fn (Text $field): Collection => $field->vectorFields())
            ->flatten(1);

        return $query === null ? $fields : $fields->filter(fn ($field): bool => $this->matchesVectorField($query, $field));
    }

    private function callerVector(QueryString|QueryImage $query, int $dims): ?array
    {
        $vector = $query->vector();

        if (count($vector) !== $dims) {
            return null;
        }

        return $vector;
    }

    protected function getVectorDimensions(Collection $vectorFields): array
    {
        return $vectorFields
            ->map(fn (NestedVector|DenseVector|BaseVector $field): int => $field->dims())
            ->unique()
            ->toArray();
    }

    protected function createTextQuery(string $queryString, float $queryBoost = 1.0, ?array $scopedFields = null): Query
    {
        $keywordBoolean = new Boolean;
        $keywordBoolean->should()->query(new MatchNone);

        $fieldsToQuery = $scopedFields ?? $this->searchFields();
        $fields = new Collection($fieldsToQuery);

        $fields->each(function ($field) use ($keywordBoolean, $queryString, $queryBoost): void {
            $field = $this->properties->get($field) ?? throw new PropertiesFieldNotFound($field);

            $queries = $this->buildFieldQueries($field, $queryString);
            $queries = new Collection($queries);

            $queries->map(function (Query $queryClause) use ($queryBoost, $field): Query {

                $queryClause = $this->configureQueryClause($queryClause, $field, $queryBoost);

                return $this->wrapNestedQuery($queryClause, $field);

            })->each(fn (Query $query): BooleanQueryBuilder => $keywordBoolean->should()->query($query));
        });

        return $this->applyTextScoring($keywordBoolean);
    }

    protected function buildFieldQueries($field, string $queryString): array
    {
        return $field->queryStringQueries($queryString);
    }

    protected function configureQueryClause(Query $queryClause, Text|Type $field, float $queryBoost): Query
    {
        return $this->weightedFieldQuery($queryClause, $field->fullPath(), $this->queryBoost($field, $queryBoost));
    }

    protected function wrapNestedQuery(Query $queryClause, $field): Query
    {
        if (($nestedPath = $field->nestedPath()) !== null) {
            return new Nested($nestedPath, $queryClause, innerHits: $this->searchContext->innerHits);
        }

        return $queryClause;
    }

    public function formatter(ResponseFormater $formatter): static
    {
        $this->formatter = $formatter;

        return $this;
    }

    public function formatRespones(array $searchResponse, array $facetsResponse, ?int $httpCode = null)
    {
        search_hits($searchResponse);
        search_hits($facetsResponse);

        if (($collapsedSort = $this->openSearchCollapsedSort()) instanceof CollapsedSort && isset($searchResponse['hits']['hits'])) {
            foreach ($searchResponse['hits']['hits'] as &$hit) {
                // Numeric missing values use a sort sentinel that can also be a real value.
                if (isset($hit['sort']) && ! isset($hit['fields'][$collapsedSort->field])) {
                    $hit['sort'][0] = null;
                }
            }

            unset($hit);
        }

        $formatter = isset($this->formatter)
            ? clone $this->formatter
            : new SigmieSearchResponse($this->properties, $this->semanticSearch);
        $formatter = $formatter->context(clone $this->searchContext)
            ->apis($this->apis)
            ->errors([
                ...$this->filterParser->errors(),
                ...$this->facetFilterErrors,
                ...$this->facetParser->errors(),
                ...$this->sortParser->errors(),
            ])
            ->facetsResponseRaw($facetsResponse)
            ->queryResponseRaw($searchResponse);

        if (is_callable([$formatter, 'responseCode'])) {
            $formatter->responseCode($httpCode ?? 200);
        }

        return $formatter;
    }

    public function get(): ResponseFormater
    {
        $multi = new NewMultiSearch($this->elasticsearchConnection);
        $multi->add($this);

        return $multi->get()[0];
    }

    public function promise(): Promise
    {
        return $this->makeSearch()->promise();
    }

    protected function applyTextScoring(Query $query): Query
    {
        return new FunctionScore(
            $query,
            source: 'return _score * params.multiplier;',
            params: ['multiplier' => $this->textScoreMultiplier],
            boostMode: 'replace'
        );
    }

    public function toMultiSearch(): array
    {
        $header = ['index' => $this->index];
        $body = [$header, $this->makeSearch()->toRaw()];

        if ($this->multisearchResCount() === 2) {
            $body[] = $header;
            $body[] = $this->makeFacetSearch()->toRaw();
        }

        return $body;
    }

    public function formatResponses(...$responses): mixed
    {
        $response = $responses[0] ?? [];
        $httpCode = $responses['httpCode'] ?? null;

        $facetsResponse = $responses[1] ?? $response;
        foreach ($this->facetAggregationAliases as $name => $alias) {
            if (isset($facetsResponse['aggregations'][$alias])) {
                $facetsResponse['aggregations'][$name] = $facetsResponse['aggregations'][$alias];
                unset($facetsResponse['aggregations'][$alias]);
            }

            unset($response['aggregations'][$alias]);
        }

        return $this->formatRespones($response, $facetsResponse, $httpCode);
    }

    public function multisearchResCount(): int
    {
        if ($this->searchContext->facetFields === [] || $this->searchContext->facetFilterString === '') {
            return 1;
        }

        // kNN pre-filters hide candidates that the selected facet still needs to count.
        if ($this->knn !== []) {
            return 2;
        }

        foreach ($this->semanticQueries as $query) {
            if ($query instanceof ElasticsearchKnn || $query instanceof OpenSearchKnn) {
                return 2;
            }
        }

        return 1;
    }

    public function forHits(): static
    {
        if (isset($this->formatter) && ! is_callable([$this->formatter, 'hits'])) {
            $search = clone $this;
            unset($search->formatter);

            return $search;
        }

        return $this;
    }

    public function hits()
    {
        return $this->forHits()->get()->hits();
    }

    public function chunk(int $size = 500): static
    {
        $this->pitIterationChunkSize = $size;

        return $this;
    }

    /**
     * @return Generator<int, Hit>
     */
    public function lazy(): Generator
    {
        yield from $this->iterateHits();
    }

    public function each(Closure $fn): void
    {
        foreach ($this->iterateHits() as $hit) {
            $fn($hit);
        }
    }

    /**
     * @return Generator<int, Hit>
     */
    protected function iterateHits(): Generator
    {
        $pit = new PointInTimeRequests($this->elasticsearchConnection);
        $isOpenSearch = $this->elasticsearchConnection->driver()->engine() === SearchEngineType::OpenSearch;

        $body = $this->buildSearch($this->pitIterationChunkSize)->toRaw();
        $innerHits = clone $this->searchContext->innerHits;

        $hasCollapse = array_key_exists('collapse', $body);
        $sort = $body['sort'] ?? [];

        unset(
            $body['from'],
            $body['size'],
            $body['aggs'],
            $body['suggest'],
            $body['track_total_hits'],
            $body['sort'],
        );

        $body['size'] = $this->pitIterationChunkSize;
        $body['sort'] = PitSortPlanner::plan($sort, $isOpenSearch, $hasCollapse, preserveSingleSort: $this->searchContext->sortString !== '');

        $keepAlive = '1m';
        $open = $pit->open($this->index, $keepAlive);
        $pitId = PointInTimeIterator::pitIdFromOpenResponse($open, $isOpenSearch);

        yield from PointInTimeIterator::iterate(
            $pitId,
            $keepAlive,
            $body,
            fn (array $requestBody): ElasticsearchResponse => $pit->search($requestBody),
            function (string $id) use ($pit): void {
                $pit->close($id);
            },
            fn (array $data): Hit => Hit::fromRaw($innerHits->formatRawHit($data)),
        );
    }

    public function apis(array $apis): static
    {
        foreach ($this->apis as $name => $previous) {
            if (($apis[$name] ?? null) !== $previous) {
                unset($this->vectorPools[$name]);
            }
        }

        return $this->registerApis($apis);
    }

    public function setVectorPool(VectorPool|array $pool, ?string $apiName = null): static
    {
        if ($pool instanceof VectorPool) {
            if ($apiName === null) {
                // If no API name specified, try to get the first one
                $apis = $this->getRequiredEmbeddingApis();
                $apiName = $apis === [] ? 'default' : reset($apis);
            }

            $this->vectorPools[$apiName] = $pool;
        } elseif ($apiName !== null) {
            if (! isset($this->vectorPools[$apiName]) && ($api = $this->getApi($apiName)) instanceof EmbeddingsApi) {
                $this->vectorPools[$apiName] = new VectorPool($api, ensureNormalized: false);
            }

            ($this->vectorPools[$apiName] ?? null)?->setPool($pool);
        } else {
            // An unnamed seed applies to each existing pool.
            foreach ($this->vectorPools as $vectorPool) {
                $vectorPool->setPool($pool);
            }
        }

        return $this;
    }

    public function getVectorPools(): array
    {
        return $this->vectorPools;
    }

    public function getVectorPool(?string $apiName = null): ?VectorPool
    {
        if ($apiName !== null) {
            return $this->vectorPools[$apiName] ?? null;
        }

        // Return first VectorPool if no name specified
        return $this->vectorPools === [] ? null : reset($this->vectorPools);
    }

    public function queryStrings(): array
    {
        return $this->searchContext->queryStrings;
    }

    public function getProperties(): ?Properties
    {
        return $this->properties;
    }

    protected function getRequiredEmbeddingApis(): array
    {
        $apis = [];

        $queries = [...$this->searchContext->queryStrings, ...$this->searchContext->queryImages];
        foreach ($queries === [] ? [null] : $queries as $query) {
            foreach ($this->getVectorFields($query) as $field) {
                $apiName = $field->queryApiName ?? $field->apiName;
                if ($apiName !== null) {
                    $apis[$apiName] = true;
                }
            }
        }

        return array_keys($apis);
    }
}
