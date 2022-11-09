<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\QueryClause as Query;
use function Sigmie\Functions\auto_fuzziness;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Text;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\SortParser;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Search\Contracts\SearchQueryBuilder as SearchQueryBuilderInterface;
use Sigmie\Shared\Collection;
use Sigmie\Query\Search;

class NewSearch extends AbstractSearchBuilder implements SearchQueryBuilderInterface
{
    protected Boolean $filters;

    protected array $sort = ['_score'];

    protected null|Properties $properties = null;

    protected string $queryString = '';

    protected string $index;

    public function queryString(string $query): static
    {
        $this->queryString = $query;

        return $this;
    }

    public function __construct(
        protected ElasticsearchConnection $elasticsearchConnection,
    ) {
        $this->filters = new Boolean;

        $this->filters->must()->matchAll();
    }

    public function index(string $index): static
    {
        $this->index = $index;

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

    public function get(): Search
    {
        $boolean = new Boolean;

        $search = new Search($boolean);

        $search->index($this->index);

        $search->setElasticsearchConnection($this->elasticsearchConnection);

        $highlight = new Collection($this->highlight);

        $highlight->each(fn (string $field) =>  $search->highlight($field, $this->highlightPrefix, $this->highlightSuffix));

        $search->fields($this->retrieve);

        $defaultFilters = json_encode($this->filters->toRaw());

        $boolean->must()->bool(fn (Boolean $boolean) => $boolean->filter()->query($this->filters));

        $search->addRaw('sort', $this->sort);

        $search->size($this->size);

        $boolean->must()->bool(function (Boolean $boolean) {

            $queryBoolean = new Boolean;

            $fields = new Collection($this->fields);

            $fields->each(function ($field) use ($queryBoolean) {

                if ($this->queryString ==='')
                {
                    $queryBoolean->should()->query(new MatchAll);
                    return;
                }

                $boost = array_key_exists($field, $this->weight) ? $this->weight[$field] : 1;

                if (is_null($this->properties)) {

                    $fuzziness = !in_array($field, $this->typoTolerantAttributes) ? null : auto_fuzziness($this->minCharsForOneTypo, $this->minCharsForTwoTypo);

                    $query = new Match_($field, $this->queryString, $fuzziness);

                    $queryBoolean->should()->query($query->boost($boost));
                } else {

                    $field = $this->properties[$field];

                    if ($field instanceof Text) {
                        $fuzziness = !in_array($field, $this->typoTolerantAttributes) ? null : auto_fuzziness($this->minCharsForOneTypo, $this->minCharsForTwoTypo);

                        $query = new Match_($field->name, $this->queryString, $fuzziness);

                        $queryBoolean->should()->query($query->boost($boost));

                        if ($field->isKeyword()) {

                            $query = new Term(
                                $field->keywordName(),
                                $this->queryString,
                            );

                            $queryBoolean->should()->query(
                                $query->boost($boost)
                            );
                        }

                        return;
                    }

                    $query = new Term(
                        $field->name,
                        $this->queryString,
                    );

                    $queryBoolean->should()->query(
                        $query->boost($boost)
                    );
                }
            });

            $boolean->should()->query($queryBoolean);
        });

        return $search;
    }
}
