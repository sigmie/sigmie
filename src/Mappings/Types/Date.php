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
            return [false, sprintf('The field %s mapped as date must be a date string', $key)];
        }

        $dateFormats = [
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

        if (! $isValid) {
            return [false, sprintf('The field %s mapped as %s must be a string in one of the following formats: ', $key, $this->typeName()).implode(', ', $dateFormats)];
        }

        return [true, ''];
    }
}
