<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

use Sigmie\Query\Shared\OrdersBuckets;

class UnionTerms extends Bucket
{
    use OrdersBuckets;

    private const SCRIPT = <<<'PAINLESS'
def values = new HashSet();

for (def field : params.fields) {
    if (!doc.containsKey(field) || doc[field].size() == 0) {
        continue;
    }

    for (def value : doc[field]) {
        // Scripted terms stringify keys. Use the same key for 2 and 2.0 without rounding longs.
        if (value instanceof Double || value instanceof Float) {
            double decimal = value.doubleValue();
            if (decimal >= -9223372036854775808.0 && decimal < 9223372036854775808.0) {
                long integer = (long) decimal;
                if ((double) integer == decimal) {
                    values.add(integer);
                    continue;
                }
            }
        }
        values.add(value);
    }
}

return values;
PAINLESS;

    protected int $size;

    /**
     * @param  list<string>  $fields
     */
    public function __construct(
        protected string $name,
        protected array $fields,
    ) {}

    public function size(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    protected function value(): array
    {
        $value = [
            'terms' => [
                'script' => [
                    'lang' => 'painless',
                    'source' => self::SCRIPT,
                    'params' => ['fields' => array_values($this->fields)],
                ],
                ...$this->order,
            ],
        ];

        if (isset($this->size)) {
            $value['terms']['size'] = $this->size;
        }

        return $value;
    }
}
