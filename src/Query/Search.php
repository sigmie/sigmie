<?php

declare(strict_types=1);

namespace Sigmie\Query;

use Http\Promise\Promise;
use Sigmie\Base\APIs\Script as APIsScript;
use Sigmie\Base\APIs\Search as APIsSearch;
use Sigmie\Base\Http\Responses\Search as SearchResponse;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Parse\FacetParser;
use Sigmie\Parse\SortParser;
use Sigmie\Query\Contracts\QueryClause as Query;
use Sigmie\Query\Queries\MatchAll;

class Search
{
    use APIsScript;
    use APIsSearch;

    protected string $index;

    protected int $trackTotalHits = 10000;

    protected int|string $from = 0;

    protected string|float $minScore = 0;

    protected int|string $size = 500;

    protected array $fields = [];

    protected array $raw = [];

    protected array $sort = [];

    protected array $highlight = [];

    protected Properties $properties;

    protected string $scriptScoreSource = "doc.containsKey('boost') && doc['boost'].size() > 0 ? doc['boost'].value : 1";

    protected string $scriptScoreBoostMode = 'multiply';

    public function __construct(
        protected Query $query = new MatchAll(),
        protected Aggs $aggs = new Aggs(),
        protected Suggest $suggest = new Suggest()
    ) {}

    public function properties(NewProperties|Properties $props): self
    {
        $this->properties = $props instanceof NewProperties ? $props->get() : $props;

        return $this;
    }

    public function aggregate(callable $callable)
    {
        $callable($this->aggs);

        return $this;
    }

    public function facets(string $string)
    {
        $parser = new FacetParser($this->properties);

        $aggs = $parser->parse($string);

        $this->aggs->add($aggs);

        return $this;
    }

    public function scriptScore(string $source, string $boostMode = 'multiply')
    {
        $this->scriptScoreSource = $source;
        $this->scriptScoreBoostMode = $boostMode;

        return $this;
    }

    public function suggest(callable $callable, ?string $text = null): Search
    {
        if (! is_null($text)) {
            $this->suggest->text($text);
        }

        $callable($this->suggest);

        return $this;
    }

    public function trackTotalHits(int $trackTotalHits = -1)
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

    public function sortString(string $sortString): self
    {
        $parser = new SortParser($this->properties);

        $this->sort = [
            ...$this->sort,
            ...$parser->parse($sortString),
        ];

        return $this;
    }

    public function sort(string $field, ?string $direction = null): self
    {
        if ($field === '_score') {
            $this->sort[] = $field;

            return $this;
        }

        $this->sort[] = [$field => $direction];

        return $this;
    }

    public function addRaw(string $key, mixed $value)
    {
        $this->raw[$key] = $value;

        return $this;
    }

    public function highlight(string $field, string $preTag, string $postTag)
    {
        $this->highlight[$field] = [
            'type' => 'plain',
            'force_source' => true,
            'pre_tags' => [$preTag],
            'post_tags' => [$postTag],
            'fragment_size' => 150,
            'number_of_fragments' => 3,
            'no_match_size' => 150,
        ];
    }

    public function response()
    {
        $raw = $this->toRaw();

        return $this->searchAPICall($this->index, $raw);
    }

    public function get(): SearchResponse
    {
        $raw = $this->getDSL();

        // ray(json_encode($raw, JSON_PRETTY_PRINT));

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

    public function toRaw(): array
    {
        $result = [
            'track_total_hits' => $this->trackTotalHits < 0 ? true : $this->trackTotalHits,
            '_source' => $this->fields,
            'query' => [
                ...(new FunctionScore(
                    $this->query,
                    source: $this->scriptScoreSource,
                    boostMode: $this->scriptScoreBoostMode
                ))->toRaw(),
            ],
            'from' => $this->from,
            'size' => $this->size,
            'min_score' => $this->minScore,
            'sort' => [...$this->sort],
            'highlight' => [
                // 'require_field_match' => false,
                'force_source' => true,
                'no_match_size' => 100,
                'fields' => [
                    ...$this->highlight,
                ],
            ],
            ...$this->raw,
        ];

        if (count($this->suggest->toRaw()) > 0) {
            $result['suggest'] = $this->suggest->toRaw();
        }

        if (count($this->aggs->toRaw()) > 0) {
            $result['aggs'] = $this->aggs->toRaw();
        }

        return $result;
    }
}
