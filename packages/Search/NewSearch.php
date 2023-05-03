<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Http\Promise\Promise;
use Sigmie\Mappings\Types\Text;

use function Sigmie\Functions\auto_fuzziness;
use Sigmie\Parse\FacetParser;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\SortParser;
use Sigmie\Query\Contracts\FuzzyQuery;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\Query;
use Sigmie\Query\Search;
use Sigmie\Query\Suggest;
use Sigmie\Search\Contracts\SearchQueryBuilder as SearchQueryBuilderInterface;
use Sigmie\Shared\Collection;

class NewSearch extends AbstractSearchBuilder implements SearchQueryBuilderInterface
{
    protected array $sort = ['_score'];

    protected string $queryString = '';

    protected string $index;

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

            $field = $properties[$field];

            foreach ($field->names() as $name) {
                $search->highlight($name, $this->highlightPrefix, $this->highlightSuffix);
            }
        });

        $search->fields($this->retrieve);

        $boolean->must()->bool(fn (Boolean $boolean) => $boolean->filter()->query($this->filters));

        $search->addRaw('sort', $this->sort);

        $search->addRaw('aggs', $this->facets->toRaw());

        $search->size($this->size);

        $search->from($this->from);

        $boolean->must()->bool(function (Boolean $boolean) {
            $queryBoolean = new Boolean;

            $fields = new Collection($this->fields);

            $fields->each(function ($field) use ($queryBoolean) {
                if ($this->queryString === '') {
                    $queryBoolean->should()->query(new MatchAll);

                    return;
                }

                $boost = array_key_exists($field, $this->weight) ? $this->weight[$field] : 1;

                $field = $this->properties[$field];

                $fuzziness = !in_array($field->name, $this->typoTolerantAttributes) ? null : auto_fuzziness($this->minCharsForOneTypo, $this->minCharsForTwoTypo);

                $queries = $field->hasQueriesCallback ? $field->queriesFromCallback($this->queryString) : $field->queries($this->queryString);

                $queries = new Collection($queries);

                $queries->map(function (Query $queryClause) use ($boost, $fuzziness) {
                    if ($queryClause instanceof FuzzyQuery) {
                        $queryClause->fuzziness($fuzziness);
                    }

                    return $queryClause->boost($boost);
                })
                    ->each(fn (Query $queryClase) => $queryBoolean->should()->query($queryClase));
            });

            $boolean->should()->query($queryBoolean);
        });

        $search->suggest(function (Suggest $suggest) {

            $this->properties
                ->completionFields()
                ->each(fn (Text $field) =>
                $suggest
                    ->completion($field->name . '-suggest',)
                    ->field($field->name)
                    ->prefix($this->queryString));
        });

        return $search;
    }

    public function get()
    {
        return $this->make()->get();
    }

    public function promise(): Promise
    {
        return $this->make()->promise();
    }
}
