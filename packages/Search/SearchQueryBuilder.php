<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Base\Contracts\DocumentCollection;
use Sigmie\Base\Contracts\QueryClause as Query;
use Sigmie\Mappings\Properties;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\SortParser;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Search\Contracts\SearchBuilder as QueryBuilderInterface;
use Sigmie\Search\Contracts\SearchQueryBuilder as SearchQueryBuilderInterface;
use Sigmie\Search\NewSearch;
use Sigmie\Search\Search;
use Sigmie\Search\Search\QueryBuilder;

use function Sigmie\Functions\auto_fuzziness;

class SearchQueryBuilder extends AbstractSearchBuilder implements SearchQueryBuilderInterface
{
    protected Boolean $filters;

    protected array $sort = ['_score'];

    protected null|Properties $properties = null;

    public function __construct(NewSearch $newSearch)
    {
        parent::__construct($newSearch);

        $this->filters = new Boolean;

        $this->filters->must()->matchAll();
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
        $query = $this->newSearch->bool(function (Boolean $boolean) {

            $boolean->must()->query($this->filters);

            //TODO handle query depending on mappings
            $boolean->must()->bool(function (Boolean $boolean) {

                $queryBoolean = new Boolean;

                foreach ($this->fields as $field) {
                    $boost = array_key_exists($field, $this->weight) ? $this->weight[$field] : 1;

                    $fuzziness = !in_array($field, $this->typoTolerantAttributes) ? null : auto_fuzziness($this->minCharsForOneTypo, $this->minCharsForTwoTypo);

                    $query = new Match_($field, $this->query, $fuzziness);

                    $queryBoolean->should()->query($query->boost($boost));
                }
            });
        })
            ->fields($this->retrieve);

        foreach ($this->highligh as $field) {
            $query->highlight($field, $this->highlightPrefix, $this->highlightSuffix);
        }

        $query->size($this->size);

        return $query;
    }
}
