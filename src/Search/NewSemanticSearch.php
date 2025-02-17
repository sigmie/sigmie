<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Http\Promise\Promise;
use Sigmie\Mappings\PropertiesFieldNotFound;
use Sigmie\Mappings\Types\Nested as TypesNested;
use Sigmie\Parse\FacetParser;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\SortParser;
use Sigmie\Query\Contracts\FuzzyQuery;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\MatchNone;
use Sigmie\Query\Queries\Query;
use Sigmie\Query\Queries\Text\Nested;
use Sigmie\Query\Search;
use Sigmie\Query\Suggest;
use Sigmie\Search\Contracts\EmbeddingsQueries;
use Sigmie\Search\Contracts\SearchQueryBuilder as SearchQueryBuilderInterface;
use Sigmie\Shared\Collection;

use function Sigmie\Functions\auto_fuzziness;

class NewSemanticSearch extends AbstractSearchBuilder implements SearchQueryBuilderInterface
{
    protected array $sort = ['_score'];

    protected string $queryString = '';

    protected array $embeddings = [];

    protected string $index;

    public function queryString(string $query): static
    {
        $this->queryString = $query;

        return $this;
    }

    public function embeddings(array $embeddings): static
    {
        $this->embeddings = $embeddings;

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
        $field = $this->properties->getNestedField($this->fields[0]);

        $search = new Search($field->queries($this->embeddings)[0]);

        $search->index($this->index);

        $search->setElasticsearchConnection($this->elasticsearchConnection);

        $search->fields($this->retrieve);

        $search->addRaw('sort', $this->sort);

        $search->addRaw('aggs', $this->facets->toRaw());

        $search->size($this->size);

        $search->from($this->from);

        $search->trackTotalHits();

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
