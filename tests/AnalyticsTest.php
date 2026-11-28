<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Http\Promise\Promise;
use InvalidArgumentException;
use RuntimeException;
use Sigmie\Analytics\Analytics;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Analytics\Enums\Period;
use Sigmie\Analytics\Widgets\Cumulative;
use Sigmie\Analytics\Widgets\GroupedMetrics;
use Sigmie\Analytics\Widgets\HistogramMetric;
use Sigmie\Analytics\Widgets\Kpi;
use Sigmie\Analytics\Widgets\MultiBreakdown;
use Sigmie\Analytics\Widgets\Retention;
use Sigmie\Analytics\Widgets\Trend;
use Sigmie\Analytics\WindowBound;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Document\Document;
use Sigmie\Mappings\Field;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Query\Aggregations\Enums\CalendarInterval;
use Sigmie\Query\NewQuery;
use Sigmie\Query\Queries\Term\Range;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Search;
use Sigmie\Sigmie;
use Sigmie\SigmieIndex;
use Sigmie\Testing\TestCase;

class AnalyticsTest extends TestCase
{
    public function test_out_of_range_integer_alias_is_a_display_label(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('time');
            $props->integer('code');
        }, [new Document(['time' => '2024-01-01', 'code' => 1])]);

        $rows = $index->analytics('time', $this->date('2024-01-01'), $this->date('2024-01-02'))
            ->groupedMetrics('codes', 'code', [], bucketAliases: ['99999999999999' => [1]], aliasesOnly: true)
            ->get()['codes']['rows'];

        $this->assertSame('99999999999999', $rows[0]['key']);
        $this->assertSame(1, $rows[0]['count']);
    }

    public function test_a_date_grouping_reports_dates_as_keys(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->datetime('created_at');
            $props->date('day');
        }, [
            new Document(['created_at' => '2024-01-01T03:00:00Z', 'day' => '2024-01-02']),
            new Document(['created_at' => '2024-01-01T04:00:00Z', 'day' => '2024-01-02']),
            new Document(['created_at' => '2024-01-01T05:00:00Z', 'day' => '2024-01-01']),
        ]);

        $result = $index->analytics('created_at', $this->date('2024-01-01'), $this->date('2024-01-02'))
            ->breakdown('breakdown', 'day', Metric::Count)
            ->heatmap('heatmap', 'day', 'day')
            ->groupedTrend('grouped_trend', Metric::Count, '', 'day')
            ->get();

        $this->assertSame(['2024-01-02T00:00:00.000Z', '2024-01-01T00:00:00.000Z'], array_column($result['breakdown']['rows'], 'key'));
        $this->assertSame(['2024-01-02T00:00:00.000Z', '2024-01-01T00:00:00.000Z'], array_column($result['heatmap']['rows'], 'key'));
        $this->assertSame(['2024-01-02T00:00:00.000Z'], array_column($result['heatmap']['rows'][0]['cells'], 'key'));
        $this->assertSame(['2024-01-02T00:00:00.000Z', '2024-01-01T00:00:00.000Z'], array_column($result['grouped_trend']['groups'], 'group'));
    }

    public function test_a_date_only_timeline_counts_local_days_west_of_utc(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('day');
            $props->number('amount');
        }, [
            new Document(['day' => '2023-12-31', 'amount' => 1]),
            new Document(['day' => '2024-01-01', 'amount' => 10]),
            new Document(['day' => '2024-01-31', 'amount' => 100]),
            new Document(['day' => '2024-02-01', 'amount' => 1000]),
        ]);
        $zone = new DateTimeZone('America/New_York');

        $result = $index->analytics('day', new DateTimeImmutable('2024-01-01', $zone), new DateTimeImmutable('2024-02-01', $zone))
            ->timezone('America/New_York')
            ->kpiDelta('revenue', Metric::Sum, 'amount')
            ->trend('daily', Metric::Sum, 'amount', CalendarInterval::Day, minDocCount: 1)
            ->get();

        $this->assertEquals(['value' => 110, 'previous' => 1], array_intersect_key($result['revenue'], ['value' => 0, 'previous' => 0]));
        $this->assertEquals(['2024-01-01T00:00:00.000Z' => 10, '2024-01-31T00:00:00.000Z' => 100], array_column($result['daily']['series'], 'value', 'label'));
    }

    public function test_offset_conversion_preserves_nanosecond_window(): void
    {
        $index = $this->indexWith(fn (NewProperties $props): NewProperties => $props->add(new Field('time', 'date_nanos')), [
            new Document(['time' => '2024-01-01T00:00:00.000000001Z']),
        ]);
        $zone = new DateTimeZone('+00:00:30');
        $from = WindowBound::fromIso('2024-01-01T00:00:00.000000001Z')->setTimezone($zone);
        $to = WindowBound::fromIso('2024-01-01T00:00:00.000000002Z')->setTimezone($zone);

        $result = $index->analytics('time', $from, $to)->kpi('events', Metric::Count)->get();

        $this->assertSame(1, $result['events']['value']);
    }

    public function test_cumulative_overflow_returns_a_null_point(): void
    {
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'date'))
            ->cumulative('running', Metric::Sum, 'amount');

        $result = $analytics->formatResponse(['aggregations' => [
            'running' => ['series' => ['buckets' => [
                ['key_as_string' => '2024-01-01', 'cumulative' => ['value' => 1e308]],
                ['key_as_string' => '2024-01-02', 'cumulative' => ['value' => 'Infinity']],
            ]]],
        ]]);

        $this->assertSame([1e308, null], array_column($result['running']['series'], 'value'));
    }

    public function test_numeric_union_combines_equivalent_integer_and_decimal_terms(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->integer('first');
            $props->double('second');
        }, [
            new Document(['created_at' => '2024-01-01', 'second' => 2.5]),
            new Document(['created_at' => '2024-01-01', 'first' => 2]),
            new Document(['created_at' => '2024-01-01', 'second' => 2.0]),
            new Document(['created_at' => '2024-01-01', 'first' => 2, 'second' => 2.0]),
        ]);

        $result = $index->analytics('created_at', $this->date('2024-01-01'), $this->date('2024-01-02'))
            ->unionBreakdown('groups', ['first', 'second'], Metric::Count)
            ->get();

        $this->assertSame(['2', '2.5'], array_column($result['groups']['rows'], 'key'));
        $this->assertSame([3, 1], array_column($result['groups']['rows'], 'count'));
    }

    public function test_count_uses_the_text_field_even_if_its_keyword_is_ignored(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->category('group');
            $props->category('message')->field(new Field('keyword', 'keyword', ['ignore_above' => 3]));
        }, [
            new Document(['created_at' => '2024-01-01', 'group' => 'a', 'message' => 'a long message']),
            new Document(['created_at' => '2024-01-01', 'group' => 'a', 'message' => 'ok']),
            new Document(['created_at' => '2024-01-01', 'group' => 'a']),
        ]);

        $result = $index->analytics('created_at', $this->date('2024-01-01'), $this->date('2024-01-02'))
            ->kpi('count', Metric::Count, 'message')
            ->groupedMetrics('groups', 'group', [['key' => 'count', 'metric' => Metric::Count, 'field' => 'message']])
            ->get();

        $this->assertSame(2, $result['count']['value']);
        $this->assertSame(2, $result['groups']['rows'][0]['value']);
    }

    public function test_aliases_exclude_terms_normalized_by_a_text_keyword_subfield(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->category('group')->field(new Keyword('keyword'));
        }, [
            new Document(['created_at' => '2024-01-01', 'group' => 'A']),
            new Document(['created_at' => '2024-01-01', 'group' => 'b']),
        ]);

        $result = $index->analytics('created_at', $this->date('2024-01-01'), $this->date('2024-01-02'))
            ->breakdown('groups', 'group', Metric::Count, bucketAliases: ['Combined' => ['A']])
            ->groupedMetrics('grouped', 'group', [], bucketAliases: ['Combined' => ['A']])
            ->get();

        foreach ($result as $widget) {
            $this->assertEqualsCanonicalizing(['Combined', 'b'], array_column($widget['rows'], 'key'));
        }
    }

    public function test_table_and_hits_keep_numeric_source_field_names(): void
    {
        $properties = new NewProperties;
        $properties->keyword('0');
        $properties->object('details', fn (NewProperties $details): Keyword => $details->keyword('0'));

        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), $properties, 'date'))
            ->table('orders');
        $document = ['_id' => '1', '_source' => ['0' => 'paid', 'details' => ['0' => 'online']]];

        $result = $analytics->formatResponseWithHits([
            'aggregations' => ['orders' => ['hits' => ['hits' => ['hits' => [$document]]]]],
            'hits' => ['hits' => [$document, ['_id' => '2', '_source' => ['0' => 'paid']]]],
        ]);

        $expected = '{"0":"paid","details":{"0":"online"}}';
        $this->assertSame($expected, json_encode($result['widgets']['orders']['rows'][0]['document']));
        $this->assertSame($expected, json_encode($result['hits']['hits'][0]['_source']));
        $this->assertSame('{"0":"paid"}', json_encode($result['hits']['hits'][1]['_source']));
    }

    public function test_histogram_metric_does_not_request_empty_numeric_buckets(): void
    {
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'date'))
            ->histogramMetric('ratings', 'price', 1, Metric::Avg, 'rating');

        $histogram = $analytics->toMultiSearch()[1]['aggs']['ratings']['aggs']['buckets']['histogram'];

        $this->assertSame(['field' => 'price', 'interval' => 1, 'min_doc_count' => 1], $histogram);
    }

    public function test_histogram_metric_keeps_occupied_buckets_with_no_metric_value(): void
    {
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'date'))
            ->histogramMetric('ratings', 'price', 10, Metric::Avg, 'rating');
        $result = $analytics->formatResponse(['aggregations' => [
            'ratings' => ['buckets' => ['buckets' => [
                ['key' => 0, 'doc_count' => 2, 'metric' => ['value' => null]],
                ['key' => 10, 'doc_count' => 0, 'metric' => ['value' => null]],
                ['key' => 20, 'doc_count' => 3, 'metric' => ['value' => 4.5]],
            ]]],
        ]]);

        $this->assertSame([
            ['label' => 0, 'value' => null, 'count' => 2],
            ['label' => 20, 'value' => 4.5, 'count' => 3],
        ], $result['ratings']['series']);
        $this->assertSame([0, 20], array_column($result['ratings']['rows'], 'key'));
    }

    public function test_boolean_terms_keys_retain_their_type_in_chart_results(): void
    {
        $properties = new NewProperties;
        $properties->bool('active');

        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), $properties, 'date'))
            ->breakdown('rank', 'active', Metric::Count)
            ->groupedTrend('trends', Metric::Count, '', 'active')
            ->heatmap('matrix', 'active', 'active');
        $buckets = [
            ['key' => 0, 'key_as_string' => 'false', 'doc_count' => 3, 'metric' => ['doc_count' => 3]],
            ['key' => 1, 'key_as_string' => 'true', 'doc_count' => 1, 'metric' => ['doc_count' => 1]],
        ];

        $result = $analytics->formatResponse(['aggregations' => [
            'rank' => ['groups' => ['buckets' => $buckets]],
            'trends' => ['groups' => ['buckets' => $buckets]],
            'matrix' => ['rows' => ['buckets' => [[...$buckets[0], 'cols' => ['buckets' => [$buckets[1]]]]]]],
        ]]);

        $this->assertSame([false, true], array_column($result['rank']['rows'], 'key'));
        $this->assertSame([false, true], array_column($result['trends']['groups'], 'group'));
        $this->assertFalse($result['matrix']['rows'][0]['key']);
        $this->assertTrue($result['matrix']['rows'][0]['cells'][0]['key']);
    }

    public function test_named_count_counts_every_value_and_default_count_counts_events(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->keyword('group');
            $props->integer('amount');
        }, [
            new Document(['created_at' => ['2024-01-01', '2024-01-02'], 'group' => 'a', 'amount' => [2, 2, 3]]),
            new Document(['created_at' => '2024-01-01', 'group' => 'b', 'amount' => 4]),
            new Document(['created_at' => '2024-01-01', 'group' => 'b', 'amount' => 5]),
        ]);

        try {
            $result = $index->analytics('created_at', $this->date('2024-01-01'), $this->date('2024-01-02'))
                ->kpi('events', Metric::Count)
                ->kpi('values', Metric::Count, 'amount')
                ->kpi('dates', Metric::Count, 'created_at')
                ->breakdown('groups', 'group', Metric::Count, 'amount', limit: 1)
                ->groupedMetrics('grouped', 'group', [['key' => 'values', 'metric' => Metric::Count, 'field' => 'amount']], 'values', limit: 1)
                ->cumulative('cumulative', Metric::Count, 'amount')
                ->get();

            $this->assertEquals(3, $result['events']['value']);
            $this->assertEquals(5, $result['values']['value']);
            $this->assertEquals(4, $result['dates']['value']);
            $this->assertSame('a', $result['groups']['rows'][0]['key']);
            $this->assertSame('a', $result['grouped']['rows'][0]['key']);
            $this->assertEquals([5], array_column($result['cumulative']['series'], 'value'));
        } finally {
            $index->delete();
        }
    }

    public function test_tied_native_buckets_keep_their_mapping_order(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->caseSensitiveKeyword('code');
            $props->integer('number');
        }, [
            new Document(['created_at' => '2024-01-01', 'code' => '2', 'number' => 10]),
            new Document(['created_at' => '2024-01-01', 'code' => '10', 'number' => 2]),
        ]);

        try {
            $result = $index->analytics('created_at', $this->date('2024-01-01'), $this->date('2024-01-02'))
                ->breakdown('codes', 'code', Metric::Count)
                ->groupedMetrics('grouped_codes', 'code', [])
                ->groupedMetrics('numbers', 'number', [])
                ->get();

            $this->assertSame(['10', '2'], array_column($result['codes']['rows'], 'key'));
            $this->assertSame(['10', '2'], array_column($result['grouped_codes']['rows'], 'key'));
            $this->assertSame(['2', '10'], array_column($result['numbers']['rows'], 'key'));
        } finally {
            $index->delete();
        }
    }

    public function test_ranked_metrics_put_groups_with_values_before_missing_groups(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->keyword('group');
            $props->keyword('other');
            $props->integer('amount');
        }, [
            new Document(['created_at' => '2024-01-01', 'group' => 'missing', 'other' => 'm']),
            new Document(['created_at' => '2024-01-01', 'group' => 'present', 'other' => 'p', 'amount' => 7]),
        ]);

        try {
            $result = $index->analytics('created_at', $this->date('2024-01-01'), $this->date('2024-01-02'))
                ->breakdown('highest_min', 'group', Metric::Min, 'amount', limit: 1)
                ->breakdown('highest_avg', 'group', Metric::Avg, 'amount', limit: 1)
                ->groupedMetrics('lowest_max', 'group', [['key' => 'biggest', 'metric' => Metric::Max, 'field' => 'amount']], 'biggest', limit: 1, direction: 'asc')
                ->multiBreakdown('lowest_pair', ['group', 'other'], Metric::Max, 'amount', limit: 1, direction: 'asc')
                ->unionBreakdown('highest_union', ['group', 'other'], Metric::Median, 'amount', limit: 1)
                ->get();

            $this->assertSame('present', $result['highest_min']['rows'][0]['key']);
            $this->assertSame('present', $result['highest_avg']['rows'][0]['key']);
            $this->assertSame('present', $result['lowest_max']['rows'][0]['key']);
            $this->assertSame(['present', 'p'], $result['lowest_pair']['rows'][0]['key_values']);
            $this->assertSame('p', $result['highest_union']['rows'][0]['key']);
        } finally {
            $index->delete();
        }
    }

    public function test_histogram_keeps_populated_bands_with_a_missing_metric(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->price();
            $props->integer('rating');
        }, [
            new Document(['created_at' => '2024-01-01', 'price' => 5]),
            new Document(['created_at' => '2024-01-01', 'price' => 25, 'rating' => 4]),
        ]);

        try {
            $series = $index->analytics('created_at', $this->date('2024-01-01'), $this->date('2024-01-02'))
                ->histogramMetric('ratings', 'price', 10, Metric::Avg, 'rating')
                ->get()['ratings']['series'];

            $this->assertEquals([
                ['label' => 0, 'value' => null, 'count' => 1],
                ['label' => 20, 'value' => 4, 'count' => 1],
            ], $series);
        } finally {
            $index->delete();
        }
    }

    public function test_millisecond_timeline_bounds_keep_the_same_documents_in_hits_and_widgets(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->datetime('created_at');
            $props->integer('amount');
        }, [
            new Document(['created_at' => '2024-01-01T00:00:00.000Z', 'amount' => 1]),
            new Document(['created_at' => '2024-01-01T00:00:00.001Z', 'amount' => 2]),
        ]);

        try {
            $analytics = $index->analytics('created_at', $this->date('2024-01-01T00:00:00.000100Z'), $this->date('2024-01-01T00:00:00.001100Z'));
            $result = $analytics->kpi('total', Metric::Sum, 'amount')
                ->trend('trend', Metric::Sum, 'amount', '1ms')
                ->search(fn (Search $search): Search => $search->size(10)->postFilter($analytics->hitsQuery('')))
                ->getWithHits();

            $this->assertEquals(2, $result['widgets']['total']['value']);
            $this->assertEquals([0, 2], array_column($result['widgets']['trend']['series'], 'value'));
            $this->assertSame([2], array_column(array_column($result['hits']['hits'], '_source'), 'amount'));
        } finally {
            $index->delete();
        }
    }

    public function test_stats_keep_finite_extrema_when_sum_and_average_overflow(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->double('amount');
        }, [
            new Document(['created_at' => '2024-01-01', 'amount' => 1e308]),
            new Document(['created_at' => '2024-01-01', 'amount' => 1e308]),
        ]);

        try {
            $stats = $index->analytics('created_at', $this->date('2024-01-01'), $this->date('2024-01-02'))
                ->stats('summary', 'amount')
                ->get()['summary'];

            $this->assertSame(2, $stats['count']);
            $this->assertSame(1e308, $stats['min']);
            $this->assertSame(1e308, $stats['max']);
            $this->assertNull($stats['avg']);
            $this->assertNull($stats['sum']);
        } finally {
            $index->delete();
        }
    }

    public function test_analytics_accepts_an_expanded_year_at_the_window_boundary(): void
    {
        $index = $this->indexWith(fn (NewProperties $props): Date => $props->date('created_at'), [
            new Document(['created_at' => '9999-12-31']),
        ]);

        try {
            $result = $index->analytics('created_at', $this->date('9999-12-31'), $this->date('+10000-01-02'))
                ->kpi('events', Metric::Count)
                ->trend('days', Metric::Count)
                ->get();

            $this->assertSame(1, $result['events']['value']);
            $this->assertSame([1, 0], array_column($result['days']['series'], 'value'));
        } finally {
            $index->delete();
        }
    }

    public function test_an_overflowed_metric_has_no_finite_value(): void
    {
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'date'))
            ->kpi('total', Metric::Sum, 'amount');
        $result = $analytics->formatResponse(['aggregations' => [
            'total' => ['doc_count' => 2, 'metric' => ['value' => 'Infinity']],
        ]]);

        $this->assertNull($result['total']['value']);
        $this->assertSame(2, $result['total']['count']);
    }

    public function test_hit_windows_are_recompiled_after_window_changes(): void
    {
        $analytics = new Analytics(
            new NewQuery($this->disconnectedConnection(), 'sales'),
            new NewProperties,
            'date',
            $this->date('2024-01-01'),
            $this->date('2024-02-01'),
        );
        $analytics->search(fn (Search $search): Search => $search->postFilter($analytics->hitsQuery('')));

        $body = fn (): array => $analytics->toMultiSearch()[1];

        $this->assertSame('2024-01-01T00:00:00+00:00', $body()['post_filter']['range']['date']['gte']);
        $analytics->from($this->date('2024-01-02'));
        $this->assertSame('2024-01-02T00:00:00+00:00', $body()['post_filter']['range']['date']['gte']);
        $analytics->to($this->date('2024-01-31'));
        $this->assertSame('2024-01-31T00:00:00+00:00', $body()['post_filter']['range']['date']['lt']);
        $analytics->format('Y-m-d');
        $this->assertSame('2024-01-31', $body()['post_filter']['range']['date']['lt']);
        $analytics->range(Period::ThisMonth);
        $this->assertSame($analytics->hitsQuery('')->toRaw(), $body()['post_filter']);
    }

    public function test_breakdown_excludes_normalized_keyword_alias_terms(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->keyword('group');
            $props->caseSensitiveKeyword('code');
        }, [
            new Document(['created_at' => '2024-01-01', 'group' => 'ΟΣ', 'code' => 'ΟΣ']),
            new Document(['created_at' => '2024-01-01', 'group' => 'οσ', 'code' => 'οσ']),
            new Document(['created_at' => '2024-01-01', 'group' => 'other', 'code' => 'other']),
        ]);
        $result = $index->analytics('created_at', $this->date('2024-01-01'), $this->date('2024-01-02'))
            ->breakdown('groups', 'group', Metric::Count, bucketAliases: ['Merged' => ['ΟΣ']])
            ->breakdown('codes', 'code', Metric::Count, bucketAliases: ['Merged' => ['ΟΣ']])
            ->groupedMetrics('grouped', 'group', [], bucketAliases: ['Merged' => ['ΟΣ']])
            ->get();

        foreach (['groups', 'grouped'] as $widget) {
            $this->assertSame(['Merged', 'other'], array_column($result[$widget]['rows'], 'key'));
            $this->assertSame([2, 1], array_column($result[$widget]['rows'], 'count'));
        }

        $this->assertContains('οσ', array_column($result['codes']['rows'], 'key'));
    }

    public function test_breakdown_serializes_aliases_with_numeric_labels_as_a_map(): void
    {
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'date'))
            ->breakdown('groups', 'status', Metric::Count, bucketAliases: ['0' => ['none']])
            ->groupedMetrics('grouped', 'status', [], bucketAliases: ['0' => ['none']]);
        $result = $analytics->formatResponse([]);

        $this->assertSame('{"0":["none"]}', json_encode($result['groups']['bucket_aliases']));
        $this->assertSame('{"0":["none"]}', json_encode($result['grouped']['bucket_aliases']));
    }

    public function test_tied_rankings_preserve_the_elasticsearch_key_order(): void
    {
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'date'))
            ->breakdown('rank', 'code', Metric::Count)
            ->groupedMetrics('grouped', 'code', []);
        $groups = ['groups' => ['buckets' => [
            ['key' => '10', 'doc_count' => 1, 'metric' => ['doc_count' => 1]],
            ['key' => '2', 'doc_count' => 1, 'metric' => ['doc_count' => 1]],
        ]]];

        $result = $analytics->formatResponse(['aggregations' => ['rank' => $groups, 'grouped' => $groups]]);

        foreach ($result as $widget) {
            $this->assertSame(['10', '2'], array_column($widget['rows'], 'key'));
        }
    }

    public function test_table_serializes_an_empty_source_as_an_object(): void
    {
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'date'))
            ->table('orders', fields: ['missing']);
        $result = $analytics->formatResponse(['aggregations' => [
            'orders' => ['hits' => ['hits' => ['hits' => [['_id' => '1', '_source' => []]]]]],
        ]]);

        $this->assertSame('{}', json_encode($result['orders']['rows'][0]['document']));
    }

    public function test_stats_preserves_finite_values_when_the_sum_overflows(): void
    {
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'date'))
            ->stats('amounts', 'amount');
        $result = $analytics->formatResponse(['aggregations' => [
            'amounts' => ['stats' => ['count' => 2, 'min' => 1e308, 'max' => 1e308, 'avg' => 'Infinity', 'sum' => 'Infinity']],
        ]]);

        $this->assertSame([
            'type' => 'stats', 'field' => 'amount', 'count' => 2, 'min' => 1e308, 'max' => 1e308,
            'avg' => null, 'sum' => null,
        ], $result['amounts']);
    }

    public function test_bucket_limits_keep_measured_groups_ahead_of_missing_metrics(): void
    {
        $index = $this->createSalesIndex();
        $index->merge([new Document(['product' => 'missing', 'created_at' => '2024-01-02'])], refresh: true);

        $analytics = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))->to($this->date('2024-02-01'));

        foreach ([Metric::Median, Metric::Avg, Metric::Min, Metric::Max] as $metric) {
            $direction = $metric === Metric::Max ? 'asc' : 'desc';
            $analytics->breakdown($metric->value, 'product', $metric, 'amount', limit: 1, direction: $direction);
        }

        $analytics->groupedMetrics('grouped', 'product', [['key' => 'metric_present', 'metric' => Metric::Median, 'field' => 'amount']], 'metric_present', limit: 1);

        foreach ($analytics->get() as $name => $widget) {
            $this->assertSame([$name === 'max' ? 'B' : 'A'], array_column($widget['rows'], 'key'), $name);
        }
    }

    public function test_equal_metrics_keep_elasticsearch_term_order(): void
    {
        foreach ([[2, 10], ['10', '2']] as $keys) {
            $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'date'))
                ->breakdown('rank', 'code', Metric::Count)
                ->groupedMetrics('grouped', 'code', []);
            $buckets = array_map(fn ($key): array => ['key' => $key, 'doc_count' => 1, 'metric' => ['doc_count' => 1]], $keys);
            $groups = ['groups' => ['buckets' => $buckets]];
            $widgets = $analytics->formatResponse(['aggregations' => ['rank' => $groups, 'grouped' => $groups]]);
            $expected = is_int($keys[0]) ? [2, 10] : ['10', '2'];

            $this->assertSame($expected, array_column($widgets['rank']['rows'], 'key'));
            $this->assertSame(array_map('strval', $expected), array_column($widgets['grouped']['rows'], 'key'));
        }
    }

    public function test_count_checks_the_requested_text_field(): void
    {
        $properties = new NewProperties;
        $properties->category('product');

        $analytics = new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), $properties, 'date');
        $aggs = $analytics->kpi('products', Metric::Count, 'product')->toMultiSearch()[1]['aggs'];

        $this->assertSame('product', $aggs['products']['aggs']['metric']['filter']['exists']['field']);
    }

    public function test_timezone_after_range_aligns_the_named_window(): void
    {
        $analytics = new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'date');
        $range = $analytics->range(Period::Today)->timezoneOffset(540)->hitsQuery('')->toRaw()['range']['date'];

        $this->assertStringEndsWith('T00:00:00+09:00', $range['gte']);
        $this->assertStringEndsWith('T00:00:00+09:00', $range['lt']);
    }

    public function test_metric_names_preserve_bucket_metadata(): void
    {
        $keys = ['key', 'key_as_string', 'doc_count'];
        $metrics = array_map(fn (string $key): array => ['key' => $key, 'metric' => Metric::Sum, 'field' => 'amount'], $keys);
        $row = $this->createSalesIndex()->analytics('created_at')
            ->from($this->date('2024-01-01'))->to($this->date('2024-01-04'))
            ->groupedMetrics('rank', 'product', $metrics, 'key')
            ->get()['rank']['rows'][0];

        $this->assertSame('A', $row['key']);
        $this->assertSame(3, $row['count']);
        $this->assertEquals(array_fill_keys($keys, 370), $row['metrics']);
    }

    public function test_metric_names_leave_room_for_the_limit_pipeline(): void
    {
        $fallback = 'pipeline_'.substr(md5('limit'), 0, 12);
        $keys = ['limit', $fallback, $fallback.'_pipeline'];
        $metrics = array_map(fn (string $key): array => ['key' => $key, 'metric' => Metric::Sum, 'field' => 'amount'], $keys);
        $row = $this->createSalesIndex()->analytics('created_at')
            ->from($this->date('2024-01-01'))->to($this->date('2024-01-04'))
            ->groupedMetrics('rank', 'product', $metrics, 'limit')
            ->get()['rank']['rows'][0];

        $this->assertEquals(array_fill_keys($keys, 370), $row['metrics']);
    }

    public function test_date_percentiles_return_numeric_values(): void
    {
        $values = $this->createSalesIndex()->analytics('created_at')
            ->from($this->date('2024-01-01'))->to($this->date('2024-01-04'))
            ->percentiles('dates', 'created_at', [0])
            ->get()['dates']['percentiles'];

        $this->assertEquals(1704067200000, ((array) $values)[0]);
    }

    public function test_table_serializes_a_numeric_source_field(): void
    {
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'date'))
            ->table('orders');
        $result = $analytics->formatResponse(['aggregations' => [
            'orders' => ['hits' => ['hits' => ['hits' => [['_id' => '1', '_source' => ['0' => 'paid']]]]]],
        ]]);

        $this->assertSame('{"0":"paid"}', json_encode($result['orders']['rows'][0]['document']));
    }

    public function test_delta_with_an_unrepresentable_percentage(): void
    {
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'date'))
            ->kpiDelta('amount', Metric::Max, 'amount');
        $result = $analytics->formatResponse(['aggregations' => ['amount' => [
            'current' => ['metric' => ['value' => 1e308]],
            'previous' => ['metric' => ['value' => 1]],
        ]]]);

        $this->assertNull($result['amount']['change_pct']);
    }

    public function test_groups_without_metric_values_remain_in_the_ranking(): void
    {
        $rows = $this->createSalesIndex()->analytics('created_at')
            ->from($this->date('2024-01-01'))->to($this->date('2024-01-04'))
            ->groupedMetrics('rank', 'product', [['key' => 'avg', 'metric' => Metric::Avg, 'field' => 'missing']], 'avg')
            ->get()['rank']['rows'];

        $this->assertSame(['A', 'B'], array_column($rows, 'key'));
        $this->assertSame([null, null], array_column($rows, 'value'));
    }

    public function test_date_bucket_aliases_accept_a_display_label(): void
    {
        $rows = $this->createSalesIndex()->analytics('created_at')
            ->from($this->date('2024-01-01'))->to($this->date('2024-01-04'))
            ->groupedMetrics('days', 'created_at', [], bucketAliases: ['Opening day' => ['2024-01-01']], aliasesOnly: true)
            ->get()['days']['rows'];

        $this->assertSame('Opening day', $rows[0]['key']);
        $this->assertSame(2, $rows[0]['count']);
    }

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
                $props->category('channel');
                $props->category('champion_country');
                $props->category('runner_up_country');

                return $props;
            }
        };

        $index->create();

        $index->merge([
            new Document(['created_at' => '2024-01-01', 'amount' => 100, 'product' => 'A', 'channel' => 'online', 'champion_country' => 'Germany', 'runner_up_country' => 'France']),
            new Document(['created_at' => '2024-01-01', 'amount' => 50, 'product' => 'B', 'channel' => 'online', 'champion_country' => 'Spain', 'runner_up_country' => 'Germany']),
            new Document(['created_at' => '2024-01-02', 'amount' => 200, 'product' => 'A', 'channel' => 'retail', 'champion_country' => 'Germany', 'runner_up_country' => 'Italy']),
            new Document(['created_at' => '2024-01-03', 'amount' => 30, 'product' => 'B', 'channel' => 'retail', 'champion_country' => 'France', 'runner_up_country' => 'Spain']),
            new Document(['created_at' => '2024-01-03', 'amount' => 70, 'product' => 'A', 'channel' => 'online', 'champion_country' => 'Italy', 'runner_up_country' => 'Germany']),
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

    public function test_trend_uses_the_same_date_only_window_as_the_kpi(): void
    {
        $name = uniqid('date_only_window_');
        $props = new NewProperties;
        $props->datetime('created_at');

        $index = $this->sigmie->newIndex($name)->properties($props)->create();
        try {
            $this->sigmie->collect($name, refresh: true)->merge([
                new Document(['created_at' => ['2024-01-01T23:00:00Z', '2024-01-02T00:00:00Z']]),
            ]);

            $result = $this->sigmie->analytics($name, 'created_at')->properties($props)
                ->from($this->date('2024-01-02 12:00'))->to($this->date('2024-01-03 12:00'))
                ->format('Y-m-d')
                ->kpi('count', Metric::Count)
                ->trend('trend', Metric::Count, interval: CalendarInterval::Hour)
                ->get();

            $this->assertEquals(1, $result['count']['value']);
            $series = $result['trend']['series'];
            $this->assertEquals(1, $series[0]['value']);
            $this->assertCount(24, $series);
            $this->assertEquals(1, array_sum(array_column($series, 'value')));
        } finally {
            $index->delete();
        }
    }

    public function test_trends_only_bucket_date_values_in_their_window(): void
    {
        $name = uniqid('time_window_');
        $props = new NewProperties;
        $props->datetime('created_at');
        $props->number('amount');
        $props->keyword('category');

        $index = $this->sigmie->newIndex($name)->properties($props)->create();
        try {
            $this->sigmie->collect($name, refresh: true)->merge([
                new Document([
                    'created_at' => ['2024-01-01T20:00:00Z', '2024-01-03T11:00:00Z', '2024-01-04T00:00:00Z'],
                    'amount' => 10,
                    'category' => 'A',
                ]),
                new Document([
                    'created_at' => ['2024-01-02T15:00:00Z', '2024-01-03T20:00:00Z'],
                    'amount' => 20,
                    'category' => 'A',
                ]),
            ]);

            $result = $this->sigmie->analytics($name, 'created_at')->properties($props)
                ->from($this->date('2024-01-02 12:00'))->to($this->date('2024-01-03 12:00'))
                ->trend('trend', Metric::Sum, 'amount')
                ->groupedTrend('by_category', Metric::Sum, 'amount', 'category')
                ->get();

            $this->assertEquals([20, 10], array_column($result['trend']['series'], 'value'));
            $this->assertEquals([20, 10], array_column($result['by_category']['groups'][0]['series'], 'value'));
        } finally {
            $index->delete();
        }
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
    public function count_breakdown_reports_its_document_population(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->breakdown('top_products', 'product', Metric::Count)
            ->get();

        $row = $result['top_products']['rows'][0];

        $this->assertSame(3, $row['population']['document_count']);
        $this->assertSame(3, $row['population']['value_count']);
    }

    /**
     * @test
     */
    public function breakdown_merges_bucket_aliases_in_elasticsearch_before_top_n_ranking(): void
    {
        $index = $this->createSalesIndex();

        $index->merge([
            new Document(['created_at' => '2024-01-02', 'amount' => 250, 'product' => 'B']),
            new Document(['created_at' => '2024-01-02', 'amount' => 180, 'product' => 'C']),
            new Document(['created_at' => '2024-01-02', 'amount' => 170, 'product' => 'Old C']),
        ], refresh: true);

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->breakdown('top_products', 'product', Metric::Sum, 'amount', limit: 2, bucketAliases: [
                'Combined C' => ['C', 'Old C'],
            ])
            ->get();

        $rows = $result['top_products']['rows'];

        $this->assertCount(2, $rows);
        $this->assertSame('A', $rows[0]['key']);
        $this->assertEquals(370.0, $rows[0]['value']);
        $this->assertSame('Combined C', $rows[1]['key']);
        $this->assertEquals(350.0, $rows[1]['value']);
        $this->assertSame(['Combined C', 'C', 'Old C'], $rows[1]['source_keys']);
        $this->assertSame(2, $rows[1]['population']['document_count']);
        $this->assertSame(2, $rows[1]['population']['value_count']);
        $this->assertNotContains('B', array_column($rows, 'key'));
        $this->assertNotContains('C', array_column($rows, 'key'));
        $this->assertNotContains('Old C', array_column($rows, 'key'));
    }

    /**
     * @test
     */
    public function multi_breakdown_ranks_top_composite_keys_by_metric(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->multiBreakdown('top_product_channels', ['product', 'channel'], Metric::Sum, 'amount', limit: 3)
            ->get();

        $rows = $result['top_product_channels']['rows'];

        $this->assertSame('multi_breakdown', $result['top_product_channels']['type']);
        $this->assertCount(3, $rows);
        $this->assertSame(['A', 'retail'], $rows[0]['key_values']);
        $this->assertEquals(200.0, $rows[0]['value']);
        $this->assertSame(['A', 'online'], $rows[1]['key_values']);
        $this->assertEquals(170.0, $rows[1]['value']);
        $this->assertSame(['B', 'online'], $rows[2]['key_values']);
        $this->assertEquals(50.0, $rows[2]['value']);
    }

    /**
     * @test
     */
    public function a_multi_breakdown_key_uses_the_caller_field_names(): void
    {
        $properties = new NewProperties;
        $properties->date('created_at');
        $properties->number('amount');
        $properties->category('product');
        $properties->category('channel');

        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'orders'), $properties, 'created_at'))
            ->from(new DateTimeImmutable('2024-01-01T00:00:00+00:00'))
            ->to(new DateTimeImmutable('2024-02-01T00:00:00+00:00'))
            ->multiBreakdown('top_product_channels', ['product', 'channel'], Metric::Sum, 'amount');

        $terms = $analytics->toSearch()->toMultiSearch()[1]['aggs']['top_product_channels']['aggs']['groups']['multi_terms']['terms'];
        $rows = $analytics->formatResponse([
            'aggregations' => [
                'top_product_channels' => [
                    'groups' => [
                        'buckets' => [[
                            'key' => ['A', 'online'],
                            'doc_count' => 2,
                            'metric' => ['value' => 240],
                        ]],
                    ],
                ],
            ],
        ]);

        $this->assertSame([
            ['field' => 'product.keyword'],
            ['field' => 'channel.keyword'],
        ], $terms);
        $this->assertSame(['product' => 'A', 'channel' => 'online'], $rows['top_product_channels']['rows'][0]['key']);
        $this->assertSame(['A', 'online'], $rows['top_product_channels']['rows'][0]['key_values']);
        $this->assertSame('A / online', $rows['top_product_channels']['rows'][0]['label']);

        $numeric = new MultiBreakdown('top', 'created_at', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), new DateTimeImmutable('2024-02-01T00:00:00+00:00'), 'Y-m-d', ['0', '1'], Metric::Sum, 'amount', 10, 'desc', ['0', '1']);
        $numericKey = $numeric->extract([
            'top' => [
                'groups' => [
                    'buckets' => [[
                        'key' => ['A', 'online'],
                        'doc_count' => 2,
                        'metric' => ['value' => 240],
                    ]],
                ],
            ],
        ])['rows'][0]['key'];

        $this->assertSame('{"0":"A","1":"online"}', json_encode($numericKey));

        $sole = new MultiBreakdown('top', 'created_at', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), new DateTimeImmutable('2024-02-01T00:00:00+00:00'), 'Y-m-d', ['0'], Metric::Sum, 'amount', 10, 'desc', ['0']);
        $soleKey = $sole->extract([
            'top' => [
                'groups' => [
                    'buckets' => [[
                        'key' => ['A'],
                        'doc_count' => 1,
                        'metric' => ['value' => 1],
                    ]],
                ],
            ],
        ])['rows'][0]['key'];

        $this->assertSame('{"0":"A"}', json_encode($soleKey));
    }

    /**
     * @test
     */
    public function a_percentile_named_zero_stays_in_the_json(): void
    {
        $properties = new NewProperties;
        $properties->date('created_at');
        $properties->number('duration_ms');

        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'requests'), $properties, 'created_at'))
            ->from(new DateTimeImmutable('2024-01-01T00:00:00+00:00'))
            ->to(new DateTimeImmutable('2024-02-01T00:00:00+00:00'))
            ->percentiles('sole', 'duration_ms', [0])
            ->percentiles('pair', 'duration_ms', [0, 1])
            ->percentiles('spread', 'duration_ms', [0, 50, 100]);

        $rows = $analytics->formatResponse([
            'aggregations' => [
                'sole' => ['metric' => ['values' => ['0.0' => 5]]],
                'pair' => ['metric' => ['values' => ['0.0' => 5, '1.0' => 9]]],
                'spread' => ['metric' => ['values' => ['0.0' => 5, '50.0' => 12, '100.0' => 20]]],
            ],
        ]);

        $this->assertSame('{"0":5}', json_encode($rows['sole']['percentiles']));
        $this->assertSame('{"0":5,"1":9}', json_encode($rows['pair']['percentiles']));
        $this->assertSame([0 => 5, 50 => 12, 100 => 20], $rows['spread']['percentiles']);
        $this->assertSame('{"0":5,"50":12,"100":20}', json_encode($rows['spread']['percentiles']));
    }

    /**
     * @test
     */
    public function union_breakdown_combines_labels_across_fields_for_counts_and_metrics(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->unionBreakdown('appearances', ['champion_country', 'runner_up_country'], Metric::Count)
            ->unionBreakdown('totals', ['champion_country', 'runner_up_country'], Metric::Sum, 'amount')
            ->get();

        $appearances = array_column($result['appearances']['rows'], null, 'key');
        $totals = $result['totals']['rows'];

        $this->assertSame('union_breakdown', $result['appearances']['type']);
        $this->assertSame(4, $appearances['Germany']['value']);
        $this->assertSame(2, $appearances['France']['value']);
        $this->assertSame(2, $appearances['Italy']['value']);
        $this->assertSame(2, $appearances['Spain']['value']);
        $this->assertSame('Germany', $totals[0]['key']);
        $this->assertEquals(420.0, $totals[0]['value']);
        $this->assertSame('Italy', $totals[1]['key']);
        $this->assertEquals(270.0, $totals[1]['value']);
        $this->assertSame('France', $totals[2]['key']);
        $this->assertEquals(130.0, $totals[2]['value']);
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

    public function test_a_distribution_wider_than_the_bucket_limit_keeps_every_bucket(): void
    {
        $name = uniqid();
        $props = new NewProperties;
        $props->date('created_at');
        $props->number('amount');
        $this->sigmie->newIndex($name)->properties($props)->create();
        $this->sigmie->collect($name, refresh: true)->merge([
            new Document(['created_at' => '2024-01-01', 'amount' => 15]),
            new Document(['created_at' => '2024-01-01', 'amount' => 700000]),
        ]);

        $buckets = $this->sigmie->analytics($name, 'created_at', $this->date('2024-01-01'), $this->date('2024-01-02'))
            ->distribution('amounts', 'amount', 10)->get()['amounts']['buckets'];

        $this->assertCount(70_000, $buckets);
        $this->assertEquals([['label' => 10, 'count' => 1], ['label' => 20, 'count' => 0]], array_slice($buckets, 0, 2));
        $this->assertEquals(['label' => 700000, 'count' => 1], $buckets[69_999]);
    }

    /**
     * @test
     */
    public function histogram_metric_computes_a_metric_inside_numeric_buckets(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->histogramMetric('average_by_amount_bucket', 'amount', 100, Metric::Avg, 'amount')
            ->get();

        $rows = $result['average_by_amount_bucket']['rows'];

        $this->assertSame('histogram_metric', $result['average_by_amount_bucket']['type']);
        $this->assertEquals(0, $rows[0]['key']);
        $this->assertEquals(50.0, $rows[0]['value']);
        $this->assertEquals(3, $rows[0]['count']);
        $this->assertEquals(100.0, $rows[1]['value']);
        $this->assertEquals(200.0, $rows[2]['value']);
    }

    public function test_grouped_metrics_sends_an_explicit_zero_minimum(): void
    {
        $metrics = [['key' => 'count', 'label' => 'Count', 'metric' => Metric::Count, 'field' => '']];
        $raw = $this->groupedMetricsWidget($metrics, 'count', 10, minCount: 0)->toRaw();
        $this->assertSame(0, $raw['groups']['aggs']['groups']['terms']['min_doc_count'] ?? null);
    }

    public function test_bucket_aliases_include_an_empty_keyword(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->keyword('group');
        }, [
            new Document(['created_at' => '2024-01-01', 'group' => '']),
            new Document(['created_at' => '2024-01-01', 'group' => '']),
            new Document(['created_at' => '2024-01-01', 'group' => 'other']),
        ]);

        try {
            $result = $index->analytics('created_at')
                ->from($this->date('2024-01-01'))
                ->to($this->date('2024-01-02'))
                ->breakdown('groups', 'group', Metric::Count, bucketAliases: ['Blank' => ['']])
                ->get();

            $this->assertSame(['Blank', 'other'], array_column($result['groups']['rows'], 'key'));
            $this->assertSame([2, 1], array_column($result['groups']['rows'], 'count'));
        } finally {
            $index->delete();
        }
    }

    /**
     * @test
     */
    public function grouped_metrics_ranks_groups_and_carries_additional_metrics(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->groupedMetrics('product_metrics', 'product', [
                ['key' => 'count', 'label' => 'Count', 'metric' => Metric::Count],
                ['key' => 'avg_amount', 'label' => 'Average amount', 'metric' => Metric::Avg, 'field' => 'amount'],
            ], sortMetric: 'count', minCount: 2)
            ->get();

        $rows = $result['product_metrics']['rows'];

        $this->assertSame('grouped_metrics', $result['product_metrics']['type']);
        $this->assertSame('A', $rows[0]['key']);
        $this->assertEquals(3, $rows[0]['metrics']['count']);
        $this->assertEquals(123.33333333333333, $rows[0]['metrics']['avg_amount']);
        $this->assertSame('B', $rows[1]['key']);
        $this->assertEquals(2, $rows[1]['metrics']['count']);
        $this->assertEquals(40.0, $rows[1]['metrics']['avg_amount']);
        $this->assertSame(3, $rows[0]['metric_populations']['avg_amount']['document_count']);
        $this->assertSame(3, $rows[0]['metric_populations']['avg_amount']['value_count']);
    }

    /**
     * @test
     */
    public function grouped_metrics_computes_alias_metrics_over_one_canonical_population(): void
    {
        $index = $this->createSalesIndex();

        $index->merge([
            new Document(['created_at' => '2024-01-02', 'product' => 'Cancelled by Driver']),
            new Document(['created_at' => '2024-01-02', 'product' => 'Cancelled by Driver']),
            new Document(['created_at' => '2024-01-02', 'amount' => 10, 'product' => 'Incomplete']),
        ], refresh: true);

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->groupedMetrics('product_metrics', 'product', [
                ['key' => 'count', 'label' => 'Count', 'metric' => Metric::Count],
                ['key' => 'avg_amount', 'label' => 'Average amount', 'metric' => Metric::Avg, 'field' => 'amount'],
            ], sortMetric: 'count', bucketAliases: [
                'Failed' => ['Cancelled by Driver', 'Incomplete'],
            ])
            ->get();

        $row = array_values(array_filter(
            $result['product_metrics']['rows'],
            fn (array $row): bool => $row['key'] === 'Failed',
        ))[0] ?? null;

        $this->assertNotNull($row);
        $this->assertSame(3, $row['count']);
        $this->assertEquals(10.0, $row['metrics']['avg_amount']);
        $this->assertSame(3, $row['metric_populations']['avg_amount']['document_count']);
        $this->assertSame(1, $row['metric_populations']['avg_amount']['value_count']);
        $this->assertSame(['Failed', 'Cancelled by Driver', 'Incomplete'], $row['source_keys']);
        $this->assertNotContains('Cancelled by Driver', array_column($result['product_metrics']['rows'], 'key'));
        $this->assertNotContains('Incomplete', array_column($result['product_metrics']['rows'], 'key'));
    }

    /**
     * @test
     */
    public function grouped_metrics_sorts_count_metrics_with_domain_specific_keys(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->groupedMetrics('product_metrics', 'product', [
                ['key' => 'orders', 'label' => 'Orders', 'metric' => Metric::Count],
                ['key' => 'avg_amount', 'label' => 'Average amount', 'metric' => Metric::Avg, 'field' => 'amount'],
            ], sortMetric: 'orders', minCount: 2)
            ->get();

        $rows = $result['product_metrics']['rows'];

        $this->assertSame('orders', $result['product_metrics']['sort_metric']);
        $this->assertSame('A', $rows[0]['key']);
        $this->assertEquals(3, $rows[0]['metrics']['orders']);
        $this->assertSame('B', $rows[1]['key']);
        $this->assertEquals(2, $rows[1]['metrics']['orders']);
    }

    /**
     * @test
     */
    public function grouped_metrics_sort_metric_count_uses_the_count_metric_even_when_it_has_a_custom_key(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->groupedMetrics('product_metrics', 'product', [
                ['key' => 'avg_amount', 'label' => 'Average amount', 'metric' => Metric::Avg, 'field' => 'amount'],
                ['key' => 'orders', 'label' => 'Orders', 'metric' => Metric::Count],
            ], sortMetric: 'count', minCount: 2)
            ->get();

        $rows = $result['product_metrics']['rows'];

        $this->assertSame('orders', $result['product_metrics']['sort_metric']);
        $this->assertSame('A', $rows[0]['key']);
        $this->assertEquals(3, $rows[0]['metrics']['orders']);
        $this->assertSame('B', $rows[1]['key']);
        $this->assertEquals(2, $rows[1]['metrics']['orders']);
    }

    /**
     * @test
     */
    public function grouped_metrics_applies_min_count_before_returning_groups(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->groupedMetrics('product_metrics', 'product', [
                ['key' => 'count', 'label' => 'Count', 'metric' => Metric::Count],
                ['key' => 'avg_amount', 'label' => 'Average amount', 'metric' => Metric::Avg, 'field' => 'amount'],
            ], sortMetric: 'count', minCount: 3)
            ->get();

        $rows = $result['product_metrics']['rows'];

        $this->assertCount(1, $rows);
        $this->assertSame('A', $rows[0]['key']);
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
    public function analytics_can_return_widgets_and_hits_from_one_search(): void
    {
        $index = $this->createSalesIndex();

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->kpi('revenue', Metric::Sum, 'amount')
            ->search(function (Search $search): void {
                $search
                    ->size(2)
                    ->sortString('amount:desc')
                    ->postFilterString("product:'A'")
                    ->trackTotalHits(true);
            })
            ->getWithHits();

        $this->assertEquals(450.0, $result['widgets']['revenue']['value']);
        $this->assertSame(3, $result['hits']['total']['value']);
        $this->assertCount(2, $result['hits']['hits']);
        $this->assertSame(200, $result['hits']['hits'][0]['_source']['amount']);
        $this->assertSame('A', $result['hits']['hits'][0]['_source']['product']);
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

    /**
     * @test
     */
    public function retention_asks_for_an_accurate_distinct_count(): void
    {
        $widget = new Retention('retention', 'created_at', $this->date('2024-01-01'), $this->date('2024-01-04'), 'Y-m-d', 'signup_at', 'user', CalendarInterval::Day);

        $cohort = $widget->toRaw()['retention']['aggs']['cohorts']['aggs'];

        $this->assertEquals(40000, $cohort['size']['cardinality']['precision_threshold']);
        $this->assertEquals(40000, $cohort['periods']['aggs']['users']['cardinality']['precision_threshold']);
    }

    /**
     * @test
     */
    public function a_field_inside_nested_documents_is_rejected(): void
    {
        $props = new NewProperties;
        $props->date('created_at');
        $props->nested('items', function (NewProperties $items): void {
            $items->keyword('sku');
            $items->number('qty');
            $items->text('note');
            $items->date('shipped_at');
            $items->geoPoint('warehouse');
        });
        $analytics = fn (): Analytics => $this->sigmie->analytics('orders', 'created_at')->properties($props);

        $rejected = 0;
        foreach ([
            fn (): Analytics => $analytics()->breakdown('skus', 'items.sku', Metric::Count),
            fn (): Analytics => $analytics()->kpi('qty', Metric::Sum, 'items.qty'),
            fn (): Analytics => $analytics()->kpi('notes', Metric::Count, 'items.note'),
            fn (): Analytics => $analytics()->retention('cohorts', 'items.shipped_at', 'items.sku'),
            fn (): Analytics => $analytics()->geo('map', 'items.warehouse'),
        ] as $nested) {
            try {
                $nested();
            } catch (InvalidArgumentException) {
                $rejected++;
            }
        }

        $this->assertSame(5, $rejected);
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
            ->kpi('first_day', Metric::Sum, 'amount', window: [$this->date('2024-01-01'), $this->date('2024-01-02')])
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

    /**
     * @test
     */
    public function multi_search_can_build_analytics_inline(): void
    {
        $index = $this->createSalesIndex();
        $name = $index->name();

        $multi = $this->sigmie->newMultiSearch();
        $multi->newAnalytics($index, 'created_at', 'metrics')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->kpi('revenue', Metric::Sum, 'amount')
            ->breakdown('top_products', 'product', Metric::Sum, 'amount');
        $multi->newQuery($name, 'rows')->matchAll()->size(2);

        [$metrics, $rows] = $multi->get();

        // newAnalytics formats its own slot — the result is already the widget map.
        $this->assertEquals(450.0, $metrics['revenue']['value']);
        $this->assertSame('A', $metrics['top_products']['rows'][0]['key']);
        $this->assertCount(2, $rows['hits']['hits']);
    }

    /**
     * @test
     */
    public function analytics_fallback_paths_are_backed_by_elasticsearch_results(): void
    {
        $index = $this->createSalesIndex();

        $analytics = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->format('Y-m-d')
            ->groupedMetrics('fallback_sort', 'product', [
                ['key' => 'revenue', 'label' => 'Revenue', 'metric' => Metric::Sum, 'field' => 'amount'],
            ], sortMetric: 'count', minCount: 2)
            ->groupedMetrics('default_metrics', 'product', [
                ['key' => '', 'label' => 'Ignored', 'metric' => Metric::Sum, 'field' => 'amount'],
                ['key' => 'invalid', 'label' => 'Invalid', 'metric' => 'sum', 'field' => 'amount'],
            ], sortMetric: 'count', minCount: 2)
            ->kpi('this_month_docs', Metric::Count, window: Period::ThisMonth);

        $result = $analytics->get();

        $this->assertSame('revenue', $result['fallback_sort']['sort_metric']);
        $this->assertSame('A', $result['fallback_sort']['rows'][0]['key']);
        $this->assertEquals(370.0, $result['fallback_sort']['rows'][0]['metrics']['revenue']);
        $this->assertSame('count', $result['default_metrics']['sort_metric']);
        $this->assertEquals(3, $result['default_metrics']['rows'][0]['metrics']['count']);
        $this->assertEquals(0.0, $result['this_month_docs']['value']);

        $this->assertSame($analytics->toSearch(), $analytics->toSearch());

        $emptyFieldAnalytics = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-04'))
            ->stats('empty_stats', '');

        $this->assertSame($emptyFieldAnalytics->toSearch(), $emptyFieldAnalytics->toSearch());
    }

    protected function setUp(): void
    {
        if ($this->runsWithoutCluster()) {
            return;
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        if ($this->runsWithoutCluster()) {
            return;
        }

        parent::tearDown();
    }

    /**
     * @test
     */
    public function grouped_metrics_keeps_a_null_sort_metric(): void
    {
        $ranked = new GroupedMetrics('groups', 'created_at', $this->date('2024-01-01'), $this->date('2024-02-01'), 'Y-m-d', 'product', [
            ['key' => 'orders', 'label' => 'Orders', 'metric' => Metric::Count, 'field' => ''],
            ['key' => 'average_amount', 'label' => 'Average amount', 'metric' => Metric::Avg, 'field' => 'amount'],
        ], 'average_amount', 10, 'desc');

        $rows = $ranked->extract([
            'groups' => [
                'groups' => [
                    'buckets' => [
                        ['key' => 'A', 'doc_count' => 4, 'average_amount' => ['value' => null]],
                        ['key' => 'B', 'doc_count' => 2, 'average_amount' => ['value' => 10]],
                        ['key' => 'C', 'doc_count' => 1, 'average_amount' => ['value' => 3]],
                    ],
                ],
            ],
        ])['rows'];

        $this->assertSame(['B', 'C', 'A'], array_column($rows, 'key'));
        $this->assertSame([10, 3, null], array_column($rows, 'value'));
        $this->assertNull($rows[2]['metrics']['average_amount']);
        $this->assertSame(4, $rows[2]['metrics']['orders']);

        $lone = new GroupedMetrics('groups', 'created_at', $this->date('2024-01-01'), $this->date('2024-02-01'), 'Y-m-d', 'product', [
            ['key' => 'average_amount', 'label' => 'Average amount', 'metric' => Metric::Avg, 'field' => 'amount'],
        ], 'average_amount', 10, 'desc');

        $loneRow = $lone->extract([
            'groups' => [
                'groups' => [
                    'buckets' => [
                        ['key' => 'A', 'doc_count' => 4, 'average_amount' => ['value' => null]],
                    ],
                ],
            ],
        ])['rows'][0];

        $this->assertNull($loneRow['value']);
        $this->assertNull($loneRow['metrics']['average_amount']);
    }

    /**
     * @test
     */
    public function histogram_metric_count_reads_metric_value(): void
    {
        $histogram = new HistogramMetric('ratings', 'created_at', $this->date('2024-01-01'), $this->date('2024-02-01'), 'Y-m-d', 'rating', 1, Metric::Count, 'rating');
        $raw = $histogram->toRaw();

        $this->assertSame('rating', $raw['ratings']['aggs']['buckets']['aggs']['metric']['value_count']['field']);

        $series = $histogram->extract([
            'ratings' => [
                'buckets' => [
                    'buckets' => [
                        ['key' => 1, 'doc_count' => 5, 'metric' => ['value' => 2]],
                    ],
                ],
            ],
        ])['series'][0];

        $this->assertSame(2, $series['value']);
        $this->assertSame(5, $series['count']);
    }

    /**
     * @test
     */
    public function cumulative_median_uses_the_metric_order_key(): void
    {
        $median = new Cumulative('growth', 'created_at', $this->date('2024-01-01'), $this->date('2024-02-01'), 'Y-m-d', Metric::Median, 'amount', CalendarInterval::Day);
        $sum = new Cumulative('growth', 'created_at', $this->date('2024-01-01'), $this->date('2024-02-01'), 'Y-m-d', Metric::Sum, 'amount', CalendarInterval::Day);

        $this->assertSame('metric.50', $median->toRaw()['growth']['aggs']['series']['aggs']['cumulative']['cumulative_sum']['buckets_path']);
        $this->assertSame('metric', $sum->toRaw()['growth']['aggs']['series']['aggs']['cumulative']['cumulative_sum']['buckets_path']);
        $this->assertSame('metric.50', Metric::Median->orderKey('metric'));
        $this->assertSame('metric', Metric::Sum->orderKey('metric'));
    }

    /**
     * @test
     */
    public function to_search_includes_later_widgets_and_filters(): void
    {
        $properties = new NewProperties;
        $properties->date('created_at');
        $properties->number('amount');
        $properties->category('product');

        $analytics = new Analytics(
            new NewQuery($this->disconnectedConnection(), 'sales'),
            $properties,
            'created_at',
            $this->date('2024-01-01'),
            $this->date('2024-02-01'),
        );

        $analytics->kpi('first', Metric::Count);

        $firstSearch = $analytics->toSearch();
        $first = $firstSearch->toMultiSearch()[1];

        $this->assertSame(['first'], array_keys($first['aggs']));
        $this->assertArrayHasKey('match_all', $first['query']['bool']['filter'][0]);

        $analytics->kpi('second', Metric::Sum, 'amount')->filters("product:'A'");
        $second = $analytics->toSearch()->toMultiSearch()[1];
        $again = $analytics->toSearch()->toMultiSearch()[1];

        $this->assertSame(['first', 'second'], array_keys($second['aggs']));
        $this->assertSame('A', $second['query']['bool']['filter'][0]['bool']['must'][0]['term']['product.keyword']['value']);
        $this->assertSame(['first', 'second'], array_keys($again['aggs']));
        $this->assertSame('A', $again['query']['bool']['filter'][0]['bool']['must'][0]['term']['product.keyword']['value']);
        $this->assertEquals($first, $firstSearch->toMultiSearch()[1]);
    }

    /**
     * @test
     */
    public function object_text_aggregates_on_the_full_keyword_path(): void
    {
        $props = new NewProperties;
        $props->date('created_at');
        $props->object('contact', function (NewProperties $props): void {
            $props->name('name');
            $props->category('role');
        });

        $analytics = new Analytics(new NewQuery($this->disconnectedConnection(), 'orders'), $props, 'created_at');
        $analytics->from(new DateTimeImmutable('2024-01-01T00:00:00+00:00'))
            ->to(new DateTimeImmutable('2024-02-01T00:00:00+00:00'))
            ->breakdown('by_name', 'contact.name', Metric::Count)
            ->breakdown('by_role', 'contact.role', Metric::Count);

        $aggs = $analytics->toSearch()->toMultiSearch()[1]['aggs'];

        $this->assertSame('contact.name.keyword', $aggs['by_name']['aggs']['groups']['terms']['field']);
        $this->assertSame('contact.role.keyword', $aggs['by_role']['aggs']['groups']['terms']['field']);
    }

    /**
     * @test
     */
    public function filter_query_and_properties_rebuild_the_compiled_search(): void
    {
        $from = new DateTimeImmutable('2024-01-01T00:00:00+00:00');
        $to = new DateTimeImmutable('2024-02-01T00:00:00+00:00');
        $keyword = new NewProperties;
        $keyword->date('created_at');
        $keyword->keyword('status');

        $text = new NewProperties;
        $text->date('created_at');
        $text->text('status')->keyword();

        $late = new Analytics(new NewQuery($this->disconnectedConnection(), 'orders'), $keyword, 'created_at');
        $late->from($from)->to($to)->kpi('k', Metric::Count);
        $late->toSearch();
        $late->filterQuery(new Term('status', 'paid'));

        $this->assertStringContainsString('paid', json_encode($late->toSearch()->toMultiSearch()[1]['query']));

        $swap = new Analytics(new NewQuery($this->disconnectedConnection(), 'orders'), $keyword, 'created_at');
        $swap->from($from)->to($to)->filters('status:"paid"')->kpi('k', Metric::Count);
        $swap->toSearch();
        $swap->properties($text);

        $this->assertStringContainsString('status.keyword', json_encode($swap->toSearch()->toMultiSearch()[1]['query']));
    }

    /**
     * @test
     */
    public function a_multi_breakdown_label_joins_the_key_values(): void
    {
        $widget = new MultiBreakdown(
            'groups',
            'created_at',
            new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2024-02-01T00:00:00+00:00'),
            'Y-m-d',
            ['channel', 'status'],
            Metric::Count,
            '',
            10,
            'desc',
        );

        $rows = $widget->extract([
            'groups' => [
                'groups' => [
                    'buckets' => [[
                        'key' => ['A', 'online'],
                        'key_as_string' => 'A|online',
                        'doc_count' => 1,
                    ]],
                ],
            ],
        ]);

        $this->assertSame('A / online', $rows['rows'][0]['label']);
    }

    /**
     * @test
     */
    public function a_multi_breakdown_boolean_label_stays_true_or_false(): void
    {
        $widget = new MultiBreakdown(
            'groups',
            'created_at',
            new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2024-02-01T00:00:00+00:00'),
            'Y-m-d',
            ['active', 'channel'],
            Metric::Count,
            '',
            10,
            'desc',
        );

        $rows = $widget->extract([
            'groups' => [
                'groups' => [
                    'buckets' => [
                        ['key' => [true, 'online'], 'doc_count' => 2, 'metric' => ['doc_count' => 2]],
                        ['key' => [false, 'online'], 'doc_count' => 1, 'metric' => ['doc_count' => 1]],
                        ['key' => [0, 'online'], 'doc_count' => 1, 'metric' => ['doc_count' => 1]],
                        ['key' => [1, 'online'], 'doc_count' => 1, 'metric' => ['doc_count' => 1]],
                    ],
                ],
            ],
        ]);

        $this->assertSame([true, 'online'], $rows['rows'][0]['key_values']);
        $this->assertSame('true / online', $rows['rows'][0]['label']);
        $this->assertSame('false / online', $rows['rows'][1]['label']);
        $this->assertSame('0 / online', $rows['rows'][2]['label']);
        $this->assertSame('1 / online', $rows['rows'][3]['label']);
    }

    /**
     * @test
     */
    public function a_named_period_ends_at_the_next_civil_midnight(): void
    {
        $format = 'Y-m-d\TH:i:sP';
        $cairo = new DateTimeZone('Africa/Cairo');
        $onGap = new DateTimeImmutable('2024-04-26 12:00:00', $cairo);
        $nextDay = new DateTimeImmutable('2024-04-27 12:00:00', $cairo);
        $probe = new DateTimeImmutable('2024-04-27 00:30:00', $cairo);

        [$todayFrom, $todayTo] = Period::Today->resolve($onGap);
        [$yesterdayFrom, $yesterdayTo] = Period::Yesterday->resolve($nextDay);

        $this->assertSame('2024-04-26T01:00:00+03:00', $todayFrom->format($format));
        $this->assertSame('2024-04-27T00:00:00+03:00', $todayTo->format($format));
        $this->assertSame('2024-04-26T01:00:00+03:00', $yesterdayFrom->format($format));
        $this->assertSame('2024-04-27T00:00:00+03:00', $yesterdayTo->format($format));
        $this->assertFalse($probe >= $todayFrom && $probe < $todayTo);
        $this->assertFalse($probe >= $yesterdayFrom && $probe < $yesterdayTo);

        $asuncion = new DateTimeZone('America/Asuncion');
        $inOctober = new DateTimeImmutable('2017-10-15 12:00:00', $asuncion);
        $inNovember = new DateTimeImmutable('2017-11-15 12:00:00', $asuncion);
        $novemberProbe = new DateTimeImmutable('2017-11-01 00:30:00', $asuncion);

        [$monthFrom, $monthTo] = Period::ThisMonth->resolve($inOctober);
        [$lastFrom, $lastTo] = Period::LastMonth->resolve($inNovember);

        $this->assertSame('2017-10-01T01:00:00-03:00', $monthFrom->format($format));
        $this->assertSame('2017-11-01T00:00:00-03:00', $monthTo->format($format));
        $this->assertSame('2017-10-01T01:00:00-03:00', $lastFrom->format($format));
        $this->assertSame('2017-11-01T00:00:00-03:00', $lastTo->format($format));
        $this->assertFalse($novemberProbe >= $monthFrom && $novemberProbe < $monthTo);
        $this->assertFalse($novemberProbe >= $lastFrom && $novemberProbe < $lastTo);

        [$quarterFrom, $quarterTo] = Period::ThisQuarter->resolve($inOctober);
        $this->assertSame('2017-10-01T01:00:00-03:00', $quarterFrom->format($format));
        $this->assertSame('2018-01-01T00:00:00-03:00', $quarterTo->format($format));

        $utc = new DateTimeImmutable('2024-05-15 10:00:00', new DateTimeZone('UTC'));
        [$from, $to] = Period::Today->resolve($utc);
        $this->assertSame('2024-05-15T00:00:00+00:00', $from->format($format));
        $this->assertSame('2024-05-16T00:00:00+00:00', $to->format($format));
        [$from, $to] = Period::ThisMonth->resolve($utc);
        $this->assertSame('2024-05-01T00:00:00+00:00', $from->format($format));
        $this->assertSame('2024-06-01T00:00:00+00:00', $to->format($format));
        [$from, $to] = Period::ThisWeek->resolve($utc);
        $this->assertSame('2024-05-13T00:00:00+00:00', $from->format($format));
        $this->assertSame('2024-05-20T00:00:00+00:00', $to->format($format));
        [$from, $to] = Period::Last7Days->resolve($utc);
        $this->assertSame('2024-05-09T00:00:00+00:00', $from->format($format));
        $this->assertSame('2024-05-16T00:00:00+00:00', $to->format($format));
        [$from, $to] = Period::ThisQuarter->resolve($utc);
        $this->assertSame('2024-04-01T00:00:00+00:00', $from->format($format));
        $this->assertSame('2024-07-01T00:00:00+00:00', $to->format($format));
        [$from, $to] = Period::ThisYear->resolve($utc);
        $this->assertSame('2024-01-01T00:00:00+00:00', $from->format($format));
        $this->assertSame('2025-01-01T00:00:00+00:00', $to->format($format));
    }

    /**
     * @test
     */
    public function extended_bounds_keep_fractional_seconds(): void
    {
        $bounds = function (string $from, string $to): array {
            $start = new DateTimeImmutable($from);
            $end = new DateTimeImmutable($to);
            $raw = (new Trend('span', 'created_at', $start, $end, 'Y-m-d\TH:i:s.vP', Metric::Sum, 'amount', CalendarInterval::Day))
                ->toRaw()['span'];

            return $raw['aggs']['series']['date_histogram']['extended_bounds'];
        };

        $fraction = $bounds('2024-01-01T00:00:00.250000+00:00', '2024-01-01T00:00:00.900000+00:00');
        $span = $bounds('2024-01-01T00:00:00.000000+00:00', '2024-01-03T00:00:00.750000+00:00');
        $whole = $bounds('2024-01-01T00:00:00+00:00', '2024-01-03T00:00:00+00:00');

        $this->assertSame(1704067200250, $fraction['min']);
        $this->assertSame(1704067200899, $fraction['max']);
        $this->assertGreaterThanOrEqual($fraction['min'], $fraction['max']);
        $this->assertSame(1704240000749, $span['max']);
        $this->assertSame(1704067200000, $whole['min']);
        $this->assertSame(1704239999999, $whole['max']);
    }

    /**
     * @test
     */
    public function a_sub_millisecond_window_keeps_its_histogram_bucket(): void
    {
        $bounds = (new Trend(
            'span',
            'created_at',
            new DateTimeImmutable('2024-01-01T00:00:00.000100+00:00'),
            new DateTimeImmutable('2024-01-01T00:00:00.000900+00:00'),
            'Y-m-d\TH:i:s.vP',
            Metric::Sum,
            'amount',
            CalendarInterval::Day,
        ))->toRaw()['span']['aggs']['series']['date_histogram']['extended_bounds'];

        $this->assertSame(1704067200000, $bounds['min']);
        $this->assertSame(1704067200000, $bounds['max']);
    }

    /**
     * @test
     */
    public function extended_bounds_before_the_epoch_keep_the_millis(): void
    {
        $bounds = function (string $from, string $to): array {
            $start = new DateTimeImmutable($from);
            $end = new DateTimeImmutable($to);
            $raw = (new Trend('span', 'created_at', $start, $end, 'Y-m-d\TH:i:s.vP', Metric::Sum, 'amount', CalendarInterval::Day))
                ->toRaw()['span'];

            return $raw['aggs']['series']['date_histogram']['extended_bounds'];
        };

        $hundred = $bounds('1969-12-31T23:59:59.000000+00:00', '1969-12-31T23:59:59.100000+00:00');
        $eight = $bounds('1969-12-31T23:59:59.100000+00:00', '1969-12-31T23:59:59.900000+00:00');
        $day = $bounds('1960-01-01T00:00:00.250000+00:00', '1960-02-01T00:00:00.000000+00:00');

        $this->assertSame(-1000, $hundred['min']);
        $this->assertSame(-901, $hundred['max']);
        $this->assertSame(-900, $eight['min']);
        $this->assertSame(-101, $eight['max']);
        $this->assertSame(-315619199750, $day['min']);
        $this->assertSame(-312940800001, $day['max']);
        $this->assertGreaterThanOrEqual($day['min'], $day['max']);

        $from = new DateTimeImmutable('1969-12-31T23:59:59.100000+00:00');
        $to = new DateTimeImmutable('1969-12-31T23:59:59.900000+00:00');
        $properties = new NewProperties;
        $properties->date('created_at');

        $previous = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), $properties, 'created_at'))
            ->format('Y-m-d\TH:i:s.vP')
            ->kpiDelta('delta', Metric::Count, window: [$from, $to])
            ->toSearch()
            ->toMultiSearch()[1]['aggs']['delta']['aggs']['previous']['filter']['range']['created_at'];

        $this->assertSame('1969-12-31T23:59:58.300+00:00', $previous['gte']);
        $this->assertSame('1969-12-31T23:59:59.100+00:00', $previous['lt']);
    }

    /**
     * @test
     */
    public function a_population_name_does_not_replace_a_metric(): void
    {
        $ratingKey = 'population_'.substr(md5('amount'), 0, 12);
        $widget = new GroupedMetrics(
            'groups',
            'created_at',
            new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2024-02-01T00:00:00+00:00'),
            'Y-m-d',
            'product',
            [
                ['key' => $ratingKey, 'label' => 'Rating', 'metric' => Metric::Avg, 'field' => 'rating'],
                ['key' => 'amount', 'label' => 'Amount', 'metric' => Metric::Avg, 'field' => 'amount'],
            ],
            'amount',
            10,
            'desc',
        );

        $aggs = $widget->toRaw()['groups']['aggs']['groups']['aggs'];
        $amountCounts = array_filter(
            $aggs,
            fn (array $body): bool => ($body['value_count']['field'] ?? null) === 'amount',
        );

        $this->assertSame(['avg' => ['field' => 'rating']], $aggs[$ratingKey]);
        $this->assertSame(['avg' => ['field' => 'amount']], $aggs['amount']);
        $this->assertCount(1, $amountCounts);
        $this->assertArrayNotHasKey($ratingKey, $amountCounts);
    }

    /**
     * @test
     */
    public function a_grouped_boolean_key_stays_true_or_false(): void
    {
        $from = new DateTimeImmutable('2024-01-01T00:00:00+00:00');
        $to = new DateTimeImmutable('2024-02-01T00:00:00+00:00');
        $ranked = new GroupedMetrics(
            'groups',
            'created_at',
            $from,
            $to,
            'Y-m-d',
            'active',
            [
                ['key' => 'orders', 'label' => 'Orders', 'metric' => Metric::Count, 'field' => ''],
            ],
            'orders',
            10,
            'desc',
        );

        $rows = $ranked->extract([
            'groups' => [
                'groups' => [
                    'buckets' => [
                        ['key' => true, 'doc_count' => 4],
                        ['key' => false, 'doc_count' => 2],
                        ['key' => 0, 'doc_count' => 3],
                        ['key' => 1, 'doc_count' => 1],
                    ],
                ],
            ],
        ])['rows'];

        $this->assertSame(['true', '0', 'false', '1'], array_column($rows, 'key'));

        $aliased = new GroupedMetrics(
            'groups',
            'created_at',
            $from,
            $to,
            'Y-m-d',
            'active',
            [
                ['key' => 'orders', 'label' => 'Orders', 'metric' => Metric::Count, 'field' => ''],
            ],
            'orders',
            10,
            'desc',
            0,
            ['Inactive' => [false, true]],
        );
        $alias = 'alias_'.substr(md5('Inactive'), 0, 12);

        $this->assertSame(
            ['Inactive', false, true],
            $aliased->toRaw()['groups']['aggs'][$alias]['filter']['terms']['active'],
        );
    }

    /**
     * @test
     */
    public function a_trimmed_alias_label_keeps_every_value(): void
    {
        $widget = new GroupedMetrics(
            'groups',
            'created_at',
            new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2024-02-01T00:00:00+00:00'),
            'Y-m-d',
            'product',
            [
                ['key' => 'orders', 'label' => 'Orders', 'metric' => Metric::Count, 'field' => ''],
            ],
            'orders',
            10,
            'desc',
            0,
            [
                'EU' => ['Germany'],
                ' EU' => ['France'],
            ],
        );
        $aggs = $widget->toRaw()['groups']['aggs'];
        $alias = 'alias_'.substr(md5('EU'), 0, 12);
        $values = ['EU', 'Germany', 'France'];

        $this->assertSame($values, $aggs['groups']['terms']['exclude']);
        $this->assertSame($values, $aggs[$alias]['filter']['terms']['product']);

        $row = $widget->extract([
            'groups' => [
                'groups' => ['buckets' => []],
                $alias => ['doc_count' => 2],
            ],
        ])['rows'][0];

        $this->assertSame('EU', $row['key']);
        $this->assertSame($values, $row['source_keys']);
    }

    /**
     * @test
     */
    public function a_previous_window_keeps_the_same_duration(): void
    {
        $properties = new NewProperties;
        $properties->date('created_at');

        $from = new DateTimeImmutable('2024-01-01T00:00:00.250000+00:00');
        $to = new DateTimeImmutable('2024-01-01T00:00:00.900000+00:00');
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), $properties, 'created_at'))
            ->format('Y-m-d\TH:i:s.vP')
            ->kpiDelta('delta', Metric::Count, window: [$from, $to]);

        $previous = $analytics->toSearch()->toMultiSearch()[1]['aggs']['delta']['aggs']['previous']['filter']['range']['created_at'];

        $this->assertSame('2023-12-31T23:59:59.600+00:00', $previous['gte']);
        $this->assertSame('2024-01-01T00:00:00.250+00:00', $previous['lt']);

        $dayFrom = new DateTimeImmutable('2024-01-01T00:00:00+00:00');
        $dayTo = new DateTimeImmutable('2024-01-03T00:00:00+00:00');
        $days = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), $properties, 'created_at'))
            ->format('Y-m-d\TH:i:s.vP')
            ->kpiDelta('delta', Metric::Count, window: [$dayFrom, $dayTo]);
        $dayPrevious = $days->toSearch()->toMultiSearch()[1]['aggs']['delta']['aggs']['previous']['filter']['range']['created_at'];

        $this->assertSame('2023-12-30T00:00:00.000+00:00', $dayPrevious['gte']);
        $this->assertSame('2024-01-01T00:00:00.000+00:00', $dayPrevious['lt']);
    }

    /**
     * @test
     */
    public function the_default_window_keeps_the_end_time(): void
    {
        $properties = new NewProperties;
        $properties->date('created_at');

        $to = new DateTimeImmutable('2024-01-31T12:00:00.750000+00:00');
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), $properties, 'created_at', null, $to))
            ->format('Y-m-d\TH:i:s.vP')
            ->kpi('n', Metric::Count);
        $range = $analytics->toSearch()->toMultiSearch()[1]['aggs']['n']['filter']['range']['created_at'];

        $this->assertSame('2024-01-01T12:00:00.750+00:00', $range['gte']);
        $this->assertSame('2024-01-31T12:00:00.750+00:00', $range['lt']);
    }

    /**
     * @test
     */
    public function a_grouped_metric_label_zero_is_kept(): void
    {
        $properties = new NewProperties;
        $properties->keyword('product');

        $analytics = new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), $properties, 'created_at');
        $analytics->groupedMetrics('groups', 'product', [
            ['key' => 'orders', 'label' => '0', 'metric' => Metric::Count],
            ['key' => 'revenue', 'label' => '   ', 'metric' => Metric::Sum, 'field' => 'amount'],
        ]);
        $metrics = $analytics->formatResponse([])['groups']['metrics'];

        $this->assertSame('0', $metrics[0]['label']);
        $this->assertSame('orders', $metrics[0]['key']);
        $this->assertSame('revenue', $metrics[1]['label']);
    }

    /** @test */
    public function a_grouped_metric_key_count_keeps_its_order_key(): void
    {
        $average = $this->groupedMetricsWidget([
            ['key' => 'count', 'label' => 'Average', 'metric' => Metric::Avg, 'field' => 'amount'],
        ], 'count', 1);
        $terms = $average->toRaw()['groups']['aggs']['groups'];

        $this->assertSame([['metric_present' => 'desc'], ['count' => 'desc']], $terms['terms']['order']);
        $this->assertSame(1, $terms['aggs']['limit']['bucket_sort']['size']);
        $this->assertSame(['avg' => ['field' => 'amount']], $terms['aggs']['count']);

        $counted = $this->groupedMetricsWidget([
            ['key' => 'count', 'label' => 'Count', 'metric' => Metric::Count, 'field' => ''],
        ], 'count', 1);

        $this->assertSame(['_count' => 'desc'], $counted->toRaw()['groups']['aggs']['groups']['terms']['order']);
    }

    /** @test */
    public function a_grouped_metric_key_keeps_its_aggregation(): void
    {
        $widget = $this->groupedMetricsWidget([
            ['key' => 'limit', 'label' => 'Average', 'metric' => Metric::Avg, 'field' => 'amount'],
            ['key' => 'min_count', 'label' => 'Total', 'metric' => Metric::Sum, 'field' => 'amount'],
            ['key' => 'revenue', 'label' => 'Revenue', 'metric' => Metric::Sum, 'field' => 'amount'],
        ], 'revenue', 10, 2);
        $aggs = $widget->toRaw()['groups']['aggs']['groups']['aggs'];

        $this->assertSame(['avg' => ['field' => 'amount']], $aggs['limit']);
        $this->assertSame(['sum' => ['field' => 'amount']], $aggs['min_count']);
        $this->assertSame(['sum' => ['field' => 'amount']], $aggs['revenue']);

        $pipelines = array_keys(array_filter(
            $aggs,
            fn (array $agg): bool => isset($agg['bucket_sort']) || isset($agg['bucket_selector']),
        ));

        $this->assertCount(1, $pipelines);
        $this->assertSame([], array_intersect($pipelines, ['limit', 'min_count', 'revenue']));

        $plain = $this->groupedMetricsWidget([
            ['key' => 'revenue', 'label' => 'Revenue', 'metric' => Metric::Sum, 'field' => 'amount'],
        ], 'revenue', 10, 2)->toRaw()['groups']['aggs']['groups']['aggs'];

        $this->assertArrayHasKey('bucket_sort', $plain['limit']);
    }

    /**
     * @param  list<array{key: string, label: string, metric: Metric, field: string}>  $metrics
     */
    private function groupedMetricsWidget(array $metrics, string $sortMetric, int $limit, int $minCount = 0): GroupedMetrics
    {
        return new GroupedMetrics(
            'groups',
            'created_at',
            new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2024-02-01T00:00:00+00:00'),
            'Y-m-d',
            'product',
            $metrics,
            $sortMetric,
            $limit,
            'desc',
            $minCount,
        );
    }

    /**
     * @test
     */
    public function a_table_sort_named_zero_stays_in_the_clause(): void
    {
        $properties = new NewProperties;
        $properties->date('created_at');
        $properties->keyword('0');
        $properties->keyword('price');

        $zero = new Analytics(
            new NewQuery($this->disconnectedConnection(), 'sales'),
            $properties,
            'created_at',
            new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2024-02-01T00:00:00+00:00'),
        );
        $zero->table('rows', sort: '0:asc');

        $sort = $zero->toSearch()->toMultiSearch()[1]['aggs']['rows']['aggs']['hits']['top_hits']['sort'];

        $this->assertSame('[{"0":"asc"}]', json_encode($sort));

        $price = new Analytics(
            new NewQuery($this->disconnectedConnection(), 'sales'),
            $properties,
            'created_at',
            new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2024-02-01T00:00:00+00:00'),
        );
        $price->table('rows', sort: 'price:asc');

        $named = $price->toSearch()->toMultiSearch()[1]['aggs']['rows']['aggs']['hits']['top_hits']['sort'];

        $this->assertSame('[{"price":"asc"}]', json_encode($named));
    }

    /**
     * @test
     */
    public function a_later_from_and_to_drop_the_named_period(): void
    {
        $properties = new NewProperties;
        $properties->date('created_at');
        $properties->number('amount');

        $named = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), $properties, 'created_at'))
            ->timezone('UTC')
            ->format('Y-m-d\TH:i:sP')
            ->range(Period::ThisMonth)
            ->kpiDelta('revenue', Metric::Sum, 'amount');
        $namedAggs = $named->toSearch()->toMultiSearch()[1]['aggs'];
        $currentFrom = new DateTimeImmutable($namedAggs['revenue']['aggs']['current']['filter']['range']['created_at']['gte']);
        $previousFrom = new DateTimeImmutable($namedAggs['revenue']['aggs']['previous']['filter']['range']['created_at']['gte']);

        $this->assertEquals($currentFrom->modify('-1 month'), $previousFrom);

        $custom = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), $properties, 'created_at'))
            ->timezone('UTC')
            ->format('Y-m-d\TH:i:sP')
            ->range(Period::ThisMonth)
            ->from(new DateTimeImmutable('2026-11-10T00:00:00+00:00'))
            ->to(new DateTimeImmutable('2026-11-12T00:00:00+00:00'))
            ->kpiDelta('revenue', Metric::Sum, 'amount');
        $aggs = $custom->toSearch()->toMultiSearch()[1]['aggs'];

        $this->assertSame('2026-11-10T00:00:00+00:00', $aggs['revenue']['aggs']['current']['filter']['range']['created_at']['gte']);
        $this->assertSame('2026-11-12T00:00:00+00:00', $aggs['revenue']['aggs']['current']['filter']['range']['created_at']['lt']);
        $this->assertSame('2026-11-08T00:00:00+00:00', $aggs['revenue']['aggs']['previous']['filter']['range']['created_at']['gte']);
        $this->assertSame('2026-11-10T00:00:00+00:00', $aggs['revenue']['aggs']['previous']['filter']['range']['created_at']['lt']);
    }

    /**
     * @test
     */
    public function a_kpi_delta_keeps_its_windows_beside_those_names(): void
    {
        $properties = new NewProperties;
        $properties->date('created_at');
        $properties->number('amount');
        $properties->keyword('status');

        $from = new DateTimeImmutable('2024-01-01T00:00:00+00:00');
        $to = new DateTimeImmutable('2024-02-01T00:00:00+00:00');
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), $properties, 'created_at', $from, $to))
            ->kpiDelta('revenue', Metric::Sum, 'amount', filter: new Term('status', 'paid'))
            ->kpi('revenue_current', Metric::Count, 'created_at')
            ->kpi('revenue_previous', Metric::Sum, 'amount');
        $aggs = $analytics->toSearch()->toMultiSearch()[1]['aggs'];

        $current = $aggs['revenue']['aggs']['current']['filter']['bool']['must'][0]['range']['created_at'];
        $previous = $aggs['revenue']['aggs']['previous']['filter']['bool']['must'][0]['range']['created_at'];

        $this->assertSame('2024-01-01T00:00:00+00:00', $current['gte']);
        $this->assertSame('2024-02-01T00:00:00+00:00', $current['lt']);
        $this->assertSame('paid', $aggs['revenue']['filter']['term']['status']['value']);
        $this->assertSame(['sum' => ['field' => 'amount']], $aggs['revenue']['aggs']['current']['aggs']['metric']);
        $this->assertSame('2023-12-01T00:00:00+00:00', $previous['gte']);
        $this->assertSame('2024-01-01T00:00:00+00:00', $previous['lt']);
        $this->assertSame(['sum' => ['field' => 'amount']], $aggs['revenue']['aggs']['previous']['aggs']['metric']);
        $this->assertSame('created_at', $aggs['revenue_current']['aggs']['metric']['value_count']['field']);
        $this->assertSame(['sum' => ['field' => 'amount']], $aggs['revenue_previous']['aggs']['metric']);

        $formatted = $analytics->formatResponse([
            'aggregations' => [
                'revenue' => [
                    'current' => ['metric' => ['value' => 40]],
                    'previous' => ['metric' => ['value' => 80]],
                ],
                'revenue_current' => ['metric' => ['value' => 4]],
                'revenue_previous' => ['metric' => ['value' => 90]],
            ],
        ]);

        $this->assertSame(40, $formatted['revenue']['value']);
        $this->assertSame(80, $formatted['revenue']['previous']);
        $this->assertSame(4, $formatted['revenue_current']['value']);
        $this->assertSame(90, $formatted['revenue_previous']['value']);
    }

    /**
     * @test
     */
    public function a_named_month_delta_uses_the_previous_calendar_month(): void
    {
        $now = new DateTimeImmutable('2017-10-15 12:00:00', new DateTimeZone('America/Asuncion'));
        [$lastFrom, $lastTo] = Period::LastMonth->resolve($now);
        $analytics = $this->pinnedAnalytics('America/Asuncion', Period::ThisMonth, $now)
            ->kpiDelta('revenue', Metric::Sum, 'amount');
        $previous = $analytics->toSearch()->toMultiSearch()[1]['aggs']['revenue']['aggs']['previous']['filter']['range']['created_at'];

        $this->assertSame($lastFrom->format(DateTimeInterface::ATOM), $previous['gte']);
        $this->assertSame($lastTo->format(DateTimeInterface::ATOM), $previous['lt']);
    }

    /**
     * @test
     */
    public function a_rolling_delta_keeps_the_elapsed_length(): void
    {
        $now = new DateTimeImmutable('2024-04-07 12:00:00', new DateTimeZone('Europe/London'));
        [$from, $to] = Period::Last7Days->resolve($now);
        $analytics = $this->pinnedAnalytics('Europe/London', Period::Last7Days, $now)
            ->kpiDelta('orders', Metric::Count);
        $body = $analytics->toSearch()->toMultiSearch()[1]['aggs']['orders'];
        $current = $body['aggs']['current']['filter']['range']['created_at'];
        $previous = $body['aggs']['previous']['filter']['range']['created_at'];
        $elapsed = static function (string $gte, string $lt): int {
            $start = new DateTimeImmutable($gte);
            $end = new DateTimeImmutable($lt);

            return ($end->getTimestamp() - $start->getTimestamp()) * 1000;
        };

        $this->assertSame($from->format(DateTimeInterface::ATOM), $current['gte']);
        $this->assertSame($to->format(DateTimeInterface::ATOM), $current['lt']);
        $this->assertSame($current['gte'], $previous['lt']);
        $this->assertSame(7 * 24 * 60 * 60 * 1000, $elapsed($current['gte'], $current['lt']));
        $this->assertSame(7 * 24 * 60 * 60 * 1000, $elapsed($previous['gte'], $previous['lt']));
    }

    /**
     * @test
     */
    public function a_kpi_delta_filter_reaches_the_previous_window(): void
    {
        $properties = new NewProperties;
        $properties->date('created_at');
        $properties->keyword('status');

        $analytics = (new Analytics(
            new NewQuery($this->disconnectedConnection(), 'sales'),
            $properties,
            'created_at',
            new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2024-02-01T00:00:00+00:00'),
        ))->kpiDelta('refunds', Metric::Count, filter: "status:'refunded'");
        $refunds = $analytics->toSearch()->toMultiSearch()[1]['aggs']['refunds'];
        $previous = json_encode($refunds['aggs']['previous']['filter']);

        $this->assertStringNotContainsString('2024-02-01', json_encode($refunds['filter']));
        $this->assertStringContainsString('refunded', $previous);
        $this->assertStringContainsString('2023-12-01T00:00:00+00:00', $previous);
        $this->assertStringContainsString('2024-01-01T00:00:00+00:00', $previous);
    }

    private function pinnedAnalytics(string $zone, Period $period, DateTimeImmutable $now): Analytics
    {
        $properties = new NewProperties;
        $properties->datetime('created_at');
        $properties->number('amount');

        $analytics = new class(new NewQuery($this->disconnectedConnection(), 'sales'), $properties, 'created_at') extends Analytics
        {
            public function pin(Period $period, DateTimeImmutable $now): static
            {
                [$this->from, $this->to] = $period->resolve($now);
                $this->period = $period;
                $this->compiledSearch = null;

                return $this;
            }
        };

        return $analytics->timezone($zone)->pin($period, $now);
    }

    /** @test */
    public function fractional_window_reaches_hit_and_widget_queries(): void
    {
        $analytics = new Analytics(
            new NewQuery($this->disconnectedConnection(), 'sales'),
            new NewProperties,
            'created_at',
            $this->date('2024-01-01T12:00:00.125250+00:00'),
            $this->date('2024-01-01T12:00:00.750+00:00'),
        );
        $analytics->kpi('result', Metric::Count);

        $expected = ['gte' => '2024-01-01T12:00:00.125250+00:00', 'lt' => '2024-01-01T12:00:00.750+00:00'];
        $this->assertSame($expected, array_intersect_key($analytics->hitsQuery('')->toRaw()['range']['created_at'], $expected));
        $range = $analytics->toSearch()->toMultiSearch()[1]['aggs']['result']['filter']['range']['created_at'];
        $this->assertSame($expected, array_intersect_key($range, $expected));
    }

    /** @test */
    public function table_sort_uses_the_mapped_nested_sort_field(): void
    {
        $properties = new NewProperties;
        $properties->nested('items', fn (NewProperties $items) => $items->text('name')->makeSortable());

        $analytics = new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), $properties, 'created_at');
        $analytics->table('rows', sort: 'items.name:asc');

        $sort = $analytics->toSearch()->toMultiSearch()[1]['aggs']['rows']['aggs']['hits']['top_hits']['sort'];

        $this->assertSame([['items.name.sortable' => ['nested' => ['path' => 'items'], 'order' => 'asc']]], $sort);
    }

    /** @test */
    public function nested_analytics_filters_and_sorts_return_the_matching_rows(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->keyword('name');
            $props->nested('items', function (NewProperties $items): void {
                $items->keyword('sku');
                $items->number('price');
            });
        }, [
            new Document(['created_at' => '2024-01-01', 'name' => 'expensive', 'items' => [['sku' => 'A', 'price' => 30]]]),
            new Document(['created_at' => '2024-01-01', 'name' => 'cheap', 'items' => [['sku' => 'A', 'price' => 20]]]),
            new Document(['created_at' => '2024-01-01', 'name' => 'excluded', 'items' => [['sku' => 'B', 'price' => 10]]]),
        ]);

        $result = $index->analytics('created_at', $this->date('2024-01-01'), $this->date('2024-01-02'))
            ->filters("items.sku:'A'")
            ->table('rows', sort: 'items.price:asc')
            ->get();

        $this->assertSame(['cheap', 'expensive'], array_column(array_column($result['rows']['rows'], 'document'), 'name'));
    }

    /** @test */
    public function grouped_metrics_rank_eligible_groups_before_limiting(): void
    {
        $index = $this->createSalesIndex();
        $index->merge(array_map(
            fn (int $i): Document => new Document(['created_at' => '2024-01-02', 'product' => 'singleton-'.$i, 'amount' => 1000]),
            range(1, 101),
        ), refresh: true);

        $result = $index->analytics('created_at', $this->date('2024-01-01'), $this->date('2024-01-04'))
            ->groupedMetrics('groups', 'product', [
                ['key' => 'revenue', 'metric' => Metric::Sum, 'field' => 'amount'],
            ], sortMetric: 'revenue', limit: 1, minCount: 2)
            ->get();

        $this->assertSame(['A'], array_column($result['groups']['rows'], 'key'));
    }

    /** @test */
    public function grouped_metric_maps_preserve_zero_keys_in_json(): void
    {
        $widget = $this->groupedMetricsWidget([
            ['key' => '0', 'label' => 'Orders', 'metric' => Metric::Count, 'field' => ''],
        ], '0', 10);
        $row = $widget->extract(['groups' => ['groups' => ['buckets' => [['key' => 'A', 'doc_count' => 3]]]]])['rows'][0];

        $this->assertSame('{"0":3}', json_encode($row['metrics']));
        $this->assertSame('{"0":{"document_count":3,"value_count":3,"field":""}}', json_encode($row['metric_populations']));
    }

    /** @test */
    public function retention_omits_periods_before_the_cohort(): void
    {
        $widget = new Retention('retention', 'created_at', $this->date('2024-01-01'), $this->date('2024-01-04'), 'Y-m-d', 'signup_at', 'user', CalendarInterval::Day);
        $result = $widget->extract(['retention' => ['cohorts' => ['buckets' => [[
            'key' => 1704153600000,
            'key_as_string' => '2024-01-02',
            'size' => ['value' => 1],
            'periods' => ['buckets' => [
                ['key' => 1704067200000, 'key_as_string' => '2024-01-01'],
                ['key' => 1704153600000, 'key_as_string' => '2024-01-02', 'users' => ['value' => 1]],
                ['key' => 1704240000000, 'key_as_string' => '2024-01-03'],
            ]],
        ]]]]]);

        $this->assertSame([
            ['label' => '2024-01-02', 'value' => 1],
            ['label' => '2024-01-03', 'value' => 0],
        ], $result['cohorts'][0]['periods']);
    }

    /** @test */
    public function a_rolling_period_starts_at_local_midnight_when_tomorrow_has_a_gap(): void
    {
        $now = new DateTimeImmutable('2024-04-25 12:00:00', new DateTimeZone('Africa/Cairo'));

        $periods = [
            [Period::Last7Days, '2024-04-19 00:00'],
            [Period::Last30Days, '2024-03-27 00:00'],
            [Period::Last90Days, '2024-01-27 00:00'],
        ];

        foreach ($periods as [$period, $expectedStart]) {
            [$from, $to] = $period->resolve($now);

            $this->assertSame($expectedStart, $from->format('Y-m-d H:i'));
            $this->assertSame('2024-04-26 01:00', $to->format('Y-m-d H:i'));
        }
    }

    /** @test */
    public function a_fractional_exclusive_end_includes_its_millisecond_bucket(): void
    {
        $trend = new Trend('volume', 'created_at', $this->date('2024-01-01T00:00:00.000100Z'), $this->date('2024-01-01T00:00:00.001500Z'), DateTimeInterface::ATOM, Metric::Count, 'created_at', '1ms');

        $this->assertSame([
            'min' => 1704067200000,
            'max' => 1704067200001,
        ], $trend->toRaw()['volume']['aggs']['series']['date_histogram']['extended_bounds']);
    }

    /** @test */
    public function a_delta_keeps_the_duration_of_a_microsecond_window(): void
    {
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'created_at'))
            ->from($this->date('2024-01-01T00:00:00.000250Z'))
            ->to($this->date('2024-01-01T00:00:00.000900Z'))
            ->kpiDelta('volume', Metric::Count);

        $range = $analytics->toMultiSearch()[1]['aggs']['volume']['aggs']['previous']['filter']['range']['created_at'];

        $this->assertSame('2023-12-31T23:59:59.999600+00:00', $range['gte']);
        $this->assertSame('2024-01-01T00:00:00.000250+00:00', $range['lt']);
    }

    /** @test */
    public function retention_only_reports_cohorts_that_have_activity(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->date('signup_at');
            $props->keyword('user_id');
        }, [
            new Document(['created_at' => '2024-01-03', 'signup_at' => '2024-01-01', 'user_id' => 'a']),
            new Document(['created_at' => '2024-01-03', 'signup_at' => '2024-01-03', 'user_id' => 'b']),
        ]);

        $cohorts = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-05'))
            ->retention('users', 'signup_at', 'user_id')
            ->get()['users']['cohorts'];

        $this->assertSame([1, 1], array_column($cohorts, 'size'));
        $this->assertSame([0, 0, 1, 0], array_column($cohorts[0]['periods'], 'value'));
    }

    /** @test */
    public function count_counts_documents_in_kpis_rankings_and_pipelines(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->datetime('created_at');
            $props->keyword('group');
            $props->keyword('other_group');
        }, [
            new Document(['created_at' => ['2024-01-01T00:00:00Z', '2024-01-01T01:00:00Z', '2024-01-01T02:00:00Z'], 'group' => 'a', 'other_group' => 'a']),
            new Document(['created_at' => '2024-01-01T00:00:00Z', 'group' => 'b', 'other_group' => 'b']),
            new Document(['created_at' => '2024-01-02T00:00:00Z', 'group' => 'b', 'other_group' => 'b']),
        ]);

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01T00:00:00Z'))
            ->to($this->date('2024-01-03'))
            ->kpi('events', Metric::Count)
            ->breakdown('groups', 'group', Metric::Count, limit: 1)
            ->multiBreakdown('pairs', ['group', 'other_group'], Metric::Count, limit: 1)
            ->unionBreakdown('union', ['group', 'other_group'], Metric::Count, limit: 1)
            ->cumulative('running', Metric::Count, '')
            ->get();

        $this->assertSame(3, $result['events']['value']);
        $this->assertSame('b', $result['groups']['rows'][0]['key']);
        $this->assertSame(['b', 'b'], $result['pairs']['rows'][0]['key_values']);
        $this->assertSame('b', $result['union']['rows'][0]['key']);
        $this->assertEquals([2, 3], array_column($result['running']['series'], 'value'));
    }

    /** @test */
    public function grouped_count_measures_the_requested_field(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->keyword('group');
            $props->keyword('code');
        }, [
            new Document(['created_at' => '2024-01-01', 'group' => 'a']),
            new Document(['created_at' => '2024-01-01', 'group' => 'a']),
            new Document(['created_at' => '2024-01-01', 'group' => 'b', 'code' => ['x', 'y']]),
        ]);

        $row = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-02'))
            ->groupedMetrics('codes', 'group', [
                ['key' => 'codes', 'metric' => Metric::Count, 'field' => 'code'],
            ], 'codes', limit: 1)
            ->get()['codes']['rows'][0];

        $this->assertSame('b', $row['key']);
        $this->assertSame(2, $row['metrics']['codes']);
        $this->assertSame(['document_count' => 1, 'value_count' => 2, 'field' => 'code'], $row['metric_populations']['codes']);
    }

    /** @test */
    public function grouped_metrics_rank_reserved_keys_by_their_metric(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->keyword('group');
            $props->number('amount');
        }, [
            new Document(['created_at' => '2024-01-01', 'group' => 'a', 'amount' => 1]),
            new Document(['created_at' => '2024-01-01', 'group' => 'a', 'amount' => 2]),
            new Document(['created_at' => '2024-01-01', 'group' => 'b', 'amount' => 100]),
            new Document(['created_at' => '2024-01-01', 'group' => 'z', 'amount' => 50]),
        ]);

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-02'))
            ->groupedMetrics('by_count', 'group', [['key' => '_count', 'metric' => Metric::Sum, 'field' => 'amount']], '_count', limit: 1)
            ->groupedMetrics('by_key', 'group', [['key' => '_key', 'metric' => Metric::Sum, 'field' => 'amount']], '_key', limit: 1)
            ->get();

        $this->assertSame('b', $result['by_count']['rows'][0]['key']);
        $this->assertSame('b', $result['by_key']['rows'][0]['key']);
    }

    /** @test */
    public function a_breakdown_count_population_matches_the_named_field(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->keyword('group');
            $props->keyword('other_group');
            $props->keyword('code');
        }, [
            new Document(['created_at' => '2024-01-01', 'group' => 'a', 'other_group' => 'a', 'code' => 'present']),
            new Document(['created_at' => '2024-01-01', 'group' => 'a']),
        ]);

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-02'))
            ->breakdown('codes', 'group', Metric::Count, 'code')
            ->unionBreakdown('all_codes', ['group', 'other_group'], Metric::Count, 'code')
            ->get();

        foreach (['codes', 'all_codes'] as $widget) {
            $row = $result[$widget]['rows'][0];

            $this->assertEquals(1, $row['value']);
            $this->assertSame(['document_count' => 2, 'value_count' => 1, 'field' => 'code'], $row['population']);
        }
    }

    /** @test */
    public function bucket_aliases_match_exact_values_with_whitespace(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->keyword('group');
        }, [
            new Document(['created_at' => '2024-01-01', 'group' => 'a']),
            new Document(['created_at' => '2024-01-01', 'group' => ' a ']),
            new Document(['created_at' => '2024-01-01', 'group' => ' a ']),
        ]);

        $aliases = ['Padded' => [' a ']];
        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-02'))
            ->groupedMetrics('grouped', 'group', [], bucketAliases: $aliases)
            ->breakdown('breakdown', 'group', Metric::Count, bucketAliases: $aliases)
            ->get();

        foreach ($result as $widget) {
            $this->assertSame(['Padded', 'a'], array_column($widget['rows'], 'key'));
            $this->assertSame([2, 1], array_column($widget['rows'], 'count'));
        }
    }

    /** @test */
    public function numeric_buckets_accept_a_text_display_alias(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->integer('status');
        }, [
            new Document(['created_at' => '2024-01-01', 'status' => 200]),
            new Document(['created_at' => '2024-01-01', 'status' => 201]),
            new Document(['created_at' => '2024-01-01', 'status' => 404]),
        ]);

        $result = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-02'))
            ->groupedMetrics('grouped', 'status', [], bucketAliases: ['Success' => [200, 201]])
            ->breakdown('breakdown', 'status', Metric::Count, bucketAliases: ['Success' => [200, 201]])
            ->get();

        foreach ($result as $widget) {
            $this->assertSame('Success', $widget['rows'][0]['key']);
            $this->assertSame([2, 1], array_column($widget['rows'], 'count'));
        }
    }

    /** @test */
    public function grouped_boolean_rows_use_false_and_true_labels(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->bool('active');
        }, [
            new Document(['created_at' => '2024-01-01', 'active' => false]),
            new Document(['created_at' => '2024-01-01', 'active' => true]),
        ]);

        $rows = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-02'))
            ->groupedMetrics('states', 'active', [])
            ->get()['states']['rows'];

        $this->assertSame(['false', 'true'], array_column($rows, 'key'));
    }

    /** @test */
    public function an_alias_only_grouped_metric_works_on_a_numeric_field(): void
    {
        $index = $this->indexWith(function (NewProperties $props): void {
            $props->date('created_at');
            $props->number('group');
        }, [
            new Document(['created_at' => '2024-01-01', 'group' => 1]),
            new Document(['created_at' => '2024-01-01', 'group' => 2]),
            new Document(['created_at' => '2024-01-01', 'group' => 3]),
        ]);

        $rows = $index->analytics('created_at')
            ->from($this->date('2024-01-01'))
            ->to($this->date('2024-01-02'))
            ->groupedMetrics('only_aliases', 'group', [['key' => 'count', 'metric' => Metric::Count]], bucketAliases: [10 => [1, 2]], aliasesOnly: true)
            ->get()['only_aliases']['rows'];

        $this->assertSame(['10'], array_column($rows, 'key'));
        $this->assertSame([2], array_column($rows, 'count'));
    }

    /** @test */
    public function calendar_periods_start_at_the_first_occurrence_of_midnight(): void
    {
        $cases = [
            ['America/Havana', '2024-11-03', '2024-11-03T00:00:00-04:00'],
            ['Asia/Amman', '2021-10-29', '2021-10-29T00:00:00+03:00'],
            ['Antarctica/Casey', '2023-03-09', '2023-03-09T00:00:00+11:00'],
        ];

        foreach ($cases as [$zone, $date, $expected]) {
            $day = new DateTimeImmutable($date.' 12:00:00', new DateTimeZone($zone));
            [$start] = Period::Today->resolve($day);
            [, $endOfYesterday] = Period::Today->resolve($day->modify('-1 day'));
            [$startFromTomorrow] = Period::Yesterday->resolve($day->modify('+1 day'));

            $this->assertSame($expected, $start->format(DateTimeInterface::ATOM));
            $this->assertEquals($start, $endOfYesterday);
            $this->assertEquals($start, $startFromTomorrow);
        }

        $zone = new DateTimeZone('America/Havana');
        $analytics = $this->pinnedAnalytics($zone->getName(), Period::ThisMonth, new DateTimeImmutable('2015-12-12', $zone))
            ->kpiDelta('volume', Metric::Count);
        $range = $analytics->toMultiSearch()[1]['aggs']['volume']['aggs']['previous']['filter']['range']['created_at'];

        $this->assertSame('2015-11-01T00:00:00-04:00', $range['gte']);
    }

    /** @test */
    public function percentile_output_preserves_the_requested_order(): void
    {
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'created_at'))
            ->percentiles('duration', 'duration_ms', [90, 12.5, 100, 0]);
        $result = $analytics->formatResponse([
            'aggregations' => ['duration' => ['metric' => ['values' => [
                '0.0' => 5,
                '12.5' => 7,
                '90.0' => 12,
                '100.0' => 20,
            ]]]],
        ]);

        $this->assertSame([90 => 12, '12.5' => 7, 100 => 20, 0 => 5], $result['duration']['percentiles']);
    }

    public function test_close_percentile_keys_stay_distinct(): void
    {
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'date'))
            ->percentiles('duration', 'amount', [50.00000000000001, 50]);
        $rows = $analytics->formatResponse(['aggregations' => [
            'duration' => ['metric' => ['values' => ['50.0' => 3, '50.00000000000001' => 4]]],
        ]]);

        $this->assertSame(['50.00000000000001' => 4, 50 => 3], $rows['duration']['percentiles']);
    }

    public function test_missing_metrics_follow_numeric_values_in_both_directions(): void
    {
        foreach (['asc' => 10, 'desc' => -10] as $direction => $value) {
            $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'date'))
                ->breakdown('rank', 'code', Metric::Avg, 'amount', direction: $direction)
                ->groupedMetrics('grouped', 'code', [['key' => 'avg', 'metric' => Metric::Avg, 'field' => 'amount']], 'avg', direction: $direction);
            $buckets = [];
            foreach (['missing' => null, 'present' => $value] as $key => $metric) {
                $buckets[] = ['key' => $key, 'doc_count' => 1, 'metric' => ['value' => $metric], 'avg' => ['value' => $metric]];
            }

            $groups = ['groups' => ['buckets' => $buckets]];
            $results = $analytics->formatResponse(['aggregations' => ['rank' => $groups, 'grouped' => $groups]]);
            foreach ($results as $widget) {
                $this->assertSame(['present', 'missing'], array_column($widget['rows'], 'key'));
            }
        }
    }

    public function test_large_counts_rank_in_integer_order(): void
    {
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'date'))
            ->breakdown('rank', 'code', Metric::Count)
            ->groupedMetrics('grouped', 'code', []);
        $buckets = [];
        foreach (['a' => 9007199254740992, 'z' => 9007199254740993] as $key => $count) {
            $buckets[] = ['key' => $key, 'doc_count' => $count, 'metric' => ['doc_count' => $count]];
        }

        $groups = ['groups' => ['buckets' => $buckets]];
        $rows = $analytics->formatResponse(['aggregations' => ['rank' => $groups, 'grouped' => $groups]]);
        foreach ($rows as $widget) {
            $this->assertSame(['z', 'a'], array_column($widget['rows'], 'key'));
        }
    }

    public function test_decimal_bucket_labels_keep_the_term_precision(): void
    {
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'date'))
            ->multiBreakdown('pairs', ['code', 'region'], Metric::Count)
            ->groupedMetrics('grouped', 'code', []);
        $key = 1.2345678901234567;
        $result = $analytics->formatResponse(['aggregations' => [
            'pairs' => ['groups' => ['buckets' => [['key' => [$key, 'EU'], 'doc_count' => 1]]]],
            'grouped' => ['groups' => ['buckets' => [['key' => $key, 'doc_count' => 1]]]],
        ]]);

        $this->assertSame('1.2345678901234567 / EU', $result['pairs']['rows'][0]['label']);
        $this->assertSame('1.2345678901234567', $result['grouped']['rows'][0]['key']);
    }

    public function test_decimal_bucket_aliases_target_the_exact_term(): void
    {
        $props = new NewProperties;
        $props->number('code')->double();
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), $props, 'date'))
            ->breakdown('rank', 'code', Metric::Count, bucketAliases: ['Close' => [1.2345678901234567]]);
        $groups = $analytics->toMultiSearch()[1]['aggs']['rank']['aggs'];

        $this->assertSame(['1.2345678901234567'], $groups['groups']['terms']['exclude']);
    }

    public function test_extreme_opposite_metrics_have_a_finite_percentage_change(): void
    {
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'date'))
            ->kpiDelta('delta', Metric::Max, 'value');
        $result = $analytics->formatResponse(['aggregations' => ['delta' => [
            'current' => ['metric' => ['value' => -1e308]],
            'previous' => ['metric' => ['value' => 1e308]],
        ]]]);

        $this->assertSame(-200.0, $result['delta']['change_pct']);
    }

    public function test_a_rise_from_a_negative_previous_value_is_a_positive_change(): void
    {
        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'date'))
            ->kpiDelta('profit', Metric::Sum, 'value');
        $change = fn (float $previous, float $current): float => $analytics->formatResponse(['aggregations' => ['profit' => [
            'current' => ['metric' => ['value' => $current]],
            'previous' => ['metric' => ['value' => $previous]],
        ]]])['profit']['change_pct'];

        $this->assertSame(50.0, $change(-100.0, -50.0));
        $this->assertSame(200.0, $change(-100.0, 100.0));
        $this->assertSame(-50.0, $change(-100.0, -150.0));
        $this->assertSame(-50.0, $change(100.0, 50.0));
    }

    private function runsWithoutCluster(): bool
    {
        return in_array($this->getName(false), [
            'test_invalid_numeric_bucket_aliases_are_ignored',
            'test_table_rejects_multiple_sort_fields',
            'test_table_preserves_sort_for_fields_outside_its_local_mapping',
            'test_grouped_metric_escapes_a_reserved_key_with_a_colliding_alias',
            'test_tied_rankings_preserve_the_elasticsearch_key_order',
            'test_table_serializes_an_empty_source_as_an_object',
            'test_stats_preserves_finite_values_when_the_sum_overflows',
            'test_periods_start_at_the_first_valid_instant_when_a_gap_crosses_midnight',
            'test_a_rolling_window_keeps_its_start_when_tomorrow_is_a_skipped_date',
            'test_a_day_ends_at_the_next_civil_date_even_if_its_wall_time_is_skipped',
            'test_default_analytics_dates_use_utc_in_a_non_utc_process',
            'test_historical_offsets_preserve_analytics_window_instants',
            'test_default_analytics_windows_send_their_own_date_format',
            'test_close_percentile_keys_stay_distinct',
            'test_equal_metrics_keep_elasticsearch_term_order',
            'test_count_checks_the_requested_text_field',
            'test_timezone_after_range_aligns_the_named_window',
            'test_missing_metrics_follow_numeric_values_in_both_directions',
            'test_large_counts_rank_in_integer_order',
            'test_decimal_bucket_labels_keep_the_term_precision',
            'test_decimal_bucket_aliases_target_the_exact_term',
            'test_extreme_opposite_metrics_have_a_finite_percentage_change',
            'test_a_rise_from_a_negative_previous_value_is_a_positive_change',
            'test_table_and_hits_keep_numeric_source_field_names',
            'test_histogram_metric_does_not_request_empty_numeric_buckets',
            'test_histogram_metric_keeps_occupied_buckets_with_no_metric_value',
            'test_boolean_terms_keys_retain_their_type_in_chart_results',
            'test_cumulative_overflow_returns_a_null_point',
            'test_an_overflowed_metric_has_no_finite_value',
            'test_hit_windows_are_recompiled_after_window_changes',
            'test_breakdown_serializes_aliases_with_numeric_labels_as_a_map',
            'calendar_periods_start_at_the_first_occurrence_of_midnight',
            'percentile_output_preserves_the_requested_order',
            'a_rolling_period_starts_at_local_midnight_when_tomorrow_has_a_gap',
            'a_fractional_exclusive_end_includes_its_millisecond_bucket',
            'a_delta_keeps_the_duration_of_a_microsecond_window',
            'fractional_window_reaches_hit_and_widget_queries',
            'table_sort_uses_the_mapped_nested_sort_field',
            'grouped_metric_maps_preserve_zero_keys_in_json',
            'retention_omits_periods_before_the_cohort',
            'a_table_sort_named_zero_stays_in_the_clause',
            'a_later_from_and_to_drop_the_named_period',
            'grouped_metrics_keeps_a_null_sort_metric',
            'histogram_metric_count_reads_metric_value',
            'cumulative_median_uses_the_metric_order_key',
            'to_search_includes_later_widgets_and_filters',
            'analytics_keeps_the_window_when_caller_dates_are_reused',
            'object_text_aggregates_on_the_full_keyword_path',
            'filter_query_and_properties_rebuild_the_compiled_search',
            'a_multi_breakdown_label_joins_the_key_values',
            'extended_bounds_keep_fractional_seconds',
            'a_previous_window_keeps_the_same_duration',
            'the_default_window_keeps_the_end_time',
            'a_grouped_metric_label_zero_is_kept',
            'a_grouped_metric_key_count_keeps_its_order_key',
            'a_grouped_metric_key_keeps_its_aggregation',
            'a_kpi_delta_keeps_its_windows_beside_those_names',
            'a_multi_breakdown_boolean_label_stays_true_or_false',
            'a_named_period_ends_at_the_next_civil_midnight',
            'extended_bounds_before_the_epoch_keep_the_millis',
            'a_population_name_does_not_replace_a_metric',
            'a_grouped_boolean_key_stays_true_or_false',
            'a_trimmed_alias_label_keeps_every_value',
            'a_named_month_delta_uses_the_previous_calendar_month',
            'a_rolling_delta_keeps_the_elapsed_length',
            'a_kpi_delta_filter_reaches_the_previous_window',
            'a_sub_millisecond_window_keeps_its_histogram_bucket',
            'a_multi_breakdown_key_uses_the_caller_field_names',
            'a_percentile_named_zero_stays_in_the_json',
            'test_grouped_metrics_sends_an_explicit_zero_minimum',
        ], true);
    }

    private function disconnectedConnection(): ElasticsearchConnection
    {
        return new class implements ElasticsearchConnection
        {
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
                throw new RuntimeException('no cluster');
            }

            public function isServerless(): bool
            {
                return false;
            }
        };
    }

    /** @test */
    public function analytics_keeps_the_window_when_caller_dates_are_reused(): void
    {
        $from = new DateTime('2024-01-01T00:00:00Z');
        $to = new DateTime('2024-02-01T00:00:00Z');
        $properties = new NewProperties;
        $properties->date('created_at');

        $analytics = new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), $properties, 'created_at', $from, $to);
        $analytics->kpi('january', Metric::Count, window: [$from, $to]);

        $search = $analytics->toSearch();

        $from->modify('+1 month');
        $to->modify('+1 month');

        $hits = $analytics->hitsQuery('')->toRaw()['range']['created_at'];
        $widget = $search->toMultiSearch()[1]['aggs']['january']['filter']['range']['created_at'];

        foreach ([$hits, $widget] as $range) {
            $this->assertSame('2024-01-01T00:00:00+00:00', $range['gte']);
            $this->assertSame('2024-02-01T00:00:00+00:00', $range['lt']);
        }
    }

    public function test_periods_start_at_the_first_valid_instant_when_a_gap_crosses_midnight(): void
    {
        $now = new DateTimeImmutable('1919-03-31 12:00:00', new DateTimeZone('America/Toronto'));
        [$from, $to] = Period::Today->resolve($now);

        $this->assertSame('1919-03-31T00:30:00-04:00', $from->format(DateTimeInterface::ATOM));
        $this->assertSame('1919-04-01T00:00:00-04:00', $to->format(DateTimeInterface::ATOM));
    }

    public function test_a_rolling_window_keeps_its_start_when_tomorrow_is_a_skipped_date(): void
    {
        $now = new DateTimeImmutable('2011-12-29 12:00:00', new DateTimeZone('Pacific/Apia'));
        [$from, $to] = Period::Last7Days->resolve($now);

        $this->assertSame('2011-12-23T00:00:00-10:00', $from->format(DateTimeInterface::ATOM));
        $this->assertSame('2011-12-31T00:00:00+14:00', $to->format(DateTimeInterface::ATOM));
    }

    public function test_a_day_ends_at_the_next_civil_date_even_if_its_wall_time_is_skipped(): void
    {
        $now = new DateTimeImmutable('1919-03-29 23:45:00', new DateTimeZone('America/Toronto'));
        [, $to] = Period::Today->resolve($now);

        $this->assertSame('1919-03-30T00:00:00-05:00', $to->format(DateTimeInterface::ATOM));
    }

    public function test_default_analytics_dates_use_utc_in_a_non_utc_process(): void
    {
        $zone = date_default_timezone_get();
        date_default_timezone_set('Pacific/Kiritimati');

        try {
            $analytics = new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'created_at');
            $range = $analytics->hitsQuery('')->toRaw()['range']['created_at'];
        } finally {
            date_default_timezone_set($zone);
        }

        $this->assertStringEndsWith('+00:00', $range['gte']);
        $this->assertStringEndsWith('+00:00', $range['lt']);
    }

    public function test_historical_offsets_preserve_analytics_window_instants(): void
    {
        $from = new DateTimeImmutable('1900-01-01 12:00:00', new DateTimeZone('Europe/Paris'));
        $analytics = new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'created_at', $from);

        $range = $analytics->hitsQuery('')->toRaw()['range']['created_at'];
        $this->assertSame('1900-01-01T11:50:39+00:00', $range['gte']);
    }

    public function test_default_analytics_windows_send_their_own_date_format(): void
    {
        $analytics = new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'created_at');
        $analytics->kpi('total', Metric::Count);

        $hit = $analytics->hitsQuery('')->toRaw()['range']['created_at'];
        $widget = $analytics->toMultiSearch()[1]['aggs']['total']['filter']['range']['created_at'];
        $this->assertSame('strict_date_optional_time_nanos', $hit['format'] ?? null);
        $this->assertSame($hit['format'], $widget['format'] ?? null);

        $analytics->format('U')->kpi('seconds', Metric::Count);
        $formatted = $analytics->toMultiSearch()[1]['aggs']['seconds']['filter']['range']['created_at'];
        $this->assertArrayNotHasKey('format', $formatted);
    }

    public function test_invalid_numeric_bucket_aliases_are_ignored(): void
    {
        $properties = new NewProperties;
        $properties->number('code');

        $analytics = (new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), $properties, 'date'))
            ->breakdown('rank', 'code', Metric::Count, bucketAliases: [' ' => [8], 'Valid' => [7, [9], ' ']]);

        $aggs = $analytics->toMultiSearch()[1]['aggs']['rank']['aggs'];

        $this->assertCount(2, $aggs);
        $this->assertSame(['7'], $aggs['groups']['terms']['exclude']);
        $this->assertSame(['7'], $aggs['alias_'.substr(md5('Valid'), 0, 12)]['filter']['terms']['code']);
    }

    public function test_table_rejects_multiple_sort_fields(): void
    {
        $analytics = new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'date');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table sort must contain exactly one field.');
        $analytics->table('rows', sort: 'date:asc title:desc');
    }

    public function test_table_preserves_sort_for_fields_outside_its_local_mapping(): void
    {
        $analytics = new Analytics(new NewQuery($this->disconnectedConnection(), 'sales'), new NewProperties, 'date');
        $analytics->table('rows', sort: 'amount:asc');

        $sort = $analytics->toMultiSearch()[1]['aggs']['rows']['aggs']['hits']['top_hits']['sort'];

        $this->assertSame([['amount' => ['order' => 'asc']]], $sort);
    }

    public function test_grouped_metric_escapes_a_reserved_key_with_a_colliding_alias(): void
    {
        $widget = $this->groupedMetricsWidget([
            ['key' => 'key', 'label' => 'Average', 'metric' => Metric::Avg, 'field' => 'amount'],
            ['key' => 'metric_6b6579', 'label' => 'Total', 'metric' => Metric::Sum, 'field' => 'amount'],
        ], 'key', 10);

        $terms = $widget->toRaw()['groups']['aggs']['groups'];

        $this->assertSame(['avg' => ['field' => 'amount']], $terms['aggs']['metric_6b6579_metric']);
        $this->assertSame(['sum' => ['field' => 'amount']], $terms['aggs']['metric_6b6579']);
        $this->assertSame([['metric_present' => 'desc'], ['metric_6b6579_metric' => 'desc']], $terms['terms']['order']);
    }
}
