<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Traits;

use const Sigmie\Functions\CALENDAR_DATE_PATTERNS;
use const Sigmie\Functions\EPOCH_DATE_FORMATS;

use DateTimeInterface;

use function Sigmie\Functions\fits_epoch_date;
use function Sigmie\Functions\format_date_value;

trait HasDateFormats
{
    public function format(string $format): void
    {
        $this->formats[] = $format;
    }

    public function toRaw(): array
    {
        $raw = parent::toRaw();
        $raw[$this->name]['format'] = implode('||', $this->formats);

        return $raw;
    }

    public function formatForWrite(DateTimeInterface $value, string $fallback): int|string
    {
        return format_date_value($value, explode('||', implode('||', $this->formats)), $fallback);
    }

    public function acceptsImplicitDateTerm(string $label): bool
    {
        $formats = explode('||', implode('||', $this->formats));
        foreach (array_intersect($formats, EPOCH_DATE_FORMATS) as $epoch) {
            if (fits_epoch_date($label, $epoch)) {
                return true;
            }
        }

        // Elasticsearch parses custom patterns. Only infer an ISO term when the mapping accepts ISO.
        if (array_intersect($formats, ['strict_date_optional_time', 'strict_date_optional_time_nanos']) === []) {
            return false;
        }

        $iso = clone $this;
        $iso->formats = ['strict_date_optional_time_nanos'];

        return $iso->validate($this->name, $label)[0];
    }

    // A null result leaves the field's documented ISO shape to Date or DateTime.
    private function acceptsEngineDateFormat(mixed $value): ?bool
    {
        if ($this->ignoresMalformedScalar($value)) {
            return true;
        }

        $iso = false;
        foreach (explode('||', implode('||', $this->formats)) as $format) {
            if (in_array($format, ['strict_date_optional_time', 'strict_date_optional_time_nanos'], true)) {
                $iso = true;
            } elseif (in_array($format, EPOCH_DATE_FORMATS, true)) {
                if (fits_epoch_date($value, $format)) {
                    return true;
                }
            } elseif (is_string($value) && $value !== '' && ! str_contains($value, "\0")) {
                $pattern = CALENDAR_DATE_PATTERNS[$format] ?? null;
                if ($pattern === null) {
                    // Leave other Java patterns to the engine.
                    return true;
                }

                $regex = strtr(preg_quote($pattern, '~'), ['Y' => '(?<year>\d{4}|\+\d{5,9})', 'm' => '(?<month>\d{2})', 'd' => '(?<day>\d{2})']);
                if (preg_match('~^'.$regex.'\z~', $value, $date) && (int) $date['year'] > 0
                    && checkdate((int) $date['month'], (int) $date['day'], 2000 + (int) $date['year'] % 400)
                    && [(int) $date['year'], (int) $date['month'], (int) $date['day']] <= [292278994, 8, 17]) {
                    return true;
                }
            }
        }

        return $iso ? null : false;
    }
}
