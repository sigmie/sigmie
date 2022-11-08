<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Base\Contracts\ElasticsearchConnection;
use function Sigmie\Functions\auto_fuzziness;
use Sigmie\Mappings\Properties;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\SortParser;
use Sigmie\Query\Contracts\QueryClause as Query;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Search\Contracts\SearchTemplateBuilder as SearchTemplateBuilderInterface;

class SearchTemplateBuilder extends AbstractSearchBuilder implements SearchTemplateBuilderInterface
{
    protected bool $filterable = false;

    protected bool $sortable = false;

    protected Boolean $filters;

    protected array $sort = ['_score'];

    protected null|Properties $properties = null;

    public function __construct(
        NewSearch $newSearch,
        protected ElasticsearchConnection $elasticsearchConnection
    ) {
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
        $query = $this->newSearch->bool(function (Boolean $boolean) {
            if ($this->filterable) {
                $boolean->must()->bool(fn (Boolean $boolean) => $boolean->addRaw('filter', '@json(filters)'));
            }

            //TODO handle query depending on mappings
            $boolean->must()->bool(function (Boolean $boolean) {
                $queryBoolean = new Boolean;
                foreach ($this->fields as $field) {
                    $boost = array_key_exists($field, $this->weight) ? $this->weight[$field] : 1;

                    $fuzziness = ! in_array($field, $this->typoTolerantAttributes) ? null : auto_fuzziness($this->minCharsForOneTypo, $this->minCharsForTwoTypo);

                    $query = new Match_($field, $this->query, $fuzziness);

                    $queryBoolean->should()->query($query->boost($boost));
                }

                if ($queryBoolean->toRaw()['bool']['should'] ?? false) {
                    $query = json_encode($queryBoolean->toRaw()['bool']['should']);

                    $boolean->addRaw('should', "@query($query)@endquery");
                }
            });
        })->fields($this->retrieve);

        $defaultSorts = [];
        foreach ($this->sorts as $field => $direction) {
            if ($field === '_score') {
                $defaultSorts[] = $field;

                continue;
            }
            $defaultSorts[] = [$field => $direction];
        }

        $defaultSorts = json_encode($defaultSorts);
        $query->addRaw('sort', "@sorting($defaultSorts)@endsorting");

        foreach ($this->highligh as $field) {
            $query->highlight($field, $this->prefix, $this->suffix);
        }

        $query->size('@var(size,10)');

        $raw = $query->toRaw();

        return new SearchTemplate($this->elasticsearchConnection, $raw);
    }
}
