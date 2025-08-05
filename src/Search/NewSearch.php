<?php

declare(strict_types=1);

namespace Sigmie\Search;

use GuzzleHttp\Promise\Utils;
use Http\Promise\Promise;
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
use Sigmie\Query\Queries\Text\Nested;
use Sigmie\Query\Search;
use Sigmie\Query\Suggest;
use Sigmie\Search\Contracts\EmbeddingsQueries;
use Sigmie\Search\Contracts\ResponseFormater;
use Sigmie\Search\Contracts\SearchQueryBuilder as SearchQueryBuilderInterface;
use Sigmie\Search\Formatters\RawElasticsearchFormat;
use Sigmie\Search\Formatters\SigmieSearchResponse;
use Sigmie\Shared\Collection;

class NewSearch extends AbstractSearchBuilder implements SearchQueryBuilderInterface
{
    protected array $sort = ['_score'];

    protected array $embeddings = [];

    protected string $index;

    protected float $textScoreMultiplier = 1.0;

    protected float $semanticScoreMultiplier = 1.0;

    protected ResponseFormater $formatter;

    protected SearchContext $searchContext;

    protected FilterParser $filterParser;

    protected FacetParser $facetParser;

    protected SortParser $sortParser;

    public function __construct(
        ElasticsearchConnection $elasticsearchConnection,
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
        $this->searchContext->queryStrings[] = [
            'query' => $query,
            'weight' => $weight
        ];

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
        $minScore = $this->semanticSearch && $this->minScore == 0 ? 0.01 : $this->minScore;

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

    public function handleQueryStrings(Boolean $boolean)
    {
        $boolean->must()->bool(function (Boolean $boolean) {

            $queryBoolean = new Boolean;

            $shouldClauses = new Collection();

            $queryStrings = new Collection($this->searchContext->queryStrings);

            $queryStrings->each(function (array $weightedQuery) use ($shouldClauses) {

                $query = $this->addTextQueries($weightedQuery['query'], $weightedQuery['weight']);

                $shouldClauses->add($query);
            });


            if (
                $shouldClauses->isEmpty() &&
                empty($this->searchContext->queryStrings) &&
                !$this->noResultsOnEmptySearch
            ) {
                $queryBoolean->should()->query(new MatchAll);
            } else if ($shouldClauses->isEmpty()) {
                $queryBoolean->should()->query(new MatchNone);
            } else {
                $shouldClauses->each(fn(Query $queryClase) => $queryBoolean->should()->query($queryClase));
            }

            $boolean->should()->query($queryBoolean);
        });
    }

    public function handleSuggest(Search $search)
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

    public function make(): Search
    {
        $search = new Search($this->elasticsearchConnection);

        $search->index($this->index);

        $this->handleHighlight($search);

        $this->handleRetrievableFields($search);

        $this->handleSort($search);

        $this->handleAggs($search);

        $this->handleSize($search);

        $this->handleFrom($search);

        $this->handleMinScore($search);

        $boolean = new Boolean;

        $this->handleFiltersQuery($boolean);

        $this->handleQueryStrings($boolean);

        $this->handleSuggest($search);

        $this->handleTrackTotalHits($search);

        $query = $this->handleBoostField($boolean);

        $search->query($query);

        return $search;
    }

    protected function onEmptyQueryString(): Query
    {
        if ($this->noResultsOnEmptySearch) {
            return new MatchNone;
        }

        return new MatchAll;
    }

    public function queryBoost(Type $field, float $queryWeight): float
    {
        $fieldWeight = $this->weight[$field->fullPath] ?? 1;

        $boost = array_key_exists($field->fullPath, $this->weight) ? $fieldWeight * $queryWeight : $queryWeight;

        return $boost;
    }

    public function queryFuzziness(Text $field): ?string
    {
        return ! in_array($field->fullPath, $this->typoTolerantAttributes) ? null : "AUTO:{$this->minCharsForOneTypo},{$this->minCharsForTwoTypo}";
    }

    protected function addTextqueries(string $queryString,  float $queryBoost = 1.0): Query
    {
        if ($queryString === '' && !$this->noResultsOnEmptySearch) {
            return $this->onEmptyQueryString();
        }

        $semanticQuery = new MatchNone;

        if ($this->semanticSearch && trim($queryString) !== '') {

            $vectorFieldsFields = $this->properties->nestedSemanticFields()
                ->filter(fn(Text $field) => $field->isSemantic())
                // Only fields that are in the fields array
                ->filter(fn(Text $field) => $fields->indexOf($field->name()) !== false)
                ->map(fn(Text $field) => $field->vectorFields())
                ->flatten(1);

            $dims = $vectorFieldsFields
                ->map(function (NestedVector|DenseVector|SigmieVector $field) use ($queryString) {

                    $name = $field->name();

                    return [
                        'text' => $queryString,
                        'dims' => (string) $field->dims(),
                        'name' => $name,
                        'vector' => []
                    ];
                })
                ->uniqueBy('dims')
                ->toArray();

            $embeddings = $this->aiProvider->batchEmbed($dims);

            // Array that has as key the dims and as value the vector
            // eg. [1024 => [1, 2, 3], 2048 => [4, 5, 6]]
            $vectorByDims = (new Collection($embeddings))->mapWithKeys(fn($item) => [$item['dims'] => $item['vector']]);

            $vectorQueries = $vectorFieldsFields
                ->map(function (TypesNested|DenseVector $field) use ($vectorByDims) {

                    $vectors = $vectorByDims->get($field->dims());

                    return $field->queries($vectors);
                })
                ->flatten(1)
                ->map(function (Query $query) use ($queryBoost) {
                    if ($query instanceof NearestNeighbors) {
                        $query->k($this->searchContext->size);
                    }

                    return $query;
                });

            $vectorBool = new Boolean;
            // An empty boolean query acts like a match_all for this reason
            // we make sure the boolean query is not empty by adding a match none
            $vectorBool->should()->query(new MatchNone);
            $vectorQueries
                ->each(fn(Query $query) => $vectorBool->should()->query($query));

            // Supported since ES 8.12
            // https://discuss.elastic.co/t/knn-search-with-function-score-or-scoring-script/356432
            $functionScore = new FunctionScore(
                $vectorBool,
                source: "return _score * {$this->semanticScoreMultiplier};",
                // source: "return _score > {$this->semanticThreshold} ? _score : 0;",
                boostMode: 'replace'
                // boostMode: 'multiply'
            );
            $shouldClauses->add($functionScore);
        }

        $keywordQuery = new MatchNone;

        if (!$this->noKeywordSearch) {
            $keywordQuery = $this->createTextQuery($queryString, $queryBoost);
        }

        $boolean = new Boolean;
        $boolean->should()->query($semanticQuery);
        $boolean->should()->query($keywordQuery);

        return $boolean;
    }

    protected function createTextQuery(string $queryString, float $queryBoost = 1.0): Query
    {
        $keywordBoolean = new Boolean;

        // An empty boolean query acts like a match_all for this reason
        // we make sure the boolean query is not empty by adding a match none
        $keywordBoolean->should()->query(new MatchNone);

        // Reuqested field names eg. ['title', 'category']
        $fields = new Collection($this->fields);

        $fields->each(function ($field) use ($keywordBoolean, $queryString, $queryBoost) {

            $field = $this->properties->get($field) ?? throw new PropertiesFieldNotFound($field);

            $queries = match (true) {
                $field->hasQueriesCallback ?? false => $field->queriesFromCallback($queryString),
                default => $field->queries($queryString)
            };

            $queries = new Collection($queries);

            $queries->map(function (Query $queryClause) use ($keywordBoolean, $queryBoost, $field) {

                // Handle fuzziness for fuzzy queries
                if ($queryClause instanceof FuzzyQuery) {
                    $fuzziness = $this->queryFuzziness($field);
                    $queryClause->fuzziness($fuzziness);
                }

                // Handle boost for all queries
                $boost = $this->queryBoost($field, $queryBoost);
                $queryClause = $queryClause->boost($boost);

                // Query nested fields if there is a parent path
                if (($field->parentPath ?? false) && $field->parentType === TypesNested::class) {
                    return new Nested($field->parentPath, $queryClause);
                }

                return $queryClause;
            })
                ->each(fn(Query $query) => $keywordBoolean->should()->query($query));
        });

        // $textQueries->each(fn(Query $query) => $shouldClauses->add($query));
        // $textQueries->each(fn(Query $query) => $textBool->should()->query($query));

        $textFnScore = new FunctionScore(
            $keywordBoolean,
            source: "return _score * {$this->textScoreMultiplier};",
            // source: "return _score / 10;",
            // source: "return Math.min(_score, 0.1);", // Caps BM25 impact
            boostMode: 'replace',
            // boostMode: 'multiply'
            // source: "return Math.min(_score, 0.5);", // Caps BM25 impact
            // boostMode: 'replace'
            // boostMode: 'multiply'
            // source: "return _score;",
        );

        return $textFnScore;
    }

    public function formatter(ResponseFormater $formatter): static
    {
        $this->formatter = $formatter;

        return $this;
    }

    public function get(): ResponseFormater
    {
        $facets = new Facets(
            $this->elasticsearchConnection,
            filters: $this->globalFilters,
            aggs: $this->facets
        );
        $facets->index($this->index);
        $facets->setElasticsearchConnection($this->elasticsearchConnection);

        [$searchResponse, $facetsResponse] = Utils::all([
            $this->make()->promise(),
            $facets->promise()
        ])->wait();

        if ($searchResponse->failed() || $facetsResponse->failed()) {
            throw new ElasticsearchException($searchResponse->json(), $searchResponse->code());
        }

        $formatter = $this->formatter ?? new SigmieSearchResponse($this->properties);

        $formatter->context($this->searchContext)
            ->errors([
                ...$this->filterParser->errors(),
                ...$this->facetParser->errors(),
                ...$this->sortParser->errors(),
            ])
            ->facetsResponseRaw($facetsResponse->json())
            ->queryResponseRaw($searchResponse->json());

        return $formatter;
    }

    public function promise(): Promise
    {
        return $this->make()->promise();
    }
}
