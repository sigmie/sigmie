<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Enums\SearchEngineType;
use Sigmie\Mappings\PropertiesFieldNotFound;
use Sigmie\Mappings\Types\Boolean as BooleanField;
use Sigmie\Mappings\Types\NestedVector;
use Sigmie\Mappings\Types\Type;
use Sigmie\Parse\FacetParser;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\SortParser;
use Sigmie\Query\BooleanQueryBuilder;
use Sigmie\Query\Contracts\QueryClause;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\MatchNone;
use Sigmie\Query\Queries\Text\Nested;
use Sigmie\Query\Search;
use Sigmie\Query\Suggest;
use Sigmie\Search\Contracts\SearchTemplateBuilder as SearchTemplateBuilderInterface;
use Sigmie\Shared\Collection;

use function Sigmie\Functions\fold_text;

/**
 * @deprecated This class is deprecated and will be removed in future versions.
 */
class NewTemplate extends AbstractSearchBuilder implements SearchTemplateBuilderInterface
{
    private const TEXT_INPUT = '@SIGMIE_TEMPLATE_TEXT_INPUT';

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
        $search = (new Search($this->elasticsearchConnection))->query($this->handleBoostField($boolean));

        $search->fields($this->retrieve === [] ? false : ($this->retrieve ?? $this->properties->fieldNames()));

        if ($this->highlight !== []) {
            if (array_filter(array_keys($this->highlight), 'is_string') === []) {
                $this->handleHighlight($search);
            } else {
                $search->highlight($this->highlight);
            }
        }

        $defaultFilters = json_encode($this->filters->toRaw());

        $boolean->must()->bool(fn (Boolean $boolean) => $boolean->addRaw('filter', sprintf('@filters(%s)@endfilters', $defaultFilters)));

        foreach ($this->filterQueries as $query) {
            $filter = json_encode([$query->toRaw()], JSON_THROW_ON_ERROR);
            $boolean->must()->bool(fn (Boolean $boolean) => $boolean->addRaw('filter', sprintf('@fixed_filter(%s)@endfixed_filter', $filter)));
        }

        $candidateFilter = new Boolean;
        $candidateFilter->addRaw('must', $boolean->must()->toRaw());

        $defaultSorts = json_encode(array_map(
            fn (mixed $clause): mixed => is_array($clause) && array_is_list($clause) ? (object) $clause : $clause,
            $this->sort,
        ));

        $search->addRaw('sort', sprintf('@sort(%s)@endsort', $defaultSorts));

        $defaultAggs = json_encode((object) $this->facets->toRaw());

        $search->addRaw('aggs', sprintf('@facets(%s)@endfacets', $defaultAggs));

        $search->size(sprintf('@size(%d)@endsize', $this->size));

        $search->from(sprintf('@from(%d)@endfrom', $this->from));

        $searchFields = $this->searchFields();
        $semanticFields = $this->semanticSearch
            ? $this->semanticFields($searchFields)
            : new Collection;
        $minScore = $this->minScore ?? 0.0;

        $search->minScore(sprintf('@minscore(%s)@endminscore', json_encode($minScore, JSON_THROW_ON_ERROR)));

        $querySources = [];
        $emptyQuery = null;

        $boolean->must()->bool(function (Boolean $boolean) use ($candidateFilter, $searchFields, $semanticFields, &$querySources, &$emptyQuery): void {
            $queryBoolean = new Boolean;
            $fields = new Collection($this->noKeywordSearch ? [] : $searchFields);
            $shouldClauses = new Collection;

            if (! $semanticFields->isEmpty()) {
                $vectorBool = new Boolean;
                $driver = $this->elasticsearchConnection->driver();
                $isOpenSearch = $driver->engine() === SearchEngineType::OpenSearch;

                foreach ($semanticFields as $field) {
                    $tag = '_embeddings_'.strtr($field->fullPath(), ['%' => '%25', '.' => '%2E']);
                    $placeholder = '@'.$tag;
                    $boost = $this->weight[$field->fullPath()] ?? 1;
                    $vectorQueries = '';
                    foreach ($field->vectorFields() as $vector) {
                        $mapped = $vector instanceof NestedVector
                            ? $driver->nestedVectorField($vector)
                            : $driver->vectorField($vector);
                        $queries = $mapped->vectorQueries([$placeholder], self::VECTOR_PAGE_WINDOW, $candidateFilter);
                        $json = str_replace(
                            json_encode([$placeholder]),
                            '{{#toJson}}'.$tag.'{{/toJson}}',
                            implode(',', array_map(fn (QueryClause $query): string => json_encode($query->boost($boost)->toRaw()), $queries)),
                        );

                        if ($isOpenSearch) {
                            // Accessing a list position beyond its bounds throws on OpenSearch.
                            $flag = '_sigmie_embedding_dimensions.'.$tag.'.'.$vector->dims;
                            $vectorQueries .= '{{#'.$flag.'}}'.$json.',{{/'.$flag.'}}';

                            continue;
                        }

                        // Test the first and last required list positions without iterating the vector.
                        $last = $tag.'.'.($vector->dims - 1);
                        $next = $tag.'.'.$vector->dims;
                        $vectorQueries .= '{{#'.$last.'}}{{^'.$next.'}}'.$json.',{{/'.$next.'}}{{/'.$last.'}}';
                    }

                    $textQueries = $this->fieldQueries($field, self::TEXT_INPUT);
                    $text = $this->renderTextInput(
                        implode(',', array_map(fn (QueryClause $query): string => json_encode($query->toRaw()), $textQueries)),
                        $tag,
                    );

                    $listCheck = $tag.($isOpenSearch ? '.size' : '.0');
                    $querySources[$placeholder] = '['.$vectorQueries
                        .($text === '' ? '' : '{{^'.$listCheck.'}}{{#'.$tag.'}}'.$text.',{{/'.$tag.'}}{{/'.$listCheck.'}}')
                        .json_encode((new MatchNone)->toRaw()).']';

                    $clause = new Boolean;
                    $clause->addRaw('should', $placeholder);
                    $vectorBool->should()->query($clause);
                }

                $functionScore = new FunctionScore(
                    $vectorBool,
                    source: 'return _score > params.threshold ? _score : 0;',
                    params: ['threshold' => $this->semanticThreshold],
                    boostMode: 'replace',
                    // Elasticsearch scores are floats. Exclude zero without shifting positive scores.
                    minScore: $this->semanticThreshold >= 0 ? 2 ** -149 : null,
                );

                $shouldClauses->add($functionScore);

                if ($querySources !== []) {
                    $fallback = '';
                    if (! $this->noResultsOnEmptySearch && ! $this->noKeywordSearch) {
                        $fallback = json_encode((new MatchAll)->toRaw()).',';
                        foreach (array_keys($querySources) as $placeholder) {
                            $tag = substr($placeholder, 1);
                            $fallback = '{{^'.$tag.'}}'.$fallback.'{{/'.$tag.'}}';
                        }
                    }

                    $emptyQuery = '['.json_encode($functionScore->toRaw()).','.$fallback
                        .json_encode((new MatchNone)->toRaw()).']';
                }
            }

            foreach ($fields as $name) {
                $field = $this->properties->get($name) ?? throw new PropertiesFieldNotFound($name);
                if ($field instanceof BooleanField && ! $field->hasQueriesCallback) {
                    $flag = '_sigmie_boolean_field.'.bin2hex(fold_text($field->name));
                    $placeholder = '@_sigmie_boolean_clause.'.bin2hex($field->fullPath());
                    $clause = new Boolean;
                    $clause->addRaw('should', $placeholder);
                    $shouldClauses->add($clause);

                    $queries = array_map(fn (QueryClause $query): array => $query->toRaw(), $this->fieldQueries($field, $field->name));
                    $querySources[$placeholder] = sprintf(
                        '{{#%1$s}}%2$s{{/%1$s}}{{^%1$s}}%3$s{{/%1$s}}',
                        $flag,
                        str_replace('{{', '\\u007b\\u007b', json_encode($queries, JSON_THROW_ON_ERROR)),
                        json_encode((new MatchNone)->toRaw(), JSON_THROW_ON_ERROR),
                    );

                    continue;
                }

                foreach ($this->fieldQueries($field, self::TEXT_INPUT) as $query) {
                    $shouldClauses->add($query);
                }
            }

            if ($shouldClauses->isEmpty()) {
                $queryBoolean->should()->query(new MatchNone);
            } else {
                $shouldClauses->each(fn (QueryClause $queryClase): BooleanQueryBuilder => $queryBoolean->should()->query($queryClase));
            }

            $query = $this->renderTextInput(
                json_encode($queryBoolean->toRaw()['bool']['should'] ?? (new MatchAll)->toRaw(), JSON_THROW_ON_ERROR),
                'query_string',
            );

            $boolean->addRaw('should', sprintf('@query_string(%s)@endquery_string', $query));
        });

        $completion = $this->properties->completionFields()->first();
        if ($this->autocompletion && $completion !== null) {

            $suggest = new Suggest;

            $suggest->completion(name: 'autocompletion')
                ->field($completion->name())
                ->size($this->autocompleteSize)
                ->fuzzyMinLegth($this->autocompleteFuzzyMinLength)
                ->fuzzyPrefixLenght($this->autocompleteFuzzyPrefixLength)
                ->fuzzy($this->autocompletion)
                ->prefix('{{query_string}}');

            $search->suggest($suggest);
        }

        $search->trackTotalHits();

        return new SearchTemplate(
            $this->elasticsearchConnection,
            $search->toRaw(),
            $this->id,
            $this->noResultsOnEmptySearch || $this->noKeywordSearch,
            $querySources,
            $emptyQuery,
            ['filters' => $this->filterable, 'sort' => $this->sortable],
        );
    }

    private function renderTextInput(string $json, string $tag): string
    {
        $lowercaseTag = '_sigmie_lowercase.'.$tag;

        // A field that lowercases its query also lowercases the placeholder.
        return strtr($json, [
            self::TEXT_INPUT => '{{'.$tag.'}}',
            strtolower(self::TEXT_INPUT) => sprintf('{{#%1$s}}{{.}}{{/%1$s}}{{^%1$s}}{{%2$s}}{{/%1$s}}', $lowercaseTag, $tag),
            '{{' => '\\u007b\\u007b',
        ]);
    }

    private function fieldQueries(Type $field, string $input): array
    {
        $queries = [];
        foreach ($field->queryStringQueries($input) as $query) {
            $query = $this->weightedFieldQuery($query, $field->fullPath(), $this->weight[$field->fullPath()] ?? 1);
            if (($path = $field->nestedPath()) !== null) {
                $query = new Nested($path, $query);
            }
            $queries[] = $query;
        }

        return $queries;
    }
}
