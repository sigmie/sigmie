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
            'unknown' => 'ignored',
        ]);

        $this->assertSame([
            'date_field' => 'occurred_at',
            'fields' => '["category","amount"]',
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
            [[...$base, 'limit' => 'many'], 'limit] must be an integer'],
            [[...$base, 'filters' => (object) ['field' => 'value']], 'filters] must be scalar'],
            [[...$base, 'widget' => 'unknown'], 'Unsupported analytics widget'],
            [[...$base, 'metric' => 'ratio', 'field' => 'amount'], 'Unsupported analytics metric'],
            [[...$base, 'interval' => 'fortnight'], 'Unsupported analytics interval'],
            [[...$base, 'range' => 'since_launch'], 'Unsupported analytics range'],
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
