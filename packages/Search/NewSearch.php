<?php

declare(strict_types=1);

namespace Sigmie\Search;

use function Sigmie\Functions\auto_fuzziness;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\SortParser;
use Sigmie\Query\Contracts\FuzzyQuery;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\Query;
use Sigmie\Query\Search;
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

    public function filter(string $filter): static
    {
        $parser = new FilterParser($this->properties);

        $this->filters = $parser->parse($filter);

        return $this;
    }

    public function sort(string $sort = '_score'): static
    {
        $parser = new SortParser($this->properties);

        $this->sort = $parser->parse($sort);

        return $this;
    }

    public function get()
    {
        $boolean = new Boolean;

        $search = new Search($boolean);

        $search->index($this->index);

        $search->setElasticsearchConnection($this->elasticsearchConnection);

        $highlight = new Collection($this->highlight);

        $highlight->each(fn (string $field) => $search->highlight($field, $this->highlightPrefix, $this->highlightSuffix));

        $search->fields($this->retrieve);

        $defaultFilters = json_encode($this->filters->toRaw());

        $boolean->must()->bool(fn (Boolean $boolean) => $boolean->filter()->query($this->filters));

        $search->addRaw('sort', $this->sort);

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

                $fuzziness = ! in_array($field->name, $this->typoTolerantAttributes) ? null : auto_fuzziness($this->minCharsForOneTypo, $this->minCharsForTwoTypo);

                $queries = new Collection($field->queries($this->queryString));

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

        return $search->get();
    }
}
