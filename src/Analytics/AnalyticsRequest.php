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
        'union_breakdown',
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

    /** @var list<string> */
    private const BOOLEAN_INTEGER_KEYS = ['include_hits', 'bucket_aliases_only'];

    /** @var list<string> */
    private const JSON_KEYS = [
        'metrics',
        'steps',
        'bucket_aliases',
    ];

    /** @var list<string> */
    private const CSV_KEYS = [
        'group_by_fields',
        'percents',
        'fields',
        'hit_fields',
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
        self::validateKeys($request);
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
        $normalized = [];

        foreach ($request as $key => $value) {
            if ($value === null) {
                continue;
            }

            if ($value === '') {
                continue;
            }

            if (in_array($key, self::INTEGER_KEYS, true)) {
                $normalized[$key] = self::integer($value, $key);

                continue;
            }

            if (in_array($key, self::JSON_KEYS, true)) {
                $normalized[$key] = self::jsonList($value, $key);

                continue;
            }

            if (in_array($key, self::CSV_KEYS, true)) {
                $normalized[$key] = self::csv($value, $key);

                continue;
            }

            if (is_array($value)) {
                throw new InvalidArgumentException(sprintf('Analytics argument [%s] must be scalar.', $key));
            }

            if (! is_scalar($value)) {
                throw new InvalidArgumentException(sprintf('Analytics argument [%s] must be scalar.', $key));
            }

            $normalized[$key] = trim((string) $value);

            if (in_array($key, ['from', 'to'], true)) {
                self::validateIsoDate($normalized[$key], $key);
            }
        }

        if (isset($normalized['range'])) {
            unset($normalized['from'], $normalized['to']);
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private static function validateKeys(array $request): void
    {
        $unknown = array_diff(array_keys($request), self::KEYS);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown analytics arguments: '.implode(', ', $unknown).'.');
        }
    }

    private static function integer(mixed $value, string $key): int
    {
        if (is_bool($value)) {
            return in_array($key, self::BOOLEAN_INTEGER_KEYS, true)
                ? (int) $value
                : throw new InvalidArgumentException(sprintf('Analytics argument [%s] must be an integer.', $key));
        }

        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('Analytics argument [%s] must be an integer.', $key));
        }

        $value = trim($value);
        if (preg_match('/^-?(0|[1-9]\d*)$/D', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('Analytics argument [%s] must be an integer.', $key));
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer !== false
            ? $integer
            : throw new InvalidArgumentException(sprintf('Analytics argument [%s] must be an integer.', $key));
    }

    private static function jsonList(mixed $value, string $key): string
    {
        if (is_string($value)) {
            $decoded = json_decode(trim($value), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException(self::jsonListError($key));
            }

            $value = $decoded;
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException(self::jsonListError($key));
        }

        return json_encode(self::canonicalValue($value, $key), JSON_THROW_ON_ERROR);
    }

    private static function jsonListError(string $key): string
    {
        return match ($key) {
            'steps' => 'Analytics funnel steps must be a JSON array.',
            'metrics' => 'Analytics grouped_metrics metrics must be a JSON array.',
            'bucket_aliases' => 'Analytics bucket_aliases must be a JSON array.',
            default => sprintf('Analytics argument [%s] must be a JSON array.', $key),
        };
    }

    private static function canonicalValue(mixed $value, string $key): mixed
    {
        if (is_float($value) && ! is_finite($value)) {
            throw new InvalidArgumentException(sprintf('Analytics argument [%s] must contain only finite numbers.', $key));
        }

        if (is_null($value) || is_scalar($value)) {
            return $value;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException(sprintf('Analytics argument [%s] contains an unsupported value.', $key));
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => self::canonicalValue($item, $key), $value);
    }

    private static function csv(mixed $value, string $key): string
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                throw new InvalidArgumentException(sprintf('Analytics argument [%s] must be a list.', $key));
            }

            $values = $value;
        } elseif (is_scalar($value)) {
            $values = explode(',', (string) $value);
        } else {
            throw new InvalidArgumentException(sprintf('Analytics argument [%s] must be a comma-separated list.', $key));
        }

        $values = array_map(function (mixed $item) use ($key): string {
            if (! is_scalar($item)) {
                throw new InvalidArgumentException(sprintf('Analytics argument [%s] must contain only scalar values.', $key));
            }

            return trim((string) $item);
        }, $values);

        return implode(',', array_values(array_filter($values, fn (string $item): bool => $item !== '')));
    }

    /**
     * @param  array<string, int|string>  $request
     */
    private static function validate(array $request): void
    {
        $widget = self::required($request, 'widget');
        if (! in_array($widget, self::WIDGETS, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported analytics widget [%s].', $widget));
        }

        self::required($request, 'date_field');
        self::validateMetric($request, $widget);
        self::validateWidget($request, $widget);
        self::validateStructuredValues($request, $widget);
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
            'union_breakdown',
            'histogram_metric',
        ];

        if (! in_array($widget, $metricWidgets, true)) {
            return;
        }

        $metric = self::required($request, 'metric');
        $resolved = Metric::tryFrom($metric)
            ?? throw new InvalidArgumentException(sprintf('Unsupported analytics metric [%s].', $metric));

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
            'union_breakdown' => self::required($request, 'group_by_fields'),
            'distribution' => self::requireMany($request, ['field', 'bucket_size']),
            'percentiles', 'stats', 'geo' => self::required($request, 'field'),
            'histogram_metric' => self::requireMany($request, ['bucket_field', 'bucket_size', 'field']),
            'grouped_metrics' => self::requireMany($request, ['group_by', 'metrics']),
            'funnel' => self::required($request, 'steps'),
            'heatmap' => self::requireMany($request, ['row_field', 'col_field']),
            'retention' => self::requireMany($request, ['cohort_field', 'id_field', 'interval']),
            default => null,
        };

        if ($widget === 'heatmap' && isset($request['metric'])) {
            $metric = Metric::tryFrom((string) $request['metric'])
                ?? throw new InvalidArgumentException(sprintf('Unsupported analytics metric [%s].', $request['metric']));

            if ($metric !== Metric::Count) {
                self::required($request, 'field');
            }
        }

        if (! isset($request['interval'])) {
            return;
        }

        $interval = (string) $request['interval'];
        $calendar = ['minute', 'hour', 'day', 'week', 'month', 'quarter', 'year'];

        if (! in_array($interval, $calendar, true) && preg_match('/^\d+(ms|s|m|h|d)$/', $interval) !== 1) {
            throw new InvalidArgumentException(sprintf('Unsupported analytics interval [%s].', $interval));
        }
    }

    /**
     * @param  array<string, int|string>  $request
     */
    private static function validateStructuredValues(array $request, string $widget): void
    {
        if (isset($request['group_by_fields'])) {
            $groupByFields = self::csvValues($request, 'group_by_fields');

            if (count($groupByFields) < 2) {
                throw new InvalidArgumentException(sprintf('The %s widget requires at least two group_by_fields.', $widget));
            }

            if ($widget === 'union_breakdown' && count(array_unique($groupByFields)) < 2) {
                throw new InvalidArgumentException('The union_breakdown widget requires at least two distinct group_by_fields.');
            }
        }

        if (isset($request['percents'])) {
            foreach (self::csvValues($request, 'percents') as $percent) {
                if (! is_numeric($percent) || ! is_finite((float) $percent) || (float) $percent < 0 || (float) $percent > 100) {
                    throw new InvalidArgumentException(sprintf('Analytics percentile [%s] must be a finite number between 0 and 100.', $percent));
                }
            }
        }

        if (isset($request['steps'])) {
            self::validateSteps(self::jsonValues($request, 'steps'));
        }

        if (isset($request['metrics'])) {
            self::validateMetrics(self::jsonValues($request, 'metrics'), (string) ($request['sort_metric'] ?? ''));
        }

        if (isset($request['bucket_aliases'])) {
            self::validateBucketAliases(self::jsonValues($request, 'bucket_aliases'));
        }

        if (($request['bucket_aliases_only'] ?? 0) === 1 && $widget !== 'grouped_metrics') {
            throw new InvalidArgumentException('Analytics bucket_aliases_only is supported only by grouped_metrics.');
        }
    }

    /**
     * @param  array<string, int|string>  $request
     * @return list<string>
     */
    private static function csvValues(array $request, string $key): array
    {
        return array_values(array_filter(
            array_map(fn (string $value): string => trim($value), explode(',', (string) ($request[$key] ?? ''))),
            fn (string $value): bool => $value !== '',
        ));
    }

    /**
     * @param  array<string, int|string>  $request
     * @return list<mixed>
     */
    private static function jsonValues(array $request, string $key): array
    {
        $values = json_decode((string) ($request[$key] ?? ''), true);

        return is_array($values) && array_is_list($values)
            ? $values
            : throw new InvalidArgumentException(sprintf('Analytics argument [%s] must be a JSON array.', $key));
    }

    /** @param list<mixed> $steps */
    private static function validateSteps(array $steps): void
    {
        if ($steps === []) {
            throw new InvalidArgumentException('The funnel widget requires at least one step.');
        }

        $labels = [];

        foreach ($steps as $step) {
            if (! is_array($step) || array_is_list($step)) {
                throw new InvalidArgumentException('Each funnel step must be an object with label and filter.');
            }

            self::validateStructuredKeys($step, ['label', 'filter'], 'funnel step');

            $label = is_string($step['label'] ?? null) ? trim($step['label']) : '';
            $filter = is_string($step['filter'] ?? null) ? trim($step['filter']) : '';

            if ($label === '' || $filter === '') {
                throw new InvalidArgumentException('Each funnel step needs a non-empty label and filter.');
            }

            if (in_array($label, $labels, true)) {
                throw new InvalidArgumentException(sprintf('Duplicate funnel step label [%s].', $label));
            }

            $labels[] = $label;
        }
    }

    /** @param list<mixed> $metrics */
    private static function validateMetrics(array $metrics, string $sortMetric): void
    {
        if ($metrics === []) {
            throw new InvalidArgumentException('The grouped_metrics widget requires at least one metric.');
        }

        $keys = [];
        $hasCount = false;

        foreach ($metrics as $metric) {
            if (! is_array($metric) || array_is_list($metric)) {
                throw new InvalidArgumentException('Each grouped_metrics metric must be an object.');
            }

            self::validateStructuredKeys($metric, ['key', 'label', 'metric', 'field'], 'grouped_metrics metric');

            $key = is_string($metric['key'] ?? null) ? trim($metric['key']) : '';
            $label = $metric['label'] ?? null;
            $resolved = is_string($metric['metric'] ?? null) ? Metric::tryFrom(trim($metric['metric'])) : null;
            $field = is_string($metric['field'] ?? null) ? trim($metric['field']) : '';

            if ($label !== null && ! is_string($label)) {
                throw new InvalidArgumentException('Each grouped_metrics metric label must be a string.');
            }

            if ($key === '' || ! $resolved instanceof Metric) {
                throw new InvalidArgumentException('Each grouped_metrics metric needs a key and valid metric.');
            }

            if ($resolved !== Metric::Count && $field === '') {
                throw new InvalidArgumentException('Each non-count grouped_metrics metric needs a field.');
            }

            if (in_array($key, $keys, true)) {
                throw new InvalidArgumentException(sprintf('Duplicate grouped_metrics metric key [%s].', $key));
            }

            $keys[] = $key;
            $hasCount = $hasCount || $resolved === Metric::Count;
        }

        if ($sortMetric !== '' && ! in_array($sortMetric, $keys, true) && ! ($sortMetric === 'count' && $hasCount)) {
            throw new InvalidArgumentException(sprintf('Unknown grouped_metrics sort_metric [%s].', $sortMetric));
        }
    }

    /** @param list<mixed> $aliases */
    private static function validateBucketAliases(array $aliases): void
    {
        $labels = [];

        foreach ($aliases as $alias) {
            if (! is_array($alias) || array_is_list($alias)) {
                throw new InvalidArgumentException('Each bucket_aliases item must be an object with label and values.');
            }

            self::validateStructuredKeys($alias, ['label', 'values'], 'bucket_aliases item');

            $label = is_string($alias['label'] ?? null) ? trim($alias['label']) : '';
            $values = $alias['values'] ?? null;

            if ($label === '' || ! is_array($values) || ! array_is_list($values) || $values === []) {
                throw new InvalidArgumentException('Each bucket_aliases item needs a non-empty label and values array.');
            }

            foreach ($values as $value) {
                if (! is_scalar($value) || trim((string) $value) === '') {
                    throw new InvalidArgumentException('Each bucket_aliases value must be a non-empty scalar.');
                }
            }

            if (in_array($label, $labels, true)) {
                throw new InvalidArgumentException(sprintf('Duplicate bucket_aliases label [%s].', $label));
            }

            $labels[] = $label;
        }
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  list<string>  $keys
     */
    private static function validateStructuredKeys(array $value, array $keys, string $name): void
    {
        $unknown = array_diff(array_keys($value), $keys);

        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf('Unknown %s keys: %s.', $name, implode(', ', $unknown)));
        }
    }

    /**
     * @param  array<string, int|string>  $request
     */
    private static function validateWindow(array $request): void
    {
        if (isset($request['range']) && ! Period::tryFrom((string) $request['range']) instanceof Period) {
            throw new InvalidArgumentException(sprintf('Unsupported analytics range [%s].', $request['range']));
        }

        foreach (['from', 'to'] as $key) {
            if (! isset($request[$key])) {
                continue;
            }

            self::validateIsoDate((string) $request[$key], $key);
        }
    }

    private static function validateIsoDate(string $value, string $key): void
    {
        $format = match (true) {
            preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1 => '!Y-m-d',
            preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value) === 1 => '!Y-m-d\TH:i:sP',
            preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{1,6}(?:Z|[+-]\d{2}:\d{2})$/D', $value) === 1 => '!Y-m-d\TH:i:s.uP',
            default => null,
        };

        $date = $format !== null ? DateTimeImmutable::createFromFormat($format, $value) : false;

        if ($date instanceof DateTimeImmutable && DateTimeImmutable::getLastErrors() === false) {
            return;
        }

        throw new InvalidArgumentException(sprintf('Analytics argument [%s] must be a valid ISO 8601 date.', $key));
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

        return $value !== '' ? $value : throw new InvalidArgumentException(sprintf('Analytics argument [%s] is required.', $key));
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
        if (! ($value >= $minimum && $value <= $maximum)) {
            throw new InvalidArgumentException(sprintf('Analytics argument [%s] must be between %d and %d.', $key, $minimum, $maximum));
        }
    }
}
