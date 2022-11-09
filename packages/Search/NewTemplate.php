<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Base\Contracts\ElasticsearchConnection;
use function Sigmie\Functions\auto_fuzziness;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Text;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\SortParser;
use Sigmie\Query\Contracts\QueryClause as Query;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Search\Contracts\SearchTemplateBuilder as SearchTemplateBuilderInterface;
use Sigmie\Shared\Collection;
use Sigmie\Query\Search;

class NewTemplate extends AbstractSearchBuilder implements SearchTemplateBuilderInterface
{
    protected Boolean $filters;

    protected array $sort = ['_score'];

    protected null|Properties $properties = null;

    protected string $id;

    public function __construct(
        protected ElasticsearchConnection $elasticsearchConnection,
    ) {
        $this->filters = new Boolean;

        $this->filters->must()->matchAll();
    }

    public function id(string $id)
    {
        $this->id = $id;

        return $this;
    }


    public function properties(Properties $properties): static
    {
        $this->properties = $properties;

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

        $highlight->each(fn (string $field) =>  $search->highlight($field, $this->highlightPrefix, $this->highlightSuffix));

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

                if (is_null($this->properties)) {

                    $fuzziness = !in_array($field, $this->typoTolerantAttributes) ? null : auto_fuzziness($this->minCharsForOneTypo, $this->minCharsForTwoTypo);

                    $query = new Match_($field, '{{query_string}}', $fuzziness);

                    $queryBoolean->should()->query($query->boost($boost));
                } else {

                    $field = $this->properties[$field];

                    if ($field instanceof Text) {
                        $fuzziness = !in_array($field, $this->typoTolerantAttributes) ? null : auto_fuzziness($this->minCharsForOneTypo, $this->minCharsForTwoTypo);

                        $query = new Match_($field->name, '{{query_string}}', $fuzziness);

                        $queryBoolean->should()->query($query->boost($boost));

                        if ($field->isKeyword()) {

                            $query = new Term(
                                $field->keywordName(),
                                '{{query_string}}',
                            );

                            $queryBoolean->should()->query(
                                $query->boost($boost)
                            );
                        }

                        return;
                    }

                    $query = new Term(
                        $field->name,
                        '{{query_string}}',
                    );

                    $queryBoolean->should()->query(
                        $query->boost($boost)
                    );
                }
            });

            $query = json_encode($queryBoolean->toRaw()['bool']['should'] ?? (new MatchAll)->toRaw());

            $boolean->addRaw('should', "@query_string($query)@endquery_string");
        });

        return new SearchTemplate($this->elasticsearchConnection, $search->toRaw(), $this->id);
    }
}
