<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Http\Promise\Promise;
use Sigmie\Base\Http\Responses\Search as ResponsesSearch;
use Sigmie\Mappings\PropertiesFieldNotFound;
use Sigmie\Mappings\Types\Nested as TypesNested;
use Sigmie\Mappings\Types\Text;
use Sigmie\Parse\FacetParser;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\SortParser;
use Sigmie\Query\Contracts\FuzzyQuery;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\MatchNone;
// use Sigmie\Query\Queries\Query;
use Sigmie\Query\Contracts\QueryClause as Query;
use Sigmie\Query\Queries\Text\Nested;
use Sigmie\Query\Search;
use Sigmie\Query\Suggest;
use Sigmie\Search\Contracts\EmbeddingsQueries;
use Sigmie\Search\Contracts\SearchQueryBuilder as SearchQueryBuilderInterface;
use Sigmie\Semantic\Reranker;
use Sigmie\Shared\Collection;
use Sigmie\Shared\EmbeddingsProvider;

use function Sigmie\Functions\auto_fuzziness;

class NewSearch extends AbstractSearchBuilder implements SearchQueryBuilderInterface
{
    protected array $sort = ['_score'];

    protected string $queryString = '';

    protected array $embeddings = [];

    protected string $index;

    protected bool $rerank = false;

    public function queryString(string $query): static
    {
        $this->queryString = $query;

        return $this;
    }

    public function index(string $index): static
    {
        $this->index = $index;

        return $this;
    }

    public function filters(string $filters, bool $thorwOnError = true): static
    {
        $parser = new FilterParser($this->properties, $thorwOnError);

        $this->filters = $parser->parse($filters);

        return $this;
    }

    public function facets(string $facets, bool $thorwOnError = true): static
    {
        $parser = new FacetParser($this->properties, $thorwOnError);

        $this->facets = $parser->parse($facets);

        return $this;
    }

    public function sort(string $sort = '_score', bool $thorwOnError = true): static
    {
        $parser = new SortParser($this->properties, $thorwOnError);

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

        $boolean->must()->bool(fn(Boolean $boolean) => $boolean->filter()->query($this->filters));

        $search->addRaw('sort', $this->sort);

        $search->addRaw('aggs', $this->facets->toRaw());

        $search->size($this->size);

        $search->from($this->from);

        $minScore = $this->semanticSearch && $this->minScore == 0 ? 0.01 : $this->minScore;

        $search->minScore($minScore);

        $boolean->must()->bool(function (Boolean $boolean) {
            $queryBoolean = new Boolean;

            $fields = new Collection($this->fields);

            $shouldClauses = new Collection();

            // Vector queries
            $embeddings = $this->aiProvider
                ->batchEmbed(
                    $this->properties->nestedSemanticFields()
                        ->filter(fn(Text $field) => $field->isSemantic())
                        // Only fields that are in the fields array
                        ->filter(fn(Text $field) => $fields->indexOf($field->name()) !== false)
                        ->map(fn(Text $field) => [
                            'text' => $this->queryString,
                            'type' => $field,
                        ])->toArray()
                );

            $semanticFields = array_values($this->properties->nestedSemanticFields()
                ->filter(fn(Text $field) => $field->isSemantic())
                // Only fields that are in the fields array
                ->filter(fn(Text $field) => $fields->indexOf($field->name()) !== false)
                ->toArray());

            $vectorQueries = (new Collection($embeddings))
                ->map(function (array $embedding, int $index) use ($semanticFields) {
                    return $this->aiProvider->queries(
                        $embedding['embeddings'],
                        $semanticFields[$index]
                    );
                })
                ->flatten(1);


            if ($this->semanticSearch && trim($this->queryString) !== '') {

                $vectorBool = new Boolean;
                $vectorQueries
                    ->each(fn(Query $query) => $vectorBool->should()->query($query));

                $functionScore = new FunctionScore(
                    $vectorBool,
                    // source: 'return _score;',
                    source: "return _score > {$this->semanticThreshold} ? _score : 0;",
                    boostMode: 'replace'
                );

                $shouldClauses->add($functionScore);
            }

            // Text queries
            $fields->each(function ($field) use (&$shouldClauses) {

                if ($this->queryString === '' && ! $this->noResultsOnEmptySearch) {
                    $shouldClauses->add(new MatchAll);

                    return;
                }

                $boost = array_key_exists($field, $this->weight) ? $this->weight[$field] : 1;

                $field = $this->properties->getNestedField($field) ?? throw new PropertiesFieldNotFound($field);

                $fuzziness = ! in_array($field->name, $this->typoTolerantAttributes) ? null : auto_fuzziness($this->minCharsForOneTypo, $this->minCharsForTwoTypo);

                $queries = match (true) {
                    $field->hasQueriesCallback ?? false => $field->queriesFromCallback($this->queryString),
                    default => $field->queries($this->queryString)
                };

                $queries = new Collection($queries);

                $queries->map(function (Query $queryClause) use ($boost, $fuzziness, $field, &$shouldClauses) {

                    if ($queryClause instanceof FuzzyQuery) {
                        $queryClause->fuzziness($fuzziness);
                    }

                    $queryClause = $queryClause->boost($boost);

                    // Query nested fields if there is a parent path
                    if (($field->parentPath ?? false) && $field->parentType === TypesNested::class) {
                        $queryClause = new Nested($field->parentPath, $queryClause);
                    }

                    $shouldClauses->add($queryClause);
                });
            });

            if ($shouldClauses->isEmpty()) {
                $queryBoolean->should()->query(new MatchNone);
            } else {
                $shouldClauses->each(fn(Query $queryClase) => $queryBoolean->should()->query($queryClase));
            }

            $boolean->should()->query($queryBoolean);
        });

        if ($this->queryString !== '') {

            $search->suggest(function (Suggest $suggest) {

                $suggest->completion(name: 'autocompletion')
                    ->field('autocomplete')
                    ->size($this->autocompleteSize)
                    ->fuzzyMinLegth($this->autocompleteFuzzyMinLength)
                    ->fuzzyPrefixLenght($this->autocompleteFuzzyPrefixLength)
                    ->fuzzy($this->autocompletion)
                    ->prefix($this->queryString);
            });
        }

        $search->trackTotalHits();

        return $search;
    }

    public function get(): ResponsesSearch
    {
        return $this->rerank ? $this->getReranked() : $this->getNotReranked();
    }

    public function getReranked(): ResponsesSearch
    {
        /** @var ResponsesSearch  */
        $res = $this->make()->get();

        $reranker = new Reranker(
            $this->queryString,
            $res->hits(),
            $this->aiProvider,
            $this->properties
        );

        return $reranker->rerank($res);
    }

    public function rerank(bool $value = true): static
    {
        $this->rerank = $value;

        return $this;
    }

    public function getNotReranked(): ResponsesSearch
    {
        return $this->make()->get();
    }

    public function promise(): Promise
    {
        return $this->make()->promise();
    }
}
