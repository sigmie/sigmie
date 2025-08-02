<?php

declare(strict_types=1);

namespace Sigmie\Search;

use GuzzleHttp\Promise\Utils;
use Http\Promise\Promise;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Base\Http\Responses\Search as ResponsesSearch;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\PropertiesFieldNotFound;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\Nested as TypesNested;
use Sigmie\Mappings\Types\NestedVector;
use Sigmie\Mappings\Types\SigmieVector;
use Sigmie\Mappings\Types\Text;
use Sigmie\Parse\FacetParser;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\SortParser;
use Sigmie\Plugins\Elastiknn\NearestNeighbors;
use Sigmie\Query\Contracts\FuzzyQuery;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\MatchNone;
// use Sigmie\Query\Queries\Query;
use Sigmie\Query\Contracts\QueryClause as Query;
use Sigmie\Query\Facets;
use Sigmie\Query\Queries\Text\Nested;
use Sigmie\Query\Search;
use Sigmie\Query\Suggest;
use Sigmie\Search\Contracts\ResponseFormater;
use Sigmie\Search\Contracts\SearchQueryBuilder as SearchQueryBuilderInterface;
use Sigmie\Search\Formatters\SigmieFacetSearchResponse;
use Sigmie\Search\Formatters\SigmieSearchResponse;
use Sigmie\Shared\Collection;

use function Sigmie\Functions\auto_fuzziness;

class NewFacetSearch
{
    protected NewSearch $search;

    protected string $facet;

    protected Properties $properties;

    protected string $query = '';

    public function __construct(
        protected ElasticsearchConnection $elasticsearchConnection,
    ) {
        $this->search = new NewSearch($this->elasticsearchConnection);

        $this->search->fields([]);
    }

    public function index(string $index): static
    {
        $this->search->index($index);

        return $this;
    }

    public function properties(Properties|NewProperties $props): static
    {
        $this->properties = $props instanceof NewProperties ? $props->get() : $props;

        $this->search->properties($props);

        return $this;
    }

    public function filters(string $filters): static
    {
        $this->search->filters($filters);

        return $this;
    }

    public function facet(string $facet)
    {
        $this->facet = $facet;

        $field = $this->properties->getNestedField($facet);

        if (!$field->isFacetSearchable()) {
            throw new \Exception("The facet '{$facet}' is not searchable.");
        }

        $this->search->autocomplete(true);
        $this->search->retrieve([$facet]);
        $this->search->fields([$facet]);

        return $this;
    }

    public function asc(): static
    {
        $this->search->sort("{$this->facet}:asc");

        return $this;
    }

    public function desc(): static
    {
        $this->search->sort("{$this->facet}:desc");

        return $this;
    }

    public function query(string $query): static
    {
        $this->query = $query;

        return $this;
    }

    public function get()
    {
        $this->search->formatter(new SigmieFacetSearchResponse($this->properties));

        $this->search->queryString($this->query);
        $this->search->size(0);
        $this->search->facets($this->facet . ':10');

        $search = $this->search->make();

        $search->suggest(function (Suggest $suggest) {

            $suggest->completion(name: $this->facet)
                ->field($this->facet . '.facet_search')
                ->size(10)
                ->fuzzyMinLegth(3)
                ->fuzzyPrefixLenght(1)
                ->fuzzy(true)
                ->prefix($this->query);
        });

        dump('Query', $search->getDSL());

        $get = $search->get();

        return $get;
    }

    public function make(): Search
    {
        return $this->search->make();
    }
}
