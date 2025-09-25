<?php

declare(strict_types=1);

namespace Sigmie\Search;

use GuzzleHttp\Promise\Utils;
use Http\Promise\Promise;
use Sigmie\AI\Contracts\Embedder;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Base\ElasticsearchException;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Base\Http\Responses\Search as ResponsesSearch;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\PropertiesFieldNotFound;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\Nested as TypesNested;
use Sigmie\Mappings\Types\NestedVector;
use Sigmie\Mappings\Types\SigmieVector;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type;
use Sigmie\Parse\FacetParser;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\SortParser;
use Sigmie\Plugins\Elastiknn\NearestNeighbors;
use Sigmie\Query\Contracts\FuzzyQuery;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\MatchNone;
// use Sigmie\Query\Queries\Query;
use Sigmie\Query\Contracts\QueryClause as Query;
use Sigmie\Query\Facets;
use Sigmie\Query\Queries\NearestNeighbors as QueriesNearestNeighbors;
use Sigmie\Query\Queries\Text\Nested;
use Sigmie\Query\Search;
use Sigmie\Query\Suggest;
use Sigmie\Search\Contracts\EmbeddingsQueries;
use Sigmie\Search\Contracts\MultiSearchable;
use Sigmie\Search\Contracts\ResponseFormater;
use Sigmie\Search\Contracts\SearchQueryBuilder as SearchQueryBuilderInterface;
use Sigmie\Search\Formatters\RawElasticsearchFormat;
use Sigmie\Search\Formatters\SigmieSearchResponse;
use Sigmie\Search\QueryString;
use Sigmie\Shared\Collection;

class NewSearch extends AbstractSearchBuilder implements SearchQueryBuilderInterface, MultiSearchable
{
    protected array $sort = ['_score'];

    protected array $embeddings = [];

    protected array $knn = [];

    protected array $semanticQueries = [];

    protected string $index;

    protected float $textScoreMultiplier = 1.0;

    protected float $semanticScoreMultiplier = 1.0;

    protected ResponseFormater $formatter;

    protected SearchContext $searchContext;

    protected FilterParser $filterParser;

    protected FacetParser $facetParser;

    protected SortParser $sortParser;

    protected array $vectorPool = [];

    public function __construct(
        ElasticsearchConnection $elasticsearchConnection,
        protected ?EmbeddingsApi $embeddingsApi = null
    ) {

        parent::__construct($elasticsearchConnection);

        $this->searchContext = new SearchContext();
        $this->filterParser = new FilterParser($this->properties, false);
        $this->facetParser = new FacetParser($this->properties, false);
        $this->sortParser = new SortParser($this->properties, false);
    }

    public function page(int $page, int $perPage = 20): static
    {
        $this->searchContext->from = ($page - 1) * $perPage;
        $this->searchContext->size = $perPage;

        return $this;
    }

    public function queryString(string $query, float $weight = 1.0): static
    {
        $this->searchContext->queryStrings[] = new QueryString($query, $weight);

        return $this;
    }

    public function autocompletePrefix(string $prefix): static
    {
        $this->searchContext->autocompletePrefixStrings[] = $prefix;

        return $this;
    }

    public function index(string $index): static
    {
        $this->index = $index;

        return $this;
    }

    public function properties(Properties|NewProperties $props): static
    {
        parent::properties($props);

        $this->filterParser->properties($props);
        $this->facetParser->properties($props);
        $this->sortParser->properties($props);

        return $this;
    }

    public function filters(string $filters, bool $thorwOnError = true): static
    {
        $this->searchContext->filterString = $filters;

        $filters = implode(
            ' AND ',
            array_filter([
                "({$this->searchContext->filterString})",
                "({$this->searchContext->facetFilterString})",
            ], fn($filter) => !empty(trim($filter, '()')))
        );

        $this->globalFilters = $this->filterParser->parse($this->searchContext->filterString);
        $this->filters = $this->filterParser->parse($filters);

        return $this;
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
    ): static {

        $facetFilterString = $this->facetParser->parseFilterString($filters);

        $this->searchContext->facetFields = $this->facetParser->fields($facets);
        $this->searchContext->facetString = $facets;
        $this->searchContext->facetFilterString = $facetFilterString;

        $allFilters = implode(
            ' AND ',
            array_filter([
                "({$this->searchContext->filterString})",
                "({$this->searchContext->facetFilterString})",
            ], fn($filter) => !empty(trim($filter, '()')))
        );

        $this->filters = $this->filterParser->parse($allFilters);
        $this->facets = $this->facetParser->parse($facets, $facetFilterString);

        return $this;
    }

    public function sort(string $sort = '_score', bool $thorwOnError = true): static
    {
        $parser = new SortParser($this->properties, $thorwOnError);

        $this->searchContext->sortString = $sort;
        $this->sort = $parser->parse($sort);

        return $this;
    }


    protected function handleHighlight(Search $search)
    {
        $highlight = new Collection($this->highlight);

        $fields = [];
        $highlight->each(function (string $field) use (&$fields) {
            $properties = $this->properties;

            $field = $properties->get($field);

            foreach ($field->names() as $name) {
                $fields[$name] = [
                    'type' => 'plain',
                    'force_source' => true,
                    'pre_tags' => [$this->highlightPrefix],
                    'post_tags' => [$this->highlightSuffix],
                    'fragment_size' => 150,
                    'number_of_fragments' => 3,
                    'no_match_size' => 150,
                ];
            }
        });

        $search->highlight([
            // 'require_field_match' => false,
            'force_source' => true,
            'no_match_size' => 100,
            'fields' => $fields
        ]);
    }

    public function size(int $size = 20): static
    {
        $this->searchContext->size = $size;

        return $this;
    }

    protected function handleRetrievableFields(Search $search)
    {
        $search->fields($this->retrieve ?? $this->properties->fieldNames());
    }

    protected function handleSize(Search $search)
    {
        $search->size($this->searchContext->size);
    }

    protected function handleFrom(Search $search)
    {
        $search->from($this->searchContext->from);
    }

    protected function handleKnn(Search $search)
    {
        if (!empty($this->knn)) {
            $search->knn($this->knn);
        }
    }

    protected function handleAggs(Search $search)
    {
        $search->addRaw('aggs', $this->facets->toRaw());
    }

    protected function handleSort(Search $search)
    {
        $search->addRaw('sort', $this->sort);
    }

    protected function handleMinScore(Search $search)
    {
        $minScore = $this->semanticSearch && $this->minScore == 0 ? 0 : $this->minScore;

        $search->minScore($minScore);
    }

    protected function handleFiltersQuery(Boolean $boolean)
    {
        $boolean->must()->bool(
            fn(Boolean $boolean) => $boolean->filter()->query(
                $this->filters
            )
        );
    }

    protected function handleQueryStrings(Boolean $boolean): void
    {
        $boolean->must()->bool(function (Boolean $boolean) {

            $queryStrings = new Collection($this->searchContext->queryStrings);

            $queryStrings->each(function (QueryString $queryString) use ($boolean) {

                $query = $this->createStringQueries($queryString->text(), $queryString->weight());

                $boolean->should()->query($query);
            });

            // Add semantic queries (function_score queries for accuracy 7)
            foreach ($this->semanticQueries as $semanticQuery) {
                $boolean->should()->query($semanticQuery);
            }
        });
    }

    protected function handleSuggest(Search $search): void
    {
        if (
            ($this->searchContext->autocompletePrefixStrings[0] ?? false)
            && ($this->properties->autocompleteField ?? false)
        ) {
            $suggest = new Suggest;

            $suggest->completion(name: 'autocompletion')
                ->field($this->properties->autocompleteField->name())
                ->size($this->autocompleteSize)
                ->fuzzyMinLegth($this->autocompleteFuzzyMinLength)
                ->fuzzyPrefixLenght($this->autocompleteFuzzyPrefixLength)
                ->fuzzy($this->autocompletion)
                ->prefix($this->searchContext->autocompletePrefixStrings[0]);

            $search->suggest($suggest);
        }
    }

    protected function handleTrackTotalHits(Search $search)
    {
        $search->trackTotalHits();
    }

    protected function handleBoostField(Query $query)
    {
        if ($this->properties->boostField ?? false) {
            return new FunctionScore(
                $query,
                source: $this->properties->boostField->scriptScoreSource(),
                boostMode: $this->properties->boostField->scriptScoreBoostMode()
            );
        }

        return $query;
    }

    public function makeFacetSearch(): Search
    {
        $facets = new Search($this->elasticsearchConnection);

        $facets->index($this->index)
            ->query($this->globalFilters)
            ->aggs($this->facets);

        return $facets;
    }

    public function makeSearch(): Search
    {
        $search = new Search($this->elasticsearchConnection);

        $search->index($this->index);

        // $this->handleHighlight($search);
        $this->handleRetrievableFields($search);
        $this->handleSort($search);
        $this->handleAggs($search);
        $this->handleSize($search);
        $this->handleFrom($search);
        $this->handleMinScore($search);
        $this->handleSuggest($search);
        $this->handleTrackTotalHits($search);

        $boolean = new Boolean;

        $this->populateVectorPool();

        // Build KNN queries independently before main query
        $this->buildKnnQueries();
        $this->handleKnn($search);

        $this->buildMainQuery($boolean);

        $query = $this->handleBoostField($boolean);

        $search->query($query);

        return $search;
    }

    protected function buildMainQuery(Boolean $boolean): void
    {
        $this->handleFiltersQuery($boolean);
        $this->handleQueryStrings($boolean);
    }

    protected function populateVectorPool(): void
    {
        $vectorFieldsFields = $this->properties->nestedSemanticFields()
            ->filter(fn(Text $field) => $field->isSemantic())
            // Only fields that are in the fields array
            ->filter(fn(Text $field) => in_array($field->fullPath, $this->fields))
            ->map(fn(Text $field) => $field->vectorFields())
            ->flatten(1);

        $dims = $vectorFieldsFields
            ->map(fn(NestedVector|DenseVector|SigmieVector $field) => $field->dims())
            ->unique()
            ->toArray();

        $pool = [];

        foreach ($dims as $dim) {
            $pool[$dim] ?? $pool[$dim] = [];
            $pool[$dim] = array_map(fn(QueryString $queryString) => $this->embeddingsApi->promiseEmbed($queryString->text(), $dim), $this->searchContext->queryStrings);
        }

        $this->vectorPool = $pool;
    }

    protected function onEmptyQueryString(): Query
    {
        if ($this->noResultsOnEmptySearch) {
            return new MatchNone;
        }

        return new MatchAll;
    }

    protected function queryBoost(Type $field, float $queryWeight): float
    {
        $fieldWeight = $this->weight[$field->fullPath] ?? 1;

        $boost = array_key_exists($field->fullPath, $this->weight) ? $fieldWeight * $queryWeight : $queryWeight;

        return $boost;
    }

    protected function queryFuzziness(Text $field): ?string
    {
        return ! in_array($field->fullPath, $this->typoTolerantAttributes) ? null : "AUTO:{$this->minCharsForOneTypo},{$this->minCharsForTwoTypo}";
    }

    protected function createStringQueries(string $queryString, float $queryBoost = 1.0): Query
    {
        if ($queryString === '' && !$this->noResultsOnEmptySearch) {
            return $this->onEmptyQueryString();
        }

        $keywordQuery = $this->buildKeywordQuery($queryString, $queryBoost);

        $boolean = new Boolean;
        $boolean->should()->query($keywordQuery);

        return $boolean;
    }

    protected function buildKnnQueries(): void
    {
        if (!$this->semanticSearch) {
            $this->knn = [];
            $this->semanticQueries = [];
            return;
        }

        $allKnnQueries = [];
        $allSemanticQueries = [];

        foreach ($this->searchContext->queryStrings as $queryString) {
            if (trim($queryString->text()) === '') {
                continue;
            }

            $result = $this->createVectorQuery($queryString->text(), $queryString->weight());
            $allKnnQueries = array_merge($allKnnQueries, $result['knn']);
            $allSemanticQueries = array_merge($allSemanticQueries, $result['semantic']);
        }

        $this->knn = $allKnnQueries;
        $this->semanticQueries = $allSemanticQueries;
    }

    protected function buildKeywordQuery(string $queryString, float $queryBoost): Query
    {
        if ($this->noKeywordSearch) {
            return new MatchNone;
        }

        return $this->createTextQuery($queryString, $queryBoost);
    }

    protected function createVectorQuery(string $queryString, float $queryBoost = 1.0): array
    {
        $vectorFields = $this->getVectorFields();
        $dims = $this->getVectorDimensions($vectorFields);

        if (empty($dims)) {
            return ['knn' => [], 'semantic' => []];
        }

        $embeddings = $this->getEmbeddings($dims, $queryString);
        $vectorByDims = $this->mapEmbeddingsByDimensions($embeddings);

        $vectorQueries = $this->buildVectorQueries($vectorFields, $vectorByDims, $queryBoost);

        $knnQueries = [];
        $semanticQueries = [];
        
        $vectorQueries->each(function (Query $query) use (&$knnQueries, &$semanticQueries) {
            $raw = $query->toRaw();
            if (isset($raw['knn'])) {
                $knnQueries[] = $raw['knn'];
            } else {
                // This is a function_score or other non-KNN query
                $semanticQueries[] = $query;
            }
        });

        return ['knn' => $knnQueries, 'semantic' => $semanticQueries];
    }

    protected function getVectorFields(): Collection
    {
        return $this->properties->nestedSemanticFields()
            ->filter(fn(Text $field) => $field->isSemantic())
            ->filter(fn(Text $field) => in_array($field->fullPath, $this->fields))
            ->map(fn(Text $field) => $field->vectorFields())
            ->flatten(1);
    }

    protected function getVectorDimensions(Collection $vectorFields): array
    {
        return $vectorFields
            ->map(fn(NestedVector|DenseVector|SigmieVector $field) => $field->dims())
            ->unique()
            ->toArray();
    }

    protected function getEmbeddings(array $dims, string $queryString): array
    {
        return array_map(fn($dim) => [
            'dims' => $dim,
            'vector' => $this->embeddingsApi->embed($queryString, $dim)
        ], $dims);
    }

    protected function mapEmbeddingsByDimensions(array $embeddings): Collection
    {
        return (new Collection($embeddings))->mapWithKeys(fn($item) => [$item['dims'] => $item['vector']]);
    }

    protected function buildVectorQueries(Collection $vectorFields, Collection $vectorByDims, float $queryBoost): Collection
    {
        return $vectorFields
            ->map(function (TypesNested|DenseVector $field) use ($vectorByDims) {

                $vectors = $vectorByDims->get($field->dims());

                return $field->queries($vectors);
            })
            ->flatten(1)
            ->map(function (Query $query) use ($queryBoost) {

                return $this->configureVectorQuery($query, $queryBoost);
            });
    }

    protected function configureVectorQuery(Query $query, float $queryBoost): Query
    {
        if ($query instanceof NearestNeighbors || $query instanceof QueriesNearestNeighbors) {
            $query->k($this->searchContext->size);
        }

        if ($query instanceof QueriesNearestNeighbors) {
            $query->filter($this->filters->toRaw());
        }

        return $query;
    }

    protected function createTextQuery(string $queryString, float $queryBoost = 1.0): Query
    {
        $keywordBoolean = new Boolean;
        $keywordBoolean->should()->query(new MatchNone);

        $fields = new Collection($this->fields);

        $fields->each(function ($field) use ($keywordBoolean, $queryString, $queryBoost) {
            $field = $this->properties->get($field) ?? throw new PropertiesFieldNotFound($field);

            $queries = $this->buildFieldQueries($field, $queryString);
            $queries = new Collection($queries);

            $queries->map(function (Query $queryClause) use ($keywordBoolean, $queryBoost, $field) {
                $queryClause = $this->configureQueryClause($queryClause, $field, $queryBoost);
                return $this->wrapNestedQuery($queryClause, $field);
            })->each(fn(Query $query) => $keywordBoolean->should()->query($query));
        });

        return $this->applyTextScoring($keywordBoolean);
    }

    protected function buildFieldQueries($field, string $queryString): array
    {
        return $field->queryStringQueries($queryString);
    }

    protected function configureQueryClause(Query $queryClause, $field, float $queryBoost): Query
    {
        if ($queryClause instanceof FuzzyQuery) {
            $fuzziness = $this->queryFuzziness($field);
            $queryClause->fuzziness($fuzziness);
        }

        $boost = $this->queryBoost($field, $queryBoost);
        return $queryClause->boost($boost);
    }

    protected function wrapNestedQuery(Query $queryClause, $field): Query
    {
        if (($field->parentPath ?? false) && $field->parentType === TypesNested::class) {
            return new Nested($field->parentPath, $queryClause);
        }
        return $queryClause;
    }

    public function formatter(ResponseFormater $formatter): static
    {
        $this->formatter = $formatter;

        return $this;
    }

    public function formatRespones($searchResponse, $facetsResponse)
    {
        $formatter = $this->formatter ?? new SigmieSearchResponse($this->properties, $this->semanticSearch);

        $formatter->context($this->searchContext)
            ->errors([
                ...$this->filterParser->errors(),
                ...$this->facetParser->errors(),
                ...$this->sortParser->errors(),
            ])
            ->facetsResponseRaw($facetsResponse)
            ->queryResponseRaw($searchResponse);

        return $formatter;
    }

    public function get(): ResponseFormater
    {
        $multi = new NewMultiSearch($this->elasticsearchConnection);

        $multi->raw($this->index, $this->makeSearch()->toRaw());
        $multi->raw($this->index, $this->makeFacetSearch()->toRaw());

        [$searchResponse, $facetsResponse] = $multi->get();

        return $this->formatRespones($searchResponse, $facetsResponse);
    }

    

    public function promise(): Promise
    {
        return $this->makeSearch()->promise();
    }

    protected function applyTextScoring(Query $query): Query
    {
        return new FunctionScore(
            $query,
            source: "return _score * {$this->textScoreMultiplier};",
            boostMode: 'replace'
        );
    }

    public function toMultiSearch(): array
    {
        return [
            [
                'index' => $this->index
            ],
            $this->makeSearch()->toRaw(),
            [
                'index' => $this->index
            ],
            $this->makeFacetSearch()->toRaw()
        ];
    }

    public function formatResponses(...$responses): mixed
    {
        $searchResponse = $responses[0] ?? [];
        $facetsResponse = $responses[1] ?? [];

        return $this->formatRespones($searchResponse, $facetsResponse);
    }

    public function multisearchResCount(): int
    {
        return 2; // search + facets
    }

    public function hits()
    {
        return $this->get()->hits();
    }
}
