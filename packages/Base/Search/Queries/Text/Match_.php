<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Queries\Text;

use Sigmie\Base\Search\Queries\Query;

class Match_ extends Query
{
    public function __construct(
        protected string $field,
        protected string $query,
    ) {
    }
    public function toRaw(): array
    {
        return [
            'match' => [
                $this->field => [
                    'query' => $this->query,
                ],
            ],
        ];
    }
}
