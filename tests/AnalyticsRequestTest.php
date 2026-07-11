<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sigmie\Analytics\AnalyticsRequest;

class AnalyticsRequestTest extends TestCase
{
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
                ['values' => ['first', 2], 'label' => 'Combined'],
                ['label' => 'Other', 'values' => [true]],
            ],
        ]);

        $this->assertSame(
            '[{"label":"Combined","values":["first",2]},{"label":"Other","values":[true]}]',
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
            [[...$base, 'bucket_aliases' => [['label' => 'Combined', 'values' => ['']]]], 'bucket_aliases value must be a non-empty scalar'],
            [[...$base, 'bucket_aliases' => [['label' => 'Combined', 'values' => [[]]]]], 'bucket_aliases value must be a non-empty scalar'],
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
}
