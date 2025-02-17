<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Mappings\PropertiesFieldNotFound;
use Sigmie\Mappings\Types\Nested as TypesNested;
use Sigmie\Parse\FacetParser;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\SortParser;
use Sigmie\Query\Contracts\FuzzyQuery;
use Sigmie\Query\Contracts\QueryClause;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\MatchNone;
use Sigmie\Query\Queries\Text\Nested;
use Sigmie\Query\Search;
use Sigmie\Query\Suggest;
use Sigmie\Search\Contracts\EmbeddingsQueries;
use Sigmie\Search\Contracts\SearchTemplateBuilder as SearchTemplateBuilderInterface;
use Sigmie\Shared\Collection;

use function Sigmie\Functions\auto_fuzziness;

class NewTemplate extends AbstractSearchBuilder implements SearchTemplateBuilderInterface
{
    protected array $sort = ['_score'];

    protected string $id;

    public function id(string $id)
    {
        $this->id = $id;

        return $this;
    }

    public function filters(string $filters): static
    {
        $parser = new FilterParser($this->properties);

        $this->filters = $parser->parse($filters);

        return $this;
    }

    public function facets(string $facets): static
    {
        $parser = new FacetParser($this->properties);

        $this->facets = $parser->parse($facets);

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

        $boolean->must()->bool(fn (Boolean $boolean) => $boolean->addRaw('filter', "@filters($defaultFilters)@endfilters"));

        $defaultSorts = json_encode($this->sort);

        $search->addRaw('sort', "@sort($defaultSorts)@endsort");

        $defaultAggs = json_encode($this->facets->toRaw());

        $search->addRaw('aggs', "@facets($defaultAggs)@endfacets");

        $search->size("@size({$this->size})@endsize");

        $search->from("@from({$this->from})@endfrom");

        $boolean->must()->bool(function (Boolean $boolean) {
            $queryBoolean = new Boolean;

            $fields = new Collection($this->fields);

            $shouldClauses = new Collection();

            $fields->each(function ($field) use (&$shouldClauses) {
                $boost = array_key_exists($field, $this->weight) ? $this->weight[$field] : 1;

                $field = $this->properties->getNestedField($field) ?? throw new PropertiesFieldNotFound($field);

                $fuzziness = ! in_array($field->name(), $this->typoTolerantAttributes) ? null : auto_fuzziness($this->minCharsForOneTypo, $this->minCharsForTwoTypo);

                $queries = match(true)
                {
                    $field->type instanceof EmbeddingsQueries => $field->queries('{{embeddings}}'),
                    $field->hasQueriesCallback => $field->queriesFromCallback('{{query_string}}'),
                    default => $field->queries('{{query_string}}')
                };

                $queries = new Collection($queries);

                $queries->map(function (QueryClause $queryClause) use ($boost, $fuzziness, $field, &$shouldClauses) {
                    if ($queryClause instanceof FuzzyQuery) {
                        $queryClause->fuzziness($fuzziness);
                    }
                    if ($field->parentPath && $field->parentType === TypesNested::class) {
                        $queryClause = new Nested($field->parentPath, $queryClause);
                    }

                    $shouldClauses->add(
                        $queryClause->boost($boost)
                    );
                });
            });

            if ($shouldClauses->isEmpty()) {
                $queryBoolean->should()->query(new MatchNone);
            } else {
                $shouldClauses->each(fn (QueryClause $queryClase) => $queryBoolean->should()->query($queryClase));
            }

            $query = json_encode($queryBoolean->toRaw()['bool']['should'] ?? (new MatchAll)->toRaw());

            $boolean->addRaw('should', "@query_string($query)@endquery_string");
        });

        if ($this->autocompletion) {

            $search->suggest(function (Suggest $suggest) {

                $suggest->completion(name: 'autocompletion')
                    ->field('autocomplete')
                    ->size($this->autocompleteSize)
                    ->fuzzyMinLegth($this->autocompleteFuzzyMinLength)
                    ->fuzzyPrefixLenght($this->autocompleteFuzzyPrefixLength)
                    ->fuzzy($this->autocompletion)
                    ->prefix('{{query_string}}');
            });
        }

        $search->trackTotalHits();

        return new SearchTemplate($this->elasticsearchConnection, $search->toRaw(), $this->id, $this->noResultsOnEmptySearch);
    }
}
