<?php

declare(strict_types=1);

namespace Sigmie\Query;

use Closure;
use Generator;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Http\PointInTimeRequests;
use Sigmie\Document\Hit;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Parse\FilterParser;
use Sigmie\Query\Contracts\Queries;
use Sigmie\Query\Contracts\QueryClause as Query;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\MatchNone;
use Sigmie\Query\Queries\Term\Exists;
use Sigmie\Query\Queries\Term\Fuzzy;
use Sigmie\Query\Queries\Term\IDs;
use Sigmie\Query\Queries\Term\Range;
use Sigmie\Query\Queries\Term\Regex;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Term\Terms;
use Sigmie\Query\Queries\Term\Wildcard;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Query\Queries\Text\MultiMatch;
use Sigmie\Search\Contracts\LazyIterableQuery;
use Sigmie\Search\Contracts\MultiSearchable;
use Sigmie\Search\PointInTimeIterator;

use function Sigmie\Functions\random_name;

class NewQuery implements LazyIterableQuery, MultiSearchable, Queries
{
    protected Search $search;

    protected Properties $properties;

    protected string $searchName = '';

    protected int $pitIterationChunkSize = 500;

    public function __construct(
        protected ElasticsearchConnection $httpConnection,
        protected ?string $index = null,
    ) {
        $this->search = new Search($httpConnection);

        $this->properties = new Properties;

        if ($index) {
            $this->search->index($index);
        }
    }

    public function formatResponses(...$responses): mixed
    {
        // NewQuery just returns the raw response, ignore httpCode
        return $responses[0];
    }

    public function index(string $index): static
    {
        $this->search->index($index);

        return $this;
    }

    public function properties(Properties|NewProperties $props): static
    {
        $this->properties = $props instanceof NewProperties ? $props->get() : $props;

        $this->search->filterParserProperties($this->properties);

        return $this;
    }

    public function term(string $field, string|bool|int|float $value): Search
    {
        return $this->search->query(new Term($field, $value));
    }

    public function bool(callable $callable, float $boost = 1): Search
    {
        $query = new Boolean($this->properties);

        $callable($query->boost($boost));

        return $this->search->query($query);
    }

    public function parse(string $filterString): Search
    {
        $parser = new FilterParser($this->properties);

        $query = $parser->parse($filterString);

        return $this->search->query($query);
    }

    public function range(
        string $field,
        array $values = [],
        float $boost = 1
    ): Search {
        $clause = new Range($field, $values);

        return $this->search->query($clause->boost($boost));
    }

    public function matchAll(float $boost = 1): Search
    {
        $clause = new MatchAll;

        return $this->search->query($clause->boost($boost));
    }

    public function query(Query $query): Search
    {
        return $this->search->query($query);
    }

    public function postFilter(Query $postFilter): Search
    {
        return $this->search->postFilter($postFilter);
    }

    public function postFilterString(string $filterString): Search
    {
        return $this->search->postFilterString($filterString);
    }

    public function matchNone(float $boost = 1): Search
    {
        $clause = new MatchNone;

        return $this->search->query($clause->boost($boost));
    }

    // TODO allow passing search analyzer
    public function match(
        string $field,
        string $query,
        float $boost = 1,
        string $analyzer = 'default'
    ): Search {
        $cluase = new Match_(
            $field,
            $query,
            analyzer: $analyzer
        );

        return $this->search->query($cluase->boost($boost));
    }

    public function multiMatch(array $fields, string $query, float $boost = 1): Search
    {
        $clause = new MultiMatch($fields, $query);

        return $this->search->query($clause->boost($boost));
    }

    public function exists(string $field, float $boost = 1): Search
    {
        $clause = new Exists($field);

        return $this->search->query($clause->boost($boost));
    }

    public function ids(array $ids, float $boost = 1): Search
    {
        $clause = new IDs($ids);

        return $this->search->query($clause->boost($boost));
    }

    public function fuzzy(string $field, string $value, float $boost = 1): Search
    {
        $clause = new Fuzzy($field, $value);

        return $this->search->query($clause->boost($boost));
    }

    public function terms(string $field, array $values, float $boost = 1): Search
    {
        $clause = new Terms($field, $values);

        return $this->search->query($clause->boost($boost));
    }

    public function regex(string $field, string $regex, float $boost = 1): Search
    {
        $clause = new Regex($field, $regex);

        return $this->search->query($clause->boost($boost));
    }

    public function wildcard(string $field, string $value, float $boost = 1): Search
    {
        $clause = new Wildcard($field, $value);

        return $this->search->query($clause->boost($boost));
    }

    public function toMultiSearch(): array
    {
        return [
            [
                'index' => $this->search->index,
            ],
            $this->search->toRaw(),
        ];
    }

    public function formatMultiSearchResponse(array $responses, int $startIndex): array
    {
        $searchResponse = $responses[$startIndex] ?? [];

        return [
            'hits' => $searchResponse['hits']['hits'] ?? [],
            'processing_time_ms' => $searchResponse['took'] ?? 0,
            'total' => $searchResponse['hits']['total']['value'] ?? 0,
            'max_score' => $searchResponse['hits']['max_score'] ?? null,
            'timed_out' => $searchResponse['timed_out'] ?? false,
        ];
    }

    public function multisearchResCount(): int
    {
        return 1; // just search
    }

    public function sliceMultiSearchResponse(array $responses): array
    {
        $searchResponse = $responses[0] ?? [];

        return [
            'hits' => $searchResponse['hits']['hits'] ?? [],
            'processing_time_ms' => $searchResponse['took'] ?? 0,
            'total' => $searchResponse['hits']['total']['value'] ?? 0,
            'max_score' => $searchResponse['hits']['max_score'] ?? null,
            'timed_out' => $searchResponse['timed_out'] ?? false,
        ];
    }

    public function getName(): string
    {
        if ($this->searchName !== '' && $this->searchName !== '0') {
            return $this->searchName;
        }

        return random_name('qr');
    }

    public function chunk(int $size = 500): static
    {
        $this->pitIterationChunkSize = $size;

        return $this;
    }

    /**
     * @return Generator<int, Hit>
     */
    public function lazy(): Generator
    {
        yield from $this->iterateHits();
    }

    public function each(Closure $fn): void
    {
        foreach ($this->iterateHits() as $hit) {
            $fn($hit);
        }
    }

    /**
     * @return Generator<int, Hit>
     */
    protected function iterateHits(): Generator
    {
        $pit = new PointInTimeRequests($this->httpConnection);
        $isOpenSearch = $this->httpConnection->driver()->engine() === SearchEngineType::OpenSearch;

        $body = $this->search->toRaw();

        unset(
            $body['from'],
            $body['size'],
            $body['aggs'],
            $body['highlight'],
            $body['suggest'],
            $body['track_total_hits'],
            $body['sort'],
        );

        $body['size'] = $this->pitIterationChunkSize;
        $body['sort'] = $isOpenSearch ? [['_id' => 'asc']] : [['_shard_doc' => 'asc']];

        $keepAlive = '1m';
        $open = $pit->open($this->search->index, $keepAlive);
        $pitId = PointInTimeIterator::pitIdFromOpenResponse($open, $isOpenSearch);

        yield from PointInTimeIterator::iterate(
            $pitId,
            $keepAlive,
            $body,
            fn (array $requestBody) => $pit->search($requestBody),
            function (string $id) use ($pit): void {
                $pit->close($id);
            },
            fn (array $data): Hit => new Hit(
                $data['_source'] ?? [],
                $data['_id'],
                isset($data['_score']) ? (float) $data['_score'] : null,
                $data['_index'] ?? null,
                $data['sort'] ?? null,
            ),
        );
    }
}
