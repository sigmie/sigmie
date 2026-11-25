<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use DateTime as PHPDateTime;
use DateTimeZone;
use Sigmie\Mappings\Traits\HasDateFacets;
use Sigmie\Mappings\Traits\HasDateFormats;

class DateTime extends Type
{
    use HasDateFacets;
    use HasDateFormats;

    protected string $type = 'date';

    public function __construct(
        string $name,
        protected array $formats = ['strict_date_optional_time_nanos']
        // Y-m-d\TH:i:s.uP
    ) {
        parent::__construct($name);
    }

    public function validate(string $key, mixed $value): array
    {
        $accepted = $this->acceptsEngineDateFormat($value);
        if ($accepted !== null) {
            return $accepted ? [true, ''] : [false, sprintf('The field %s does not match its configured date formats', $key)];
        }

        if (! is_string($value) || str_contains($value, "\0")) {
            return [false, sprintf('The field %s mapped as datetime must be a datetime string', $key)];
        }

        $candidate = $value;
        // PHP parses offset seconds but cannot print them for the round-trip check.
        if (preg_match('/([+-]\d{2})(:?)([0-5]\d)\2([0-5]\d)\z/', $candidate, $offset) === 1
            && (abs((int) $offset[1]) < 18 || $offset[4] === '00')) {
            $candidate = substr($candidate, 0, -2 - strlen($offset[2]));
        }

        // PHP writes a zero offset with a plus sign.
        $candidate = preg_replace('/-00(:00|00)\z/', '+00$1', $candidate) ?? $candidate;

        // PHP's Y parser has no sign. Gregorian leap years are symmetric around year zero.
        $candidate = preg_replace('/\A-(?!0000-)(?=\d{4}-)/', '', $candidate) ?? $candidate;

        // `u` is exactly 6 digits. A shorter ISO fraction parses, then format() writes 6 and the round trip fails.
        if (preg_match('/^(.*T\d{2}:\d{2}:\d{2})\.(\d{1,9})(.*)$/', $candidate, $match) === 1) {
            $candidate = $match[1].'.'.str_pad(substr($match[2], 0, 6), 6, '0').$match[3];
        }

        $dateTimeFormats = [
            'Y-m-d\TH:i:s.uP',
            'Y-m-d\TH:i:s.u\Z',
            'Y-m-d\TH:i:s.uO',
            'Y-m-d\TH:i:s.u',
            'Y-m-d\TH:i:sP',
            'Y-m-d\TH:i:s\Z',
            'Y-m-d\TH:i:sO',
            'Y-m-d\TH:i:s',
        ];

        $timezone = new DateTimeZone('UTC');
        $isValid = false;
        foreach ($dateTimeFormats as $format) {

            $dateTime = PHPDateTime::createFromFormat('!'.$format, $candidate, $timezone);

            if ($dateTime && abs($dateTime->getOffset()) <= 18 * 3600 && $dateTime->format($format) === $candidate) {

                $isValid = true;

                break;
            }
        }

        if (! $isValid) {
            return [false, sprintf('The field %s mapped as %s must be a string in one of the following formats: ', $key, $this->typeName()).implode(', ', $dateTimeFormats)];
        }

        return [true, ''];
    }
}
