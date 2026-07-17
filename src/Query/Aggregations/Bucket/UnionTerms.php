<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

class UnionTerms extends Bucket
{
    private const SCRIPT = <<<'PAINLESS'
def values = new HashSet();

for (def field : params.fields) {
    if (!doc.containsKey(field) || doc[field].size() == 0) {
        continue;
    }

    for (def value : doc[field]) {
        values.add(value);
    }
}

return values;
PAINLESS;

    protected int $size;

    protected array $order = [];

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

    public function order(string $subaggregation, string $direction): static
    {
        $this->order = ['order' => [
            $subaggregation => $direction,
        ]];

        return $this;
    }

    protected function value(): array
    {
        $value = [
            'terms' => [
                'script' => [
                    'lang' => 'painless',
                    'source' => self::SCRIPT,
                    'params' => ['fields' => $this->fields],
                ],
                ...$this->order,
            ],
        ];

        if ($this->size ?? false) {
            $value['terms']['size'] = $this->size;
        }

        return $value;
    }
}
