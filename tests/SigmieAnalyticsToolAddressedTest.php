<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use DateTimeInterface;
use Http\Promise\Promise;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sigmie\AI\AsTool;
use Sigmie\AI\SigmieAnalyticsTool;
use Sigmie\Analytics\Analytics;
use Sigmie\Analytics\QueryRecipe;
use Sigmie\AI\SigmieIndexTool;
use Sigmie\Search\Formatters\SigmieSearchResponse;
use Sigmie\Search\NewSearch;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Base\Http\Responses\Search as SearchResponse;
use Sigmie\Sigmie;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Mappings\Contracts\Type as TypeContract;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\BaseVector;
use Sigmie\Mappings\Types\NestedVector;
use Sigmie\Query\Aggregations\Enums\CalendarInterval;
use Sigmie\Query\NewQuery;
use Sigmie\SigmieIndex;

require_once __DIR__.'/Stubs/LaravelAiStubs.php';

use Laravel\Ai\Tools\Request;

class SigmieAnalyticsToolAddressedTest extends TestCase
{
    public function test_trend_accepts_a_minimum_of_two_documents_per_bucket(): void
    {
        $index = $this->createMock(SigmieIndex::class);
        $fields = new NewProperties;
        $fields->date('created_at');
        $index->method('properties')->willReturn($fields);
        $analytics = $this->createMock(Analytics::class);
        $index->method('analytics')->willReturn($analytics);
        $analytics->expects($this->once())->method('trend')->with(
            'result', Metric::Count, '', CalendarInterval::Day, null, null, 2,
        )->willReturnSelf();
        $analytics->method('get')->willReturn(['result' => ['type' => 'trend']]);

        $result = (new SigmieAnalyticsTool($index))->result(new Request([
            'widget' => 'trend',
            'date_field' => 'created_at',
            'metric' => 'count',
            'min_doc_count' => 2,
        ]));

        $this->assertSame(['type' => 'trend'], $result);
    }

    public function test_ranked_widget_can_use_a_metric_key_that_matches_a_private_field(): void
    {
        $index = $this->createMock(SigmieIndex::class);
        $fields = new NewProperties;
        $fields->date('created_at');
        $fields->keyword('city');
        $fields->number('private_amount');
        $index->method('properties')->willReturn($fields);
        $index->method('exceptFromTools')->willReturn(['private_amount']);
        $analytics = $this->createMock(Analytics::class);
        $index->method('analytics')->willReturn($analytics);
        $analytics->expects($this->once())->method('groupedMetrics')->with(
            'result',
            'city',
            [['key' => 'private_amount', 'label' => 'private_amount', 'metric' => Metric::Count]],
            'private_amount',
            10,
            'asc',
        )->willReturnSelf();
        $analytics->method('get')->willReturn(['result' => ['type' => 'grouped_metrics']]);

        $result = (new SigmieAnalyticsTool($index))->result(new Request([
            'widget' => 'grouped_metrics',
            'date_field' => 'created_at',
            'group_by' => 'city',
            'metrics' => '[{"key":"private_amount","metric":"count"}]',
            'sort_metric' => 'private_amount',
            'sort' => 'private_amount:asc',
        ]));

        $this->assertSame(['type' => 'grouped_metrics'], $result);
    }

    public function test_ranked_widget_with_one_keyword_shows_distinct_fields_in_union_example(): void
    {
        $index = $this->createMock(SigmieIndex::class);
        $fields = new NewProperties;
        $fields->date('created_at');
        $fields->number('amount');
        $fields->keyword('city');
        $index->method('properties')->willReturn($fields);
        $description = (new SigmieAnalyticsTool($index))->description();

        preg_match('/^- top labels across fields: (.+)$/m', $description, $match);
        $arguments = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('city,<second_keyword_field>', $arguments['group_by_fields']);
    }

    /** @withoutCluster */
    public function test_two_index_tool_suites_have_distinct_names(): void
    {
        $names = [];
        foreach (['orders', 'products'] as $name) {
            $index = new class($name) extends SigmieIndex
            {
                use AsTool;

                public function __construct(private string $alias) {}

                public function name(): string
                {
                    return $this->alias;
                }

                public function properties(): NewProperties
                {
                    return new NewProperties;
                }
            };
            foreach ($index->tools() as $tool) {
                $names[] = method_exists($tool, 'name') ? $tool->name() : $tool::class;
            }
        }
        $this->assertCount(12, array_unique($names));
    }

    public function test_recipe_recognizes_unicode_spaced_output_fields(): void
    {
        $props = new NewProperties;
        $props->date('created_at');
        $props->keyword('עיר');
        $props->number('τιμή');
        $index = $this->createMock(SigmieIndex::class);
        $index->method('properties')->willReturn($props);
        $recipe = QueryRecipe::fromArray([
            'dataset' => 'events',
            'template' => ['widget' => 'table', 'date_field' => 'created_at', 'fields' => "עיר,\u{3000}τιμή"],
        ]);

        $this->assertSame($recipe, $recipe->validateAgainst($index));
    }

    public function test_match_requests_accept_unicode_space_between_paths(): void
    {
        $index = $this->createMock(SigmieIndex::class);
        $search = $this->createMock(NewSearch::class);
        $index->method('newSearch')->willReturn($search);
        $response = $this->createMock(SigmieSearchResponse::class);
        $response->method('json')->willReturn([]);
        $search->method('get')->willReturn($response);
        foreach (['queryString', 'retrieve', 'except', 'page'] as $method) {
            $search->method($method)->willReturnSelf();
        }
        $search->expects($this->exactly(2))->method('innerHits')
            ->withConsecutive(['東京', 2], ['Αθήνα', 3])->willReturnSelf();

        (new SigmieIndexTool($index))->result(new Request(['matches' => "東京:2\u{3000}Αθήνα:3"]));
    }

    public function test_unicode_blank_metric_label_uses_its_key(): void
    {
        $properties = new NewProperties;
        $properties->keyword('עיר');
        $analytics = new Analytics(new NewQuery($this->createMock(ElasticsearchConnection::class)), $properties, 'created_at');
        $analytics->groupedMetrics('groups', 'עיר', [
            ['key' => 'σύνολο', 'label' => "\u{00A0}\u{3000}", 'metric' => Metric::Count],
        ]);

        $this->assertSame('σύνολο', $analytics->formatResponse([])['groups']['metrics'][0]['label']);
    }

    /**
     * @test
     */
    public function percentiles_zero_and_one_hundred_reach_the_widget(): void
    {
        $engine = new class implements SearchEngine
        {
            public function engine(): SearchEngineType
            {
                return SearchEngineType::Elasticsearch;
            }

            public function vectorField(BaseVector $field): TypeContract
            {
                return $field;
            }

            public function nestedVectorField(NestedVector $field): TypeContract
            {
                return $field;
            }

            public function indexSettings(): array
            {
                return [];
            }
        };

        $connection = new class($engine) implements ElasticsearchConnection
        {
            public function __construct(private SearchEngine $engine) {}

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                throw new RuntimeException('no cluster');
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                throw new RuntimeException('no cluster');
            }

            public function driver(): SearchEngine
            {
                return $this->engine;
            }

            public function isServerless(): bool
            {
                return false;
            }
        };

        $props = new NewProperties;
        $props->datetime('created_at');
        $props->number('amount')->float();

        $index = new class($connection, $props) extends SigmieIndex
        {
            public array $percents = [];

            public function __construct(
                private ElasticsearchConnection $connection,
                private NewProperties $props,
            ) {}

            public function name(): string
            {
                return 'sales';
            }

            public function properties(): NewProperties
            {
                return $this->props;
            }

            public function analytics(string $dateField, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): Analytics
            {
                $index = $this;

                return new class(new NewQuery($this->connection, $this->name()), $this->props, $dateField, $from, $to, $index) extends Analytics
                {
                    public function __construct(
                        NewQuery $query,
                        NewProperties $properties,
                        string $dateField,
                        ?DateTimeInterface $from,
                        ?DateTimeInterface $to,
                        private object $index,
                    ) {
                        parent::__construct($query, $properties, $dateField, $from, $to);
                    }

                    public function percentiles(string $as, string $field, array $percents = [50, 75, 95, 99], $filter = null, $window = null): static
                    {
                        $this->index->percents = $percents;

                        return parent::percentiles($as, $field, $percents, $filter, $window);
                    }

                    public function get(): array
                    {
                        return ['result' => ['captured' => true]];
                    }
                };
            }
        };

        $result = (new SigmieAnalyticsTool($index))->result(new Request([
            'widget' => 'percentiles',
            'date_field' => 'created_at',
            'field' => 'amount',
            'percents' => '0,50,100',
            'from' => '2024-01-01',
            'to' => '2024-02-01',
        ]));

        $this->assertSame(['captured' => true], $result);
        $this->assertEquals([0, 50, 100], $index->percents);
    }

    /**
     * @test
     */
    public function a_histogram_count_reaches_the_widget_without_a_field(): void
    {
        $engine = new class implements SearchEngine
        {
            public function engine(): SearchEngineType
            {
                return SearchEngineType::Elasticsearch;
            }

            public function vectorField(BaseVector $field): TypeContract
            {
                return $field;
            }

            public function nestedVectorField(NestedVector $field): TypeContract
            {
                return $field;
            }

            public function indexSettings(): array
            {
                return [];
            }
        };

        $connection = new class($engine) implements ElasticsearchConnection
        {
            public function __construct(private SearchEngine $engine) {}

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                throw new RuntimeException('no cluster');
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                throw new RuntimeException('no cluster');
            }

            public function driver(): SearchEngine
            {
                return $this->engine;
            }

            public function isServerless(): bool
            {
                return false;
            }
        };

        $props = new NewProperties;
        $props->datetime('created_at');
        $props->number('price')->float();
        $props->number('rating')->float();

        $index = new class($connection, $props) extends SigmieIndex
        {
            public ?string $field = null;

            public function __construct(
                private ElasticsearchConnection $connection,
                private NewProperties $props,
            ) {}

            public function name(): string
            {
                return 'products';
            }

            public function properties(): NewProperties
            {
                return $this->props;
            }

            public function analytics(string $dateField, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): Analytics
            {
                $index = $this;

                return new class(new NewQuery($this->connection, $this->name()), $this->props, $dateField, $from, $to, $index) extends Analytics
                {
                    public function __construct(
                        NewQuery $query,
                        NewProperties $properties,
                        string $dateField,
                        ?DateTimeInterface $from,
                        ?DateTimeInterface $to,
                        private object $index,
                    ) {
                        parent::__construct($query, $properties, $dateField, $from, $to);
                    }

                    public function histogramMetric(string $as, string $bucketField, int $interval, Metric $metric, string $field = '', $filter = null, $window = null): static
                    {
                        $this->index->field = $field;

                        return $this;
                    }

                    public function get(): array
                    {
                        return ['result' => ['field' => $this->index->field]];
                    }
                };
            }
        };

        $tool = new SigmieAnalyticsTool($index);
        $rated = $tool->result(new Request([
            'widget' => 'histogram_metric',
            'date_field' => 'created_at',
            'metric' => 'count',
            'field' => 'rating',
            'bucket_field' => 'price',
            'bucket_size' => 50,
            'from' => '2024-01-01',
            'to' => '2024-02-01',
        ]));

        $this->assertSame(['field' => 'rating'], $rated);

        $counted = $tool->result(new Request([
            'widget' => 'histogram_metric',
            'date_field' => 'created_at',
            'metric' => 'count',
            'field' => null,
            'bucket_field' => 'price',
            'bucket_size' => 50,
            'from' => '2024-01-01',
            'to' => '2024-02-01',
        ]));

        $this->assertSame(['field' => ''], $counted);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Analytics argument [field] is required.');

        $tool->result(new Request([
            'widget' => 'histogram_metric',
            'date_field' => 'created_at',
            'metric' => 'avg',
            'bucket_field' => 'price',
            'bucket_size' => 50,
            'from' => '2024-01-01',
            'to' => '2024-02-01',
        ]));
    }

    /**
     * @test
     */
    public function a_date_only_bound_stays_in_utc(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Pacific/Kiritimati');

        try {
            $bounds = $this->kpiBounds('2024-01-01', '2024-02-01', null);
            $offsetZero = $this->kpiBounds('2024-01-01', '2024-02-01', 0);
        } finally {
            date_default_timezone_set($previous);
        }

        $this->assertSame('2024-01-01T00:00:00+00:00', $bounds['from']);
        $this->assertSame('2024-02-01T00:00:00+00:00', $bounds['to']);
        $this->assertNull($bounds['timezone']);
        $this->assertSame('2024-01-01T00:00:00+00:00', $offsetZero['from']);
        $this->assertSame('+00:00', $offsetZero['timezone']);
    }

    /**
     * @test
     */
    public function long_text_is_not_a_keyword_group_by_field(): void
    {
        $index = new class extends SigmieIndex
        {
            public function __construct() {}

            public function name(): string
            {
                return 'articles';
            }

            public function properties(): NewProperties
            {
                $props = new NewProperties;
                $props->date('created_at');
                $props->longText('synopsis');
                $props->keyword('tag');

                return $props;
            }
        };

        $description = (new SigmieAnalyticsTool($index))->description();
        $line = '';
        foreach (explode("\n", $description) as $row) {
            if (str_starts_with($row, 'Keyword fields for')) {
                $line = $row;
                break;
            }
        }

        $this->assertSame(
            'Keyword fields for `group_by` / `row_field` / `col_field` / `id_field`: tag',
            $line,
        );
        $this->assertNull($index->properties()->get()->get('synopsis')->keywordName());
    }

    /**
     * @test
     */
    public function include_hits_stay_inside_the_widget_window(): void
    {
        $engine = new class implements SearchEngine
        {
            public function engine(): SearchEngineType
            {
                return SearchEngineType::Elasticsearch;
            }

            public function vectorField(BaseVector $field): TypeContract
            {
                return $field;
            }

            public function nestedVectorField(NestedVector $field): TypeContract
            {
                return $field;
            }

            public function indexSettings(): array
            {
                return [];
            }
        };

        $bodies = [];

        $connection = new class($engine, $bodies) implements ElasticsearchConnection
        {
            public function __construct(private SearchEngine $engine, private array &$bodies) {}

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                $this->bodies[] = $request->body();

                return new SearchResponse(200, ['Content-Type' => 'application/json'], json_encode([
                    'took' => 1,
                    'timed_out' => false,
                    'hits' => ['total' => ['value' => 0, 'relation' => 'eq'], 'hits' => []],
                    'aggregations' => ['result' => ['doc_count' => 0, 'metric' => ['value' => 0]]],
                ]));
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                throw new RuntimeException('no cluster');
            }

            public function driver(): SearchEngine
            {
                return $this->engine;
            }

            public function isServerless(): bool
            {
                return false;
            }
        };

        $index = new class(new Sigmie($connection)) extends SigmieIndex
        {
            public function name(): string
            {
                return 'orders';
            }

            public function properties(): NewProperties
            {
                $props = new NewProperties;
                $props->date('created_at');
                $props->number('amount');
                $props->category('product');
                $props->keyword('status');

                return $props;
            }
        };

        $tool = new SigmieAnalyticsTool($index);
        $tool->result(new Request([
            'widget' => 'kpi',
            'date_field' => 'created_at',
            'metric' => 'sum',
            'field' => 'amount',
            'from' => '2024-01-01',
            'to' => '2024-02-01',
            'filters' => "status:'paid'",
            'include_hits' => 1,
            'hit_fields' => 'amount,product',
            'hit_sort' => 'amount:desc',
            'hit_limit' => 5,
            'hit_filters' => "product:'A'",
        ]));

        $body = $bodies[0];
        $hits = json_encode($body['post_filter']);
        $query = json_encode($body['query']);

        $this->assertStringContainsString('2024-01-01T00:00:00+00:00', $hits);
        $this->assertStringContainsString('2024-02-01T00:00:00+00:00', $hits);
        $this->assertStringContainsString('"A"', $hits);
        $this->assertStringContainsString('paid', $query);
        $this->assertStringNotContainsString('2024-01-01T00:00:00+00:00', $query);

        $tool->result(new Request([
            'widget' => 'kpi_delta',
            'date_field' => 'created_at',
            'metric' => 'sum',
            'field' => 'amount',
            'from' => '2024-01-01',
            'to' => '2024-02-01',
            'include_hits' => 1,
        ]));

        $delta = $bodies[1];
        $this->assertStringContainsString('2024-01-01T00:00:00+00:00', json_encode($delta['post_filter']));
        $this->assertStringNotContainsString('2024-01-01T00:00:00+00:00', json_encode($delta['query']));
        $this->assertStringContainsString('2023-12-01T00:00:00+00:00', json_encode($delta['aggs']));
    }

    /**
     * @return array{from: string, to: string, timezone: ?string}
     */
    private function kpiBounds(string $from, string $to, int|string|null $offset): array
    {
        $engine = new class implements SearchEngine
        {
            public function engine(): SearchEngineType
            {
                return SearchEngineType::Elasticsearch;
            }

            public function vectorField(BaseVector $field): TypeContract
            {
                return $field;
            }

            public function nestedVectorField(NestedVector $field): TypeContract
            {
                return $field;
            }

            public function indexSettings(): array
            {
                return [];
            }
        };

        $connection = new class($engine) implements ElasticsearchConnection
        {
            public function __construct(private SearchEngine $engine) {}

            public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse
            {
                throw new RuntimeException('no cluster');
            }

            public function promise(ElasticsearchRequest $request): Promise
            {
                throw new RuntimeException('no cluster');
            }

            public function driver(): SearchEngine
            {
                return $this->engine;
            }

            public function isServerless(): bool
            {
                return false;
            }
        };

        $props = new NewProperties;
        $props->date('created_at');

        $index = new class($connection, $props) extends SigmieIndex
        {
            public function __construct(
                private ElasticsearchConnection $connection,
                private NewProperties $props,
            ) {}

            public function name(): string
            {
                return 'sales';
            }

            public function properties(): NewProperties
            {
                return $this->props;
            }

            public function analytics(string $dateField, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): Analytics
            {
                return new class(new NewQuery($this->connection, $this->name()), $this->props, $dateField, $from, $to) extends Analytics
                {
                    public function get(): array
                    {
                        return ['result' => [
                            'from' => $this->from->format(DateTimeInterface::ATOM),
                            'to' => $this->to->format(DateTimeInterface::ATOM),
                            'timezone' => $this->timeZone,
                        ]];
                    }
                };
            }
        };

        $request = [
            'widget' => 'kpi',
            'date_field' => 'created_at',
            'metric' => 'count',
            'from' => $from,
            'to' => $to,
        ];

        if ($offset !== null) {
            $request['timezone_offset'] = $offset;
        }

        return (new SigmieAnalyticsTool($index))->result(new Request($request));
    }
    public function test_literal_field_names_in_analytics_example_json(): void
    {
        $index = $this->createMock(SigmieIndex::class);
        $fields = new NewProperties;
        $fields->number('price"usd');
        $fields->keyword('order\\state');
        $index->method('properties')->willReturn($fields);
        $description = (new SigmieAnalyticsTool($index))->description();
        preg_match('/^- top groups with two metrics: (.+)$/m', $description, $group);
        preg_match('/^- conversion funnel: (.+)$/m', $description, $funnel);

        $metrics = json_decode(json_decode($group[1], true)['metrics'], true, flags: JSON_THROW_ON_ERROR);
        $steps = json_decode(json_decode($funnel[1], true)['steps'], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('price"usd', $metrics[1]['field']);
        $this->assertSame("order\\state:'a'", $steps[0]['filter']);
    }

}
