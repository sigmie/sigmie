<?php

declare(strict_types=1);

namespace Sigmie\Analytics;

use DateTimeImmutable;
use InvalidArgumentException;
use Sigmie\Analytics\Enums\Metric;
use Sigmie\Analytics\Enums\Period;

class AnalyticsRequest
{
    /** @var list<string> */
    public const WIDGETS = [
        'kpi',
        'kpi_delta',
        'trend',
        'cumulative',
        'grouped_trend',
        'breakdown',
        'multi_breakdown',
        'distribution',
        'histogram_metric',
        'grouped_metrics',
        'percentiles',
        'stats',
        'table',
        'funnel',
        'heatmap',
        'retention',
        'geo',
    ];

    /** @var list<string> */
    public const KEYS = [
        'widget',
        'date_field',
        'metric',
        'field',
        'bucket_field',
        'metrics',
        'sort_metric',
        'min_count',
        'min_doc_count',
        'interval',
        'group_by',
        'group_by_fields',
        'limit',
        'bucket_size',
        'percents',
        'fields',
        'sort',
        'steps',
        'row_field',
        'col_field',
        'cohort_field',
        'id_field',
        'precision',
        'range',
        'timezone_offset',
        'from',
        'to',
        'filters',
        'bucket_aliases',
        'bucket_aliases_only',
        'include_hits',
        'hit_filters',
        'hit_fields',
        'hit_sort',
        'hit_limit',
    ];

    /** @var list<string> */
    private const INTEGER_KEYS = [
        'min_count',
        'min_doc_count',
        'limit',
        'bucket_size',
        'precision',
        'timezone_offset',
        'include_hits',
        'hit_limit',
        'bucket_aliases_only',
    ];

    /**
     * @param  array<string, int|string>  $request
     */
    private function __construct(private readonly array $request) {}

    /**
     * @param  array<string, mixed>  $request
     */
    public static function fromArray(array $request): self
    {
        $request = self::normalize($request);
        self::validate($request);

        return new self($request);
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return $this->request;
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->request, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, int|string>
     */
    private static function normalize(array $request): array
    {
        $request = array_intersect_key($request, array_flip(self::KEYS));
        $normalized = [];

        foreach ($request as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (in_array($key, self::INTEGER_KEYS, true)) {
                (is_numeric($value) || is_bool($value)) || throw new InvalidArgumentException("Analytics argument [{$key}] must be an integer.");
                $normalized[$key] = (int) $value;

                continue;
            }

            if (is_array($value)) {
                $normalized[$key] = json_encode($value, JSON_THROW_ON_ERROR);

                continue;
            }

            is_scalar($value) || throw new InvalidArgumentException("Analytics argument [{$key}] must be scalar.");
            $normalized[$key] = trim((string) $value);
        }

        if (isset($normalized['range'])) {
            unset($normalized['from'], $normalized['to']);
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param  array<string, int|string>  $request
     */
    private static function validate(array $request): void
    {
        $widget = self::required($request, 'widget');
        in_array($widget, self::WIDGETS, true)
            || throw new InvalidArgumentException("Unsupported analytics widget [{$widget}].");

        self::required($request, 'date_field');
        self::validateMetric($request, $widget);
        self::validateWidget($request, $widget);
        self::validateWindow($request);
        self::validateBounds($request);
    }

    /**
     * @param  array<string, int|string>  $request
     */
    private static function validateMetric(array $request, string $widget): void
    {
        $metricWidgets = [
            'kpi',
            'kpi_delta',
            'trend',
            'cumulative',
            'grouped_trend',
            'breakdown',
            'multi_breakdown',
            'histogram_metric',
        ];

        if (! in_array($widget, $metricWidgets, true)) {
            return;
        }

        $metric = self::required($request, 'metric');
        $resolved = Metric::tryFrom($metric)
            ?? throw new InvalidArgumentException("Unsupported analytics metric [{$metric}].");

        if ($resolved !== Metric::Count) {
            self::required($request, 'field');
        }
    }

    /**
     * @param  array<string, int|string>  $request
     */
    private static function validateWidget(array $request, string $widget): void
    {
        if ($widget === 'funnel' && trim((string) ($request['steps'] ?? '')) === '') {
            throw new InvalidArgumentException('The funnel widget requires steps.');
        }

        if ($widget === 'grouped_metrics' && trim((string) ($request['metrics'] ?? '')) === '') {
            throw new InvalidArgumentException('The grouped_metrics widget requires metrics.');
        }

        match ($widget) {
            'grouped_trend' => self::requireMany($request, ['group_by', 'field']),
            'breakdown' => self::required($request, 'group_by'),
            'multi_breakdown' => self::required($request, 'group_by_fields'),
            'distribution', 'percentiles', 'stats', 'geo' => self::required($request, 'field'),
            'histogram_metric' => self::requireMany($request, ['bucket_field', 'bucket_size', 'field']),
            'grouped_metrics' => self::requireMany($request, ['group_by', 'metrics']),
            'funnel' => self::required($request, 'steps'),
            'heatmap' => self::requireMany($request, ['row_field', 'col_field']),
            'retention' => self::requireMany($request, ['cohort_field', 'id_field', 'interval']),
            default => null,
        };

        if (! isset($request['interval'])) {
            return;
        }

        $interval = (string) $request['interval'];
        $calendar = ['minute', 'hour', 'day', 'week', 'month', 'quarter', 'year'];

        in_array($interval, $calendar, true) || preg_match('/^\d+(ms|s|m|h|d)$/', $interval) === 1
            || throw new InvalidArgumentException("Unsupported analytics interval [{$interval}].");
    }

    /**
     * @param  array<string, int|string>  $request
     */
    private static function validateWindow(array $request): void
    {
        if (isset($request['range'])) {
            Period::tryFrom((string) $request['range']) instanceof Period
                || throw new InvalidArgumentException("Unsupported analytics range [{$request['range']}].");
        }

        foreach (['from', 'to'] as $key) {
            if (! isset($request[$key])) {
                continue;
            }

            new DateTimeImmutable((string) $request[$key]);
        }
    }

    /**
     * @param  array<string, int|string>  $request
     */
    private static function validateBounds(array $request): void
    {
        self::between($request, 'limit', 1, 100);
        self::between($request, 'hit_limit', 1, 100);
        self::between($request, 'precision', 1, 12);
        self::between($request, 'timezone_offset', -840, 840);
        self::between($request, 'include_hits', 0, 1);
        self::between($request, 'bucket_aliases_only', 0, 1);

        if (isset($request['bucket_size']) && (int) $request['bucket_size'] < 1) {
            throw new InvalidArgumentException('Analytics bucket_size must be at least 1.');
        }

        if (isset($request['min_count']) && (int) $request['min_count'] < 0) {
            throw new InvalidArgumentException('Analytics min_count cannot be negative.');
        }

        self::between($request, 'min_doc_count', 0, 1);
    }

    /**
     * @param  array<string, int|string>  $request
     */
    private static function required(array $request, string $key): string
    {
        $value = trim((string) ($request[$key] ?? ''));

        return $value !== '' ? $value : throw new InvalidArgumentException("Analytics argument [{$key}] is required.");
    }

    /**
     * @param  array<string, int|string>  $request
     * @param  list<string>  $keys
     */
    private static function requireMany(array $request, array $keys): void
    {
        foreach ($keys as $key) {
            self::required($request, $key);
        }
    }

    /**
     * @param  array<string, int|string>  $request
     */
    private static function between(array $request, string $key, int $minimum, int $maximum): void
    {
        if (! isset($request[$key])) {
            return;
        }

        $value = (int) $request[$key];
        ($value >= $minimum && $value <= $maximum)
            || throw new InvalidArgumentException("Analytics argument [{$key}] must be between {$minimum} and {$maximum}.");
    }
}
