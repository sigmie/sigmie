<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Queries\Text;

use Sigmie\Base\Search\Queries\Query;

class MultiMatch extends Query
{
    public function __construct(
        protected string $query,
        protected array $fields = [],
    ) {
    }
    public function toRaw(): array
    {
        $res = [
            'multi_match' => [
                'query' => $this->query,
                'boost' => $this->boost,
            ],
        ];

        if (count($this->fields) > 0) {
            $res['multi_match']['fields'] = $this->fields;
        }

        return $res;
    }
}
