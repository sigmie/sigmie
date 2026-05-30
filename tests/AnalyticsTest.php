<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Query\Aggregations\Enums\CalendarInterval;
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
}
