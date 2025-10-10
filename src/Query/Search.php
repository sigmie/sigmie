<?php

declare(strict_types=1);

namespace Sigmie\Query;

use Http\Promise\Promise;
use Sigmie\Base\APIs\Search as APIsSearch;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Http\Responses\Search as SearchResponse;
use Sigmie\Query\Aggs;
use Sigmie\Query\Contracts\QueryClause as Query;
use Sigmie\Query\Queries\MatchAll;

class Search
{
    use APIsSearch;

    public string $index;

    protected bool|int $trackTotalHits;

    protected int|string $from = 0;

    protected string|float $minScore = 0;

    protected int|string $size = 500;

    protected array $fields = [];

    protected array $raw = [];

    protected array $sort = [];

    protected array $knn = [];

    protected array $highlight;

    // protected Properties $properties;

    protected string $scriptScoreSource;

    protected string $scriptScoreBoostMode = 'multiply';

    protected Query $query;

    protected Aggs $aggs;

    protected Suggest $suggest;

    public function __construct(ElasticsearchConnection $connection)
    {
        $this->setElasticsearchConnection($connection);

        $this->query = new MatchAll();
        $this->aggs = new Aggs();
    }

    // public function properties(NewProperties|Properties $props): self
    // {
    //     $this->properties = $props instanceof NewProperties ? $props->get() : $props;

    //     return $this;
    // }

    public function aggregate(callable $callable)
    {
        $callable($this->aggs);

        return $this;
    }

    // public function facets(string $string)
    // {
    //     $parser = new FacetParser($this->properties);

    //     $aggs = $parser->parse($string);

    //     $this->aggs->add($aggs);

    //     return $this;
    // }

    public function highlight(array $highlight): self
    {
        $this->highlight = $highlight;

        return $this;
    }

    // public function scriptScore(string $source, string $boostMode = 'multiply')
    // {
    //     $this->scriptScoreSource = $source;
    //     $this->scriptScoreBoostMode = $boostMode;

    //     return $this;
    // }

    public function suggest(Suggest $suggest): self {

        $this->suggest = $suggest;

        return $this;
    }

    public function knn(array $knn): self
    {
        $this->knn = $knn;

        return $this;
    }

    // public function suggest(callable $callable, ?string $text = null): Search
    // {
    //     if (! is_null($text)) {
    //         $this->suggest->text($text);
    //     }

    //     $callable($this->suggest);

    //     return $this;
    // }

    public function trackTotalHits(bool|int $trackTotalHits = true)
    {
        $this->trackTotalHits = $trackTotalHits;

        return $this;
    }

    public function minScore(string|float $minScore): self
    {
        $this->minScore = $minScore;

        return $this;
    }

    public function fields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    public function from(string|int $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function size(int|string $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function index(string $index): self
    {
        $this->index = $index;

        return $this;
    }

    // public function sortString(string $sortString): self
    // {
    //     $parser = new SortParser($this->properties);

    //     $this->sort = [
    //         ...$this->sort,
    //         ...$parser->parse($sortString),
    //     ];

    //     return $this;
    // }

    public function sort(array $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    public function addRaw(string $key, mixed $value)
    {
        $this->raw[$key] = $value;

        return $this;
    }

    public function response()
    {
        $raw = $this->toRaw();

        return $this->searchAPICall($this->index, $raw);
    }

    public function get(): SearchResponse
    {
        $raw = $this->getDSL();

        return $this->searchAPICall($this->index, $raw);
    }

    public function promise(): Promise
    {
        $raw = $this->getDSL();

        $request = $this->searchRequest($this->index, $raw);

        return $this->elasticsearchConnection->promise($request);
    }

    public function getDSL(): array
    {
        return $this->toRaw();
    }

    public function query(Query $query)
    {
        $this->query = $query;

        return $this;
    }

    public function aggs(Aggs $aggs)
    {
        $this->aggs = $aggs;

        return $this;
    }

    public function toRaw(): array
    {
        $result = [
            '_source' => $this->fields,
            'query' => $this->query->toRaw(),
            'from' => $this->from,
            'size' => $this->size,
            'min_score' => $this->minScore,
            'knn' => $this->knn,
            'sort' => $this->sort,
            ...$this->raw,
        ];

        if ($this->highlight ?? false) {
            $result['highlight'] = $this->highlight;
        }

        if ($this->trackTotalHits ?? false) {
            $result['track_total_hits'] = $this->trackTotalHits;
        }

        if ($this->suggest ?? false) {
            $result['suggest'] = $this->suggest->toRaw();
        }

        if (isset($this->aggs)) {
            $aggsRaw = $this->aggs->toRaw();
            if (!empty($aggsRaw)) {
                $result['aggs'] = $aggsRaw;
            }
        }

        return $result;
    }
}
