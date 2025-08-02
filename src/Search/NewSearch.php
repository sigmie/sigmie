<?php

declare(strict_types=1);

namespace Sigmie\Search;

use GuzzleHttp\Promise\Utils;
use Http\Promise\Promise;
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
use Sigmie\Shared\EmbeddingsProvider;

use function Sigmie\Functions\auto_fuzziness;

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
        $this->facets = $this->facetParser->parse($facets, $filters);

        return $this;
    }

    public function sort(string $sort = '_score', bool $thorwOnError = true): static
    {
        $parser = new SortParser($this->properties, $thorwOnError);

        $this->searchContext->sortString = $sort;
        $this->sort = $parser->parse($sort);

        return $this;
    }

    public function make(): Search
    {
        $boolean = new Boolean;

        $search = new Search($boolean);

        $search->index($this->index);

        $search->setElasticsearchConnection($this->elasticsearchConnection);

        $highlight = new Collection($this->highlight);

        // $field = $this->properties[$field];
        // $highlight->each(fn (string $field) => $search->highlight($field, $this->highlightPrefix, $this->highlightSuffix));
        $highlight->each(function (string $field) use ($search) {
            $properties = $this->properties;

            $field = $properties->getNestedField($field);

            foreach ($field->names() as $name) {
                $search->highlight($name, $this->highlightPrefix, $this->highlightSuffix);
            }
        });

        $search->fields($this->retrieve ?? $this->properties->fieldNames());

        $boolean->must()->bool(
            fn(Boolean $boolean) => $boolean->filter()->query(
                $this->filters
            )
        );

        $search->addRaw('sort', $this->sort);

        $search->addRaw('aggs', $this->facets->toRaw());

        $search->size($this->searchContext->size);

        $search->from($this->searchContext->from);

        $minScore = $this->semanticSearch && $this->minScore == 0 ? 0.01 : $this->minScore;

        $search->minScore($minScore);

        $boolean->must()->bool(function (Boolean $boolean) {
            $queryBoolean = new Boolean;

            $fields = new Collection($this->fields);

            $shouldClauses = new Collection();

            // Text queries for weighted query strings
            foreach ($this->searchContext->queryStrings as $weightedQuery) {
                $this->addTextQueries($weightedQuery['query'], $fields, $shouldClauses, $weightedQuery['weight']);
            }

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

        if (!empty($this->searchContext->queryStrings[0]['query'] ?? '')) {

            $search->suggest(function (Suggest $suggest) {

                $suggest->completion(name: 'autocompletion')
                    ->field('autocomplete')
                    ->size($this->autocompleteSize)
                    ->fuzzyMinLegth($this->autocompleteFuzzyMinLength)
                    ->fuzzyPrefixLenght($this->autocompleteFuzzyPrefixLength)
                    ->fuzzy($this->autocompletion)
                    ->prefix($this->searchContext->queryStrings[0]['query']);
            });
        }

        $search->trackTotalHits();

        return $search;
    }

    private function addTextqueries(array|string $queryString, Collection $fields, Collection &$shouldClauses, float $queryBoost = 1.0): void
    {
        $textTypes = $this->properties->nestedSemanticFields()
            ->filter(fn(Text $field) => $field->isSemantic())
            // Only fields that are in the fields array
            ->filter(fn(Text $field) => $fields->indexOf($field->name()) !== false)
            ->map(fn(Text $field) => [
                'text' => $queryString,
                'type' => $field,
            ])->toArray();

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

        if ($queryString === '' && !$this->noResultsOnEmptySearch) {
            $shouldClauses->add(new MatchAll);
            return;
        }

        if (!$this->noKeywordSearch) {

            $textQueries = (new Collection());

            $fields->each(function ($field) use (&$shouldClauses, &$textQueries, $queryString, $queryBoost) {

                $boost = array_key_exists($field, $this->weight) ? $this->weight[$field] * $queryBoost : $queryBoost;

                $field = $this->properties->getNestedField($field) ?? throw new PropertiesFieldNotFound($field);

                $fuzziness = !in_array($field->name, $this->typoTolerantAttributes) ? null : auto_fuzziness($this->minCharsForOneTypo, $this->minCharsForTwoTypo);

                $queries = match (true) {
                    $field->hasQueriesCallback ?? false => $field->queriesFromCallback($queryString),
                    default => $field->queries($queryString)
                };

                $queries = new Collection($queries);

                $queries->map(function (Query $queryClause) use ($boost, $fuzziness, $field, &$shouldClauses, &$textQueries) {
                    if ($queryClause instanceof FuzzyQuery) {
                        $queryClause->fuzziness($fuzziness);
                    }

                    $queryClause = $queryClause->boost($boost);

                    // Query nested fields if there is a parent path
                    if (($field->parentPath ?? false) && $field->parentType === TypesNested::class) {
                        $queryClause = new Nested($field->parentPath, $queryClause);
                    }

                    $textQueries->add($queryClause);
                });
            });

            $textQueries->each(fn(Query $query) => $shouldClauses->add($query));

            $textBool = new Boolean;

            // An empty boolean query acts like a match_all for this reason
            // we make sure the boolean query is not empty by adding a match none
            $textBool->should()->query(new MatchNone);

            $textQueries->each(fn(Query $query) => $textBool->should()->query($query));

            $textFnScore = new FunctionScore(
                $textBool,
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

            $shouldClauses->add($textFnScore);
        }
    }

    public function formatter(ResponseFormater $formatter): static
    {
        $this->formatter = $formatter;

        return $this;
    }

    public function get(): ResponseFormater
    {
        $facets = new Facets(
            filters: $this->globalFilters,
            aggs: $this->facets
        );
        $facets->index($this->index);
        $facets->setElasticsearchConnection($this->elasticsearchConnection);


        [$searchResponse, $facetsResponse] = Utils::all([
            $this->make()->promise(),
            $facets->promise()
        ])->wait();

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
