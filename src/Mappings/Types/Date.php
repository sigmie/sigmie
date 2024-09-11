<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use DateTime;

class Date extends Type
{
    protected string $type = 'date';

    public function __construct(
        string $name,
        protected array $formats = ['strict_date_optional_time_nanos']
        // Y-m-d\TH:i:s.uP
    ) {
        parent::__construct($name);
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        return $queries;
    }

    public function format(string $format): void
    {
        $this->formats[] = $format;
    }

    public function toRaw(): array
    {
        $raw = parent::toRaw();

        $raw[$this->name]['format'] = implode('|', $this->formats);

        return $raw;
    }

    public function validate(string $key, mixed $value): array
    {
        // $validFormats = [
        //     '2023-09-11', // Date only
        //     '2023-09-11T15:30:00Z', // Date and time with UTC timezone
        //     '2023-09-11T15:30:00.123456789Z', // Date and time with UTC timezone and nanoseconds
        //     '2023-09-11T15:30:00.123Z', // Date and time with UTC timezone and milliseconds
        //     '2023-09-11T15:30:00.123456789+02:00', // Date and time with UTC timezone and nanoseconds and offset
        // ];

        if (!is_string($value)) {
            return [false, "The field {$key} mapped as date must be a date string"];
        }

        $dateFormats = [
            'Y-m-d\TH:i:s.uP',
            'Y-m-d\TH:i:s.u\Z',
            'Y-m-d\TH:i:s.uO',
            'Y-m-d\TH:i:s.u',
            'Y-m-d\TH:i:sP',
            'Y-m-d\TH:i:s\Z',
            'Y-m-d\TH:i:sO',
            'Y-m-d\TH:i:s',
            'Y-m-d',
        ];

        $isValid = false;
        foreach ($dateFormats as $format) {

            $dateTime = DateTime::createFromFormat($format, $value);

            if ($dateTime && $dateTime->format($format) === $value) {

                $isValid = true;

                break;
            }
        }

        if (!$isValid) {
            return [false, "The field {$key} mapped as {$this->typeName()} must be a string in one of the following formats: " . implode(', ', $dateFormats)];
        }

        return [true, ''];
    }
}
