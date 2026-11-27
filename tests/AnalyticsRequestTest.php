<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigmie\Analytics\AnalyticsRequest;

class AnalyticsRequestTest extends TestCase
{
    public function test_an_optional_count_field_must_still_be_a_field_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('metric field must be a string');

        AnalyticsRequest::fromArray([
            'widget' => 'grouped_metrics', 'date_field' => 'day', 'group_by' => 'city',
            'metrics' => '[{"key":"total","metric":"count","field":[]}]',
        ]);
    }

    public function test_fixed_intervals_require_a_positive_duration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported analytics interval [00ms]');

        AnalyticsRequest::fromArray(['widget' => 'trend', 'date_field' => 'day', 'metric' => 'count', 'interval' => '00ms']);
    }

    public function test_funnel_steps_require_a_json_array_even_with_numeric_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('funnel steps must be a JSON array');

        AnalyticsRequest::fromArray([
            'widget' => 'funnel', 'date_field' => 'day',
            'steps' => '{"0":{"label":"visited","filter":"visits>0"}}',
        ]);
    }

    public function test_calendar_interval_labels_can_be_reused_in_requests(): void
    {
        $request = AnalyticsRequest::fromArray([
            'widget' => 'trend', 'date_field' => 'date', 'metric' => 'count', 'interval' => 'Day',
        ]);

        $this->assertSame('day', $request->toArray()['interval']);
    }

    public function test_integral_json_numbers_satisfy_integer_tool_arguments(): void
    {
        $request = AnalyticsRequest::fromArray([
            'widget' => 'table', 'date_field' => 'date', 'limit' => 2.0, 'include_hits' => 1.0,
        ]);

        $this->assertSame(2, $request->toArray()['limit']);
        $this->assertSame(1, $request->toArray()['include_hits']);
    }

    public function test_blank_optional_range_keeps_explicit_window_bounds(): void
    {
        $request = AnalyticsRequest::fromArray([
            'widget' => 'kpi', 'date_field' => 'date', 'metric' => 'count',
            'range' => " \t ", 'from' => '2024-01-01', 'to' => '2024-02-01',
        ])->toArray();

        $this->assertArrayNotHasKey('range', $request);
        $this->assertSame('2024-01-01', $request['from']);
        $this->assertSame('2024-02-01', $request['to']);
    }

    public function test_csv_fields_use_the_same_whitespace_as_widget_execution(): void
    {
        $request = AnalyticsRequest::fromArray([
            'widget' => 'union_breakdown', 'date_field' => 'date', 'metric' => 'count',
            'group_by_fields' => ['brand', '　', '　category'],
        ]);

        $this->assertSame('brand,category', $request->toArray()['group_by_fields']);
    }

    public function test_window_accepts_nanosecond_precision_iso_dates(): void
    {
        $from = '2026-01-01T00:00:00.123456789+00:00';
        $request = AnalyticsRequest::fromArray([
            'widget' => 'kpi', 'metric' => 'count', 'date_field' => 'created_at', 'from' => $from,
        ]);

        $this->assertSame($from, $request->toArray()['from']);
    }

    public function test_percentile_inputs_keep_their_precision(): void
    {
        foreach ([50.00000000000001, [50.00000000000001]] as $input) {
            $request = AnalyticsRequest::fromArray([
                'widget' => 'percentiles', 'date_field' => 'date', 'field' => 'value', 'percents' => $input,
            ]);

            $this->assertSame('50.00000000000001', $request->toArray()['percents']);
        }
    }

    public function test_json_aliases_keep_unsigned_integer_terms(): void
    {
        $request = AnalyticsRequest::fromArray([
            'widget' => 'breakdown', 'date_field' => 'date', 'group_by' => 'code', 'metric' => 'count',
            'bucket_aliases' => '[{"label":"Last","values":[18446744073709551615]}]',
        ]);

        $this->assertSame('[{"label":"Last","values":["18446744073709551615"]}]', $request->toArray()['bucket_aliases']);
    }

    /** @test */
    public function it_normalizes_structured_values_windows_and_fingerprints(): void
    {
        $request = AnalyticsRequest::fromArray([
            'widget' => 'table',
            'date_field' => 'occurred_at',
            'fields' => ['category', 'amount'],
            'limit' => '10',
            'range' => 'last_30_days',
            'from' => '2026-01-01',
            'to' => '2026-02-01',
            'sort' => null,
            'hit_sort' => '',
        ]);

        $this->assertSame([
            'date_field' => 'occurred_at',
            'fields' => 'category,amount',
            'limit' => 10,
            'range' => 'last_30_days',
            'widget' => 'table',
        ], $request->toArray());
        $this->assertSame($request->fingerprint(), AnalyticsRequest::fromArray($request->toArray())->fingerprint());

        $window = AnalyticsRequest::fromArray([
            'widget' => 'kpi',
            'date_field' => 'occurred_at',
            'metric' => 'count',
            'from' => '2026-01-01',
            'to' => '2026-02-01',
        ])->toArray();

        $this->assertSame('2026-01-01', $window['from']);
        $this->assertSame('2026-02-01', $window['to']);
    }

    /** @test */
    public function it_canonicalizes_equivalent_structured_values(): void
    {
        $array = AnalyticsRequest::fromArray([
            'widget' => 'grouped_metrics',
            'date_field' => 'occurred_at',
            'group_by' => 'category',
            'metrics' => [[
                'metric' => 'avg',
                'key' => 'average',
                'field' => 'amount',
            ]],
        ]);
        $json = AnalyticsRequest::fromArray([
            'widget' => 'grouped_metrics',
            'date_field' => 'occurred_at',
            'group_by' => 'category',
            'metrics' => '[{"field":"amount","key":"average","metric":"avg"}]',
        ]);

        $this->assertSame('[{"field":"amount","key":"average","metric":"avg"}]', $array->toArray()['metrics']);
        $this->assertSame($array->toArray(), $json->toArray());
        $this->assertSame($array->fingerprint(), $json->fingerprint());
    }

    /** @test */
    public function it_canonicalizes_funnel_steps(): void
    {
        $request = AnalyticsRequest::fromArray([
            'widget' => 'funnel',
            'date_field' => 'occurred_at',
            'steps' => [
                ['label' => 'Visited', 'filter' => 'event:visited'],
                ['filter' => 'event:purchased', 'label' => 'Purchased'],
            ],
        ]);

        $this->assertSame(
            '[{"filter":"event:visited","label":"Visited"},{"filter":"event:purchased","label":"Purchased"}]',
            $request->toArray()['steps'],
        );
    }

    /** @test */
    public function it_canonicalizes_bucket_aliases(): void
    {
        $request = AnalyticsRequest::fromArray([
            'widget' => 'grouped_metrics',
            'date_field' => 'occurred_at',
            'group_by' => 'category',
            'metrics' => [['key' => 'count', 'metric' => 'count']],
            'bucket_aliases' => [
                ['values' => ['first', 2, ''], 'label' => 'Combined'],
                ['label' => 'Other', 'values' => [true]],
            ],
        ]);

        $this->assertSame(
            '[{"label":"Combined","values":["first",2,""]},{"label":"Other","values":[true]}]',
            $request->toArray()['bucket_aliases'],
        );
    }

    /** @test */
    public function it_preserves_supported_boolean_flags_and_filters_percentile_boundaries_at_execution(): void
    {
        $grouped = AnalyticsRequest::fromArray([
            'widget' => 'grouped_metrics',
            'date_field' => 'occurred_at',
            'group_by' => 'category',
            'metrics' => [['key' => 'count', 'metric' => 'count']],
            'bucket_aliases_only' => true,
        ]);
        $percentiles = AnalyticsRequest::fromArray([
            'widget' => 'percentiles',
            'date_field' => 'occurred_at',
            'field' => 'amount',
            'percents' => '0,25,100',
        ]);

        $this->assertSame(1, $grouped->toArray()['bucket_aliases_only']);
        $this->assertSame('0,25,100', $percentiles->toArray()['percents']);
    }

    /** @test */
    public function it_normalizes_a_union_breakdown_request(): void
    {
        $request = AnalyticsRequest::fromArray([
            'widget' => 'union_breakdown',
            'date_field' => 'occurred_at',
            'metric' => 'sum',
            'field' => 'prize',
            'group_by_fields' => ['champion_country', 'runner_up_country'],
            'limit' => '5',
        ]);

        $this->assertSame([
            'date_field' => 'occurred_at',
            'field' => 'prize',
            'group_by_fields' => 'champion_country,runner_up_country',
            'limit' => 5,
            'metric' => 'sum',
            'widget' => 'union_breakdown',
        ], $request->toArray());
    }

    /** @test */
    public function a_histogram_count_omits_the_metric_field(): void
    {
        $counted = AnalyticsRequest::fromArray([
            'widget' => 'histogram_metric',
            'date_field' => 'created_at',
            'metric' => 'count',
            'field' => 'rating',
            'bucket_field' => 'price',
            'bucket_size' => 50,
        ]);

        $this->assertSame('rating', $counted->toArray()['field']);

        $request = AnalyticsRequest::fromArray([
            'widget' => 'histogram_metric',
            'date_field' => 'created_at',
            'metric' => 'count',
            'field' => null,
            'bucket_field' => 'price',
            'bucket_size' => 50,
        ]);

        $this->assertSame([
            'bucket_field' => 'price',
            'bucket_size' => 50,
            'date_field' => 'created_at',
            'metric' => 'count',
            'widget' => 'histogram_metric',
        ], $request->toArray());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Analytics argument [field] is required.');

        AnalyticsRequest::fromArray([
            'widget' => 'histogram_metric',
            'date_field' => 'created_at',
            'metric' => 'avg',
            'bucket_field' => 'price',
            'bucket_size' => 50,
        ]);
    }

    /**
     * @test
     *
     * @dataProvider invalidRequests
     *
     * @param  array<string, mixed>  $request
     */
    public function it_rejects_invalid_request_contracts(array $request, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        AnalyticsRequest::fromArray($request);
    }

    /** @return list<array{array<string, mixed>, string}> */
    public static function invalidRequests(): array
    {
        $base = [
            'widget' => 'kpi',
            'date_field' => 'occurred_at',
            'metric' => 'count',
        ];
        $funnel = [
            'widget' => 'funnel',
            'date_field' => 'occurred_at',
        ];
        $groupedMetrics = [
            'widget' => 'grouped_metrics',
            'date_field' => 'occurred_at',
            'group_by' => 'category',
        ];

        return [
            [[...$base, 'unknown' => 'value'], 'Unknown analytics arguments: unknown'],
            [[...$base, 'limit' => 'many'], 'limit] must be an integer'],
            [[...$base, 'limit' => 1.5], 'limit] must be an integer'],
            [[...$base, 'limit' => '1e2'], 'limit] must be an integer'],
            [[...$base, 'limit' => true], 'limit] must be an integer'],
            [[...$base, 'filters' => ['field:value']], 'filters] must be scalar'],
            [[...$base, 'filters' => (object) ['field' => 'value']], 'filters] must be scalar'],
            [[...$base, 'steps' => '{'], 'Analytics funnel steps must be a JSON array'],
            [[...$base, 'metrics' => '{'], 'Analytics grouped_metrics metrics must be a JSON array'],
            [[...$base, 'bucket_aliases' => '{'], 'Analytics bucket_aliases must be a JSON array'],
            [[...$base, 'metrics' => '{"key":"count"}'], 'Analytics grouped_metrics metrics must be a JSON array'],
            [[...$base, 'metrics' => [INF]], 'metrics] must contain only finite numbers'],
            [[...$base, 'metrics' => [(object) ['key' => 'count']]], 'metrics] contains an unsupported value'],
            [[...$base, 'fields' => ['first' => 'amount']], 'fields] must be a list'],
            [[...$base, 'fields' => (object) ['first' => 'amount']], 'fields] must be a comma-separated list'],
            [[...$base, 'fields' => [['amount']]], 'fields] must contain only scalar values'],
            [[...$base, 'widget' => 'unknown'], 'Unsupported analytics widget'],
            [[...$base, 'metric' => 'ratio', 'field' => 'amount'], 'Unsupported analytics metric'],
            [[...$base, 'interval' => 'fortnight'], 'Unsupported analytics interval'],
            [[...$base, 'range' => 'since_launch'], 'Unsupported analytics range'],
            [[...$base, 'from' => 'tomorrow'], 'from] must be a valid ISO 8601 date'],
            [[...$base, 'from' => '2026-02-30'], 'from] must be a valid ISO 8601 date'],
            [[...$base, 'range' => 'last_30_days', 'from' => 'tomorrow'], 'from] must be a valid ISO 8601 date'],
            [[
                'widget' => 'distribution',
                'date_field' => 'occurred_at',
                'field' => 'amount',
            ], 'bucket_size] is required'],
            [[
                'widget' => 'multi_breakdown',
                'date_field' => 'occurred_at',
                'metric' => 'count',
                'group_by_fields' => ['category'],
            ], 'requires at least two group_by_fields'],
            [[
                'widget' => 'union_breakdown',
                'date_field' => 'occurred_at',
                'metric' => 'count',
                'group_by_fields' => ['champion_country'],
            ], 'requires at least two group_by_fields'],
            [[
                'widget' => 'union_breakdown',
                'date_field' => 'occurred_at',
                'metric' => 'count',
                'group_by_fields' => ['champion_country', 'champion_country'],
            ], 'requires at least two distinct group_by_fields'],
            [[
                'widget' => 'heatmap',
                'date_field' => 'occurred_at',
                'row_field' => 'category',
                'col_field' => 'subcategory',
                'metric' => 'sum',
            ], 'field] is required'],
            [[
                'widget' => 'heatmap',
                'date_field' => 'occurred_at',
                'row_field' => 'category',
                'col_field' => 'subcategory',
                'metric' => 'ratio',
            ], 'Unsupported analytics metric'],
            [[
                'widget' => 'percentiles',
                'date_field' => 'occurred_at',
                'field' => 'amount',
                'percents' => '50,1e309',
            ], 'must be a finite number between 0 and 100'],
            [[
                'widget' => 'funnel',
                'date_field' => 'occurred_at',
                'steps' => ['malformed'],
            ], 'funnel step must be an object'],
            [[...$funnel, 'steps' => []], 'funnel widget requires at least one step'],
            [[...$funnel, 'steps' => [['filter' => 'event:visited']]], 'funnel step needs a non-empty label and filter'],
            [[...$funnel, 'steps' => [['label' => 'Visited', 'filter' => ' ']]], 'funnel step needs a non-empty label and filter'],
            [[...$funnel, 'steps' => [
                ['label' => 'Visited', 'filter' => 'event:visited'],
                ['label' => 'Visited', 'filter' => 'event:returned'],
            ]], 'Duplicate funnel step label [Visited]'],
            [[...$funnel, 'steps' => [[
                'label' => 'Visited',
                'filter' => 'event:visited',
                'unknown' => true,
            ]]], 'Unknown funnel step keys: unknown'],
            [[
                'widget' => 'grouped_metrics',
                'date_field' => 'occurred_at',
                'group_by' => 'category',
                'metrics' => [['key' => 'average', 'metric' => 'avg']],
            ], 'non-count grouped_metrics metric needs a field'],
            [[
                'widget' => 'grouped_metrics',
                'date_field' => 'occurred_at',
                'group_by' => 'category',
                'metrics' => [['key' => 'count', 'metric' => 'count', 'unknown' => true]],
            ], 'Unknown grouped_metrics metric keys: unknown'],
            [[...$groupedMetrics, 'metrics' => []], 'grouped_metrics widget requires at least one metric'],
            [[...$groupedMetrics, 'metrics' => ['count']], 'grouped_metrics metric must be an object'],
            [[...$groupedMetrics, 'metrics' => [['key' => 'count', 'metric' => 'ratio']]], 'grouped_metrics metric needs a key and valid metric'],
            [[...$groupedMetrics, 'metrics' => [['key' => 'count', 'label' => [], 'metric' => 'count']]], 'grouped_metrics metric label must be a string'],
            [[...$groupedMetrics, 'metrics' => [
                ['key' => 'count', 'metric' => 'count'],
                ['key' => 'count', 'metric' => 'count'],
            ]], 'Duplicate grouped_metrics metric key [count]'],
            [[...$groupedMetrics,
                'metrics' => [['key' => 'count', 'metric' => 'count']],
                'sort_metric' => 'revenue',
            ], 'Unknown grouped_metrics sort_metric [revenue]'],
            [[...$base, 'bucket_aliases' => [['label' => 'Combined', 'values' => 'one']]], 'values array'],
            [[...$base, 'bucket_aliases' => ['malformed']], 'bucket_aliases item must be an object'],
            [[...$base, 'bucket_aliases' => [['label' => 'Combined', 'values' => [[]]]]], 'bucket_aliases value must be a scalar'],
            [[...$base, 'bucket_aliases' => [
                ['label' => 'Combined', 'values' => ['one']],
                ['label' => 'Combined', 'values' => ['two']],
            ]], 'Duplicate bucket_aliases label [Combined]'],
            [[...$base, 'bucket_aliases_only' => true], 'bucket_aliases_only is supported only by grouped_metrics'],
            [[
                'widget' => 'funnel',
                'date_field' => 'occurred_at',
            ], 'funnel widget requires steps'],
            [[
                'widget' => 'grouped_metrics',
                'date_field' => 'occurred_at',
            ], 'grouped_metrics widget requires metrics'],
            [[
                'widget' => 'histogram_metric',
                'date_field' => 'occurred_at',
                'metric' => 'avg',
                'field' => 'amount',
                'bucket_field' => 'amount',
                'bucket_size' => 0,
            ], 'bucket_size must be at least 1'],
            [[
                'widget' => 'grouped_metrics',
                'date_field' => 'occurred_at',
                'group_by' => 'category',
                'metrics' => '[{"key":"orders","metric":"count"}]',
                'min_count' => -1,
            ], 'min_count cannot be negative'],
            [[...$base, 'limit' => 101], 'limit] must be between 1 and 100'],
        ];
    }

    public function test_windows_reject_invalid_iso_offsets(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Analytics argument [from] must be a valid ISO 8601 date.');

        AnalyticsRequest::fromArray([
            'widget' => 'kpi',
            'date_field' => 'created_at',
            'metric' => 'count',
            'from' => '2024-02-29T12:00:00+01:99',
        ]);
    }

    public function test_metrics_reject_an_associative_php_array(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('metrics must be a JSON array');

        AnalyticsRequest::fromArray([
            'widget' => 'grouped_metrics', 'date_field' => 'date', 'group_by' => 'city',
            'metrics' => ['total' => ['key' => 'total', 'metric' => 'count']],
        ]);
    }

    public function test_histograms_reject_a_negative_minimum_document_count(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Analytics min_doc_count cannot be negative.');

        AnalyticsRequest::fromArray([
            'widget' => 'histogram_metric', 'date_field' => 'date', 'bucket_field' => 'price',
            'metric' => 'count', 'bucket_size' => 10, 'min_doc_count' => -1,
        ]);
    }
}
