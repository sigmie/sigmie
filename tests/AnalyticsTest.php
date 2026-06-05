<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Analytics\Enums\Period;
use Sigmie\Analytics\Widgets\Kpi;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Query\Aggregations\Enums\CalendarInterval;
use Sigmie\Query\Queries\Term\Range;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Sigmie;
use Sigmie\SigmieIndex;
use Sigmie\Testing\TestCase;

class AnalyticsTest extends TestCase
{
    private function createSalesIndex(): SigmieIndex
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $props = new NewProperties;
                $props->date('created_at');
                $props->number('amount');
                $props->category('product');

                return $props;
            }
        };

        $index->create();

        $index->merge([
            new Document(['created_at' => '2024-01-01', 'amount' => 100, 'product' => 'A']),
            new Document(['created_at' => '2024-01-01', 'amount' => 50, 'product' => 'B']),
            new Document(['created_at' => '2024-01-02', 'amount' => 200, 'product' => 'A']),
            new Document(['created_at' => '2024-01-03', 'amount' => 30, 'product' => 'B']),
            new Document(['created_at' => '2024-01-03', 'amount' => 70, 'product' => 'A']),
        ], refresh: true);

        return $index;
    }

    private function date(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date, new DateTimeZone('UTC'));
    }

    /**
     * @test
     */
    public function trend_sums_a_metric_per_day(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->trend('revenue', Metric::Sum, 'amount', CalendarInterval::Day)
            ->get();

        $series = $result['revenue']['series'];

        $this->assertCount(3, $series);
        $this->assertEquals(150.0, $series[0]['value']);
        $this->assertEquals(200.0, $series[1]['value']);
        $this->assertEquals(100.0, $series[2]['value']);
    }

    /**
     * @test
     */
    public function trend_rebuckets_per_month(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->trend('revenue', Metric::Sum, 'amount', CalendarInterval::Month)
            ->get();

        $series = $result['revenue']['series'];

        $this->assertCount(1, $series);
        $this->assertEquals(450.0, $series[0]['value']);
        $this->assertSame('Month', $result['revenue']['interval']);
    }

    /**
     * @test
     */
    public function kpi_returns_a_scalar(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->kpi('revenue', Metric::Sum, 'amount')
            ->kpi('distinct_products', Metric::Unique, 'product')
            ->kpi('orders', Metric::Count)
            ->get();

        $this->assertEquals(450.0, $result['revenue']['value']);
        $this->assertEquals(2, $result['distinct_products']['value']);
        $this->assertEquals(5, $result['orders']['value']);
    }

    /**
     * @test
     */
    public function kpi_delta_computes_period_over_period_change(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-02'))
            ->to($this->date('2024-01-04'))
            ->kpiDelta('revenue', Metric::Sum, 'amount')
            ->get();

        $this->assertEquals(300.0, $result['revenue']['value']);
        $this->assertEquals(150.0, $result['revenue']['previous']);
        $this->assertEquals(100.0, $result['revenue']['change_pct']);
    }

    /**
     * @test
     */
    public function breakdown_ranks_top_groups_by_metric(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->breakdown('top_products', 'product', Metric::Sum, 'amount')
            ->get();

        $rows = $result['top_products']['rows'];

        $this->assertSame('A', $rows[0]['key']);
        $this->assertEquals(370.0, $rows[0]['value']);
        $this->assertSame('B', $rows[1]['key']);
        $this->assertEquals(80.0, $rows[1]['value']);
    }

    /**
     * @test
     */
    public function cumulative_runs_a_running_total(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->cumulative('growth', Metric::Sum, 'amount', CalendarInterval::Day)
            ->get();

        $series = $result['growth']['series'];

        $this->assertEquals(150.0, $series[0]['value']);
        $this->assertEquals(350.0, $series[1]['value']);
        $this->assertEquals(450.0, $series[2]['value']);
    }

    /**
     * @test
     */
    public function distribution_buckets_numeric_values(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->distribution('amounts', 'amount', 100)
            ->get();

        $buckets = [];

        foreach ($result['amounts']['buckets'] as $bucket) {
            $buckets[(int) $bucket['label']] = $bucket['count'];
        }

        $this->assertEquals(3, $buckets[0]);
        $this->assertEquals(1, $buckets[100]);
        $this->assertEquals(1, $buckets[200]);
    }

    /**
     * @test
     */
    public function percentiles_returns_values(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->percentiles('amount_spread', 'amount', [50, 95])
            ->get();

        $percentiles = $result['amount_spread']['percentiles'];

        $this->assertArrayHasKey('50', $percentiles);
        $this->assertArrayHasKey('95', $percentiles);
        $this->assertGreaterThan(0, $percentiles['50']);
    }

    /**
     * @test
     */
    public function grouped_trend_splits_a_trend_by_a_dimension(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->groupedTrend('by_product', Metric::Sum, 'amount', 'product', CalendarInterval::Day)
            ->get();

        $groups = [];

        foreach ($result['by_product']['groups'] as $group) {
            $groups[$group['group']] = $group['series'];
        }

        $this->assertArrayHasKey('A', $groups);
        $this->assertArrayHasKey('B', $groups);
        $this->assertEquals(370.0, array_sum(array_column($groups['A'], 'value')));
        $this->assertEquals(80.0, array_sum(array_column($groups['B'], 'value')));
    }

    /**
     * @test
     */
    public function filters_narrow_the_window(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->filters("product:'A'")
            ->kpi('revenue', Metric::Sum, 'amount')
            ->get();

        $this->assertEquals(370.0, $result['revenue']['value']);
    }

    /**
     * @test
     */
    public function filter_query_adds_a_hard_clause(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->filterQuery(new Range('amount', ['>=' => 100]))
            ->kpi('revenue', Metric::Sum, 'amount')
            ->get();

        // amounts >= 100: 100 + 200 = 300
        $this->assertEquals(300.0, $result['revenue']['value']);
    }

    /**
     * @test
     */
    public function a_per_widget_filter_scopes_only_that_widget(): void
    {
        $index = $this->createSalesIndex();

        // A funnel in a single query: every KPI shares the window, but each counts its own slice.
        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->kpi('all_revenue', Metric::Sum, 'amount')
            ->kpi('a_revenue', Metric::Sum, 'amount', filter: new Term('product.keyword', 'A'))
            ->kpi('b_revenue', Metric::Sum, 'amount', filter: new Term('product.keyword', 'B'))
            ->get();

        $this->assertEquals(450.0, $result['all_revenue']['value']);   // 100+50+200+30+70
        $this->assertEquals(370.0, $result['a_revenue']['value']);     // 100+200+70
        $this->assertEquals(80.0, $result['b_revenue']['value']);      // 50+30
    }

    /**
     * @test
     */
    public function a_per_widget_filter_accepts_a_filter_string(): void
    {
        $index = $this->createSalesIndex();

        // Same funnel, but each slice is expressed with the filter DSL instead of a query object.
        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->kpi('all_revenue', Metric::Sum, 'amount')
            ->kpi('a_revenue', Metric::Sum, 'amount', filter: "product:'A'")
            ->kpi('b_revenue', Metric::Sum, 'amount', filter: "product:'B'")
            ->get();

        $this->assertEquals(450.0, $result['all_revenue']['value']);   // 100+50+200+30+70
        $this->assertEquals(370.0, $result['a_revenue']['value']);     // 100+200+70
        $this->assertEquals(80.0, $result['b_revenue']['value']);      // 50+30
    }

    /**
     * @test
     */
    public function a_trend_can_be_scoped_with_a_per_widget_filter(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->trend('a_revenue', Metric::Sum, 'amount', CalendarInterval::Day, filter: "product:'A'")
            ->get();

        $series = $result['a_revenue']['series'];

        $this->assertEquals(100.0, $series[0]['value']);   // 2024-01-01: only A
        $this->assertEquals(200.0, $series[1]['value']);   // 2024-01-02: A
        $this->assertEquals(70.0, $series[2]['value']);    // 2024-01-03: A (B's 30 excluded)
    }

    /**
     * @test
     */
    public function a_breakdown_can_be_scoped_with_a_per_widget_filter(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->breakdown('top_products', 'product', Metric::Sum, 'amount', filter: new Term('product.keyword', 'A'))
            ->get();

        $rows = $result['top_products']['rows'];

        $this->assertCount(1, $rows);            // B is filtered out entirely
        $this->assertSame('A', $rows[0]['key']);
        $this->assertEquals(370.0, $rows[0]['value']);
    }

    /**
     * @test
     */
    public function a_kpi_delta_filter_applies_to_both_windows(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-02'))
            ->to($this->date('2024-01-04'))
            ->kpiDelta('a_revenue', Metric::Sum, 'amount', filter: "product:'A'")
            ->get();

        $this->assertEquals(270.0, $result['a_revenue']['value']);        // current window, A only: 200+70
        $this->assertEquals(100.0, $result['a_revenue']['previous']);     // previous window, A only: 100
        $this->assertEquals(170.0, $result['a_revenue']['change_pct']);
    }

    /**
     * @test
     */
    public function the_unique_metric_asks_for_an_accurate_distinct_count(): void
    {
        $widget = new Kpi('distinct', 'created_at', $this->date('2024-01-01'), $this->date('2024-01-04'), 'Y-m-d', Metric::Unique, 'product');

        $cardinality = $widget->toRaw()['distinct']['aggs']['metric']['cardinality'];

        $this->assertEquals('product', $cardinality['field']);
        $this->assertEquals(40000, $cardinality['precision_threshold']);
    }

    private function createTimedIndex(array $docs): SigmieIndex
    {
        $index = new class($this->sigmie) extends SigmieIndex
        {
            protected string $indexName;

            public function __construct(Sigmie $sigmie)
            {
                parent::__construct($sigmie);
                $this->indexName = uniqid();
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $props = new NewProperties;
                $props->datetime('created_at');
                $props->number('amount');

                return $props;
            }
        };

        $index->create();
        $index->merge(array_map(fn (array $d): Document => new Document($d), $docs), refresh: true);

        return $index;
    }

    private function labelOfValue(array $series, float $value): string
    {
        foreach ($series as $bucket) {
            if ((float) $bucket['value'] === $value) {
                return $bucket['label'];
            }
        }

        return '';
    }

    /**
     * @test
     */
    public function timezone_offset_shifts_the_day_bucket(): void
    {
        // 16:00 UTC on May 1 is 01:00 on May 2 in Tokyo (+09:00).
        $index = $this->createTimedIndex([
            ['created_at' => '2024-05-01T16:00:00Z', 'amount' => 100],
        ]);

        $utc = $index->analytics('created_at')
            ->from($this->date('2024-04-30'))->to($this->date('2024-05-03'))
            ->trend('s', Metric::Sum, 'amount', CalendarInterval::Day)
            ->get();

        $tokyo = $index->analytics('created_at')
            ->timezoneOffset(540)
            ->from($this->date('2024-04-30'))->to($this->date('2024-05-03'))
            ->trend('s', Metric::Sum, 'amount', CalendarInterval::Day)
            ->get();

        $this->assertStringStartsWith('2024-05-01', $this->labelOfValue($utc['s']['series'], 100.0));
        $this->assertStringStartsWith('2024-05-02', $this->labelOfValue($tokyo['s']['series'], 100.0));
    }

    /**
     * @test
     */
    public function range_uses_a_named_period(): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $index = $this->createTimedIndex([
            ['created_at' => $now->format('Y-m-d\TH:i:s\Z'), 'amount' => 100],
            ['created_at' => $now->modify('-2 years')->format('Y-m-d\TH:i:s\Z'), 'amount' => 999],
        ]);

        $result = $index->analytics('created_at')
            ->range(Period::ThisMonth)
            ->kpi('revenue', Metric::Sum, 'amount')
            ->get();

        // Only the current-month doc is in range; the 2-years-ago doc is excluded.
        $this->assertEquals(100.0, $result['revenue']['value']);
    }

    /**
     * @test
     */
    public function calendar_period_makes_delta_compare_to_previous_instance(): void
    {
        $utc = new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $utc);
        $lastMonth = (new DateTimeImmutable('first day of last month', $utc))->setTime(12, 0);

        $index = $this->createTimedIndex([
            ['created_at' => $now->format('Y-m-d\TH:i:s\Z'), 'amount' => 100],
            ['created_at' => $lastMonth->format('Y-m-d\TH:i:s\Z'), 'amount' => 40],
        ]);

        $result = $index->analytics('created_at')
            ->range(Period::ThisMonth)
            ->kpiDelta('revenue', Metric::Sum, 'amount')
            ->get();

        $this->assertEquals(100.0, $result['revenue']['value']);
        $this->assertEquals(40.0, $result['revenue']['previous']);   // last calendar month, not equal-duration
        $this->assertEquals(150.0, $result['revenue']['change_pct']);
    }

    /**
     * @test
     */
    public function analytics_can_be_built_from_the_sigmie_facade(): void
    {
        $index = $this->createSalesIndex();

        // Lower-level entry point: same builder, properties passed explicitly so the keyword
        // field ('product') resolves and the typed filter DSL works.
        $result = $this->sigmie->analytics($index->name(), 'created_at')
            ->properties($index->properties())
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->breakdown('top', 'product', Metric::Sum, 'amount')
            ->get();

        $this->assertSame('A', $result['top']['rows'][0]['key']);
        $this->assertEquals(370.0, $result['top']['rows'][0]['value']);
    }

    /**
     * @test
     */
    public function period_resolves_calendar_boundaries(): void
    {
        $now = new DateTimeImmutable('2024-05-15 10:00:00', new DateTimeZone('+09:00'));

        [$from, $to] = Period::ThisMonth->resolve($now);
        $this->assertSame('2024-05-01T00:00:00+09:00', $from->format('Y-m-d\TH:i:sP'));
        $this->assertSame('2024-06-01T00:00:00+09:00', $to->format('Y-m-d\TH:i:sP'));

        // ISO week: 2024-05-15 is a Wednesday → Monday the 13th.
        [$from] = Period::ThisWeek->resolve($now);
        $this->assertSame('2024-05-13T00:00:00+09:00', $from->format('Y-m-d\TH:i:sP'));

        $this->assertSame('-1 month', Period::ThisMonth->previousModifier());
        $this->assertNull(Period::Last7Days->previousModifier());
    }

    /**
     * The sales index only has January data; a trend over Jan→Jun must still return a bucket per
     * month, with the empty months (Feb–May) zero-filled — not trimmed at the last bucket with data.
     *
     * @test
     */
    public function trend_zero_fills_the_full_requested_window(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-06-01'))
            ->trend('revenue', Metric::Sum, 'amount', CalendarInterval::Month)
            ->get();

        $series = $result['revenue']['series'];

        $this->assertCount(5, $series); // Jan, Feb, Mar, Apr, May — not just January
        $this->assertEquals(450.0, $series[0]['value']);
        $this->assertEquals(0.0, $series[1]['value']);
        $this->assertEquals(0.0, $series[2]['value']);
        $this->assertEquals(0.0, $series[3]['value']);
        $this->assertEquals(0.0, $series[4]['value']);
    }

    /**
     * A cumulative curve over a wider-than-data window flattens across the empty tail instead of
     * stopping in January.
     *
     * @test
     */
    public function cumulative_zero_fills_the_full_requested_window(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-06-01'))
            ->cumulative('growth', Metric::Sum, 'amount', CalendarInterval::Month)
            ->get();

        $series = $result['growth']['series'];

        $this->assertCount(5, $series);
        $this->assertEquals(450.0, $series[0]['value']);
        $this->assertEquals(450.0, $series[4]['value']); // running total holds across the empty months
    }

    /**
     * Every group's series in a grouped trend spans the full window with zero-filled empty months.
     *
     * @test
     */
    public function grouped_trend_zero_fills_each_group_across_the_full_window(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-06-01'))
            ->groupedTrend('by_product', Metric::Sum, 'amount', 'product', CalendarInterval::Month, 10)
            ->get();

        $groups = [];
        foreach ($result['by_product']['groups'] as $group) {
            $groups[$group['group']] = $group['series'];
        }

        $this->assertCount(5, $groups['A']);
        $this->assertCount(5, $groups['B']);
        $this->assertEquals(370.0, $groups['A'][0]['value']);
        $this->assertEquals(80.0, $groups['B'][0]['value']);
        $this->assertEquals(0.0, $groups['A'][4]['value']);
        $this->assertEquals(0.0, $groups['B'][4]['value']);
    }

    /**
     * Build a throwaway index from a properties definition and a set of documents.
     *
     * @param  callable(NewProperties): void  $defineProperties
     * @param  list<Document>  $documents
     */
    private function indexWith(callable $defineProperties, array $documents): SigmieIndex
    {
        $index = new class($this->sigmie, $defineProperties) extends SigmieIndex
        {
            protected string $indexName;

            /** @var callable(NewProperties): void */
            protected $define;

            public function __construct(Sigmie $sigmie, callable $define)
            {
                parent::__construct($sigmie);

                $this->indexName = uniqid();
                $this->define = $define;
            }

            public function name(): string
            {
                return $this->indexName;
            }

            public function properties(): NewProperties
            {
                $props = new NewProperties;

                ($this->define)($props);

                return $props;
            }
        };

        $index->create();
        $index->merge($documents, refresh: true);

        return $index;
    }

    /**
     * @test
     */
    public function table_returns_the_matching_documents(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->table('recent', fields: ['amount', 'product'], limit: 2, sort: 'amount:desc')
            ->get();

        $rows = $result['recent']['rows'];

        $this->assertSame('table', $result['recent']['type']);
        $this->assertCount(2, $rows);
        $this->assertEquals(200, $rows[0]['document']['amount']);
        $this->assertSame('A', $rows[0]['document']['product']);
        $this->assertEquals(100, $rows[1]['document']['amount']);
        $this->assertNotNull($rows[0]['id']);
    }

    /**
     * @test
     */
    public function stats_summarises_a_numeric_field(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->stats('order_size', 'amount')
            ->get();

        $stats = $result['order_size'];

        $this->assertSame('stats', $stats['type']);
        $this->assertEquals(5, $stats['count']);
        $this->assertEquals(30.0, $stats['min']);
        $this->assertEquals(200.0, $stats['max']);
        $this->assertEquals(450.0, $stats['sum']);
        $this->assertEquals(90.0, $stats['avg']);
    }

    /**
     * @test
     */
    public function funnel_reports_step_conversions(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->category('event');
        }, [
            new Document(['created_at' => '2024-01-01', 'event' => 'visit']),
            new Document(['created_at' => '2024-01-01', 'event' => 'visit']),
            new Document(['created_at' => '2024-01-02', 'event' => 'visit']),
            new Document(['created_at' => '2024-01-02', 'event' => 'visit']),
            new Document(['created_at' => '2024-01-02', 'event' => 'signup']),
            new Document(['created_at' => '2024-01-03', 'event' => 'signup']),
            new Document(['created_at' => '2024-01-03', 'event' => 'paid']),
        ]);

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->funnel('signup', [
                'visited' => "event:'visit'",
                'signed' => "event:'signup'",
                'paid' => "event:'paid'",
            ])
            ->get();

        $steps = $result['signup']['steps'];

        $this->assertSame('funnel', $result['signup']['type']);
        $this->assertSame(['visited', 'signed', 'paid'], array_column($steps, 'label'));
        $this->assertEquals([4, 2, 1], array_column($steps, 'count'));
        $this->assertEquals(1.0, $steps[0]['conversion']);
        $this->assertEquals(0.5, $steps[1]['conversion']);
        $this->assertEquals(0.25, $steps[2]['conversion']);
        $this->assertEquals(0.5, $steps[2]['step_conversion']);
    }

    /**
     * @test
     */
    public function heatmap_crosses_two_dimensions(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->category('region');
            $props->category('device');
        }, [
            new Document(['created_at' => '2024-01-01', 'region' => 'US', 'device' => 'mobile']),
            new Document(['created_at' => '2024-01-01', 'region' => 'US', 'device' => 'mobile']),
            new Document(['created_at' => '2024-01-02', 'region' => 'US', 'device' => 'desktop']),
            new Document(['created_at' => '2024-01-02', 'region' => 'EU', 'device' => 'mobile']),
        ]);

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->heatmap('regions', 'region', 'device')
            ->get();

        $cells = [];
        foreach ($result['regions']['rows'] as $row) {
            foreach ($row['cells'] as $cell) {
                $cells[sprintf('%s/%s', $row['key'], $cell['key'])] = $cell['count'];
            }
        }

        $this->assertSame('heatmap', $result['regions']['type']);
        $this->assertEquals(2, $cells['US/mobile']);
        $this->assertEquals(1, $cells['US/desktop']);
        $this->assertEquals(1, $cells['EU/mobile']);
    }

    /**
     * @test
     */
    public function retention_grids_cohorts_by_period(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->date('signup_at');
            $props->category('user');
        }, [
            new Document(['created_at' => '2024-01-01', 'signup_at' => '2024-01-01', 'user' => 'u1']),
            new Document(['created_at' => '2024-01-02', 'signup_at' => '2024-01-01', 'user' => 'u1']),
            new Document(['created_at' => '2024-01-01', 'signup_at' => '2024-01-01', 'user' => 'u2']),
            new Document(['created_at' => '2024-01-02', 'signup_at' => '2024-01-02', 'user' => 'u3']),
        ]);

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-03'))
            ->retention('cohorts', 'signup_at', 'user', CalendarInterval::Day)
            ->get();

        $cohorts = [];
        foreach ($result['cohorts']['cohorts'] as $cohort) {
            $periods = [];
            foreach ($cohort['periods'] as $period) {
                $periods[substr($period['label'], 0, 10)] = $period['value'];
            }

            $cohorts[substr($cohort['cohort'], 0, 10)] = ['size' => $cohort['size'], 'periods' => $periods];
        }

        $this->assertSame('retention', $result['cohorts']['type']);
        $this->assertEquals(2, $cohorts['2024-01-01']['size']);
        $this->assertEquals(2, $cohorts['2024-01-01']['periods']['2024-01-01']);
        $this->assertEquals(1, $cohorts['2024-01-01']['periods']['2024-01-02']);
        $this->assertEquals(1, $cohorts['2024-01-02']['size']);
        $this->assertEquals(1, $cohorts['2024-01-02']['periods']['2024-01-02']);
    }

    /**
     * @test
     */
    public function geo_buckets_points_into_cells(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->geoPoint('location');
        }, [
            new Document(['created_at' => '2024-01-01', 'location' => ['lat' => 52.37, 'lon' => 4.90]]),
            new Document(['created_at' => '2024-01-01', 'location' => ['lat' => 52.37, 'lon' => 4.90]]),
            new Document(['created_at' => '2024-01-02', 'location' => ['lat' => 40.71, 'lon' => -74.00]]),
        ]);

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->geo('areas', 'location', precision: 5)
            ->get();

        $buckets = $result['areas']['buckets'];

        $this->assertSame('geo', $result['areas']['type']);
        $this->assertCount(2, $buckets);
        $this->assertEquals(2, $buckets[0]['count']);
        $this->assertEquals(1, $buckets[1]['count']);
    }

    /**
     * @test
     */
    public function a_widget_can_override_its_window(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->kpi('all_time', Metric::Sum, 'amount')
            ->kpi('first_day', Metric::Sum, 'amount')->over($this->date('2024-01-01'), $this->date('2024-01-02'))
            ->get();

        $this->assertEquals(450.0, $result['all_time']['value']);
        $this->assertEquals(150.0, $result['first_day']['value']);
    }

    /**
     * @test
     */
    public function analytics_can_ride_in_a_multi_search(): void
    {
        $index = $this->createSalesIndex();
        $name = $index->name();

        $analytics = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->kpi('revenue', Metric::Sum, 'amount')
            ->breakdown('top_products', 'product', Metric::Sum, 'amount');

        $multi = $this->sigmie->newMultiSearch();
        $multi->addQuery($analytics->toSearch(), 'metrics');
        $multi->newQuery($name, 'rows')->matchAll()->size(2);

        [$metrics, $rows] = $multi->get();

        $dashboard = $analytics->formatResponse($metrics);

        $this->assertEquals(450.0, $dashboard['revenue']['value']);
        $this->assertSame('A', $dashboard['top_products']['rows'][0]['key']);
        $this->assertCount(2, $rows['hits']['hits']);
    }
}
