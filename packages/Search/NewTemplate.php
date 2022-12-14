<?php

declare(strict_types=1);

namespace Sigmie\Search;

use function Sigmie\Functions\auto_fuzziness;
use Sigmie\Mappings\Types\Text;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\SortParser;
use Sigmie\Query\Contracts\FuzzyQuery;
use Sigmie\Query\Contracts\QueryClause;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Query\Search;
use Sigmie\Search\Contracts\SearchTemplateBuilder as SearchTemplateBuilderInterface;
use Sigmie\Shared\Collection;

class NewTemplate extends AbstractSearchBuilder implements SearchTemplateBuilderInterface
{
    protected array $sort = ['_score'];

    protected string $id;

    public function id(string $id)
    {
        $this->id = $id;

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

    public function filterable(bool $filterable = true): static
    {
        $this->filterable = $filterable;

        return $this;
    }

    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function get(): SearchTemplate
    {
        $boolean = new Boolean;
        $search = new Search($boolean);
        $highlight = new Collection($this->highlight);

        $highlight->each(fn (string $field) => $search->highlight($field, $this->highlightPrefix, $this->highlightSuffix));

        $search->fields($this->retrieve);

        $defaultFilters = json_encode($this->filters->toRaw());

        $boolean->must()->bool(fn (Boolean $boolean) => $boolean->addRaw('filter', "@filter($defaultFilters)@endfilter"));

        $defaultSorts = json_encode($this->sort);

        $search->addRaw('sort', "@sort($defaultSorts)@endsort");

        $search->size("@size({$this->size})@endsize");

        $boolean->must()->bool(function (Boolean $boolean) {
            $queryBoolean = new Boolean;

            $fields = new Collection($this->fields);

            $fields->each(function ($field) use ($queryBoolean) {

                $boost = array_key_exists($field, $this->weight) ? $this->weight[$field] : 1;

                $field = $this->properties[$field];

                $fuzziness = !in_array($field->name, $this->typoTolerantAttributes) ? null : auto_fuzziness($this->minCharsForOneTypo, $this->minCharsForTwoTypo);

                $queries = $field->hasQueriesCallback ? $field->queriesFromCallback('{{query_string}}') : $field->queries('{{query_string}}');

                $queries = new Collection($queries);

                $queries->map(function (QueryClause $queryClause) use ($boost, $fuzziness) {

                    if ($queryClause instanceof FuzzyQuery) {
                        $queryClause->fuzziness($fuzziness);
                    }

                    return $queryClause->boost($boost);
                })
                    ->each(fn (QueryClause $queryClase) => $queryBoolean->should()->query($queryClase));
            });

            $query = json_encode($queryBoolean->toRaw()['bool']['should'] ?? (new MatchAll)->toRaw());

            $boolean->addRaw('should', "@query_string($query)@endquery_string");
        });

        return new SearchTemplate($this->elasticsearchConnection, $search->toRaw(), $this->id);
    }
}
