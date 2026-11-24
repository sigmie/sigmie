<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Metrics;

use Sigmie\Query\Aggregations\Bucket\Bucket;
use Sigmie\Query\Shared\Missing;

class Composite extends Bucket
{
    use Missing;

    public function __construct(
        protected string $name,
        protected array $sources,
        protected int $size = 10,
        protected ?array $after = null,
    ) {}

    protected function value(): array
    {
        $sources = array_map(static function (mixed $source): mixed {
            // A source named "0" is the only key, so the map is a PHP list.
            // json_encode would drop the name.
            if (is_array($source) && $source !== [] && array_is_list($source)) {
                return (object) $source;
            }

            return $source;
        }, array_values($this->sources));

        $value = [
            'composite' => [
                'sources' => $sources,
                'size' => $this->size,
            ],
        ];

        if ($this->after) {
            $value['composite']['after'] = (object) $this->after;
        }

        return $value;
    }
}
