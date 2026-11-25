<?php

declare(strict_types=1);

namespace Sigmie\Query;

use Http\Promise\Promise;
use Sigmie\Base\APIs\Search as APIsSearch;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\Responses\Search as SearchResponse;
use Sigmie\Mappings\Properties;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\SortParser;
use Sigmie\Query\Contracts\Aggs as AggsContract;
use Sigmie\Query\Contracts\QueryClause as Query;
use Sigmie\Query\Queries\MatchAll;

use function Sigmie\Functions\merge_named;
use function Sigmie\Functions\object_map;
use function Sigmie\Functions\sort_clauses;

class Search
{
    use APIsSearch;

    public string $index;

    protected bool|int $trackTotalHits;

    protected int|string $from = 0;

    protected string|float $minScore = 0;

    protected int|string $size = 500;

    protected array|false $fields = [];

    protected array $except = [];

    protected array $raw = [];

    protected array $sort = [];

    protected array $knn = [];

    protected array $highlight;

    // protected Properties $properties;

    protected string $scriptScoreSource;

    protected string $scriptScoreBoostMode = 'multiply';

    protected ?Query $query = null;

    protected ?Query $postFilter = null;

    protected ?Properties $filterParserProperties = null;

    protected AggsContract $aggs;

    protected Suggest $suggest;

    public function __construct(ElasticsearchConnection $connection)
    {
        $this->setElasticsearchConnection($connection);

        $this->aggs = new Aggs;
    }

    public function __clone(): void
    {
        $this->aggs = clone $this->aggs;
    }

    // public function properties(NewProperties|Properties $props): self
    // {
    //     $this->properties = $props instanceof NewProperties ? $props->get() : $props;

    //     return $this;
    // }

    public function aggregate(callable $callable): static
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

    public function suggest(Suggest $suggest): self
    {

        $this->suggest = $suggest;

        return $this;
    }

    public function knn(array $knn): self
    {
        $this->knn = is_int(array_key_first($knn)) ? array_values($knn) : $knn;

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

    public function trackTotalHits(bool|int $trackTotalHits = true): static
    {
        $this->trackTotalHits = $trackTotalHits;

        return $this;
    }

    public function minScore(string|float $minScore): self
    {
        $this->minScore = $minScore;

        return $this;
    }

    public function fields(array|false $fields): self
    {
        $this->fields = $fields === false ? false : array_values($fields);

        return $this;
    }

    /**
     * Source fields to drop from every hit. Elasticsearch applies excludes after fields(), so an
     * excluded path stays hidden even when a broader included field contains it.
     */
    public function except(array $fields): self
    {
        $this->except = array_values($fields);

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

    public function sort(array $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    /** @return list<mixed> */
    public static function sortClauses(array $sort): array
    {
        return sort_clauses($sort);
    }

    public function sortString(string $sort): static
    {
        $parser = new SortParser($this->filterParserProperties ?? new Properties);

        return $this->sort($parser->parse($sort));
    }

    public function addRaw(string $key, mixed $value): static
    {
        $this->raw[$key] = $value;

        return $this;
    }

    public function response(): SearchResponse
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

        return $this->elasticsearchConnection->promise($request)->then(
            fn (ElasticsearchResponse $response): SearchResponse => $this->typedResponse($request, $response, SearchResponse::class),
        );
    }

    public function getDSL(): array
    {
        return $this->toRaw();
    }

    public function query(Query $query): static
    {
        $this->query = $query;

        return $this;
    }

    public function postFilter(Query $postFilter): static
    {
        $this->postFilter = $postFilter;

        return $this;
    }

    public function filterParserProperties(Properties $properties): static
    {
        $this->filterParserProperties = $properties;

        return $this;
    }

    public function postFilterString(string $filterString): static
    {
        $parser = new FilterParser($this->filterParserProperties ?? new Properties);

        return $this->postFilter($parser->parse($filterString));
    }

    public function aggs(AggsContract $aggs): static
    {
        $this->aggs = $aggs;

        return $this;
    }

    private function hasOrderedHighlightFields(array $fields): bool
    {
        // Elasticsearch also accepts a list of single-field objects to specify highlight order.
        foreach ($fields as $field) {
            $entry = (array) $field;
            if (count($entry) !== 1) {
                return false;
            }

            // These two field options also take an object. An explicit object
            // entry disambiguates an ordered field with either reserved name.
            if (is_array($field) && in_array(array_key_first($entry), ['highlight_query', 'options'], true)) {
                return false;
            }

            $options = reset($entry);
            if ($options === [] && (is_object($field) || ! in_array(array_key_first($entry), ['pre_tags', 'post_tags', 'matched_fields'], true))) {
                continue;
            }

            if (! is_object($options) && (! is_array($options) || array_is_list($options))) {
                return false;
            }
        }

        return true;
    }

    public function toRaw(): array
    {
        $query = $this->query;
        $hasRetriever = isset($this->raw['retriever']);
        if (! $hasRetriever && $this->knn === [] && ! isset($this->raw['knn'])) {
            $query ??= new MatchAll;
        }

        $result = merge_named([
            ...$query instanceof Query ? ['query' => $query->toRaw()] : [],
            'from' => $this->from,
            'size' => $this->size,
            'min_score' => $this->minScore,
            ...! $hasRetriever || $this->sort !== [] ? ['sort' => $this->sort] : [],
        ], $this->raw);

        if ($this->fields === false) {
            $result['_source'] = false;
        } elseif ($this->except !== []) {
            $result['_source'] = ['includes' => $this->fields, 'excludes' => $this->except];
        } elseif ($this->fields !== []) {
            $result['_source'] = $this->fields;
        }

        if ($this->knn !== []) {
            $result['knn'] = $this->knn;
        }

        if ($this->highlight ?? false) {
            $highlight = $this->highlight;

            $fields = $highlight['fields'] ?? null;
            if (is_array($fields) && $fields !== []) {
                $fieldOptions = static fn (mixed $options): mixed => $options === [] ? (object) [] : $options;
                if (array_filter(array_keys($fields), is_string(...)) === [] && $this->hasOrderedHighlightFields($fields)) {
                    $highlight['fields'] = array_map(
                        static fn (mixed $field): mixed => is_object($field)
                            ? (object) array_map($fieldOptions, (array) $field)
                            : object_map(array_map($fieldOptions, $field)),
                        array_values($fields),
                    );
                } else {
                    $highlight['fields'] = object_map(array_map($fieldOptions, $fields));
                }
            }

            $result['highlight'] = $highlight;
        }

        if (isset($this->trackTotalHits)) {
            $result['track_total_hits'] = $this->trackTotalHits;
        }

        if ($this->suggest ?? false) {
            $result['suggest'] = $this->suggest->toRaw();
        }

        if (isset($this->aggs)) {
            $aggsRaw = $this->aggs->toRaw();
            if ($aggsRaw !== []) {
                $result['aggs'] = merge_named((array) ($result['aggs'] ?? []), $aggsRaw);
            }
        }

        if ($this->postFilter instanceof Query) {
            $result['post_filter'] = $this->postFilter->toRaw();
        }

        return self::normalizeBody($result);
    }

    public static function normalizeBody(array $body): array
    {
        if (isset($body['sort']) && is_array($body['sort'])) {
            $body['sort'] = self::sortClauses($body['sort']);
        }

        foreach (['aggs', 'aggregations', 'script_fields', 'runtime_mappings', 'suggest'] as $key) {
            if (isset($body[$key]) && is_array($body[$key]) && array_is_list($body[$key])) {
                $body[$key] = (object) $body[$key];
            }
        }

        if (isset($body['indices_boost']) && is_array($body['indices_boost'])) {
            $body['indices_boost'] = array_values(array_map(
                static fn (mixed $boost): mixed => is_array($boost) ? object_map($boost) : $boost,
                $body['indices_boost'],
            ));
        }

        return $body;
    }
}
