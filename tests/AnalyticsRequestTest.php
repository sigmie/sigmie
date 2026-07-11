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

        return [
            [[...$base, 'unknown' => 'value'], 'Unknown analytics arguments: unknown'],
            [[...$base, 'limit' => 'many'], 'limit] must be an integer'],
            [[...$base, 'limit' => 1.5], 'limit] must be an integer'],
            [[...$base, 'limit' => '1e2'], 'limit] must be an integer'],
            [[...$base, 'limit' => true], 'limit] must be an integer'],
            [[...$base, 'filters' => (object) ['field' => 'value']], 'filters] must be scalar'],
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
            [[...$base, 'bucket_aliases' => [['label' => 'Combined', 'values' => 'one']]], 'values array'],
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
