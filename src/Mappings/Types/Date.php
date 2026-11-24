<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\Traits\HasDateFacets;
use Sigmie\Mappings\Traits\HasDateFormats;

class Date extends Type
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
            return [false, sprintf('The field %s mapped as date must be a date string', $key)];
        }

        if (preg_match('/\A(?!-0000)-?\d{4}-\d{2}-\d{2}\z/', $value) === 1) {
            $date = date_parse($value);
            if ($date['warning_count'] === 0 && $date['error_count'] === 0) {
                return [true, ''];
            }
        }

        return [false, sprintf('The field %s mapped as %s must be a string in one of the following formats: Y-m-d', $key, $this->typeName())];
    }
}
