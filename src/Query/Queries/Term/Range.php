<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Term;

use DateTimeInterface;
use Sigmie\Parse\RangeOperatorParser;
use Sigmie\Query\Queries\Query;

use function Sigmie\Functions\iso_date_bound;
use function Sigmie\Functions\keyed;

class Range extends Query
{
    public const ISO_DATE_FORMAT = 'strict_date_optional_time_nanos';

    public const EXPANDED_DATE_FORMAT = "uuuu-MM-dd'T'HH:mm:ss.SSSSSSXXX";

    /**
     * values example: ['>=' => '2','<=' => '200']
     */
    public function __construct(
        protected string $field,
        protected array $values = [],
        protected string $relation = 'intersects',
        protected ?string $format = null,
    ) {}

    public function bounds(): array
    {
        $bounds = [];
        $formats = [];
        $numericBound = false;
        foreach ($this->values as $operator => $value) {
            $operator = (new RangeOperatorParser)->parse($operator);
            if ($value instanceof DateTimeInterface) {
                $value = iso_date_bound($value);
                $formats[] = preg_match('/^[+-]\d{5}/', $value) === 1 ? self::EXPANDED_DATE_FORMAT : self::ISO_DATE_FORMAT;
            } elseif (is_int($value) || is_float($value)) {
                $numericBound = true;
            }
            $bounds[$operator] = $value;
        }

        if ($this->format !== null) {
            $formats = [...explode('||', $this->format), ...$formats];
        } elseif ($formats !== [] && $numericBound) {
            array_unshift($formats, 'epoch_millis');
        }

        if ($formats !== []) {
            $bounds['format'] = implode('||', array_unique($formats));
        }

        return $bounds;
    }

    public function toRaw(): array
    {
        return [
            'range' => keyed($this->field, [
                'relation' => $this->relation,
                'boost' => $this->boost,
                ...$this->bounds(),
            ]),
        ];
    }
}
