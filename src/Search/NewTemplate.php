<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Query\BooleanQueryBuilder;
use Sigmie\Mappings\PropertiesFieldNotFound;
use Sigmie\Mappings\Types\Nested as TypesNested;
use Sigmie\Mappings\Types\Text;
use Sigmie\Parse\FacetParser;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\SortParser;
use Sigmie\Query\Contracts\FuzzyQuery;
use Sigmie\Query\Contracts\QueryClause;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\MatchNone;
use Sigmie\Query\Queries\Query;
use Sigmie\Query\Queries\Text\Nested;
use Sigmie\Query\Search;
use Sigmie\Query\Suggest;
use Sigmie\Search\Contracts\SearchTemplateBuilder as SearchTemplateBuilderInterface;
use Sigmie\Shared\Collection;


/**
 * @deprecated This class is deprecated and will be removed in future versions.
 */
class NewTemplate extends AbstractSearchBuilder implements SearchTemplateBuilderInterface
{
    protected array $sort = ['_score'];

    protected string $id;

    protected float $semanticThreshold = 0.01;

    public function id(string $id): static
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

    public function semanticThreshold(float $threshold): static
    {
        $this->semanticThreshold = $threshold;

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

        $highlight->each(fn(string $field): Search => $search->highlight($field));

        $search->fields($this->retrieve ?? $this->properties->fieldNames());

        $defaultFilters = json_encode($this->filters->toRaw());

        $boolean->must()->bool(fn(Boolean $boolean) => $boolean->addRaw('filter', sprintf('@filters(%s)@endfilters', $defaultFilters)));

        $defaultSorts = json_encode($this->sort);

        $search->addRaw('sort', sprintf('@sort(%s)@endsort', $defaultSorts));

        $defaultAggs = json_encode($this->facets->toRaw());

        $search->addRaw('aggs', sprintf('@facets(%s)@endfacets', $defaultAggs));

        $search->size(sprintf('@size(%d)@endsize', $this->size));

        $search->from(sprintf('@from(%d)@endfrom', $this->from));

        $minScore = $this->semanticSearch && $this->minScore == 0 ? 0.01 : $this->minScore;

        $search->minScore(sprintf('@minscore(%s)@endminscore', $minScore));

        $embeddingsTags = [];

        $boolean->must()->bool(function (Boolean $boolean) use (&$embeddingsTags): void {
            $queryBoolean = new Boolean;

            $fields = new Collection($this->fields);

            $shouldClauses = new Collection();

            $defaultEmbeddings = json_encode([]);

            // Vector queries
            $vectorQueries = $this->properties->nestedSemanticFields()
                ->map(function (Text $field) use ($defaultEmbeddings, &$embeddingsTags): array {

                    $tag = '_embeddings_' . str_replace('.', '', $field->name());
                    $embeddingsTags[] = $tag;

                    return $field->queries(
                        sprintf('@%s(%s)@end%s', $tag, $defaultEmbeddings, $tag)
                    );
                })
                ->flatten(1);

            if ($this->semanticSearch) {
                $vectorBool = new Boolean;
                $vectorQueries
                    ->each(fn(Query $query): BooleanQueryBuilder => $vectorBool->should()->query($query));

                $functionScore = new FunctionScore(
                    $vectorBool,
                    source: sprintf('return _score > %s ? _score : 0;', $this->semanticThreshold),
                    boostMode: 'replace'
                );

                $shouldClauses->add($functionScore);
            }


            $fields->each(function ($field) use (&$shouldClauses): void {
                $boost = array_key_exists($field, $this->weight) ? $this->weight[$field] : 1;

                $field = $this->properties->get($field) ?? throw new PropertiesFieldNotFound($field);

                $fuzziness = in_array($field->name(), $this->typoTolerantAttributes) ? sprintf('AUTO:%d,%d', $this->minCharsForOneTypo, $this->minCharsForTwoTypo) : null;

                $queries = match (true) {
                    $field->hasQueriesCallback => $field->queriesFromCallback('{{query_string}}'),
                    default => $field->queries('{{query_string}}')
                };

                $queries = new Collection($queries);

                $queries->map(function (QueryClause $queryClause) use ($boost, $fuzziness, $field, &$shouldClauses): void {
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
                $shouldClauses->each(fn(QueryClause $queryClase): BooleanQueryBuilder => $queryBoolean->should()->query($queryClase));
            }

            $query = json_encode($queryBoolean->toRaw()['bool']['should'] ?? (new MatchAll)->toRaw());

            $boolean->addRaw('should', sprintf('@query_string(%s)@endquery_string', $query));
        });

        if ($this->autocompletion) {

            $search->suggest(function (Suggest $suggest): void {

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

        return new SearchTemplate(
            $this->elasticsearchConnection,
            $search->toRaw(),
            $this->id,
            $this->noResultsOnEmptySearch,
            $embeddingsTags
        );
    }
}
