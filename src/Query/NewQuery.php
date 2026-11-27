<?php

declare(strict_types=1);

namespace Sigmie\Query;

use Closure;
use Generator;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Document\Hit;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Parse\FilterParser;
use Sigmie\Parse\SortParser;
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
use Sigmie\Search\RawQuery;

use function Sigmie\Functions\random_name;
use function Sigmie\Functions\search_summary;

class NewQuery implements LazyIterableQuery, MultiSearchable, Queries
{
    protected Search $search;

    protected Properties $properties;

    protected array $sort = [];

    protected string $index;

    protected string $searchName = '';

    protected int $pitIterationChunkSize = 500;

    public function __construct(
        protected ElasticsearchConnection $httpConnection,
        ?string $index = null,
    ) {
        $this->index = $index ?? '';
        $this->search = (new Search($httpConnection))->index($this->index);

        $this->properties = new Properties;
    }

    public function formatResponses(...$responses): mixed
    {
        // NewQuery just returns the raw response, ignore httpCode
        return $responses[0];
    }

    public function index(string $index): static
    {
        $this->index = $index;
        $this->search = (clone $this->search)->index($index);

        return $this;
    }

    public function properties(Properties|NewProperties $props): static
    {
        $this->properties = $props instanceof NewProperties ? $props->get() : $props;

        $this->search = (clone $this->search)->filterParserProperties($this->properties);

        return $this;
    }

    public function sort(array $sort): static
    {
        $this->sort = $sort;
        $this->search = (clone $this->search)->sort($sort);

        return $this;
    }

    public function sortString(string $sort): static
    {
        $parser = new SortParser($this->properties, true);

        return $this->sort($parser->parse($sort));
    }

    public function term(string $field, string|bool|int|float $value): Search
    {
        return $this->query(new Term($field, $value));
    }

    public function bool(callable $callable, float $boost = 1): Search
    {
        $query = new Boolean($this->properties);

        $callable($query->boost($boost));

        return $this->query($query);
    }

    public function parse(string $filterString): Search
    {
        $parser = new FilterParser($this->properties);

        $query = $parser->parse($filterString);

        return $this->query($query);
    }

    public function range(
        string $field,
        array $values = [],
        float $boost = 1
    ): Search {
        $clause = new Range($field, $values);

        return $this->query($clause->boost($boost));
    }

    public function matchAll(float $boost = 1): Search
    {
        $clause = new MatchAll;

        return $this->query($clause->boost($boost));
    }

    public function query(Query $query): Search
    {
        $search = (new Search($this->httpConnection))
            ->filterParserProperties($this->properties)
            ->sort($this->sort)
            ->index($this->index)
            ->query($query);

        return $this->search = $search;
    }

    public function postFilter(Query $postFilter): Search
    {
        return $this->search = (clone $this->search)->postFilter($postFilter);
    }

    public function postFilterString(string $filterString): Search
    {
        return $this->search = (clone $this->search)->postFilterString($filterString);
    }

    public function matchNone(float $boost = 1): Search
    {
        $clause = new MatchNone;

        return $this->query($clause->boost($boost));
    }

    // TODO allow passing search analyzer
    public function match(
        string $field,
        string $query,
        float $boost = 1,
        ?string $analyzer = null
    ): Search {
        $clause = new Match_(
            $field,
            $query,
            analyzer: $analyzer
        );

        return $this->query($clause->boost($boost));
    }

    public function multiMatch(array $fields, string $query, float $boost = 1): Search
    {
        $clause = new MultiMatch($fields, $query);

        return $this->query($clause->boost($boost));
    }

    public function exists(string $field, float $boost = 1): Search
    {
        $clause = new Exists($field);

        return $this->query($clause->boost($boost));
    }

    public function ids(array $ids, float $boost = 1): Search
    {
        $clause = new IDs($ids);

        return $this->query($clause->boost($boost));
    }

    public function fuzzy(string $field, string $value, float $boost = 1): Search
    {
        $clause = new Fuzzy($field, $value);

        return $this->query($clause->boost($boost));
    }

    public function terms(string $field, array $values, float $boost = 1): Search
    {
        $clause = new Terms($field, $values);

        return $this->query($clause->boost($boost));
    }

    public function regex(string $field, string $regex, float $boost = 1): Search
    {
        $clause = new Regex($field, $regex);

        return $this->query($clause->boost($boost));
    }

    public function wildcard(string $field, string $value, float $boost = 1): Search
    {
        $clause = new Wildcard($field, $value);

        return $this->query($clause->boost($boost));
    }

    public function toMultiSearch(): array
    {
        return $this->rawQuery()->toMultiSearch();
    }

    public function formatMultiSearchResponse(array $responses, int $startIndex): array
    {
        return search_summary($responses[$startIndex] ?? []);
    }

    public function multisearchResCount(): int
    {
        return 1; // just search
    }

    public function sliceMultiSearchResponse(array $responses): array
    {
        return $this->formatMultiSearchResponse($responses, 0);
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
        yield from $this->rawQuery()->chunk($this->pitIterationChunkSize)->lazy();
    }

    private function rawQuery(): RawQuery
    {
        return new RawQuery($this->httpConnection, $this->search->index, $this->search->toRaw());
    }
}
