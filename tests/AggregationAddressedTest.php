<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Analytics\Widgets\Breakdown;
use Sigmie\Analytics\Widgets\Trend;
use Sigmie\Base\Http\Responses\Search;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\Price;
use Sigmie\Parse\FacetParser;
use Sigmie\Query\Aggregations\Bucket\AutoDateHistogram;
use Sigmie\Query\Aggregations\Bucket\MultiTerms;
use Sigmie\Query\Aggregations\Bucket\Nested;
use Sigmie\Query\Aggregations\Bucket\RareTerms;
use Sigmie\Query\Aggregations\Bucket\Sort;
use Sigmie\Query\Aggregations\Bucket\Terms;
use Sigmie\Query\Aggregations\Bucket\UnionTerms;
use Sigmie\Query\Aggregations\Enums\MinimumInterval;
use Sigmie\Query\Aggregations\Metrics\Max;
use Sigmie\Query\Aggregations\Metrics\Min;
use Sigmie\Query\Aggregations\Metrics\Rate;
use Sigmie\Query\Aggregations\Pipeline\SortBucket;
use Sigmie\Query\Aggs;
use Sigmie\Query\Contracts\Aggs as AggsContract;
use Sigmie\Query\Queries\Raw;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Search\Formatters\RawElasticsearchFormat;

class AggregationAddressedTest extends TestCase
{
    public function test_date_alias_uses_explicit_terms_for_custom_formats(): void
    {
        $day = new DateTimeImmutable('2026-11-01');
        $widget = new Breakdown('days', 'date', $day, $day->modify('+1 day'), DateTimeImmutable::ATOM,
            'custom', Metric::Count, 'date', 10, 'desc', ['Opening day' => ['2026/11/01']], new Date('custom', ['yyyy/MM/dd']));

        $this->assertSame(['2026/11/01'], $widget->toRaw()['days']['aggs']['groups']['terms']['exclude']);
    }

    public function test_widget_names_retain_their_public_key_and_use_distinct_valid_aggregation_names(): void
    {
        $day = new DateTimeImmutable('2026-11-01');
        $names = [];
        foreach (['sales[net]', 'sales%5Bnet%5D', 'paid>open', ''] as $label) {
            $widget = new Trend($label, 'date', $day, $day->modify('+1 day'), DateTimeImmutable::ATOM, Metric::Sum, 'amount', '1d');
            $name = array_key_first($widget->toRaw());
            $this->assertMatchesRegularExpression('/^[^\[\]>]+$/D', $name);
            $this->assertSame($label, $widget->name());
            $buckets = [['key_as_string' => '2026-11-01', 'metric' => ['value' => 7]]];
            $this->assertSame(7, $widget->extract([$name => ['series' => ['buckets' => $buckets]]])['series'][0]['value']);
            $names[] = $name;
        }

        $this->assertSame($names, array_unique($names));
    }

    public function test_histogram_script_encodes_parameter_maps_as_objects(): void
    {
        foreach ([[], ['first']] as $params) {
            $histogram = (new Aggs)->dateHistogram('days', 'date', '1d')
                ->script(['source' => 'return 0', 'params' => $params, 'options' => []]);
            $script = $histogram->toRaw()['days']['date_histogram']['script'];

            $this->assertSame(json_encode((object) $params), json_encode($script['params']));
            $this->assertSame('{}', json_encode($script['options']));
        }
    }

    public function test_trend_histogram_script_keeps_historical_offset_seconds(): void
    {
        $from = new DateTimeImmutable('1900-01-01 12:00:00 Europe/Paris');
        $to = $from->modify('+1 minute');
        $trend = new Trend('sales', 'date', $from, $to, DateTimeImmutable::ATOM, Metric::Count, 'date', '1m');
        $params = $trend->toRaw()['sales']['aggs']['series']['date_histogram']['script']['params'];

        $this->assertEquals($from, new DateTimeImmutable($params['from']));
        $this->assertEquals($to, new DateTimeImmutable($params['to']));
    }

    public function test_range_filter_serializes_php_dates_as_range_query_bounds(): void
    {
        $after = new DateTimeImmutable('2025-04-10T12:34:56.123456+00:00');
        $raw = (new Aggs)->rangeFilter('recent', 'created_at', ['>=' => $after])->toRaw();
        $range = $raw['recent']['filter']['range']['created_at'];

        $this->assertSame('2025-04-10T12:34:56.123456+00:00', $range['gte']);
        $this->assertSame('strict_date_optional_time_nanos', $range['format']);
    }

    public function test_range_filter_keeps_expanded_years_and_epoch_millisecond_bounds(): void
    {
        $before = new DateTimeImmutable('+10000-01-01T00:00:00.123456Z');
        $raw = (new Aggs)->rangeFilter('events', 'created_at', ['>=' => 946684800000, '<' => $before])->toRaw();
        $range = $raw['events']['filter']['range']['created_at'];

        $this->assertSame('+10000-01-01T00:00:00.123456+00:00', $range['lt']);
        $this->assertSame(946684800000, $range['gte']);
        $this->assertSame("epoch_millis||uuuu-MM-dd'T'HH:mm:ss.SSSSSSXXX", $range['format']);
    }

    public function test_range_filter_keeps_expanded_years(): void
    {
        $after = new DateTimeImmutable('+10000-04-10T12:34:56.123456+00:00');
        $before = new DateTimeImmutable('+10000-04-11T12:34:56.123456+00:00');
        $raw = (new Aggs)->rangeFilter('recent', 'created_at', ['>=' => $after, '<' => $before])->toRaw();
        $range = $raw['recent']['filter']['range']['created_at'];

        $this->assertSame('+10000-04-10T12:34:56.123456+00:00', $range['gte']);
        $this->assertSame('+10000-04-11T12:34:56.123456+00:00', $range['lt']);
        $this->assertSame("uuuu-MM-dd'T'HH:mm:ss.SSSSSSXXX", $range['format']);
    }

    public function test_analytics_serializes_expanded_years_in_both_filter_and_script(): void
    {
        $from = new DateTimeImmutable('+10000-04-10T12:34:56.123456+00:00');
        $to = $from->modify('+1 hour');
        $trend = (new Trend('trend', 'created_at', $from, $to, DateTimeImmutable::ATOM, Metric::Count, 'created_at', '1h'))->toRaw()['trend'];

        $range = $trend['filter']['range']['created_at'];
        $this->assertSame('+10000-04-10T12:34:56.123456+00:00', $range['gte']);
        $this->assertStringContainsString("uuuu-MM-dd'T'HH:mm:ss.SSSSSSXXX", $range['format']);
        $this->assertSame($range['gte'], $trend['aggs']['series']['date_histogram']['script']['params']['from']);
    }

    public function test_aggregation_sorts_preserve_sparse_clause_order(): void
    {
        $sort = [2 => ['sales' => 'desc'], 4 => ['price' => 'asc']];
        $aggs = new Aggs;
        $aggs->topHits('hits', sort: $sort);
        $aggs->sort('page', $sort);

        $raw = $aggs->toRaw();

        $this->assertSame(array_values($sort), $raw['hits']['top_hits']['sort']);
        $this->assertSame(array_values($sort), $raw['page']['bucket_sort']['sort']);
    }

    public function test_rate_can_use_a_daily_histogram_unit(): void
    {
        $aggs = new Aggs;
        $aggs->dateHistogram('days', 'date', '1d')->aggregate(
            fn (Aggs $daily): Rate => $daily->rate('sales', 'amount', unit: 'day')
        );

        $this->assertSame('day', $aggs->toRaw()['days']['aggs']['sales']['rate']['unit']);
    }

    public function test_sort_fields_with_default_options_encode_as_objects(): void
    {
        $aggs = new Aggs;
        $aggs->topHits('hits', sort: [['_score' => []]]);
        $aggs->sort('page', [['_key' => []]]);

        $raw = $aggs->toRaw();

        $this->assertSame('[{"_score":{}}]', json_encode($raw['hits']['top_hits']['sort']));
        $this->assertSame('[{"_key":{}}]', json_encode($raw['page']['bucket_sort']['sort']));
    }

    public function test_term_filter_preserves_scalar_term_values(): void
    {
        foreach ([false, 0, 2.5] as $term) {
            $raw = (new Aggs)->termFilter('matches', 'field', $term)->toRaw();
            $this->assertSame($term, $raw['matches']['filter']['term']['field']['value']);
        }
    }

    public function test_bucket_sort_accepts_a_named_offset(): void
    {
        $raw = (new Sort('page', ['_key'], from: 1))->toRaw();

        $this->assertSame(1, $raw['page']['bucket_sort']['from']);
    }

    public function test_decimal_percentile_keys_are_readable_through_aggregation_paths(): void
    {
        $body = ['aggregations' => ['prices' => ['values' => ['50.0' => 12.5]]]];
        $response = new Search(body: json_encode($body));
        $formatted = (new RawElasticsearchFormat)->queryResponseRaw($body);

        foreach ([$response, $formatted] as $reader) {
            $this->assertSame(12.5, $reader->aggregation('prices.values.50.0'));
            $this->assertNull($reader->aggregation('prices.values.90.0'));
        }
    }

    public function test_bucket_aggregate_keeps_previous_subaggregations(): void
    {
        $nested = (new Aggs)->nested('items', 'items', fn (Aggs $sub): Min => $sub->min('low', 'items.price'));
        $nested->aggregate(fn (Aggs $sub): Max => $sub->max('high', 'items.price'));

        $this->assertSame(['low', 'high'], array_keys($nested->toRaw()['items']['aggs']));
    }

    public function test_missing_accepts_fractional_and_boolean_field_values(): void
    {
        $aggs = new Aggs;
        $aggs->avg('mean', 'price')->missing(1.25);
        $aggs->terms('flags', 'active')->missing(false);

        $this->assertSame(1.25, $aggs->toRaw()['mean']['avg']['missing']);
        $this->assertFalse($aggs->toRaw()['flags']['terms']['missing']);
    }

    public function test_histograms_without_extended_limits_keep_native_bounds(): void
    {
        $aggs = new Aggs;
        $aggs->histogram('prices', 'price', 5, extendedBounds: []);
        $aggs->dateHistogram('days', 'date', '1d', extendedBounds: []);

        $raw = $aggs->toRaw();

        $this->assertArrayNotHasKey('extended_bounds', $raw['prices']['histogram']);
        $this->assertArrayNotHasKey('extended_bounds', $raw['days']['date_histogram']);
    }

    public function test_unbounded_range_filter_keeps_the_field(): void
    {
        $raw = (new Aggs)->rangeFilter('priced', 'price', [])->toRaw();

        $this->assertSame('{"range":{"price":{}}}', json_encode($raw['priced']['filter']));
    }

    public function test_unbounded_range_bucket_is_an_object(): void
    {
        $raw = (new Aggs)->range('all', 'price', [[]])->toRaw();

        $this->assertSame('[{}]', json_encode($raw['all']['range']['ranges']));
    }

    public function test_nested_accepts_object_valued_subaggregations(): void
    {
        $sub = $this->createStub(AggsContract::class);
        $sub->method('toRaw')->willReturn((object) ['total' => ['sum' => ['field' => 'items.price']]]);

        $raw = (new Nested('items', 'items', $sub))->toRaw();
        $this->assertSame('{"total":{"sum":{"field":"items.price"}}}', json_encode($raw['items']['aggs']));
    }

    public function test_filtered_aggregation_values_encode_as_lists(): void
    {
        $aggs = new Aggs;
        $aggs->terms('brands', 'brand')->exclude([2 => 'a']);
        $aggs->range('prices', 'price', [2 => ['from' => 1]]);
        $aggs->percentiles('cuts', 'price', [2 => 90]);
        $aggs->percentileRanks('ranks', 'price', [2 => 20]);
        $aggs->topHits('hits', sourceIncludes: [2 => 'price'], sourceExcludes: [2 => 'brand']);
        $aggs->composite('page', [2 => ['brand' => ['terms' => ['field' => 'brand']]]]);

        $raw = $aggs->toRaw();

        $this->assertSame(['a'], $raw['brands']['terms']['exclude']);
        $this->assertSame([['from' => 1]], $raw['prices']['range']['ranges']);
        $this->assertSame([90], $raw['cuts']['percentiles']['percents']);
        $this->assertSame([20], $raw['ranks']['percentile_ranks']['values']);
        $this->assertSame(['includes' => ['price'], 'excludes' => ['brand']], $raw['hits']['top_hits']['_source']);
        $this->assertSame([['brand' => ['terms' => ['field' => 'brand']]]], $raw['page']['composite']['sources']);
    }

    public function test_multi_terms_fields_remain_a_list_after_filtering(): void
    {
        $raw = (new Aggs)->multiTerms('pairs', [1 => 'brand', 3 => 'model'])->toRaw();

        $this->assertSame([['field' => 'brand'], ['field' => 'model']], $raw['pairs']['multi_terms']['terms']);
    }

    public function test_union_terms_script_receives_a_list_after_filtering_fields(): void
    {
        $raw = (new Aggs)->unionTerms('labels', [1 => 'brand', 3 => 'model'])->toRaw();

        $this->assertSame(['brand', 'model'], $raw['labels']['terms']['script']['params']['fields']);
    }

    /** @test */
    public function price_facet_uses_the_requested_decimal_interval(): void
    {
        $properties = new NewProperties;
        $properties->price();

        $raw = (new FacetParser($properties))->parse('price:0.5')->toRaw();

        $this->assertSame(0.5, $raw['price']['aggs']['price_histogram']['histogram']['interval']);
    }

    /** @test */
    public function price_facet_keeps_distinct_fractional_bucket_bounds(): void
    {
        $facet = (new Price('price'))->facets(['price_histogram' => ['buckets' => [
            ['key' => 0.0, 'doc_count' => 4],
            ['key' => 0.5, 'doc_count' => 7],
        ]]]);

        $this->assertSame('{"0":4,"0.5":7}', json_encode($facet['histogram']));
    }

    /**
     * @test
     */
    public function term_filter_is_a_term_query_on_the_field(): void
    {
        $raw = (new Aggs)->termFilter('status_filter', 'status', 'active')->toRaw();

        $this->assertSame('active', $raw['status_filter']['filter']['term']['status']['value']);
        $this->assertSame('status', array_key_first($raw['status_filter']['filter']['term']));
    }

    /**
     * @test
     */
    public function a_filter_query_key_named_one_stays(): void
    {
        $json = json_encode((new Aggs)->filter('kept', new Raw('{"1":"kept","2":"also"}'))->toRaw());

        $this->assertStringContainsString('"1":"kept"', $json);
        $this->assertStringContainsString('"2":"also"', $json);

        $term = (new Aggs)->filter('status_filter', new Term('status', 'active'))->toRaw();

        $this->assertSame('active', $term['status_filter']['filter']['term']['status']['value']);
    }

    /**
     * @test
     */
    public function a_range_filter_on_field_zero_keeps_the_field(): void
    {
        $json = json_encode((new Aggs)->rangeFilter('prices', '0', ['>=' => 1])->toRaw());

        $this->assertStringContainsString('"range":{"0":{"gte":1}}', $json);
    }

    /**
     * @test
     */
    public function a_composite_source_named_zero_keeps_its_name(): void
    {
        $json = json_encode((new Aggs)->composite('pages', [
            ['0' => ['terms' => ['field' => 'status']]],
        ], 5)->toRaw());

        $this->assertStringContainsString('{"0":{"terms":{"field":"status"}}}', $json);
    }

    /**
     * @test
     */
    public function a_scripted_metric_param_named_zero_keeps_its_name(): void
    {
        $json = json_encode((new Aggs)->scriptedMetric(
            'count',
            'state.n = 0',
            'state.n += 1',
            'return state.n',
            'return states',
            ['0' => 'paid'],
        )->toRaw());

        $this->assertStringContainsString('"params":{"0":"paid"}', $json);
    }

    /**
     * @test
     */
    public function a_top_hits_sort_named_zero_keeps_the_field(): void
    {
        $json = json_encode((new Aggs)->topHits('hits', sort: [['0' => ['order' => 'asc']]])->toRaw());

        $this->assertStringContainsString('{"0":{"order":"asc"}}', $json);
    }

    /**
     * @test
     */
    public function an_aggregation_meta_named_zero_stays_an_object(): void
    {
        $terms = json_encode((new Aggs)->terms('genres', 'genre')->meta(['0' => 'rank'])->toRaw());
        $max = json_encode((new Aggs)->max('count_max', 'count')->meta(['0' => 'rank'])->toRaw());
        $script = json_encode((new Aggs)->scriptedMetric('count', 'a', 'b', 'c', 'd')->meta(['0' => 'rank'])->toRaw());
        $pipeline = json_encode((new Aggs)->cumulativeSum('running', 'total')->meta(['0' => 'rank'])->toRaw());
        $sort = json_encode((new SortBucket('ranked', 'total', 'desc'))->meta(['0' => 'rank'])->toRaw());

        $this->assertStringContainsString('"meta":{"0":"rank"}', $terms);
        $this->assertStringContainsString('"meta":{"0":"rank"}', $max);
        $this->assertStringContainsString('"meta":{"0":"rank"}', $script);
        $this->assertStringContainsString('"meta":{"0":"rank"}', $pipeline);
        $this->assertStringContainsString('"meta":{"0":"rank"}', $sort);
    }

    /**
     * @test
     */
    public function an_aggregation_order_named_zero_keeps_its_name(): void
    {
        $terms = json_encode((new Aggs)->terms('cats', 'category')->order('0', 'desc')->toRaw());
        $multi = json_encode((new Aggs)->multiTerms('pairs', ['a', 'b'])->order('0', 'asc')->toRaw());
        $union = json_encode((new Aggs)->unionTerms('labels', ['a', 'b'])->order('0', 'desc')->toRaw());

        $this->assertStringContainsString('"order":{"0":"desc"}', $terms);
        $this->assertStringContainsString('"order":{"0":"asc"}', $multi);
        $this->assertStringContainsString('"order":{"0":"desc"}', $union);
    }

    /**
     * @test
     */
    public function a_bucket_sort_field_named_zero_keeps_the_field(): void
    {
        $json = json_encode((new Aggs)->sort('ranked', [['0' => ['order' => 'asc']]])->toRaw());

        $this->assertStringContainsString('{"0":{"order":"asc"}}', $json);
    }

    /**
     * @test
     */
    public function a_bucket_selector_path_named_zero_keeps_its_name(): void
    {
        $json = json_encode((new Aggs)->bucketSelector('keep', ['0' => 'sales>total'], 'params.0 > 1')->toRaw());

        $this->assertStringContainsString('"buckets_path":{"0":"sales>total"}', $json);
    }

    /**
     * @test
     */
    public function auto_date_histogram_missing_stays_on_its_own_key(): void
    {
        $raw = (new AutoDateHistogram('created', 'created_at', 6, MinimumInterval::Day))
            ->missing(0)
            ->toRaw();

        $this->assertSame(0, $raw['created']['auto_date_histogram']['missing']);
        $this->assertArrayNotHasKey('date_histogram', $raw['created']);
    }

    /**
     * @test
     */
    public function rate_keeps_the_field(): void
    {
        $raw = (new Rate('yearly_rate', 'amount'))->toRaw();

        $this->assertSame('amount', $raw['yearly_rate']['rate']['field']);
        $this->assertSame('year', $raw['yearly_rate']['rate']['unit']);
    }

    /**
     * @test
     */
    public function sort_bucket_keeps_meta(): void
    {
        $raw = (new SortBucket('sorted', 'metric', 'desc'))->meta(['label' => 'rank'])->toRaw();

        $this->assertSame(['label' => 'rank'], $raw['sorted']['meta']);
        $this->assertSame('desc', $raw['sorted']['bucket_sort']['sort']['metric']['order']);
    }

    /**
     * @test
     */
    public function a_sort_bucket_path_named_zero_keeps_the_field(): void
    {
        $json = json_encode((new SortBucket('ranked', '0', 'desc'))->toRaw());

        $this->assertStringContainsString('{"0":{"order":"desc"}}', $json);

        $kept = (new SortBucket('ranked', 'metric', 'asc'))->toRaw();

        $this->assertSame('asc', $kept['ranked']['bucket_sort']['sort']['metric']['order']);
    }

    /**
     * @test
     */
    public function rare_terms_sends_max_doc_count(): void
    {
        $rare = new RareTerms('rare', 'tags');
        $rare->maxDocCount(10);

        $this->assertSame(10, $rare->toRaw()['rare']['rare_terms']['max_doc_count']);
    }

    /**
     * @test
     */
    public function empty_nested_omits_aggs(): void
    {
        $aggs = new Aggs;
        $aggs->nested('comments', 'comments', function (): void {});

        $raw = $aggs->toRaw();

        $this->assertArrayNotHasKey('aggs', $raw['comments']);
        $this->assertSame('comments', $raw['comments']['nested']['path']);
    }

    /**
     * @test
     */
    public function size_zero_is_sent(): void
    {
        $terms = (new Terms('tags', 'tag'))->size(0)->toRaw();
        $multi = (new MultiTerms('pair', ['a', 'b']))->size(0)->toRaw();
        $union = (new UnionTerms('either', ['a', 'b']))->size(0)->toRaw();
        $sort = (new Sort('sorted', [['metric' => ['order' => 'desc']]], 0))->toRaw();

        $this->assertSame(0, $terms['tags']['terms']['size']);
        $this->assertSame(0, $multi['pair']['multi_terms']['size']);
        $this->assertSame(0, $union['either']['terms']['size']);
        $this->assertSame(0, $sort['sorted']['bucket_sort']['size']);
    }

    public function test_histogram_bounds_accept_php_dates_without_using_the_display_format(): void
    {
        $aggs = new Aggs;
        $aggs->dateHistogram('days', 'created_at', '1d', extendedBounds: [
            'min' => new DateTimeImmutable('1969-12-31T23:59:59.125Z'),
            'max' => new DateTimeImmutable('2040-01-01T00:00:00Z'),
        ], format: 'yyyy-MM-dd');

        $bounds = $aggs->toRaw()['days']['date_histogram']['extended_bounds'];
        $this->assertSame(['min' => -875, 'max' => 2208988800000], $bounds);
    }
}
