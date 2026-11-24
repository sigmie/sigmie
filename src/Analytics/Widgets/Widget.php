<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Sigmie\Analytics\WindowBound;
use Sigmie\Mappings\Types\Boolean as BooleanField;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\DateTime;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Price;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type;
use Sigmie\Query\Aggregations\Bucket\DateHistogram;
use Sigmie\Query\Aggregations\Bucket\Filter;
use Sigmie\Query\Aggregations\Enums\CalendarInterval;
use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\Query;
use Sigmie\Query\Queries\Term\Range;
use Sigmie\Shared\Contracts\ToRaw;

use function Sigmie\Functions\epoch_milliseconds;
use function Sigmie\Functions\iso_date_bound;
use function Sigmie\Functions\with_minute_offset;

/**
 * A dashboard widget: a self-contained chunk of analytics (a KPI, a trend, a breakdown…)
 * that knows how to render itself as Elasticsearch aggregations ({@see ToRaw()}) and how to
 * normalise the response into a chart-ready shape ({@see extract()}).
 *
 * Every widget scopes itself to its own time window with a `filter` bucket via {@see scoped()},
 * so widgets with different windows (e.g. a period-over-period delta) can share one query.
 */
abstract class Widget implements ToRaw
{
    /**
     * An extra hard filter ANDed into this widget's own `filter` bucket, alongside its time window.
     */
    protected ?Query $filter = null;

    private bool $millisecondBounds = false;

    private ?string $countedField = null;

    private readonly string $widgetName;

    public function __construct(
        protected string $name,
        protected string $dateField,
        protected DateTimeInterface $from,
        protected DateTimeInterface $to,
        protected string $dateFormat,
    ) {
        // Percent-escape Elasticsearch's reserved characters without colliding with literal escapes.
        $this->widgetName = $name;
        $this->name = $name === '' ? '%' : strtr($name, [
            '%' => '%25', '[' => '%5B', ']' => '%5D', '>' => '%3E',
        ]);
    }

    public function name(): string
    {
        return $this->widgetName;
    }

    /**
     * Restrict this widget to a slice of the window. Unlike the analytics-wide filters()/filterQuery(),
     * this applies to this widget only, so a single query can hold widgets counting different slices —
     * e.g. a funnel of "total", "engaged" and "completed" KPIs over the same time window.
     */
    public function filter(Query $filter): static
    {
        $this->filter = $filter;

        return $this;
    }

    /**
     * Scope this widget to the millisecond a `date` timeline stores, so it matches the hits query.
     */
    public function withMillisecondBounds(): static
    {
        $widget = clone $this;
        $widget->millisecondBounds = true;

        return $widget;
    }

    /**
     * Count the documents that contain $field. A text field has no doc values to count.
     */
    public function countingDocumentsWith(string $field): static
    {
        $widget = clone $this;
        $widget->countedField = $field;

        return $widget;
    }

    /**
     * The field whose documents a count without a field counts: the timeline unless set.
     */
    protected function countedField(): string
    {
        return $this->countedField ?? $this->dateField;
    }

    abstract public function toRaw(): array;

    abstract public function extract(array $aggregations): array;

    /**
     * Milliseconds since the epoch. format('Uv') concatenates the signed second
     * with the millisecond digits, so a time before the epoch is not that instant.
     */
    public static function epochMillis(DateTimeInterface $time): int
    {
        return epoch_milliseconds($time);
    }

    public static function formatWindowBound(DateTimeInterface $time, string $format): string
    {
        if ($format !== DateTimeInterface::ATOM) {
            return $time->format($format);
        }

        $time = with_minute_offset($time);
        if (WindowBound::remainder($time) !== 0 || abs((int) $time->format('Y')) > 9999) {
            return self::isoWindowBound($time);
        }

        $microseconds = $time->format('u');
        if ($microseconds === '000000') {
            return $time->format($format);
        }

        return $time->format(str_ends_with($microseconds, '000') ? 'Y-m-d\TH:i:s.vP' : 'Y-m-d\TH:i:s.uP');
    }

    private static function isoWindowBound(DateTimeInterface $time): string
    {
        $iso = iso_date_bound($time);
        $remainder = WindowBound::remainder($time);

        return $remainder === 0 ? $iso : substr_replace($iso, sprintf('%03d', $remainder), -6, 0);
    }

    /**
     * Force a date histogram to span the FULL requested window [$from, $to), emitting zero-count
     * buckets for periods with no data. Without this the histogram stops at the last bucket that
     * has documents, so a query for a wide range (e.g. a full year over partial data) silently
     * trims the empty tail instead of showing it. Epoch millis; max is exclusive-aware.
     *
     * @return array{min: int, max: int}
     */
    protected function extendedBounds(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $min = self::epochMillis($from);
        // A fractional end still intersects its last millisecond bucket.
        $max = self::epochMillis($to) - (int) ! self::hasSubMillisecond($to);

        return [
            'min' => $min,
            'max' => max($min, $max),
        ];
    }

    private function histogramBound(DateTimeInterface $time): DateTimeInterface
    {
        if ($this->dateFormat === DateTimeInterface::ATOM) {
            return with_minute_offset($time);
        }

        // The range query reads formatted bounds in UTC when no offset is included.
        $format = match ($this->dateFormat) {
            'c' => DateTimeInterface::ATOM,
            'r' => DateTimeInterface::RFC2822,
            default => $this->dateFormat,
        };

        $bound = DateTimeImmutable::createFromFormat('!'.$format, self::formatWindowBound($time, $this->dateFormat), new DateTimeZone('UTC'));

        return with_minute_offset($bound ?: $time);
    }

    protected static function bucketLabel(mixed $value): string
    {
        return is_float($value) || is_bool($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (string) $value;
    }

    protected static function termsKey(array $bucket): mixed
    {
        return match ($bucket['key_as_string'] ?? null) {
            'false' => false,
            'true' => true,
            default => $bucket['key'],
        };
    }

    protected static function compareRankedRows(array $a, array $b, string $direction): int
    {
        $missing = ($a['value'] === null) <=> ($b['value'] === null);
        if ($missing !== 0) {
            return $missing;
        }

        $order = $a['value'] <=> $b['value'];

        return $direction === 'asc' ? $order : -$order;
    }

    public static function windowQuery(
        string $field,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $format,
        bool $millisecondBounds = false,
    ): Range {
        $fromFraction = self::hasSubMillisecond($from);
        $toFraction = self::hasSubMillisecond($to);

        if ($millisecondBounds && $format === DateTimeInterface::ATOM && ($fromFraction || $toFraction)) {
            // Discrete timestamps in [from, to) are in [ceil(from), ceil(to)).
            return new Range($field, [
                '>=' => self::epochMillis($from) + (int) $fromFraction,
                '<' => self::epochMillis($to) + (int) $toFraction,
            ], format: 'epoch_millis');
        }

        $bounds = [
            '>=' => self::formatWindowBound($from, $format),
            '<' => self::formatWindowBound($to, $format),
        ];
        $engineFormat = null;
        if ($format === DateTimeInterface::ATOM) {
            $engineFormat = Range::ISO_DATE_FORMAT;
            if (preg_match('/^[+-]\d{5}/', $bounds['>=']) || preg_match('/^[+-]\d{5}/', $bounds['<'])) {
                $engineFormat .= '||'.Range::EXPANDED_DATE_FORMAT;
            }
        }

        return new Range($field, $bounds, format: $engineFormat);
    }

    private static function hasSubMillisecond(DateTimeInterface $time): bool
    {
        return (int) $time->format('u') % 1000 !== 0 || WindowBound::remainder($time) !== 0;
    }

    /** @return array<string, array{label: string, values: list<string|bool>}> */
    protected function normalizeBucketAliases(array $aliases, ?Type $groupType): array
    {
        $normalized = [];

        foreach ($aliases as $label => $values) {
            $label = trim((string) $label);

            if ($label === '') {
                continue;
            }

            // Treat the label as an implicit alias value only if the field can represent it.
            $labelIsValue = match (true) {
                $groupType instanceof BooleanField => in_array($label, ['true', 'false'], true),
                $groupType instanceof Number, $groupType instanceof Price => Number::fitsMapping($groupType->type(), $label),
                $groupType instanceof Date, $groupType instanceof DateTime => $groupType->acceptsImplicitDateTerm($label),
                default => true,
            };

            $name = 'alias_'.substr(md5($label), 0, 12);
            $values = [...($normalized[$name]['values'] ?? []), ...($labelIsValue ? [$label] : []), ...(array) $values];
            $unique = [];
            foreach ($values as $value) {
                if (! is_scalar($value)) {
                    continue;
                }

                $value = is_bool($value) ? $value : self::bucketLabel($value);
                if (is_string($value) && trim($value) === ''
                    && ($groupType instanceof Number || $groupType instanceof Price || $groupType instanceof BooleanField)) {
                    continue;
                }

                if (! in_array($value, $unique, true)) {
                    $unique[] = $value;
                }
            }

            if ($unique !== []) {
                $normalized[$name] = ['label' => $label, 'values' => $unique];
            }
        }

        return $normalized;
    }

    /** @return list<string|bool> */
    protected function excludedAliasValues(array $aliases, ?Type $groupType): array
    {
        $values = array_merge(...array_column($aliases, 'values'));

        $keyword = $groupType instanceof Text ? $groupType->keywordField() : $groupType;

        if (! $keyword instanceof Keyword || $keyword->normalizer() === null) {
            return $values;
        }

        // Terms exclusions compare indexed keys without running the field normalizer.
        $mode = defined('MB_CASE_LOWER_SIMPLE') ? MB_CASE_LOWER_SIMPLE : MB_CASE_LOWER;

        return array_map(
            fn (string|bool $value): string|bool => is_string($value) ? mb_convert_case($value, $mode, 'UTF-8') : $value,
            $values,
        );
    }

    protected function windowHistogram(
        Aggs $aggs,
        string $name,
        CalendarInterval|string $interval,
        ?string $timeZone,
        int $minDocCount = 0,
    ): DateHistogram {
        $from = $this->histogramBound($this->from);
        $to = $this->histogramBound($this->to);

        // The range query admits a document when any of its dates match.
        // Clip its individual dates before Elasticsearch rounds them into buckets.
        return $aggs->dateHistogram($name, $this->dateField, $interval, minDocCount: $minDocCount, extendedBounds: $this->extendedBounds($from, $to), timeZone: $timeZone)
            ->script([
                'source' => <<<'PAINLESS'
                    Instant from = Instant.parse(params.from);
                    Instant to = Instant.parse(params.to);
                    List dates = new ArrayList();
                    for (def date : doc[params.field]) {
                        Instant time = date.toInstant();
                        if (!time.isBefore(from) && time.isBefore(to)) {
                            dates.add(time.toEpochMilli());
                        }
                    }
                    return dates;
                    PAINLESS,
                'params' => [
                    'field' => $this->dateField,
                    'from' => self::isoWindowBound($from),
                    'to' => self::isoWindowBound($to),
                ],
            ]);
    }

    /**
     * Wrap the inner aggregations in a `filter` bucket scoped to [$from, $to).
     */
    protected function scoped(
        string $name,
        DateTimeInterface $from,
        DateTimeInterface $to,
        callable $inner,
    ): Filter {
        $range = self::windowQuery($this->dateField, $from, $to, $this->dateFormat, $this->millisecondBounds);

        return (new Filter($name, $this->scopedQuery($range)))->aggregate($inner);
    }

    /**
     * The query for this widget's filter bucket: just the time range, or the time range ANDed
     * with the per-widget {@see Filter()} when one is set.
     */
    private function scopedQuery(Range $range): Query
    {
        if (! $this->filter instanceof Query) {
            return $range;
        }

        $boolean = new Boolean;
        $boolean->must()->query($range);
        $boolean->must()->query($this->filter);

        return $boolean;
    }
}
