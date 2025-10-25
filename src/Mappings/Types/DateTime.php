<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use DateTime as PHPDateTime;

class DateTime extends Type
{
    protected string $type = 'date';

    public function __construct(
        string $name,
        protected array $formats = ['strict_date_optional_time_nanos']
        // Y-m-d\TH:i:s.uP
    ) {
        parent::__construct($name);
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
        if (! is_string($value)) {
            return [false, sprintf('The field %s mapped as datetime must be a datetime string', $key)];
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

        $isValid = false;
        foreach ($dateTimeFormats as $format) {

            $dateTime = PHPDateTime::createFromFormat($format, $value);

            if ($dateTime && $dateTime->format($format) === $value) {

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
